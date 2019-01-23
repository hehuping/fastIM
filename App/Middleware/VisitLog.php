<?php

namespace App\Middleware;

use App\Common\Util;
use Closure;
use Lib\Logger;


//访问日志
class VisitLog
{
    public static function handle($next, $swRequest, $swResponse, $userInfo)
    {
        $userInfo = $userInfo?$userInfo:[];
        $data = isset($swRequest->header)?$swRequest->header:(isset($swResponse->data)?$swResponse->data:'');
        $data = is_string($data)?$data:Util::json_encode_unicode($data);
        $info = $data.'|'.Util::json_encode_unicode($userInfo);
        Logger::write('info:'.$info,'VisitLog_middleware_');
        $result = $next($swRequest, $swResponse, $userInfo);
        return $result;
    }
}
