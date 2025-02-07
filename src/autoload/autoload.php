<?php

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
function cls_no_ns($obj)
{
    try {
        $cls = get_class($obj);
        $carr = explode("\\", $cls);
        return array_pop($carr);
    } catch(Exception $e) {
        return null;
    }

}

/**
 * 从某个类中筛选出符合指定条件的 methods
 * 返回 Reflection Method 对象数组
 *  [
 *      methodName => ReflectionMethod 实例
 *      ...
 *  ]
 */
function cls_methods_filter($cls, $condition, $filter=\ReflectionMethod::IS_PROTECTED)
{
    if (!class_exists($cls)) return [];
    $rcls = new \ReflectionClass($cls);
    $methods = $rcls->getMethods($filter);
    $ms = [];
    for ($i=0;$i<count($methods);$i++) {
        $mi = $methods[$i];
        //执行判断函数，返回 true 则符合条件
        if ($condition($mi)===true) {
            $name = strtolower($mi->name);
            $ms[$name] = $mi;
        }
    }
    return $ms;
}


/**
 * !!! 在 start.php 中执行，此处执行会与 cgyio 框架冲突 !!!
 * global util functions autoload
 * func dir = BOX_PATH/util/func
 */
//autoRequireFiles(BOX_PATH . DS . "util");


/**
 * !!! 在 start.php 中执行，此处执行会与 cgyio 框架冲突 !!!
 * require core class
 * file = BOX_PATH/autoload/Box.php
 */
//require_once(BOX_PATH . DS . "autoload" . DS . "Box" . EXT);
