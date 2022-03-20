<?php

/**
 * Composer Autoload file
 * Runafter composer autoloader inited, do some prepare operation
 */



/**
 * patch for vendor/autoload.php
 */
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
                    $alo->addPsr4('Atto\\Box\\App\\'.$app.'\\', [
                        $app_dir, 
                        $app_dir.DS.'library',
                        $app_dir.DS.'modules'
                    ]);
                    $alo->addPsr4('Atto\\Box\\App\\'.ucfirst(strtolower($app)).'\\', [
                        $app_dir, 
                        $app_dir.DS.'library',
                        $app_dir.DS.'modules'
                    ]);
                    
                    //error class
                    $alo->addPsr4('Atto\\Box\\error\\'.$app.'\\', [
                        $app_dir.DS.'error'
                    ]);
                    $alo->addPsr4('Atto\\Box\\error\\'.ucfirst(strtolower($app)).'\\', [
                        $app_dir.DS.'error'
                    ]);
            
                }
            }
            $alo->addPsr4('Atto\\Box\\App\\', $psr_app);
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
                    $alo->addPsr4('Atto\\Box\\error\\'.$md.'\\', $md_dir.DS."error");
            
                }
            }
            closedir($dh);
            $alo->addPsr4('Atto\\Box\\route\\', $ds);
        }

        /**
         * patch error classes
         */
        $alo->addPsr4('Atto\\Box\\error\\', [
            BOX_PATH.DS."error", 
            ROOT_PATH.DS."error"
        ]);

    }
}



/**
 * global methods
 */

/**
 * autoRequireFiles()
 * require file from some dir
 * 
 * @param String $dir       files folder
 * @param Array $except     files that no need to require
 * @return void
 */
function autoRequireFiles($dir = "", $except = [])
{
    if (is_dir($dir)) {
        $dh = @opendir($dir);
        while (false !== ($file = readdir($dh))) {
            if ($file == "." || $file == "..") continue;
            $fcp = $dir . DS . $file;
            if (is_dir($fcp)) continue;
            if (false !== strpos($file, EXT)) {
                $fn = str_replace(EXT, "", $file);
                if (!in_array($fn, $except)) {
                    require_once($fcp);
                }
            }
        }
        closedir($dh);
    }
}

/**
 * cls()
 * get class by path str like "foo/Bar"  == \Atto\Box\foo\Bar
 * 
 * @param String $path      full class name
 * @param String $pathes...
 * @return Class            not found return null
 */
function cls($path = "")
{
    $ps = func_get_args();
    if (empty($ps)) return null;
    $cl = null;
    for ($i=0; $i<count($ps); $i++) {
        $cls = NS . str_replace("/","\\", $ps[$i]);
        if (class_exists($cls)) {
            $cl = $cls;
            break;
        }
    }
    //$cls = NS . str_replace("/","\\", $path);
    //return class_exists($cls) ? $cls : null;
    return $cl;
}
function clspre($path = "")
{
    $path = trim($path, "/");
    return NS . str_replace("/","\\", $path) . "\\";
}



/**
 * global util functions autoload
 * func dir = BOX_PATH/util/func
 */
autoRequireFiles(BOX_PATH . DS . "util");



/**
 * require core class
 * file = BOX_PATH/autoload/Box.php
 */
require_once(BOX_PATH . DS . "autoload" . DS . "Box" . EXT);

/**
 * configration
 */
Box::defaultConf();



/**
 * global error handler
 */
\Atto\Box\Error::setHandler();



/**
 * autoload files
 * dir = BOX_PATH/autoload
 */
autoRequireFiles(BOX_PATH . DS . "autoload", ["autoload", "Box"]);

