<?php

/**
 * Attobox Framework / log
 * log file path: [webroot]/assets/log/...
 */

namespace Atto\Box;

class Logger 
{
    /**
     * log file ext
     */
    public static $ext = ".txt";
    
    /**
     * log file path prefix
     */
    public static $prefix = "log/";     // path_find("log/.../...") ==> [webroot]/assets/log/.../...

    /**
     * 查询 WEB_LOG 开关状态
     */

    /**
     * get log file path
     * @param String $logfile       log file name
     * @return String log file path  or  null
     */
    public static function file($logfile = "")
    {
        if (!is_notempty_str($logfile)) return null;
        $fp = self::$prefix.$logfile.self::$ext;
        return path_find($fp);
    }

    /**
     * prepare log content
     * @param Mixed $log        log content
     * @return String processed log content
     */
    public static function prepare($log = null)
    {
        $log = str($log);
        return "[".date("Y-m-d H:i:s", time())."] >>> ".$log."\r\n";
    }

    /**
     * write to log file
     * @param String $logfile       log file name
     * @param String $log           log content
     * @return Bool
     */
    public static function log($logfile = "", $log = null)
    {
        if (WEB_LOG !== true) return false;

        $log = self::prepare($log);
        $f = self::file($logfile);
        if (empty($f)) return false;
        $h = fopen($f, "a");    // append mode 追加模式
        try {
            fwrite($h, $log);
            fclose($h);
            return true;
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * clear log content
     * @param String $logfile       log file name
     * @return Bool
     */
    public static function clear($logfile = "")
    {
        if (WEB_LOG !== true) return false;

        $f = self::file($logfile);
        if (empty($f)) return false;
        return file_put_contents($f, "") !== false;
    }

    /**
     * __callStatic
     * Logger::foobar('log content')  -->  Logger::log('foobar', 'log content')
     * Logger::clearFoobar()  -->  Logger::clear('foobar')
     */
    public static function __callStatic($key, $args)
    {
        if (WEB_LOG !== true) return false;
        $self = static::class;

        if (!is_notempty_arr($args)) $args = [];
        if (substr($key, 0, 5)=="clear") {
            $logfile = strtolower(substr($key, 6));
            array_unshift($args, $logfile);
            return call_user_func_array([$self, "clear"], $args);
        } else {
            array_unshift($args, $key);
            return call_user_func_array([$self, "log"], $args);
        }

    }

}