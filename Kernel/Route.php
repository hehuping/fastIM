<?php
/**
 * Created by PhpStorm.
 * User: v_hhpphe
 * Date: 2017/8/25
 * Time: 10:09
 */
namespace Kernel;

use Closure;

use Lib\Logger;
use Lib\Assert;

class Route
{
    //正常返回封装为Result输出，路由返回这个值，则不做的处理
    const Ignore = "#erWQC89XU@3sYI5So#";

    private static $pathMatch = []; //路由匹配规则
    private static $globalMiddlewares = [];//全局中间件
    private static $groupAttributes = [];//分组中间件
    private static $websocketGroupAttributes = [];//分组中间件
    private static $eventList = ['OPEN', 'MESSAGE', 'CLOSE'];

    //设置全局中间件
//    public static function use(string $middlewareStr)
//    {
//        Assert::isTrue(!empty($middlewareStr), "global' middleware must not empty!");
//        $middlewares = explode('|', $middlewareStr);
//        self::$globalMiddlewares = array_merge(self::$globalMiddlewares, $middlewares);
//    }

    /**
     * 路由分组
     * @param array $attributes
     * @param Closure $callback
     */
    public static function group(array $attributes, Closure $callback)
    {
        Assert::isTrue(isset($attributes['prefix']) && is_string($attributes['prefix']), "group'prefix must not empty!");
        Assert::isTrue(isset($attributes['middleware']) && is_string($attributes['middleware']), "group'middleware must not empty!");
        //解析中间件
        $attributes["middleware"] = explode('|', $attributes["middleware"]);

        array_push(self::$groupAttributes, $attributes);
        call_user_func($callback);
        array_pop(self::$groupAttributes);
    }

    /**
     * @param string $uri
     * @param callable $dispatcher
     * @param string $middleware
     */
    public static function get(string $uri = '/', callable $dispatcher, string  $middleware = '')
    {
        self::addRoute('GET', $uri, $dispatcher, $middleware);
    }

    public static function post(string $uri = '/', callable $dispatcher, string  $middleware = '')
    {
        self::addRoute('POST', $uri, $dispatcher, $middleware);
    }

    public static function any(string $uri = '/', callable $dispatcher, string  $middleware = '')
    {
        self::addRoute(array('GET', 'POST'), $uri, $dispatcher, $middleware);
    }

    public static function websoket(string $uri = '/', callable $dispatcher, string  $middleware = '')
    {
        self::addRoute('WEBSOCKET', $uri, $dispatcher, $middleware);
    }

    /**
     * 获取路由信息
     * @param $uri
     * @return array
     */
    public static function getRoute($uri)
    {
        if (isset(self::$pathMatch[$uri])) {
            $tmp=self::$pathMatch[$uri];
            return ['method'=>$tmp['method'], 'callback'=>$tmp['callback']];
        } else {
            return [];
        }
    }

    /**
     * @param $uri
     * @param string $eventName
     * @return array
     */
    public static function getWebsoketDispatcher($uri, string $eventName){
        if (isset(self::$pathMatch[$uri])) {
            $tmp=self::$pathMatch[$uri];
            Assert::isTrue(isset(self::$pathMatch[$uri][$eventName]), "$eventName is not defined" );
            return $tmp[$eventName]['callback'];
        } else {
            return [];
        }
    }

    /**
     * 设置路由信息
     * @param $httpMethod
     * @param $uri
     * @param $middleware
     * @param callable $dispatcher
     */
    private static function addRoute($httpMethod, string $uri, callable $dispatcher, string $middleware)
    {
        $middlewares = [];
        Assert::isTrue($dispatcher!=null && is_callable($dispatcher), "route execute func can't not be null or not callback!" );
        //解析中间件
        if (isset($middleware) && is_string($middleware) && !empty($middleware)) {
            $middlewares = explode('|', $middleware);
        }

        for ($i=count(self::$groupAttributes); $i>0; $i--) {
            $groupAttribute = self::$groupAttributes[$i-1];
            //添加分组Url前缀
            $uri = $groupAttribute['prefix'].$uri;
            //添加分组中间件
            $groupMiddlewares = $groupAttribute['middleware'];
            $middlewares = array_merge($groupMiddlewares, $middlewares);
        }

        //添加全局中间件
        for ($i=count(self::$globalMiddlewares); $i>0; $i--) {
            $globalMiddleware = self::$globalMiddlewares[$i-1];
            array_unshift($middlewares, $globalMiddleware);
        }

        Assert::isTrue(!isset(self::$pathMatch[$uri]), "$uri is exist!");

        //中间件命名空间注册
        $middlewares = array_map(function ($value) {
            $class = Application::$MiddlewarePath.'\\'.$value;
            Assert::isTrue(class_exists($class), "middleware'{$class}'is not exist!");
            return $class;
        }, $middlewares);

        $callback = self::pipeline($middlewares, $dispatcher);

        $routeInfo = ['method'=>(array) $httpMethod, 'middlewares'=>$middlewares, 'dispatcher'=>$dispatcher, 'callback'=>$callback];
        self::$pathMatch[$uri] = $routeInfo;
    }

    /**
     * 添加websocket 事件分发
     * @param string $eventName
     * @param callable $dispatcher
     * @param string $middleware
     */
    private static function addWebSocketRoute(string $eventName, callable $dispatcher, string $middleware){

        $middlewares = [];
        $eventName = strtoupper($eventName);
        Assert::isTrue(in_array($eventName, self::$eventList), "the even $eventName is not right!" );
        Assert::isTrue($dispatcher!=null && is_callable($dispatcher), "route execute func can't not be null or not callback!" );
        //解析中间件
        if (isset($middleware) && is_string($middleware) && !empty($middleware)) {
            $middlewares = explode('|', $middleware);
        }

        for ($i=count(self::$websocketGroupAttributes); $i>0; $i--) {
            $groupAttribute = self::$websocketGroupAttributes[$i-1];
            //添加分组Url前缀
            $uri = $groupAttribute['url'];
            //添加分组中间件
            $groupMiddlewares = $groupAttribute['middleware'];
            $middlewares = array_merge($groupMiddlewares, $middlewares);
        }

        //添加全局中间件
        for ($i=count(self::$globalMiddlewares); $i>0; $i--) {
            $globalMiddleware = self::$globalMiddlewares[$i-1];
            array_unshift($middlewares, $globalMiddleware);
        }

        Assert::isTrue(!isset(self::$pathMatch[$uri][$eventName]), "event is exist!");

        //中间件命名空间注册
        $middlewares = array_map(function ($value) {
            $class = Application::$MiddlewarePath.'\\'.$value;
            Assert::isTrue(class_exists($class), "middleware'{$class}'is not exist!");
            return $class;
        }, $middlewares);

        $callback = self::pipeline($middlewares, $dispatcher);
        $routeInfo = ['eventName'=>$eventName, 'middlewares'=>$middlewares, 'dispatcher'=>$dispatcher, 'callback'=>$callback];
        self::$pathMatch[$uri][$eventName] = $routeInfo;
    }

    /**
     * websocket 请求分组分发
     * @param string $url
     * @param array $attributes
     * @param Closure $callback
     */
    public static function websocketGroup(string $url,array $attributes, Closure $callback)
    {
        //Assert::isTrue(isset($attributes['prefix']) && is_string($attributes['prefix']), "group'prefix must not empty!");
        Assert::isTrue(isset($attributes['middleware']) && is_string($attributes['middleware']), "group'middleware must not empty!");
        //解析中间件
        $attributes["middleware"] = explode('|', $attributes["middleware"]);
        $attributes["url"] = $url;

        array_push(self::$websocketGroupAttributes, $attributes);
        call_user_func($callback);
        array_pop(self::$websocketGroupAttributes);
    }

    public static function websocketOpenEvent(callable $callback, $middleware=''){
        self::addWebSocketRoute('OPEN', $callback, $middleware);
    }
    public static function websocketMessageEvent(callable $callback, $middleware=''){
        self::addWebSocketRoute('MESSAGE', $callback, $middleware);
    }
    public static function websocketCloseEvent(callable $callback, $middleware=''){
        self::addWebSocketRoute('CLOSE', $callback, $middleware);
    }

    /**
     * 管道执行函数,预生成
     * @param $middlewares
     * @param callable $dispatcher
     * @return mixed
     */
    private static function pipeline($middlewares, callable $dispatcher)
    {
        $middlewares = array_reverse($middlewares);
        $fn = function ($next, $item) {
            return function ($request, $response, $userInfo) use ($next, $item) {
                return $item::handle($next, $request, $response, $userInfo);
            };
        };
        $callback = array_reduce($middlewares, $fn, $dispatcher);
        return $callback;
    }

    /**
     * 获取路由信息列表(调试用）
     * @return array
     */
    public static function getRouteList()
    {
        $res =[];
        foreach (self::$pathMatch as $key => $value) {
            $res[] = json_encode([$key,$value['method'],$value['middlewares']]);
        }
        return $res;
    }
}
