<?php

namespace App\Common;

use Lib\Config;
use Lib\Assert;
use Lib\Pdox;
use Lib\Logger;
use Redis;
use PDO;

//工具类
class Util
{
    public static $redisInstance = null;

    //获取redis的单例(切记不可用于事务操作,会导致多线程操作冲突)
    public static function GetRedisInst()
    {
        if (self::$redisInstance == null) {
            self::$redisInstance = self::CreateRedis();
        }
        return self::$redisInstance;
    }

    //创建新的redis的对象
    public static function CreateRedis($coroutine=false)
    {
        $config = Config::get("redis");

        Assert::isTrue($config != null, "redis配置为空!");
        Assert::isTrue(array_key_exists("host", $config) && !empty($config["host"]), "redis配置host为空!");
        Assert::isTrue(array_key_exists("port", $config) && !empty($config["port"]), "redis配置port为空!");
        Assert::isTrue(array_key_exists("db", $config), "redis配置db为空!");

        try {
            if($coroutine){
                $redis = new \Swoole\Coroutine\Redis();
                //$redis = new Redis();
            }else{
                $redis = new Redis();
            }
            $redis->connect($config["host"], $config["port"]);
            if (array_key_exists("password", $config) && !empty($config["password"])) {
                $redis->auth($config["password"]);
            }
            $redis->select((int)$config["db"]);
            return $redis;
        } catch (\Throwable $e) {
            Logger::write("[ERROR][CreateRedis]Redis连接异常：" . $e->getMessage());
            throw new \Exception($e->getFile() . 'Error in ' . $e->getLine() . ":" . $e->getMessage());
        }

    }

    function RunRedisTran(callable $fn)
    {
        $result = null;
        $redis = Util::CreateRedis();
        try {
            $result = $fn($redis);
            return $result;
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            $redis->close();
        }
    }

    private static $pdoInstance = null;

    //获取pdo的单例(切记不可用于事务操作,会导致多线程操作冲突)
    public static function GetPDOInst()
    {
        if (self::$pdoInstance == null) {
            self::$pdoInstance = self::CreatePDO();
        }
        return self::$pdoInstance;
    }

    //创建新的pdo的对象
    public static function CreatePDO()
    {
        $config = Config::get("pdo");

        Assert::isTrue($config != null, "pdo配置为空!");
        Assert::isTrue(array_key_exists("dsn", $config) && !empty($config["dsn"]), "pdo配置dsn为空!");
        Assert::isTrue(array_key_exists("user", $config) && !empty($config["user"]), "pdo配置user为空!");
        Assert::isTrue(array_key_exists("pwd", $config) && !empty($config["pwd"]), "pdo配置pwd为空!");

        $dbh = new PDO($config["dsn"], $config["user"], $config["pwd"]);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);//设置出错抛出异常
        return $dbh;
    }

    public static function RunPDOTran(callable $fn)
    {
        $result = null;
        $dbh = Util::CreatePDO();
        try {
            $dbh->beginTransaction();
            $result = $fn($dbh);
            $dbh->commit();
            return $result;
        } catch (\Throwable $e) {
            $dbh->rollBack();
            throw $e;
        } finally {
            $dbh = null;
        }
    }

    /**
     *  GET 请求
     * @param $url
     * @return bool|mixed
     */
    public static function CurlGet($url, $header = [])
    {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== false) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            $res = $sContent;
        } else {
            $error = curl_errno($oCurl);
            trigger_error($error);
            return false;
        }
        curl_close($oCurl);
        return $res;
    }

    /**
     * POST 请求
     * @param $url
     * @param $param
     * @param bool|false $post_file
     * @return bool|mixed
     */
    public static function CurlPost($url, $param, $post_file = false)
    {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== false) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        if (is_string($param) || $post_file) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach ($param as $key => $val) {
                $aPOST[] = $key . "=" . urlencode($val);
            }
            $strPOST = join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, array('Expect:'));//关闭100-continue请求
        curl_setopt($oCurl, CURLOPT_POST, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            $res = $sContent;
        } else {
            $error = curl_errno($oCurl);
            trigger_error($error);
            return $error . '/' . $sContent;
        }
        curl_close($oCurl);
        return $res;
    }

    public static function readCsv(string $filename)
    {
        set_time_limit(0);
        setlocale(LC_ALL, 'zh_CN');
        ini_set('memory_limit', '500M');
        $file = fopen($filename, 'r');
        if ($file) {
            $i = 0;
            $data = array();
            while (!feof($file)) {
                $row = fgetcsv($file);

                if ($i > 0) {
                    //针对中文转码
                    /*foreach($row as $k => $v){
                        $row[$k]  = mb_convert_encoding($v, "UTF-8", "GBK");
                    }*/
                    array_push($data, $row);
                }
                $i++;
            }
            fclose($file);
            return $data;
        } else {
            return false;
        }
    }

    //创建新的pdox的对象
    public static function createPdox()
    {
        $config = Config::get("pdox");
        assert($config != null, "pdox配置为空!");
        assert(array_key_exists("driver", $config) && !empty($config["driver"]), "pdox配置host为空!");
        assert(array_key_exists("host", $config) && !empty($config["host"]), "pdox配置host为空!");
        assert(array_key_exists("database", $config) && !empty($config["database"]), "pdox配置database为空!");
        assert(array_key_exists("username", $config) && !empty($config["username"]), "pdox配置username为空!");
        assert(array_key_exists("password", $config) && !empty($config["password"]), "pdox配置password为空!");
        assert(array_key_exists("charset", $config) && !empty($config["charset"]), "pdox配置charset为空!");
        assert(array_key_exists("collation", $config) && !empty($config["collation"]), "pdox配置collation为空!");
        // assert(array_key_exists("cachedir", $config) && !empty($config["cachedir"]), "pdox配置cachedir为空!");

        $pdox = new Pdox(Config::get('pdox'));
        return $pdox;
    }

    /**
     * 设置统一返回格式
     * @param string $data
     * @param string $error
     * @param int $code
     * @return array
     */
    public static function resultFormat($data = '', $error = 0, $code = 200)
    {
        return ['code' => $code, 'data' => $data, 'error' => $error];
    }

    /**
     * 设置下载excel文件
     * @param $response
     * @param $fileName
     * @param $filePath
     */
    public static function setDownloadHeader($response, $fileName, $filePath)
    {
        //Logger::write('downloading '.$fileName.' from '.$filePath);
        $response->header('Content-Type', 'application/vnd.ms-excel');
        $response->header('Content-Disposition', 'attachment; filename=' . $fileName);
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
        $response->sendfile($filePath);
    }

    /**
     * HTTP POST异步客户端
     * @param $swResponse
     * @param $host
     * @param $port
     * @param $url
     * @param $header
     * @param $data
     * @param $param
     * @param callable $callback
     */
    public static function AsyncHttpPostCline($swResponse, string $host, int $port, string $url, array $header, array $data, array $param, callable $callback)
    {
        //异步HTTP客户端开始
        \Swoole\Async::dnsLookup($host, function ($domainName, $ip) use ($swResponse, $port, $header, $url, $data, $param, $callback) {

            $cli = new \Swoole\Http\Client($ip, $port);
            $cli->setMethod('POST');
            $host = ['Host' => $domainName];
            $header = array_merge($header, $host);
            $cli->setHeaders($header);

            $cli->post($url, $data, function ($cli) use ($swResponse, $callback, $param) {
                $callback($swResponse, $param, $cli);
            });

        });
    }

    public static function getRelUid($uid)
    {
        if (strpos($uid, 'u_') !== false) {
            return ltrim($uid, 'u_');
        }
        if(strpos($uid, 'd_') !==false){
            return ltrim($uid, 'd_');
        }
        return $uid;
    }

    public static function resulePaser($action='open', $data=[], $errcode=0, $errmsg='')
    {
        return  [
            'action' => $action,
            'data' => $data,
            'errcode' => $errcode,
            'errmsg'=>$errmsg
            ];
    }

    public static function json_encode_unicode($arr){
        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    }

    public static function setTableData($swoole_table, $userInfo, int $fd, string $path)
    {

        $uid = $userInfo['auth_type'] == 1 ? 'u_' . $userInfo['id'] : 'd_' . $userInfo['id'];
        $username = $userInfo['auth_type'] == 1 ? $userInfo['username'] : $userInfo['relname'];
        $info = ['uid' => $uid, 'fd' => $fd, 'username' => $username, 'auth_type' => $userInfo['auth_type'], 'path' => $path];
        $swoole_table->set($uid, $info);
        $swoole_table->set($fd, $info);
        return $userInfo;
    }

    public static function setUserData($userInfo, int $fd, string $path)
    {
        $auth_type = (int)$userInfo['auth_type'];
        if($auth_type == 1){
            $uid='u_' . $userInfo['id'];
            $username = $userInfo['username'];
            $openid = $userInfo['openid'];
        }elseif ($auth_type == 2){
            $uid=  'd_' . $userInfo['id'];
            $username = $userInfo['relname'];
            $openid = $userInfo['openid'];
        }else{
            return false;
        }
        //$uid = (int)$userInfo['auth_type'] == 1 ? 'u_' . $userInfo['id'] : 'd_' . $userInfo['id'];
        //$username = $userInfo['auth_type'] == 1 ? $userInfo['username'] : $userInfo['relname'];
        $info = ['uid' => $uid, 'fd' => $fd, 'openid'=>$openid, 'username' => $username, 'auth_type' => $userInfo['auth_type'], 'path' => $path];
        $prefix = Config::get('chat_refix');
        $cacheTime = Config::get('cache_time');
        $redis = self::CreateRedis(true);
        // 已存在
        $getUidRes = $redis->get($prefix.$uid);
        $getFdRes = $redis->get($prefix.$fd);
        if (!empty($getUidRes) && !self::checkFd($getUidRes, $fd)) {
            // 频道发生变动
            self::delUserData($uid);
            self::delUserData($fd);
            $getUidRes = '';
            $getFdRes = '';
        }

        if (!empty($getUidRes) && !empty($getFdRes)) {
            Logger::write('[INFO][setUserData]缓存中存在'.($prefix.$uid).'和' . ($prefix.$fd), 'user_auth_middleware_');
            return $userInfo;
        }

        // 不存在
        if (empty($getUidRes)) {
            $setUidRes = $redis->set($prefix.$uid, json_encode($info), $cacheTime);
            if (!$setUidRes) {
                Logger::write('[ERROR][setUserData]设置用户缓存'.($prefix.$uid) . '失败，返回' . json_encode($setUidRes, 300),'user_auth_middleware_');
                return false;
            }
        }
        if (empty($getFdRes)) {
            $setFdRes = $redis->set($prefix.$fd, json_encode($info), $cacheTime);
            if (!$setFdRes) {
                Logger::write('[ERROR][setUserData]设置用户缓存'.($prefix.$fd) . '失败，返回' . json_encode($setFdRes, 300),'user_auth_middleware_');
                return false;
            }
        }
        return $userInfo;
    }

    public static function getUserData($key)
    {
        $prefix = Config::get('chat_refix');
        $redis = self::CreateRedis(true);
        $userInfo = $redis->get($prefix.$key);
        Logger::write("[INFO][getUserData]查询缓存中" . $key . "的值，返回：". $userInfo, 'socket');
        return json_decode($userInfo);
    }

    public static function delUserData($key)
    {
        $prefix = Config::get('chat_refix');
        $redis = self::CreateRedis(true);
        return $redis->del($prefix.$key);
    }
    /**
     * 检查缓存的json中的fd参数和当前fd参数是否一致。
     * 如果不一致会出现收不到信息的情况
     */
    public static function checkFd($userInfoJstr, $fd)
    {
        $userInfo = json_decode($userInfoJstr, true);
        if (isset($userInfo['fd']) && $userInfo['fd'] == $fd) {
            return true;
        }
        Logger::write('[WARN][checkFd]用户：' . $userInfo['uid'] . '的fd参数发生改变，原：' . $userInfo['fd'] . '现：'. $fd, 'user_auth_middleware_');
        return false;
    }
}
