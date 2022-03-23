<?php

/*
 *  CPHP框架  工具函数
 *  path_xxxx 路径工具函数
 */

//将输入的预定义路径名称，转换为路径，路径名称已定义为常量
function path_cnst($cnst = "")
{
    if (empty($cnst)) {
        return ROOT_PATH;
    } else {
        if (defined(strtoupper($cnst)."_PATH")) {
            return constant(strtoupper($cnst)."_PATH");
        } else {
            return null;
        }
    }
}

//根据输入，返回物理路径（文件可能不存在）
function path_mk($path = "")
{
    if (empty($path)) return path_fix(ROOT_PATH);
    $path = trim(str_replace("/", DS, $path), DS);
    if (!is_null(path_relative($path))) return path_fix($path);
    $parr = explode(DS, $path);
    $p = [];
    $root = array_shift($parr);
    $rootpath = path_cnst($root);
    if (!is_null($rootpath)) {
        $p[] = $rootpath;
    } else {
        $p[] = ROOT_PATH;
        array_unshift($parr, $root);
    }
    $p = array_merge($p, $parr);
    return path_fix(implode(DS, $p));
}

//将输入的绝对路径path，转换为相对于 root 的相对路径
function path_relative($path = "", $root = ROOT_PATH)
{
    if (!is_notempty_str($path)) return null;
    $path = path_fix($path);
    $root = path_fix($root);
    if (false !== strpos($path, $root)) {
        return str_replace($root.DS, "", $path);
    }
    return null;
}

//根据输入，查找真实存在的文件，返回文件路径，未找到则返回null
function path_find(
    $path = "",     //要查找的文件或文件夹
    $options = []   //可选的参数
) {
    if (file_exists($path) || is_dir($path)) return $path;
    $path = trim(str_replace("/", DS, $path), DS);
    $options = arr_extend([
        "inDir" => ASSET_DIRS,
        "subDir" => "",
        "checkDir" => false
    ], $options);
    $local = [];

    $subDir = $options["subDir"];
    if (empty($subDir)) $subDir = [];
    $subDir = is_indexed($subDir) ? $subDir : (is_notempty_str($subDir) ? explode(",", $subDir) : []);
    $checkDir = $options["checkDir"];
    $inDir = $options["inDir"];
    if (empty($inDir)) $inDir = [];
    $inDir = is_indexed($inDir) ? $inDir : (is_notempty_str($inDir) ? explode(",", $inDir) : []);
    $appDir = [];
    if (!empty($inDir)) {
        $parr = explode(DS, $path);
        if (strtolower($parr[0]) == "app" || !is_null(cls("App/".ucfirst(strtolower($parr[0]))))) {
            if (strtolower($parr[0]) == "app") array_shift($parr);
            if (is_dir(APP_PATH.DS.$parr[0])) {
                $app = array_shift($parr);
                foreach ($inDir as $i => $dir) {
                    $appDir[] = "app/$app/$dir";
                }
                $npath = implode(DS, $parr);
                $gl = _path_findarr($npath, [
                    "inDir" => $appDir,
                    "subDir" => $subDir,
                    "checkDir" => $checkDir
                ], $local);
            }
        /*} else if (!is_null(path_cnst($parr[0]))) {
            $cnst = array_shift($parr);
            foreach ($inDir as $i => $dir) {
                $cnstDir[] = "$cnst/$dir";
            }
            $npath = implode(DS, $parr);
            $gl = _path_findarr($npath, [
                "inDir" => $cnstDir,
                "subDir" => $subDir,
                "checkDir" => $checkDir
            ], $local);*/
        } else {
            if (!is_null(path_cnst($parr[0]))) {
                $cnst = array_shift($parr);
            } else {
                $cnst = "root";
            }
            foreach ($inDir as $i => $dir) {
                $cnstDir[] = "$cnst/$dir";
            }
            $npath = implode(DS, $parr);
            $gl = _path_findarr($npath, [
                "inDir" => $cnstDir,
                "subDir" => $subDir,
                "checkDir" => $checkDir
            ], $local);
        }
    }
    $gl = _path_findarr($path, [
        "inDir" => $inDir,
        "subDir" => $subDir,
        "checkDir" => $checkDir
    ], $local);
    
    //var_dump($local); exit;
    break_dump("break_pathfind", $local);
    
    foreach ($local as $i => $v) {
        if (file_exists($v)) return $v;
        if ($checkDir && is_dir($v)) return $v;
    }
    return null;
}

//建立查找路径数组
function _path_findarr(
    $path = "",
    $options = [],  //已经由 find 方法处理过，可直接使用，不做排错
    &$olocal = []    //将建立的路径数组 merge 到此数组中
) {
    $inDir = $options["inDir"];
    $subDir = $options["subDir"];
    $checkDir = $options["checkDir"];
    $inApp = isset($inDir[0]) && str_has($inDir[0],"app/");
    $local = [];
    $info = pathinfo(path_mk($path));
    if (!$inApp) {
        $local[] = path_mk($path);
        if ($checkDir) $local[] = $info["dirname"].DS.$info["filename"];
    }
    foreach ($subDir as $k => $sdi) {
        $sdi = str_replace("/",  DS, $sdi);
        $local[] = $info["dirname"].DS.$sdi.DS.$info["basename"];
        if ($checkDir) $local[] = $info["dirname"].DS.$sdi.DS.$info["filename"];
    }
    foreach ($inDir as $i => $idi) {
        $idi = str_replace("/", DS, $idi);
        $pi = path_mk($idi.DS.$path);
        $local[] = $pi;
        $info = pathinfo($pi);
        if ($checkDir) $local[] = $info["dirname"].DS.$info["filename"];
        foreach ($subDir as $k => $sdi) {
            $sdi = str_replace("/",  DS, $sdi);
            $local[] = $info["dirname"].DS.$sdi.DS.$info["basename"];
            if ($checkDir) $local[] = $info["dirname"].DS.$sdi.DS.$info["filename"];
        }
    }
    //$local = array_unique($local);
    $olocal = array_merge($olocal, $local);
    $olocal = array_unique($olocal);
}

//在给定的多个path中，挑选真实存在的文件，$all = true 则返回所有存在的文件路径，否则返回第一个存在的路径
//未找到任何存在的文件则返回null
function path_exists(
    $pathes = [], 
    $options = []
) {
    if (!is_notempty_arr($pathes)) return null;
    $options = arr_extend([
        "inDir" => ASSET_DIRS,
        "subDir" => "",
        "checkDir" => false,
        "all" => false
    ], $options);
    $all = $options["all"];
    unset($options["all"]);
    $exists = [];
    foreach ($pathes as $i => $v) {
        $rv = path_find($v, $options);
        if (!is_null($rv)) $exists[] = $rv;
    }
    if (empty($exists)) return null;
    return $all ? $exists : $exists[0];
}

//遍历路径path，callback($file)，$recursive = true时，递归遍历所有子目录
function path_traverse($path = "", $callback = null, $recursive = false)
{
    if (!is_dir($path) || !is_callable($callback)) return false;
    $dh = @opendir($path);
    $rst = [];
    while (false !== ($file = readdir($dh))) {
        if ($file == "." || $file == "..") continue;
        $_rst = $callback($path, $file);
        $fp = $path.DS.$file;
        if ($recursive && is_dir($fp)) {
            $_rst = path_traverse($fp, $callback, true);
        }
        if ($_rst==="_continue_") continue;
        if ($_rst==="_break_") break;
        $rst[] = [$file, $_rst];
    }
    closedir($dh);
    return $rst;
}




//对 path 进行处理，返回正确的路径字符串
function path_fix($path = "")
{
    $path = path_up($path, DS);

    return $path;
}

//计算path数组中的..标记，返回计算后的path字符串，foo/bar/jaz/../../tom => foo/tom
function path_up($path = "", $dv = DS)
{
    if (empty($path) || !is_string($path)) return "";
    $path = str_replace("/", $dv, $path);
    $path = str_replace(DS, $dv, $path);
    $path = explode($dv, $path);
    if ($path[0] == "..") return "";
    for ($i=0; $i<count($path); $i++) {
        if ($path[$i] == "") $path[$i] = "__empty__";
        if ($path[$i] == "..") {
            for ($j=$i-1; $j>=-1; $j--) {
                if ($j < 0) {   //越界
                    return "";
                }
                if ($path[$j] != ".." && !is_null($path[$j])) {
                    $path[$j] = null;
                    break;
                }
            }
        }
    }
    $path = array_merge(array_diff($path, [null,".."]), []);
    return str_replace("__empty__", "", implode($dv, $path));
}