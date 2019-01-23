<?php
/**
 * Created by PhpStorm.
 * User: hehuping
 * Date: 2018/6/7
 * Time: 10:05
 * 后台管理路由
 */

use Kernel\Route;
use App\Service\Event\Event;
use App\Common\Util;
use Lib\Logger;
use App\Common\Request;
use App\Common\UserStatus;


Route::websocketGroup('/test', ['middleware' => 'VisitLog'], function () {
    //连接打开事件
    Route::websocketOpenEvent(function ($request, $response,$userInfo) {
        $server = $GLOBALS['server'];
        $uid = Util::getRelUid($userInfo['id']);
        $userType = $userInfo['auth_type'];
        //通知所有fd医生上线
        if ($userType==UserStatus::IS_DOCTOR) {
            foreach ($server->connections as $fd) {
                $res = Util::resulePaser('online', ['doctor_id' => $uid, 'msg' => '医生上线通知']);
                if ($fd != $request->fd) {
                    $server->push($fd, Util::json_encode_unicode($res));
               }
            }
        }
        Logger::write("[INFO][websocketOpenEvent]uid:" . $uid . '，fd=' . $request->fd . '请求打开链接', 'socket');
        $checkOnlineRes = UserStatus::setUserOnline($uid, $userType, $request->fd);
        return true;
    }, 'UserAuth');


    //消息事件
    Route::websocketMessageEvent(function ($server, $frame,$userInfo ) {
        //获取当前用户的uid
        $sendUid = (int)Util::getRelUid($userInfo->uid);
        $data = json_decode($frame->data);
        //解析医生和用户ID
        $encryption = new \Lib\Encryption();
        $usif =$encryption->decrypt($data->data->usif);
        $usif=explode('@',$usif);
        $toUid = $usif[1];
        $booking_id =$usif[0];

        if(!$toUid){
            $userInfo->booking_id = $booking_id;
            $userInfo->msg_uuid = $data->data->msg_uuid;
            Logger::write("[ERROR][用户ID解析失败][uid:{$userInfo->uid}]" . Util::json_encode_unicode($data).',id:'.Util::json_encode_unicode($usif).",rel_usif:".$data->data->usif, 'socket');
            $res = Util::resulePaser('pushback', $userInfo, 50002, '消息发送失败！(id error)');
            $server->push($frame->fd, Util::json_encode_unicode($res));
            return Route::Ignore;
        }

        // 检查消息发起人是否被标记在线
        //$checkOnlineRes = UserStatus::checkAndSetOnline($sendUid, $userInfo, $frame->fd, 'websocketMessageEvent');
        //Logger::write("[INFO][收到动作:".$data->action."检查发起方用户是否在线]".$userInfo->uid . '，返回：' . json_encode($checkOnlineRes, 300), 'socket');


        $modal = new Event();
        //判断用户是发送消息还是获取消息
        if ($data->action == 'push') {
            //解析消息
            $msg = $data->data->messages;
            if (empty($msg)) {
                $res = Util::resulePaser('pushback', $userInfo, 50003, '消息体为空！(messages error)');
                $server->push($frame->fd, Util::json_encode_unicode($res));
                return Route::Ignore;
            }

            //如果在线push给指定用户
            $datas = [
                'c_uid' => $sendUid,
                'to_uid' => $toUid,
                'msg_type' => $data->data->msg_type,
                'messages' => is_string($msg)?htmlspecialchars($msg):$msg,
                'booking_id' => $booking_id,
                'is_read' => 0,
                'ctime' => date('Y-m-d H:i:s'),
                'c_user_type' => $userInfo->auth_type,
                'is_first'=>0,
                'fd' => $frame->fd,
            ];
            //写入未读列表
            $index = $modal->addCache($datas, $booking_id);
            // 页面区分是哪条消息用
            $datas['msg_uuid'] = $data->data->msg_uuid;
            if ($index !== false) {
                $datas['id'] = $index;
                //反馈发送成功
                $res = Util::resulePaser('pushback', $datas);
                $server->push($frame->fd, json_encode($res));
                // $modal->insertDB($datas, $sendUid, $toUid, $msg, 0);
                // 检查接收方是否在线
                $toUserType = $userInfo->auth_type == UserStatus::IS_DOCTOR ? UserStatus::IS_USER : UserStatus::IS_DOCTOR;
                $reviceUser = UserStatus::checkUserOnline($toUid, $toUserType, 'push');
                //异步推送模板消息
//                $modal->sendTemplateMessage($userInfo, $toUid,$msg,$toUserType,$booking_id);
                if (!empty($reviceUser) && isset($reviceUser->fd)) {
                    //在线push
                    Logger::write("[INFO][push]{$sendUid} 发给 {$toUid}，在线直接发", 'socket');
                    $res = Util::resulePaser('push', $datas);
                    $server->push($reviceUser->fd, Util::json_encode_unicode($res));
                } else {
                    // 不在线
                    Logger::write("[INFO][push]{$sendUid} 发给 {$toUid}，不在线，不推", 'socket');
                }
            } else {
                //反馈发送失败
                $res = Util::resulePaser('pushback', $datas, 50001, '消息发送失败！(redis error)','msg_set_redis_err_');
                Logger::write("[ERROR][消息发送失败，消息写入redis错误][uid:{$userInfo->uid}]" . Util::json_encode_unicode($datas), 'socket');
                $server->push($frame->fd, json_encode($res));
            }

        } elseif ($data->action == 'read') {
           // $booking_id = $data->data->booking_id;
            $msgIndex = $data->data->id; //消息在链表中的索引
            //如果在线push给指定用户
            $toUserType = $userInfo->auth_type == UserStatus::IS_DOCTOR ? UserStatus::IS_USER : UserStatus::IS_DOCTOR;
            $reviceUser = UserStatus::checkUserOnline($toUid, $toUserType, 'read');
            //标记为已读
            $modal->setCacheRead($msgIndex, $booking_id);
            if ($reviceUser) {
                $data = ['booking_id' => $booking_id, 'id' => $msgIndex];
                $res = Util::resulePaser('read', $data);

                $server->push($reviceUser->fd, Util::json_encode_unicode($res));
            }
        }elseif ($data->action == 'reconnect'){
            //断线重连，获取链表最后50条消息，把未读的消息推给他
            $notRead = $modal->getNotReadMsgByIndex($booking_id, isset($data->data->index) ? $data->data->index : 0,$userInfo->auth_type);
            $res = Util::resulePaser('reconnect', $notRead);
            //$res['data'] = (array)$notRead;
            Logger::write("[INFO][reconnect]用户uid:{$userInfo->uid}申请重新连接成功，并推送最近50条消息给用户", 'socket');
            $server->push($frame->fd, json_encode($res));
        }elseif ($data->action=='retract'){
            //消息撤回
            $msgIndex = $data->data->id; //消息在链表中的索引
            //设置撤回
            $msg_content=$modal->retractMessage($msgIndex, $booking_id);
            //如果在线push给指定用户
            $toUserType = $userInfo->auth_type == UserStatus::IS_DOCTOR ? UserStatus::IS_USER : UserStatus::IS_DOCTOR;
            $reviceUser = UserStatus::checkUserOnline($toUid, $toUserType, 'retract');
            $data = ['booking_id' => $booking_id, 'id' => $msgIndex,'c_user_type'=>$userInfo->auth_type,'msg_type'=>$msg_content['msg_type']];
            $res = Util::resulePaser('retract', $data);
            if ($reviceUser) {
                $server->push($reviceUser->fd, Util::json_encode_unicode($res));
            }
            $server->push($frame->fd, Util::json_encode_unicode($res));

        }

        return Route::Ignore;
    });


    //断开连接事件
    Route::websocketCloseEvent(function ($server, $frame, $userInfo) {
       // echo "close \n";
        //删除用户所在redis fd集合
        $userType = $userInfo->auth_type;
        $data = ['doctor_id' => '', 'user_id' => ''];
        $relUid = Util::getRelUid($userInfo->uid);
        //删除swoole_table ['uid'=>$uid, 'fd'=>$fd, 'path'=>$path];
        if ($userType == UserStatus::IS_DOCTOR) {
            $data['doctor_id'] = $relUid;
        } else {
            $data['user_id'] = $relUid;
        }

        Logger::write("[INFO][websocketCloseEvent]收到".($userType == UserStatus::IS_DOCTOR ? '医生' : '病友'). ':' . $relUid . "断开请求，不清理缓存", 'socket');
        //return true;
        $model = new Event();
        $model->setUserOffline(Util::getRelUid($userInfo->uid), $frame->fd, $userType);
        Util::delUserData($userInfo->uid);
        Util::delUserData($frame->fd);
        //通知所有fd 此用户下线(返回uid)
        foreach ($server->connections as $fd) {
            if($fd!=$frame->fd){
                $res = Util::resulePaser('close', $data);
                Logger::write("[INFO][websocketCloseEvent]通知终端：" . $fd . '' .($userType == UserStatus::IS_DOCTOR ? '医生' : '病友'). ':' . $relUid . "已断开", 'socket');
                $server->push($fd, json_encode($res));
            }
        }
    });
    return Route::Ignore;
});


Route::post("/firstpush", function ($swRequest, $swResponse) {
    $server = $GLOBALS['server'];
    $request = new Request($swRequest);
    $post = $request->getPost();
    $online=0;
    if(isset($post['to_uid'])){
        $reviceUser=$post['c_user_type']==UserStatus::IS_DOCTOR?'u_' . $post['to_uid'] : 'd_' . $post['to_uid'];
        $reviceUser = Util::getUserData($reviceUser);
        if (!empty($reviceUser)) {
            $online=1;
            //在线push
            $res = Util::resulePaser('push', $post);
            $server->push($reviceUser->fd, Util::json_encode_unicode($res));
        }
    }
    $swResponse->end($online);
    return Route::Ignore;
});


Route::any("/getClineCount", function () {
    $redis = Util::GetRedisInst();
    return $redis->sCard(Event::$OnlineUserSetName);
});

//在线检查
Route::any("/online", function () {
    return "online";
});

Route::any("/reconnect", function () {
    $redis = Util::GetRedisInst();
    $msg = $redis->lRange("chat:BookingMsgList:342", 2, -1);
    $newMsg = [];
    $index=1;
    foreach ($msg as $key=>$value){
        $content = (array)json_decode($value);
        if($content['c_user_type'] !=1 && $content['is_read']==0){
            $content['id'] = $index;
            $newMsg[] = $content;
        }
        $index+=1;
    }
    $res = Util::resulePaser('reconnect', $newMsg);
    return $res;
});



