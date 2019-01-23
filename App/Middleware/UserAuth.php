<?php
/**
 * Created by PhpStorm.
 * User: hehuping
 * Date: 2018/7/6
 * Time: 9:47
 */

namespace App\Middleware;

use Kernel\Route;
use Lib\Config;
use Lib\Logger;
use Lib\Encryption;
use App\Common\Util;

//连接签名认证
class UserAuth
{
    public static function handle($next, $sWrequest, $sWresponse){
        $uri = $sWrequest->server['request_uri'];
        //$token = isset($sWrequest->header['sec-websocket-protocol'])?$sWrequest->header['sec-websocket-protocol']:(isset($sWrequest->get['sec-websocket-protocol'])?$sWrequest->get['sec-websocket-protocol']:'');
        //开发者工具偶尔设置不了header,可通过get传值代替
       // echo "\n HEADER: {$sWrequest->header['sec-websocket-protocol']} \n";
       // echo "\n GET: ".urldecode($sWrequest->get['sec-websocket-protocol']) ." \n";

        if(isset($sWrequest->header['sec-websocket-protocol'])){
            $token = $sWrequest->header['sec-websocket-protocol'];
            $token = urldecode($token);
        }else{
            $token = isset($sWrequest->get['sec-websocket-protocol'])?$sWrequest->get['sec-websocket-protocol']:'';
        }
        //安卓端 '+' bug
        $token = str_replace(' ', '+', $token);
        $userInfo = self::getUserInfo($token);

        if($userInfo){
            //设置swoole table关联
            try{
                $userInfo = Util::setUserData($userInfo, $sWrequest->fd, $uri);
                if(!$userInfo){
                    Logger::write('[ERROR][SESSION中用户类型错误]:'.Util::json_encode_unicode($userInfo),'user_auth_middleware_');
                    return false;
                }
            }catch (\Exception $e){
                Logger::write('[ERROR][设置用户信息失败]:'.Util::json_encode_unicode($userInfo),'user_auth_middleware_');
                return false;
            }
            Logger::write('[INFO][UserAuth:handle]用户' . $userInfo->uid . '通过身份验证','user_auth_middleware_');
            return $next($sWrequest, $sWresponse, $userInfo);
        }
        //Logger::write('[ERROR][鉴权失败，session信息为空]：'.Util::json_encode_unicode($sWrequest->header),'user_auth_middleware_');
        return false;
    }

    public static function getUserInfo($token)
    {
        //解密token
        $encryption = new Encryption();
        $sessionid = $encryption->decrypt($token);
        //获取加密session
        $redis = Util::CreateRedis(true);
        $data = $redis->get('ci_sess:' . $sessionid);
        if(!$data){
            $data = $redis->get('ci_sess_dev:' . $sessionid);
        }
        if(!$data){
            $data = $redis->get('ci_sess_test:' . $sessionid);
        }

        if (!$data) {
            Logger::write('[error][用户session解析失败]获取redis中"ci_sess_dev：'.$sessionid.'"或"ci_sess_test:'.$sessionid.'"异常','user_auth_middleware_');
            return false;
        }
       /* //获取用户关键信息 key
        $sessionKey = Config::get('session_key') . '|';
        //截取信息部分
        $data = substr($data, strpos($data, $sessionKey) + strlen($sessionKey));*/
        $session = explode('|', $data);
        $openidInfo = unserialize($session[3]);
//        var_dump(unserialize($openidInfo));
        if(isset($session[2])){
            $session = substr($session[2], 0, strpos($session[2],'sessionKey_social_info'));
            $userInfo=unserialize($session);
            if($openidInfo&&$userInfo){
                return array_merge($userInfo,$openidInfo);
            }
            return false;
        }else{
            Logger::write('[error][用户session解析失败]:redis data=>'.$data.',session=>'.Util::json_encode_unicode($session).',token=>'.$token.',sessionid=>'.$sessionid,'user_auth_middleware_');
            return false;
        }
    }
}