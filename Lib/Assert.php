<?php
namespace Lib;

//断言类
class Assert
{
    public static function isTrue($flag, $message)
    {
        self::watefall()->isTrue($flag, $message)->done();
    }

    public static function notNull($flag, $message)
    {
        self::watefall()->notNull($flag, $message)->done();
    }

    public static function checkTimeFormat($timeStr,$format,$message) {
        self::watefall()->checkTimeFormat($timeStr,$format,$message)->done();
    }

    public static function watefall()
    {
        return new Assertion();
    }
}

class Assertion
{
    private $exceptions = array();

    public function isTrue($flag, $message)
    {
        if (!$flag) {
            array_push($this->exceptions, $message);
        }
        return $this;
    }

    public function notNull($flag, $message)
    {
        if ($flag==null || empty($flag)) {
            array_push($this->exceptions, $message);
        }
        return $this;
    }

    /**
     * @name checkTimeFormat
     * @attribute public
     * @description 检查时间格式是否正确
     * @param $timeStr
     * @param $format
     * @param $message
     * @return $this
     */
    public function checkTimeFormat($timeStr = '',$format = 'Y-m-d:H:i:s',$message) {
        if(date($format,strtotime($timeStr)) !== $timeStr)
            array_push($this->exceptions,$message);
        return $this;
    }

    public function done()
    {
        if (!empty($this->exceptions)) {
            throw new AssertException(json_encode($this->exceptions));
        }
    }
}
