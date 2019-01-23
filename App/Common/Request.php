<?php

namespace App\Common;

use Lib\Logger;
use Lib\Assert;
use App\Common\Visitor;
use Swoole\Http\Request as SwooleRequest;

class Request
{
    public $get;
    public $post;
    public $server;
    public $header;
    public $cookie;
    public $files;

    public function __construct(SwooleRequest $request)
    {
        $this->get = isset($request->get)?$request->get:[];
        $this->post = isset($request->post)?$request->post:[];
        $this->server = isset($request->server)?$request->server:[];
        $this->header = isset($request->header)?$request->header:[];
        $this->cookie = isset($request->cookie)?$request->cookie:[];
        $this->files = isset($request->filese)?$request->files:[];
    }

    public static function make(SwooleRequest $request)
    {
        return new Request($request);
    }

    public function getPost():array
    {
        return isset($this->post) ? $this->post : [];
    }

    /**
     * 获取请求参数（get，post）
     * @param string $key
     * @return bool|string
     */
    public function getParam($key = '')
    {
        if ($this->server['request_method'] =='POST') {
            $input = $this->post;
        } else {
            $input = $this->get;
        }
        return isset($input[$key])?$input[$key]:'';
    }

    public function getHeader($key = '')
    {
        return isset($this->header[$key])?$this->header[$key]:'';
    }

    public function getCookie($key = '')
    {
        return isset($this->cookie[$key])?$this->cookie[$key]:'';
    }

    public function getVisitor()
    {
        $staffId = $this->header["staffid"];
        $staffName = $this->header["staffname"];
        Assert::isTrue(!empty($staffId), "staffId不能为空！");
        Assert::isTrue(!empty($staffName), "staffName不能为空！");

        $visitor = new Visitor($staffId, $staffName);
        $visitor->staffId = $staffId;
        $visitor->staffName = $staffName;
        return $visitor;
    }
    //返回请求相关信息，用于写日志
    public function getRequestInfo(){
        $info = $this->server;
        $param = ['GET'=>'', 'POST'=>''];
        if(isset($this->get)){
            $param['GET'] = http_build_query($this->get);
        }elseif(isset($this->post)){
            $param['POST'] = http_build_query($this->post);
        }
        $info = array_merge($info, $param);
        unset($info['query_string']);
        unset($info['server_software']);
        unset($info['request_time_float']);
        unset($info['server_protocol']);
        unset($info['server_port']);
        return implode(',', array_values($info));
    }
}
