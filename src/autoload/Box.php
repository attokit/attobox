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
    public static $uac = null;
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
    public static function conf($conf = null)
    {
        if (is_null($conf)) {
            $f = BOX_PATH.DS."config".EXT;
            if (file_exists($f));
            $conf = require($f);
            //load modules configs
            $mds = MODULE_PATH;
            $mdh = opendir($mds);
            while(($md = readdir($mdh)) !== false) {
                if ($md=="." || $md=="..") continue;
                $mdp = $mds.DS.$md;
                if (!is_dir($mdp)) continue;
                $cf = $mdp.DS."config".EXT;
                if (file_exists($cf)) {
                    $_conf = require($cf);
                    $conf = arr_extend($conf, $_conf);
                }
            }
            closedir($mdh);
        }
        if (is_notempty_arr($conf) && is_associate($conf)) {
            self::$_TEMP_CONF = arr_extend(self::$_TEMP_CONF, $conf);
        }

        //加载 app/.../library/config.php
        if ($conf=="app") {
            $appdir = self::$_TEMP_CONF["APP_PATH"];
            $appconf = [];
            if (is_dir($appdir)) {
                $adh = opendir($appdir);
                while(($ad = readdir($adh))!==false) {
                    if ($ad=="." || $ad=="..") continue;
                    $cnf = $appdir.DS.$ad.DS."library".DS."config".EXT;
                    if (!file_exists($cnf)) continue;
                    $cnfi = require($cnf);
                    if (!empty($cnfi)) {
                        foreach ($cnfi as $k => $v) {
                            $appconf["APP_".strtoupper($ad)."_".strtoupper($k)] = $v;
                        }
                    }
                }
                closedir($adh);
            }
            if (!empty($appconf)) {
                self::$_TEMP_CONF = arr_extend(self::$_TEMP_CONF, $appconf);
            }
        }
    }

    /**
     * path()
     * create path constants by customize config (based on WEB_KEY)
     * @return void
     */
    private static function _path()
    {
        if (!isset(self::$_TEMP_CONF["WEB_KEY"])) self::conf();
        $key = self::$_TEMP_CONF["WEB_KEY"];
        $root = PRE_PATH.($key=="" ? "" : DS.$key);
        $path = [
            "ROOT_PATH"     => $root,
            "APP_PATH"      => $root . DS . "app",
            "ROUTE_PATH"    => $root . DS . "route",
            "ASSET_PATH"    => $root . DS . "asset",
            "SRC_PATH"      => $root . DS . "assets",
            "ASSETS_PATH"   => $root . DS . "assets",
            "LIB_PATH"      => $root . DS . "library",
            "DB_PATH"       => $root . DS . "library/db",
            //"MODEL_PATH"    => $root . DS . "model",
            "RECORD_PATH"   => $root . DS . "record",
            "OPR_PATH"      => $root . DS . "operater",
            "PAGE_PATH"     => $root . DS . "page",
            "PLUGIN_PATH"   => $root . DS . "plugin",
        ];
        self::conf($path);
    }

    /**
     * define constant
     * define constant based on Box::$_CONF
     * @return void
     */
    private static function _define()
    {
        self::$_CONF = arr_extend(self::$_CONF, self::$_TEMP_CONF);
        foreach (self::$_CONF as $k => $v) {
            if (!defined($k)) {
                define($k, $v);
            }
        }
    }

    /**
     * getConf()
     * get runtime config data
     * @return Array $_CONF
     */
    public static function getConf()
    {
        return self::$_CONF;
    }



    /**
     * patch composer autoload
     * must call after constant defined
     * @return void
     */
    private static function _patchAutoload()
    {
        $ns = trim(NS, "\\");
        $af = VENDOR_PATH . DS . "autoload.php";
        if (file_exists($af)) {
            $al = file_get_contents($af);
            $alc = "ComposerAutoloader" . explode("::getLoader", explode("return ComposerAutoloader", $al)[1])[0];
            $alo = $alc::getLoader();
            if (!empty($alo)) {

                /**
                 * patch app classes autoload
                 */
                if (is_dir(APP_PATH)) {
                    $apps_dh = opendir(APP_PATH);
                    $psr_app = [APP_PATH];
                    while (($app = readdir($apps_dh)) !== false) {
                        if ($app == "." || $app == "..") continue;
                        $app_dir = APP_PATH . DS . $app;
                        if (is_dir($app_dir)) {
                            
                            $psr_app[] = $app_dir;
                            $alo->addPsr4($ns.'\\App\\'.$app.'\\', [
                                $app_dir, 
                                $app_dir.DS.'library',
                                $app_dir.DS.'modules'
                            ]);
                            $alo->addPsr4($ns.'\\App\\'.ucfirst(strtolower($app)).'\\', [
                                $app_dir, 
                                $app_dir.DS.'library',
                                $app_dir.DS.'modules'
                            ]);
                            $alo->addPsr4($ns.'\\app\\'.$app.'\\', [
                                $app_dir, 
                                $app_dir.DS.'library',
                                $app_dir.DS.'modules'
                            ]);
                            $alo->addPsr4($ns.'\\app\\'.ucfirst(strtolower($app)).'\\', [
                                $app_dir, 
                                $app_dir.DS.'library',
                                $app_dir.DS.'modules'
                            ]);

                            //route class
                            /*$alo->addPsr4($ns.'\\route\\'.$app.'\\', [
                                $app_dir.DS.'route'
                            ]);
                            $alo->addPsr4($ns.'\\route\\'.ucfirst(strtolower($app)).'\\', [
                                $app_dir.DS.'route'
                            ]);*/
                            
                            //error class
                            $alo->addPsr4($ns.'\\error\\'.$app.'\\', [
                                $app_dir.DS.'error'
                            ]);
                            $alo->addPsr4($ns.'\\error\\'.ucfirst(strtolower($app)).'\\', [
                                $app_dir.DS.'error'
                            ]);
                    
                        }
                    }
                    $alo->addPsr4($ns.'\\App\\', $psr_app);
                    $alo->addPsr4($ns.'\\app\\', $psr_app);
                    closedir($apps_dh);
                }

                /**
                 * patch module classes autoload
                 */
                $mdp = BOX_PATH . DS . "modules";
                if (is_dir($mdp)) {
                    $ds = [BOX_PATH.DS."route", ROOT_PATH.DS."route"];
                    $dh = opendir($mdp);
                    while (($md = readdir($dh)) !== false) {
                        if ($md == "." || $md == "..") continue;
                        $md_dir = $mdp . DS . $md;
                        if (is_dir($md_dir)) {
                            
                            $ds[] = $md_dir.DS."route";
                            
                            //error class
                            //$alo->addPsr4($ns.'\\error\\'.$md.'\\', $md_dir.DS."error");
                            $alo->addPsr4($ns.'\\error\\', $md_dir.DS."error");
                    
                        }
                    }
                    closedir($dh);
                    $alo->addPsr4($ns.'\\route\\', $ds);
                }

                /**
                 * patch web route
                 */
                $alo->addPsr4($ns.'\\route\\', [
                    ROOT_PATH.DS."route"
                ]);

                /**
                 * patch web library
                 */
                $alo->addPsr4($ns.'\\', [
                    ROOT_PATH, ROOT_PATH.DS."library"
                ]);

                /**
                 * patch error classes
                 */
                $alo->addPsr4($ns.'\\error\\', [
                    BOX_PATH.DS."error", 
                    ROOT_PATH.DS."error"
                ]);

            }
        }
    }



    /**
     * Attobox Router response
     * @return exit
     */
    private static function _run()
    {
        //flow start
        self::$request = \Atto\Box\Request::current();
        self::$router = \Atto\Box\Router::current();
        //var_dump(self::$router->info());
        self::$request->app = self::$router->getAppFromRoute();
        //var_dump(self::$request->app);
        if (\Atto\Box\Uac::required()) {
            self::$uac = \Atto\Box\Uac::start();
            self::$request->uac = self::$uac;
            //var_dump(self::$uac);
        }
        self::$response = \Atto\Box\Response::current();
        //var_dump(self::$response->info());
        self::$response->create()->export();
        exit;
    }



    /**
     * start
     */
    public static function start($conf = []) 
    {
        //set default config
        self::conf();
        //set customize config
        self::conf($conf);
        //set path constant
        self::_path();
        //set app config
        self::conf("app");
        //define constant
        self::_define();
        //patch composer autoload
        self::_patchAutoload();

        //error handler
        \Atto\Box\Error::setHandler();

        //autoload files in BOX_PATH/autoload
        autoRequireFiles(BOX_PATH . DS . "autoload", ["autoload", "Box"]);

        //start route
        self::_run();
        
        exit;
    }

}