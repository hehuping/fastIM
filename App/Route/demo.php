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


Route::websocketGroup('/demo', ['middleware' => 'VisitLog'], function () {
    //连接打开事件
    Route::websocketOpenEvent(function ($request, $response,$userInfo) {
        return true;
    }, 'UserAuth');


    //消息事件
    Route::websocketMessageEvent(function ($server, $frame,$userInfo ) {
        return Route::Ignore;
    });


    //断开连接事件
    Route::websocketCloseEvent(function ($server, $frame, $userInfo) {
        return Route::Ignore;
    });

});


Route::post("/firstpush", function ($swRequest, $swResponse) {

    $swResponse->end('123');
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




