<?php

namespace App\Common;

use Lib\Logger;
use App\Service\Event\Event;
use App\Common\Util;

// 用户状态类
class UserStatus
{
    const IS_USER=1;
    const IS_DOCTOR=2;

    /**
     * 检查用户是否在线，如果在线直接返回频道id，不在线则把用户设置成在线（把用户信息放到redis中）
     * 用来修复，当前端socket链接后，不知道为啥没有缓存用户信息，从而导致误判用户不在线的问题
     * param $uid int 用户id
     * param $userInfo array 用户信息
     * param $fd int  用户在socket中的链接号
     * return bool/int  false：不在线/init用户频道id
     */
    public static function checkAndSetOnline($uid, $userInfo, $fd, $logType = '')
    {
	$userInfo = is_array($userInfo) ? $userInfo : (array)$userInfo;
        $userType = $userInfo['auth_type'];
        // 1.检查是否在线
        $cacheUserInfo = self::checkUserOnline($uid, $userType, !empty($logType) ? $logType : 'checkAndSetOnline');
        if (!empty($cacheUserInfo)){
            Logger::write("[INFO][User/checkAndSetOnline]非空：" . json_encode($userInfo, 300), 'socket');
            return $cacheUserInfo;
        }

        // 2.重新设置用户在线
        $clientResult = self::setUserOnline($uid, $userInfo, $fd);
        Logger::write("[INFO][User/checkAndSetOnline]".($userType == self::IS_DOCTOR ? '医生' : '用户'). $uid . "不在线，重新设置在线返回：" . $clientResult, 'socket');
        return $clientResult;
    }

    /**
     * 检查当前用户是否在线
     * param $uid init 用户id
     * param $userType init 1：用户/2：医生
     * return bool/object false不在线/object用户信息的对象
     */
    public static function checkUserOnline($uid, $userType, $logType = '')
    {
        $userKey = $userType == self::IS_DOCTOR ? 'd_' . $uid : 'u_' . $uid;
        Logger::write("[INFO][checkUserOnline]" . (!empty($logType) ? '动作：' . $logType : ''). "查询" . $userKey . "是否在线：", 'socket');
        $reviceUser = Util::getUserData($userKey);
        if (empty($reviceUser)) {
            Logger::write("[INFO][checkUserOnline]查询结果：" . $userKey . '不在线', 'socket');
            return false;
        }

        Logger::write("[INFO][checkUserOnline]用户". $userKey."在线，查询结果：" . json_encode($reviceUser, 300), 'socket');
        return $reviceUser;
    }

    /**
     * param $uid init 用户id
     * param $userType init 1：用户/2：医生
     * param $fd int 用户在socket中的频道
     * return bool true在线/false不在线
     */
    public static function setUserOnline($uid, $userType, $fd)
    {
        $modal = new Event();
        //写入redis在线集合
        $clientResult = $modal->setUserOnline($uid, $fd, $userType);
        Logger::write("[INFO][setUserOnline]".($userType == self::IS_DOCTOR ? '医生' : '用户'). $uid . "连接，缓存Redis返回：" . $clientResult, 'socket');
        return $clientResult;
    }
}
