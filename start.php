<?php


$options = getopt("s:");
$options = empty($options) ? '' : $options['s'];

//注册autoload函数
require_once __DIR__.'/Kernel/autoload.php';

$pidFile = __DIR__.'/pid1';

$pid = null;
if(file_exists($pidFile)){
    $pid = file_get_contents($pidFile);
}

$cfg = [
    'host'=>'0.0.0.0',
    'port'=>9501,
    'worker_num' => 2,                                             //worker进程数，cpu倍数
    'daemonize' => 0 ,                                               //是否守护进程运行,1是0否
    'max_request' => 10000,                                         //最大请求数，超出则重启worker进程
    'max_conn' => 10000,                                            //最大连接数,超出则拒绝
   // 'dispatch_mode' =>3,                                            //worker进程分配模式,4按ip（dispatch_mode=1/3时，底层会屏蔽onConnect/onClose事件，原因是这2种模式下无法保证onConnect/onClose/onReceive的顺序）
    'enable_coroutine' => true,
    'open_tcp_nodelay' => true,                                     //是否关闭TCP Nagle合并算法
    'log_file' => __DIR__.'/Data/sys/'.date('Y_m_d').'.log',        //swoole系统日志
    'pid_file' => $pidFile,                                         //系统进程文件
   // 'heartbeat_check_interval' => 5,
    //'heartbeat_idle_time' => 3600,
];


if($pid && $options){
    switch($options){
        //reload worker
        case 'reload':
            exec('kill -USR1 '.$pid);
            echo "reload success ! \n";
            break;
        case 'stop':
            //kill -SIGTERM is doesn't work
            exec('kill -15 '.$pid);
            echo "stop service !\n";
            break;
        case 'restart':
            exec('kill -15 '.$pid);
            echo "stop service !\n";
            sleep(2);
            exec('php start.php');
            echo "start service !\n";
            break;
        default:
            echo "No such pid \n";
    }
}else{
    try{
        $app = new \Kernel\Application();
        $app->init($cfg)->run();
    }catch (Throwable $exception){
         $log = "{$exception->getTraceAsString()}: {$exception->getMessage()} ; StackTraces:  {$exception->getFile()} : {$exception->getLine()}"."\n";
        error_log($log, 3,'./globle_error.log');
        echo "启动失败，错误原因：".$log;
    }

}


