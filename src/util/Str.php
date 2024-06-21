<?php
/*
 *  CPHP框架  工具函数
 *  str_xxxx string工具函数
 */

//each，正则匹配，并循环
function str_each($str = "", $reg = "", $closure = null)
{
    if (!is_notempty_str($str)) return false;
    preg_match_all($reg, $str, $matches);
    $ms = $matches[0];
    if (!empty($ms)) {
        $rst = [];
        foreach ($ms as $k => $v) {
            $rstk = $closure($v, $k);
            if ($rstk === false) {
                break;
            } else if ($rstk === true) {
                continue;
            }
        }
        return $rst;
    }
    return false;
}

//split，返回 arr 类型数据，delimiter为字符时调用explode，为int时调用str_split
function split($str = "", $delimiter = null)
{
    if (!is_notempty_str($str)) return [];
    if (is_notempty_str($delimiter)) {
        $arr = explode($delimiter, $str);
    } else {
        if (is_null($delimiter)) {
            $arr = str_split($str);   //分割成单字符数组
        } elseif (is_int($delimiter)) {
            $arr = str_split($str, (int)$delimiter);
        } else {
            $arr = [ $str ];
        }
    }
    return $arr;
}

//replace，search == 正则 或 字符串
function replace($search, $replace, $str)
{
    if (substr($search, 0, 1) == "/") {
        return preg_replace($search, $replace, $str);
    } else {
        return str_replace($search, $replace, $str);
    }
}

//replace all，$kv = [ [$search, $replace], [], ... ]
function replace_all($kv = [], $str)
{
    for ($i=0;$i<count($kv);$i++) {
        $ki = $kv[$i];
        $str = replace($ki[0], $ki[1], $str);
    }
    return $str;
}

//tocamelcase
function strtocamel($str, $ucfirst=false)
{
    if (!is_notempty_str($str)) return $str;
    $str = preg_replace("/\_|\/|\,|\\|\s*/","-",$str);
    $str = strtolower($str);
    if (strpos($str,"-")===false) {
        return $ucfirst ? ucfirst($str) : $str;
    } else {
        $arr = explode("-",$str);
        $fs = strtolower(array_shift($arr));
        if ($ucfirst) $fs = ucfirst($fs);
        $arr = array_map(function($item){
            return ucfirst(strtolower($item));
        }, $arr);
        return $fs.implode("",$arr);
    }
}

//camelcase to snakecase:  fooBar --> foo-bar
function strtosnake($str, $glup="_")
{
    $snakeCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1'.$glup.'$2', $str));
    return $snakeCase;
}

//has
function str_has($str, $var)
{
    if (!is_notempty_str($str) || !is_notempty_str($var)) return false;
    return false !== strpos($str, $var);
}

//beginWith，是否以 var 开头
function str_begin($str, $var)
{
    if (!is_notempty_str($str) || !is_notempty_str($var)) return false;
    $len = strlen($var);
    return substr($str, 0, $len) == $var;
}

//endWith，是否以 var 结尾
function str_end($str, $var)
{
    if (!is_notempty_str($str) || !is_notempty_str($var)) return false;
    $len = strlen($var);
    return substr($str, strlen($str) - $len) == $var;
}

//替换%{XXX}%
function str_tpl($str = "", $val = [], $reg = "/\%\{[^\}\%]+\}\%/", $sign = ["%{", "}%"])
{
    str_each($str, $reg, function($v, $k) use (&$str, $val, $sign) {
        $s = str_replace($sign[0],"",$v);
        $s = str_replace($sign[1],"",$s);
        if (is_numeric($s)) $s = (int)$s - 1;  //如果是%{1}%形式，从1开始计数
        $tval = arr_item($val, $s);
        if(!is_null($tval)){
            $str = replace($v, $tval, $str);
            //$this->set($this->replace($v, $tval)->val());
        }else{
            $str = replace($v, "null", $str);
            //$this->set($this->replace($v, "null")->val());
        }
    });
    return $str;
}
//提取出 %{foo}%...%{bar}% 中的 foo,bar
function str_tplkey($str = "", $reg = "/\%\{[^\}\%]+\}\%/", $sign = ["%{", "}%"])
{
    $keys = [];
    str_each($str, $reg, function($v, $k) use (&$keys, $sign) {
        $s = str_replace($sign[0],"",$v);
        $s = str_replace($sign[1],"",$s);
        $keys[] = $s;
    });
    return $keys;
}

//随机字符串
function str_nonce($length = 16, $symbol = true)
{
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $symbols = "_-+@#$%^&*";
    if ($symbol) {
        $chars .= $symbols;
    }
    $str = "";
    for ($i=0; $i<$length; $i++) {
        $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
}