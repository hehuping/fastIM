<?php
/**
 * Created by PhpStorm.
 * User: hehuping
 * Date: 2018/7/6
 * Time: 9:47
 */

namespace App\Middleware;

//连接签名认证
class UserAuth
{
    public static function handle($next, $sWrequest, $sWresponse){
        $uri = $sWrequest->server['request_uri'];
        return $next($sWrequest, $sWresponse, []);
        //return false;
    }
}