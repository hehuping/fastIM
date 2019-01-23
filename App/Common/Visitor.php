<?php

namespace App\Common;

//访问者
class Visitor
{
    function __construct($staffId, $staffName)
    {
        $this->staffId=$staffId;
        $this->staffName=$staffName;
    }

    //访问者员工Id
    public $staffId = -1;
    //访问者员工名称
    public $staffName = "";
}
