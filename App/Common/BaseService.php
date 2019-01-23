<?php

namespace App\Common;

use Lib\Assert;
use Lib\Logger;
use Lib\Config;
use \PDO;
use Lib\Validate;

abstract class BaseService
{
    protected $serviceLogFile = "service";
    protected $visitor;

    protected function __construct()
    {
    }

    //初始化业务对象
    protected function init(Visitor $visitor)
    {
        Assert::isTrue($visitor!=null&&$visitor->staffId>0&&!empty($visitor->staffName), "访问者不能为空!");
        $this->visitor = $visitor;
    }

    //设置实体的创建者
    protected function setCreator($entity)
    {
        $visitor = $this->visitor;
        Assert::isTrue($visitor!=null&&$visitor->staffId>0&&!empty($visitor->staffName), "访问者不能为空");
        $date = date('Y-m-d', time());
        $entity['CreateTime'] = $date;
        $entity['CreateStaffId'] = $visitor->staffId;
        $entity['CreateStaffName'] =  $visitor->staffName;
        $entity['UpdateTime'] = $date;
        $entity['UpdateStaffId'] = $visitor->staffId;
        $entity['UpdateStaffName'] = $visitor->staffName;
        return $entity;
    }

    //设置实体的最后修改者
    protected function setLastUpdater($entity)
    {
        $visitor = $this->visitor;
        Assert::isTrue($visitor!=null&&$visitor->staffId>0&&!empty($visitor->staffName), "访问者不能为空!");
        $entity['UpdateTime'] = date('Y-m-d', time());
        $entity['UpdateStaffId'] = $visitor->staffId;
        $entity['UpdateStaffName'] = $visitor->staffName;
        return $data;
    }

    //业务逻辑层日志
    protected function log($message)
    {
        $visitor = $this->visitor;
        Assert::isTrue($visitor!=null&&$visitor->staffId>0&&!empty($visitor->staffName), "访问者不能为空!");
        $visitorJson = json_encode($this->visitor);
        Logger::write("业务日志:类".__CLASS__.";方法".__METHOD__.";访问者{$visitorJson};消息{$message}!", $this->serviceLogFile );
    }

    //数据格式校验
    protected function validate(array $data, array $rule)
    {
        $validate = new Validate($rule);
        $result = $validate->check($data);
        Assert::isTrue($result, $validate->getError());
    }
}
