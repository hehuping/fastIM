<?php
//单元测试的配置文件
return array(
    'pdo' => [
        'dsn'=>'mysql:host=127.0.0.1;dbname=demo',
        'user'=>'root',
        'pwd'=>'828kindy@@'
    ],
    'redis' => [
        'host' => "127.0.0.1",
        'port' => 6379,
        'db'   => 1,
        'password' => '12345678',
    ],
    //管理员配置
    'admin' => ['kindywu','v_qiqiqihu','v_zdhe','v_zrwang','v_jhpeng'],
    'bus_token' => 'test',
    'excel_path' => '/Temp/Excel/'
);
