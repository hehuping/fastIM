<?php
namespace Lib;

//Http返回结果
class Result
{
    public $code;
    public $data;
    public $msg;

    public function __construct(int $code = 200, $data, string $msg = '')
    {
        $this->errcode= $code;
        $this->errmsg = $msg;
        $this->data = $data;
    }
}
