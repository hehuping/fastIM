<?php

function autoload($class) {
    if(strpos($class,'\\') !== false) {
        $classpath = str_replace('\\', '/', $class);
        $filename = dirname(__DIR__)."/$classpath.php";
        if(is_file($filename)){
            require_once $filename;
        }
    }
}
//注册自动加载函数
spl_autoload_register('autoload');