<?php
/**
 * Created by PhpStorm.
 * User: hehuping
 * Date: 2018/6/12
 * Time: 18:03
 */

namespace App\Service\Event;

use App\Common\UserStatus;
use App\Common\Util;
use const Kernel\ONLINE_DOCTOR_SETNAME;
use const Kernel\ONLINE_USER_SETNAME;
use Lib\Config;
use Lib\Encryption;
use Lib\Logger;

const TO_USER_TEMPLATE_ID = 'Y_Ke8g8T9Njb8_Uf0ZTay8l6A4ZCa4yvi5pXUYVYY1s';
const TO_DOCTOR_TEMPLATE_ID = '-pA7fqCYfGp1fNC0mu_VzT3XfkUXXUTtCXaZVbEb-8w';

class Event
{

    protected $redis;
    protected $pdox;
    public static $OnlineUserSetName = 'chat:online:user';
    public static $OnlineDoctorSetName = 'chat:online:doctor';
    public static $BookingHash = 'chat:Bookinghash';
    public static $BookingMsgList = 'chat:BookingMsgList:';
    public static $access_token = 'chat:template_access_token';

    public function __construct()
    {
//        $this->pdox = Util::createPdox();
        $this->redis = Util::CreateRedis(true);
    }

    public function setUserOnline($userid, $fd, $type = 1)
    {

        $set = $type == 1 ? ONLINE_USER_SETNAME : ONLINE_DOCTOR_SETNAME;
        return $this->redis->sAdd($set, "$userid*$fd");
    }

    public function setUserOffline($userid, $fd, $type = 1)
    {
        $set = $type == 1 ? ONLINE_USER_SETNAME : ONLINE_DOCTOR_SETNAME;
        $this->redis->sRem($set, "$userid*$fd");
    }

    public function getUserGroup($uid)
    {
        $pdox = Util::createPdox();
        $result = $pdox->table('message')
            ->select('messages,gid,c_uid, to_uid, ctime, is_read, c_username, to_username')
            ->orWhere(['c_uid' => $uid, 'to_uid' => $uid])
            ->where(['status' => 0])
            ->orderBy('ctime desc')
            ->groupBy('gid')
            ->getAll(true);
        //var_dump($pdox->getQuery());
        return $result;
    }

    /**
     * 通过分组ID获取消息
     * @param string $gid
     * @return array|mixed
     */
    public function getMsgByGroupId(string $gid)
    {
        $pdox = Util::createPdox();
        return $pdox->table('message')
            ->select('messages,gid,c_uid, to_uid, ctime, is_read, c_username, to_username')
            ->where(['gid' => $gid, 'status' => 0])
            ->orderBy('ctime desc')
            ->limit(200)
            ->getAll(true);

    }

    /**
     * @param array $data
     * @param int $cuid
     * @param string $message
     * @param int $touid
     * @param int $is_read
     */
    public function insertDB($data, int $cuid, int $touid, string $message, int $is_read)
    {
        $pdox = Util::createPdox();
        $max = max($cuid, $touid);
        $min = min($cuid, $touid);
        $data = [
            'gid' => $min . 'group' . $max,
            'c_uid' => $cuid,
            'to_uid' => $touid,
            'is_read' => $is_read,
            'messages' => $message,
            'c_username' => $data['c_username'],
            'to_username' => $data['to_username'],
            'ctime' => date('Y-m-d H:i:s'),
            'msg_type' => $data['msg_type'],
            'booking_id' => $data['booking_id'],
        ];

        return $pdox->table('message')->insert($data);
    }

    /**
     * 消息写入缓存
     * @param $data
     * @param $booking_id
     * @return bool|int
     */
    public function addCache($data, $booking_id)
    {
        //以booking_id为key，把消息写入链表，并且返回消息索引
        $date = date('Y-m-d H:i:s');
        $this->redis->hSetNx(self::$BookingHash, $booking_id, $date);
        $count = $this->redis->rPush(self::$BookingMsgList . $booking_id, json_encode($data, JSON_UNESCAPED_UNICODE));
        if ($count === false) {
            return false;
        }
        return $count - 1;
    }

    /**
     * 标记为已读
     * @param $index
     * @param $booking_id
     */
    public function setCacheRead($index, $booking_id)
    {
        $ret = $this->redis->lIndex(self::$BookingMsgList . $booking_id, $index);
        $ret = (array)json_decode($ret);
        $ret['is_read'] = 1;
        return $this->redis->lSet(self::$BookingMsgList . $booking_id, $index, json_encode($ret, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 根据索引获取索引以后未读消息
     * @param $booking_id
     * @param $index
     * @param $userType
     * @return array
     */
    public function getNotReadMsgByIndex($booking_id, $index, $userType)
    {
        $index += 1;
        $msg = $this->redis->lRange(self::$BookingMsgList . $booking_id, $index, -1);
        $newMsg = [];
        foreach ($msg as $key => $value) {
            $content = (array)json_decode($value);
            if ($content['c_user_type'] != $userType && $content['is_read'] == 0) {
                if (!isset($content['is_retract']) || (isset($content['is_retract']) && $content['is_retract'] == 0)) {
                    $content['id'] = $index;
                    $newMsg[] = $content;
                }
            }
            $index += 1;
        }
        sort($newMsg);
        reset($newMsg);
        return $newMsg;
    }

    //消息撤回
    public function retractMessage($index, $booking_id)
    {
        $ret = $this->redis->lIndex(self::$BookingMsgList . $booking_id, $index);
        $ret = (array)json_decode($ret);
        $ret['is_retract'] = 1;
        $res=$this->redis->lSet(self::$BookingMsgList . $booking_id, $index, json_encode($ret, JSON_UNESCAPED_UNICODE));
        return $ret;
    }

    /**
     * @param $sendUser
     * @param int $toUserId
     * @param string $message
     */
    public function sendTemplateMessage($sendUser, int $toUserId, string $message, int $sendType, int $booking_id):void {
        //get openid from mysql
        $mysql_config = Config::get('pdox');
        $appID = Config::get('WX_APPID');
        $appSEC = Config::get('WX_APPSECRET');

        go(function() use ($mysql_config, $sendType, $sendUser, $message, $toUserId, $appID, $appSEC,$booking_id){
            //mysql to get to user openid
            $swoole_mysql = new \Swoole\Coroutine\MySQL();
            $swoole_mysql->connect($mysql_config);
            $cloum = ($sendType==UserStatus::IS_DOCTOR)?'doctor_id':'user_id';
            $sql1 = "select `social_id` from `social_binder` where $cloum=$toUserId";
            $sql2 = "select `prepay_id` from `reading_booking` where `id`={$booking_id}";
            $res1 = $swoole_mysql->query($sql1);
            $res2 = $swoole_mysql->query($sql2);

            if ($res1 === false ||$res2===false) {
                Logger::write("mysql query fild!,sql1=>[$sql1], sql2=>[$sql2]");
                echo "mysql query faild!";
                return ;//Util::resultFormat('', 'mysql query fild!', -1);

            }

            var_dump($sql1);
            var_dump($sql2);
            $to_openid = $res1[0]['social_id'];
            $prepay_id = $res2[0]['prepay_id'];

            //init http cline
//            $host = 'api.weixin.qq.com';
            $host = 'apps.daishutijian.com';
            $cli = new \Swoole\Coroutine\Http\Client($host, 443, true);
            $cli->setHeaders([
                //'Host' => "localhost", //some times set localhost response where be 404
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept' => 'text/html,application/xhtml+xml,application/xml,application/json',
                'Accept-Encoding' => 'gzip,deflate,br',
            ]);
            $cli->set(['timeout' => 2]);

            //get  access token from cache
            $access_token = $this->redis->get(self::$access_token);
            if(!$access_token){
                //http cline to get access_tokrn
//                $path = "/cgi-bin/token?grant_type=client_credential&appid={$appID}&secret={$appSEC}";
                $path = "/report/app/api/Share/account_token";
                $cli->get($path);
                $ret = $cli->body;
                $cli->close();
                $ret = json_decode($ret);
                //echo socket_strerror($cli->errCode);

                var_dump($ret);
                if(isset($ret->errcode) && $ret->errcode!=200){
                    Logger::write("get accecc_token error !,".json_encode($ret));
                    return ;
                }

                $encryption = new \Lib\Encryption();
                $access_token = $encryption->decrypt($ret->data->access_token);
//                $this->redis->set(self::$access_token, $access_token, 7000);
            }

            go(function() use($sendType,$to_openid, $prepay_id,$sendUser,$message,$access_token){
                $cli2 = new \Swoole\Coroutine\Http\Client('api.weixin.qq.com', 443, true);
                $cli2->setHeaders([
                    //'Host' => "localhost", //some times set localhost response where be 404
                    "User-Agent" => 'Chrome/49.0.2587.3',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml,application/json',
                    'Accept-Encoding' => 'gzip,deflate,br',
                ]);
                $cli2->set(['timeout' => 2]);
                $path = '/cgi-bin/message/wxopen/template/send?access_token='.$access_token;
                $post_data = $this->_getTemplateData($sendType, $to_openid, $prepay_id,$sendUser->username,$message);
                $cli2->post($path, json_encode($post_data));
                $ret2 = $cli2->body;
                $cli2->close();

                var_dump($post_data);
                var_dump($ret2);
            });


        });
    }

    private function _getTemplateData($sendType,$toOpenId,$prepayId,$userName,$message ){
        $sendData['touser'] = $toOpenId;
        $sendData['form_id'] = $prepayId;
        if($sendType==UserStatus::IS_DOCTOR){
            //发给医生
            $sendData['template_id'] = TO_DOCTOR_TEMPLATE_ID;
            $sendData['page'] = '';         // 显示的页面地址
            $sendData['data'] = [
                'keyword1' => [
                    "value" => $userName,    // 用户名字
                    "color" => '',
                ],
                'keyword2' => [
                    'value' => $message,      // 咨询内容
                    'color' => '',
                ],
            ];
        }else{
            //发给用户
            $sendData['template_id'] = TO_USER_TEMPLATE_ID;
            $sendData['page'] = '';         // 显示的页面地址
            $sendData['data'] = [
                'keyword1' => [
                    "value" => $userName,    // 医生姓名
                    "color" => '',
                ],
                'keyword2' => [
                    'value' => time(),      // 回复时间
                    'color' => '',
                ],
                'keyword3' => [
                    'value' => $message,      // 回复内容
                    'color' => '',
                ],
            ];
        }

        return $sendData;

    }
}