<?php
//系统运行的配置文件
return array(

    'pdox' => [
        'driver'    => 'mysql',
         'host'      => '***',
        'database'  => 'chat',
        'username'  => 'root',
        'user'  => 'root',
        'password'  => '***',
        'charset'   => 'utf8', //指定字符集
        'collation' => 'utf8_general_ci',
        'prefix'    => '',
        'port'=>'63113'
    ],
    'redis' => [
        'host' => "127.0.0.1",
        'password'=>'hehuping',
        'port' => 6379,
        'db'   => 4
    ],

    'chat_refix'=>'chat:userInfo:',
    'cache_time'=> 12*3600, //用户信息缓存12小时

    'ROOT_DIR' => dirname(dirname(__DIR__)),
);
