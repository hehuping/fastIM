<?php
use Lib\Logger;
use Kernel\Route;
use Lib\Config;

//设置时区
date_default_timezone_set('PRC');

//初始化日志类
$log = array(
    'log_time_format'   =>  ' Y-m-d H:i:s ',
    'log_file_size'     =>  2097152,
    'log_path'          =>  __DIR__.'/../Temp/log/',
    'log_adapter'       =>  'file'
);

Logger::init($log);

set_error_handler('errorHandler');

//初始化配置类
Config::load(__DIR__.'/../App/Config');

/**
 * 加载路由配置
 */
foreach (glob(__DIR__.'/../App/Route/*.php') as $router) {
    require_once $router;
}

/**
* 自定义错误处理方法
* @param $error           错误代码
* @param $message         错误信息
* @param string $errfile  报错文件
* @param string $errline  报错行数
*/
function errorHandler($error, $message, $errfile = '', $errline = '')
{
    $level = Logger::$ErrorLevel[$error];
    $log = "{$level}: {$message} ; StackTraces:  $errfile : $errline";
    //单独文件记录错误日志
    Logger::write( $log , 'SysMessage_');
    if($error==E_WARNING || $error==E_NOTICE){
        return;
    }
    throw new ErrorException($log);
}
