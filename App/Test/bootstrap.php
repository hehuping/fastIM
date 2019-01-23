<?php
use Lib\Logger;
use Lib\Config;

//注册autoload函数
function autoload($class) {
    if(strpos($class,'\\') !== false) {
        $classpath = str_replace('\\', '/', $class);
        $filename = "../../$classpath.php";
        if(is_file($filename)){
            require_once $filename;
        }
    }
}
//注册自动加载函数
spl_autoload_register('autoload');

//设置时区
date_default_timezone_set('PRC');

//初始化日志类
$log = array(
    'log_time_format'   =>  ' Y-m-d H:i:s ',
    'log_file_size'     =>  2097152,
    'log_path'          =>  __DIR__.'/Temp/log/',
    'log_adapter'       =>  'file'
);
Logger::init($log);

//初始化配置类
Config::load(__DIR__.'/Config');
