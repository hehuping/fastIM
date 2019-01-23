<?php
namespace Lib;

class Logger
{
    //错误等级描述数组
    static public $ErrorLevel = [
        E_ERROR => "Error",
        E_WARNING => "Warning",
        E_PARSE => "Parsing Error",
        E_NOTICE => "Notice",
        E_CORE_ERROR => "Core Error",
        E_CORE_WARNING => "Core Warning",
        E_COMPILE_ERROR => "Compile Error",
        E_COMPILE_WARNING => "Compile Warning",
        E_USER_ERROR => "User Error",
        E_USER_WARNING => "User Warning",
        E_USER_NOTICE => "User Notice",
        E_STRICT => "Runtime Notice",
        E_RECOVERABLE_ERROR=>'E_RECOVERABLE_ERROR',
        E_DEPRECATED=>'E_DEPRECATED',
        E_USER_DEPRECATED=>'E_USER_DEPRECATED'
    ];

    static private $config  =   array(
        'log_time_format'   =>  ' Y-m-d H:i:s ',
        'log_file_size'     =>  2097152,
        'log_path'          =>  '',
        'log_adapter'       =>  'swoole'
    );

    /**
     * 日志初始化
     * @access public
     * @param string $config 配置信息(array类型)
     * log_time_format ISO 8601 格式的日期,如 2004-02-12T15:19:21+00:00
     * log_file_size   日志文件大小
     * log_path        日志存放目录（必须指定）
     * log_adapter     日志写入方式（性能相关） file:使用error_log发送到文件；swoole:使用swoole_async_writefile发送
     * @return void
     */
    public static function init($config = array())
    {
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * 日志写入接口
     * @access public
     * @param string $message 日志信息
     * @param string $logFile  写入目标
     * @return void
     */
    public static function write($message, $logFile = '')
    {
        $now = date(self::$config['log_time_format']);
        if (empty($logFile)) {
            $logFile = self::$config['log_path'].date('Y_m_d').'.log';
        } else {
            $logFile = self::$config['log_path'].$logFile.date('Y_m_d').'.log';
        }
        if (!is_dir(self::$config['log_path'])) {
            mkdir(self::$config['log_path'], 0755, true);
        }
        //检测日志文件大小，超过配置大小则备份日志文件重新生成
        if (is_file($logFile) && floor(self::$config['log_file_size']) <= filesize($logFile)) {
            @rename($logFile, dirname($logFile).'/'.time().'-'.basename($logFile));
        }

        if (self::$config['log_adapter'] == 'swoole') {
            swoole_async_writefile($logFile, "[{$now}] "."\r\n{$message}\r\n", function () {
            }, FILE_APPEND);
        } else {
            error_log("[{$now}] "."\r\n{$message}\r\n", 3, $logFile);
        }
    }
}
