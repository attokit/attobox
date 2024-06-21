<?php
/*
 *  CPHP框架  工具函数
 *  is_xxxx判断函数
 */


/**
 * 判断类型
 * @param Mixed $any 要判断类型的数据
 * @param String $types is_***() 星号部分，多个用,隔开
 * @param String $logic 多个类型之间的逻辑关系，默认 '||'，可选 '&&'
 */
function iss($any, $types, $logic="||")
{
    $tps = explode(",", $types);
    $rst = array_map(function($tpi) use ($any) {
        $func = "is_".strtolower($tpi);
        if (!function_exists($func)) return false;
        return $func($any);
    }, $tps);
    $type = $logic=="&&";
    for ($i=0;$i<count($rst);$i++) {
        if ($logic=="&&") {
            $type = $type && $rst[$i];
            if ($type===false) break;
        } else {
            $type = $type || $rst[$i];
        }
    }
    return $type;
}


/*
 *  Array
 */

function is_indexed($var = null)
{
    if (!is_array($var)) return false;
    if (empty($var)) return true;
    /*foreach ($var as $key => $value) {
        if (!is_integer($key)) {
            return false;
        }
    }
    return true;*/
    return is_numeric(implode("", array_keys($var)));
}

function is_associate($var = null, $allStr = false)
{
    if (!is_array($var)) return false;
    if ($allStr) {
        foreach ($var as $key => $value) {
            if (!is_string($key)) {
                return false;
            }
        }
        return true;
    } else {
        foreach ($var as $key => $value) {
            if (is_string($key)) {
                return true;
            }
        }
        return false;
    }
}

//数组是否 一维数组
function is_onedimension($var = null)
{
    if (!is_array($var)) return false;
    foreach ($var as $k => $v) {
        if (is_array($v)) {
            return false;
        }
    }
    return true;
}

//is_array($var) && !empty($var)
function is_notempty_arr($var = null)
{
    return is_array($var) && !empty($var);
}



/*
 *  String
 */

//字符大小写判断
function is_lower($var = "")
{
    if (!is_notempty_str($var)) return false;
    return strtolower($var) === $var;
}
function is_upper($var = "")
{
    if (!is_notempty_str($var)) return false;
    return strtoupper($var) === $var;
}
function is_lower_upper($var = "")
{
    if (!is_notempty_str($var)) return false;
    return !is_lower($var) && !is_upper($var);
}

function is_query($var = null)
{
    if (!is_string($var) || $var == "") return false;
    if (false === strpos($var, "&")) {
        if (false === strpos($var, "=")) {
            return false;
        } else {
            $sarr = explode("=", $var);
            return count($sarr) == 2;
        }
    } else {
        $sarr = explode("&", $var);
        $rst = true;
        for ($i=0; $i<count($sarr); $i++) {
            //if ($sarr[$i] == "") continue;
            if (false === is_query($sarr[$i])){
                $rst = false;
                break;
            }
        }
        return $rst;
    }
}

//is null,true,false in string
function is_ntf($var = null)
{
    return is_string($var) && in_array(strtolower($var), ["null","true","false"]);
}

//可以explode
function is_explodable($var = null, $split = null)
{
    if (!is_string($var) || $var == "") return false;
    $splits = ["/","\\",DS,",","|",";",".","&"];
    if (is_null($split)) {
        $scount = 0;
        $sp = null;
        foreach ($splits as $k => $v) {
            if (substr_count($var, $v) > $scount) {
                $scount = substr_count($var, $v);
                $sp = $v;
                //return $v;
            }
        }
        if (!is_null($sp)) return $sp;
        return false;
    } else {
        return substr_count($var, $split) > 0;
    }
}

//is_string($var) && !empty($var)
function is_notempty_str($var = null)
{
    return is_string($var) && !empty($var);
}



/*
 *
 */



/*
 *  other
 */

function is_json($var = null)
{
    if (!is_string($var) || empty($var)) {
        return false;
    } else {
        $jd = json_decode($var);
        if (is_null($jd)) return false;
        return json_last_error() == JSON_ERROR_NONE;
    }
}

function is_xml($var = null)
{
    if (!is_string($var)) return false;
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($var, 'SimpleXMLElement', LIBXML_NOCDATA);
    return $xml !== false;
}

function is_remote($file = null)
{
    if (!is_notempty_str($file)) return false;
    return false !== strpos($file, "://");
}

//is_set 判断变量是否已定义，
//当 var==null 时 isset(var) == false，但是 is_set(var) == true
function is_set($var) 
{
    return isset($var) || is_null($var);
}

//判断 key 是否存在，部分情况下代替 isset($arr[$key])
function is_def($arr, $key)
{
    if (!is_array($arr) || empty($arr)) return false;
    return in_array($key, array_keys($arr));
}
