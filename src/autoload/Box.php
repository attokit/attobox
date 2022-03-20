<?php

/**
 * Attobox Framework / Box core
 * define core class of Attobox
 * 
 * @access global
 */

class Box
{
    //runtime configration
    private static $_CONF = [];

    //temp config
    private static $_TEMP_CONF = [];

    /**
     * 核心流程
     */
    public static $request = null;
    public static $router = null;
    public static $response = null;

    private function __construct() { }



    /**
     * configration methods
     */

    /**
     * conf()
     * set temp config data
     * 
     * @param Array $conf       temp config data
     * @return void
     */
    public static function conf($conf = [])
    {
        if (is_notempty_arr($conf) && is_associate($conf)) {
            self::$_TEMP_CONF = arr_extend(self::$_TEMP_CONF, $conf);
        }
    }

    /**
     * defaultConf()
     * load default config file
     * 
     * @param String $conf      default config file path or filename
     * @return void
     */
    public static function defaultConf($conf = "default")
    {
        if (file_exists($conf)) {
            $file = $conf;
        } else {
            $dir = BOX_PATH.DS."config".DS;
            $file = $dir.$conf.EXT;
            if (!file_exists($file)) $file = $dir."default".EXT;
        }
        $conf = require($file);
        self::conf($conf);
    }

    /**
     * setConf()
     * create runtime config data from temp
     * and define all constant from array_keys of $_CONF
     * 
     * @return void
     */
    public static function setConf()
    {
        self::$_CONF = arr_extend(self::$_CONF, self::$_TEMP_CONF);
        //define constant
        foreach (self::$_CONF as $k => $v) {
            $ck = strtoupper($k);
            if (!defined($ck)) {
                define($ck, $v);
            }
        }
    }

    /**
     * getConf()
     * get runtime config data
     * 
     * @return Array $_CONF
     */
    public static function getConf()
    {
        return self::$_CONF;
    }



    /**
     * start
     */
    public static function start() 
    {
        //set config
        self::setConf();

        //flow start
        self::$request = \Atto\Box\Request::current();
        self::$router = \Atto\Box\Router::current();
        //var_dump(self::$router->info());
        self::$response = \Atto\Box\Response::current();
        //var_dump(self::$response->info());
        self::$response->create()->export();
    }

}