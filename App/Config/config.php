<?php
//系统运行的配置文件
return array(

    'pdox' => [
        'driver'    => 'mysql',
         'host'      => 'gz-cdb-0dtr3s6g.sql.tencentcdb.com',
        'database'  => 'report',
        'username'  => 'root',
        'user'  => 'root',
        'password'  => '2lmXkHIyj1Vsp53kto5wghfy',
        'charset'   => 'utf8', //指定字符集
        'collation' => 'utf8_general_ci',
        'prefix'    => '',
        'port'=>'63113'
    ],
    'redis' => [
        //'host' => "127.0.0.1",
        //'host' => "123.207.100.25",
        //'password'=>'MAbeRqDZwdb399af76280f8',
        'host' => "10.66.140.164",
        'password'=>'crs-ee62ptzd:j2daW9w@pca*psuQ',
        'port' => 6379,
        'db'   => 4
    ],
    'encryption_key' => hex2bin('ab9a573b1205e2e05b0a60e918b7969a'),
    'session_key' => 'sessionKey_user_info',
    'chat_refix'=>'chat:userInfo:',
    'cache_time'=> 12*3600, //用户信息缓存12小时

    'ROOT_DIR' => dirname(dirname(__DIR__)),

    'WX_ACCESS_TOKEN_URL'=>'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=wxf6bc8290efab15d5&secret=9da69aa7107da00b3764b3a17c63f813',
    'WX_APPID' => 'wxf6bc8290efab15d5',
    'WX_APPSECRET' =>'e48eeb4daec508432736666b003cd146',

);
