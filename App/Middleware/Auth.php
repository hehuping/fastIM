<?php
namespace App\Middleware;

use Closure;
use Kernel\Route;
use Lib\Config;
use Lib\Logger;
use App\Common\Request;

//连接签名认证
class Auth
{

    public static function handle($next, $server, $sWrequest, $userInfo, $swoole_Table){
        $token = "test";
        //获取头部参数
        $request = new Request($sWrequest);
        //时间戳
        $timestamp = $request->getHeader('timestamp');
        //随机数
        $random = $request->getHeader('random');
        //签名结果
        $signature = $request->getHeader('signature');

        $localSignStr = $random . $timestamp . $token;
        $localSignature = strtoupper(hash('sha256', $localSignStr));
        if($localSignature != $signature || (time()-$timestamp)>300){
            //签名校验不通过
            $server->push($sWrequest->fd, json_encode(['errCode'=>-1, 'msg'=>'签名认证失败！'], JSON_UNESCAPED_UNICODE));
            $server->close($sWrequest->fd);
            Logger::write('签名验证失败：[userinfo:'.json_encode($userInfo)."]");
            return Route::Ignore;

        }

        return  $next($server, $request, $userInfo, $swoole_Table);
    }
}
