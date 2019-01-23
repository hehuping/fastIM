<?php
/**
 * Created by PhpStorm.
 * User: v_zuocheng
 * Date: 2017/12/8
 * Time: 9:41
 */

namespace App\Common;

class ResultModel
{
    //非法访问
    public static $IllegallyAccess = -1;
    //不在抽奖名单
    public static $NotInWhiteList = 0;
    //抽票未开启
    public static $BallotNotStart = 1;
    //抽票已开启
    public static $BallotStart = 2;
    //抽中票
    public static $BallotHit = 3;
    //未抽中票
    public static $BallotNotHit = 4;
    //抽票结束
    public static $BallotEnd = 5;
    //抽过票
    public static $HasBallot = 6;
    //没抽过票
    public static $NotHasBallot = 7;
    //抽票结果List
    public static $BallotResultList = 8;
    //不在管理员名单
    public static $NotInManagerWhiteList = 9;

    public function result($response, $data = '', $httpcode = 200, $error = 0)
    {
        $result = ['httpcode' => $httpcode, 'data' => $data, 'error' => $error];
        $response->status($httpcode);
        $response->end(json_encode($result, JSON_UNESCAPED_UNICODE));
    }
    public function resultArray($response, $result)
    {
        $response->end(json_encode($result, JSON_UNESCAPED_UNICODE));
    }
    public function getResult($error, $engName = '', $data = ''): array
    {
        if ($error == self::$IllegallyAccess) {
            $data = "非法访问，请联系管理员v_zuocheng处理";
        } elseif ($error == self::$NotInWhiteList) {
            $data = "页面访问受限，请联系v_zuocheng加入白名单";
        } elseif ($error == self::$BallotNotStart) {
            $data = "抽票通道尚未开启";
        } elseif ($error == self::$BallotStart) {
            $data = "抽票通道已经开启";
        } elseif ($error == self::$BallotHit) {
            $data = "已抽中票";
        } elseif ($error == self::$BallotNotHit) {
                $data = "未抽中票";
        } elseif ($error == self::$BallotEnd) {
            $data = "抽票已结束";
        } elseif ($error == self::$HasBallot) {
            $data = "已经抽过票了";
        } elseif ($error == self::$NotHasBallot) {
            $data = "未抽过票";
        } elseif ($error == self::$BallotResultList && $data == '') {
            $data = "抽票结果List为空";
        } elseif ($error == self::$NotInManagerWhiteList && $data == '') {
            $data = "管理功能，访问受限，请联系v_zuocheng加入白名单";
        }

        $result = ['httpcode' => 200, 'data' => $data, 'error' => $error, 'engname' => $engName];
        return $result;
    }
}