<?php
/**
 * Created by PhpStorm.
 * User: v_hhpphe
 * Date: 2017/8/1
 * Time: 12:09
 */

namespace Kernel;

use App\Common\Util;
use Lib\Config;
use \Swoole\Http\Server as SwooleServer;
use \Swoole\Websocket\Server as WebsocketServer;
use \Swoole\Http\Request as SwooleRequest;
use \Swoole\Http\Response as SwooleResponse;

use Lib\Logger;
use Lib\Result;

class Application
{
    static public $AppPath = "App";
    static public $MiddlewarePath;

    protected $config = array();
    protected $route;

    public function init($cfg, $appPath = "App")
    {
        $this->config = $cfg;
        self::$AppPath = $appPath;
        Config::load(__DIR__ . '/../App/Config');
        return $this;
    }

    public function run()
    {
        $server = new WebsocketServer($this->config['host'], $this->config['port']/*, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL*/);
        //创建磁盘文件
        if (isset($this->config['log_file'])) {
            if (!is_dir(dirname($this->config['log_file']))) {
                mkdir(dirname($this->config['log_file']), 0755, true);
            }
        }
        $server->set($this->config);

        $server->on('Start', function () {
            cli_set_process_title("Swoole_Master");
            echo "Master Start\n";
        });

        $server->on("WorkerStart", function () use($server){
            //全局server
            $GLOBALS['server'] = $server;
            cli_set_process_title('Swoole_worker');
            self::$MiddlewarePath = "App\Middleware";
            require_once __DIR__ . "/../App/bootstrap.php";

            echo "Worker Start\n";
        });

        $server->on('Request', function (SwooleRequest $swRequest, SwooleResponse $swResponse) {
           // echo "http link \n";
            $uri = $swRequest->server['request_uri'];
            $method = $swRequest->server['request_method'];
            $routeInfo = Route::getRoute($uri);
            if (empty($routeInfo)) {
                $this->handleResponse($swResponse, new Result(404, null, 'uri not found'), 404);
            } elseif (!in_array(strtoupper($method), $routeInfo['method'])) {
                $this->handleResponse($swResponse, new Result(403, null, 'method not support.'), 403);
            } else {
                $callback = $routeInfo['callback'];
                try {
                    $data = $callback($swRequest, $swResponse);
                    //处理结果data为空一般是由于处理函数内部已经对response进行了处理，比如文件下载，不需要再end
                    if ($data != Route::Ignore) {
                        $this->handleResponse($swResponse, $data);
                    }
                } catch (\Throwable $err) {
                    $this->handleResponse($swResponse, new Result(500, null, $err->getMessage()), 500);
                }
            }
        });


        //打开连接事件
        $server->on('Open', function (WebsocketServer $server, $request) {
            $uri = $request->server['request_uri'];
            $callback = Route::getWebsoketDispatcher($uri, 'OPEN');
            if (empty($callback)) {
                $this->websocketResponse($server, $request, new Result(404, null, '路径或TOKEN错误！'));
                Logger::write('[ERROR]:回调方法不存在', 'open');
                //$server->disconnect($request->fd);
                $server->close($request->fd);
            } else {
                try {
                    $data = $callback($request, $server, []);
                    if ($data !==true) {
                        $this->websocketResponse($server, $request, new Result(500, null, '鉴权失败!'));
                        //$server->disconnect($request->fd);
                        $server->close($request->fd);
                    }
                } catch (\Throwable $err) {
                    $this->websocketResponse($server, $request, new Result(500, null, $err->getMessage()));
                    //$server->disconnect($request->fd);
                    $server->close($request->fd);
                }
            }

        });

        //消息事件
        $server->on('Message', function (WebsocketServer $server, $frame) {
            echo "message \n";
            $this->dispatcher($server, $frame, 'MESSAGE');
        });

        //连接关闭事件
        $server->on('Close', function ($server, $fd) {
            echo "close \n";
            $frame = (object)[];
            $frame->fd = $fd;
            $this->dispatcher($server, $frame, 'CLOSE');
        });

        $server->start();
    }

    private function dispatcher(SwooleServer $server, $frame, $event)
    {
        //获取fd关联的请求分发路径
        try{
            $reqInfo = $this->getUserInfo($frame->fd);
        }catch (\RedisException $err){
            $errs = '[ERROR]:'.$err->getMessage().',line '.$err->getLine().',on File:'.$err->getFile();
            Logger::write($errs, 'dispatcher_getuserinfo_error');
            $res=Util::resulePaser('fail','',5001,$errs);
            if($event!='CLOSE'){
                $this->websocketResponse($server, $frame, $res);
            }
        }

        if (!$reqInfo) {
            if($event!='CLOSE'){
                $this->websocketResponse($server, $frame, new Result(1001, null, '获取请求路径信息失败！'));
                //$server->disconnect($frame->fd);
                $server->close($frame->fd);
                return false;
            }
            //$server->close($frame->fd);
        } else {
            $callback = Route::getWebsoketDispatcher($reqInfo->path, strtoupper($event));
            try {
                $data = $callback($server, $frame, $reqInfo);
                if ($data != Route::Ignore) {
                    //$this->websocketResponse($server, $frame, $data);
                }
            } catch (\Throwable $err) {
                $errs = '[ERROR]:'.$err->getMessage().',line '.$err->getLine().',on File:'.$err->getFile();
                Logger::write($errs, 'dispatcher_callback_error');
                if($event!='CLOSE'){
                    $this->websocketResponse($server, $frame, new Result(500, null, $errs));
                }
            }
        }
    }

    //简化结果处理
    private function handleResponse(SwooleResponse $swResponse, $result, $status = 200)
    {
        $swResponse->status($status);
        /* $swResponse->header('Access-Control-Allow-Origin', '*');
         $swResponse->header('Access-Control-Allow-Headers', 'x-requested-with,content-type');*/
        $swResponse->write(json_encode($result, JSON_UNESCAPED_UNICODE));
        $swResponse->end();
    }

    /**
     * 普通长连接返回处理
     * @param $server
     * @param $request
     * @param $result
     */
    private function websocketResponse(SwooleServer $server, $request, $result)
    {
        Logger::write('DEBUG:'. json_encode($result, JSON_UNESCAPED_UNICODE), 'debug_log-');
        $server->push($request->fd, json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    //服务启动，初始化redis在线记录集合
    private function initRedisOnline()
    {
        $redis = Util::CreateRedis();
        $redis->del(ONLINE_DOCTOR_SETNAME);
        $redis->del(ONLINE_USER_SETNAME);
    }

    private function getUserInfo($fd){
        $prefix = Config::get('chat_refix');
        $redis = Util::CreateRedis(true);
        $info = $redis->get($prefix.$fd);
        return json_decode($info);
    }
}
