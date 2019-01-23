<?php
/**
 * Created by PhpStorm.
 * User: v_hhpphe
 * Date: 2017/8/25
 * Time: 10:09
 */

namespace App\Service\BaseSite;

use App\Common\Util;
use Lib\Assert;
use Lib\Config;
use Lib\Validate;

class DemoService{

    protected $redis = null;
    protected $AppNamePrefix = "AppNameList";
    protected $appInfoPrefix = 'AppInfo:';
    protected $error;

    public function __construct()
    {
        $this->redis = Util::GetRedisInst();
    }

    /**
     * 获取所有应用状态信息
     * @return array
     */
    public function getAllAppInfo(){
        $allApp = $this->redis->SMEMBERS($this->AppNamePrefix);
        $allInfo = [];
        foreach($allApp as $v){
            $info = $this->redis->hGetAll($this->appInfoPrefix.$v);
            $info['isDelete'] = (int)$info['isDelete'];
            $allInfo[] = $info;
        }
        return $allInfo;
    }
}
