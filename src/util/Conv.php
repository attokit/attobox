<?php
/*
 *  CPHP框架  工具函数
 *  类型转换函数
 */

function arr($var = null)
{
    if (empty($var)) {
        return [];
    } elseif (is_array($var)) {
        return $var;
    } elseif (is_string($var)) {
        if (is_json($var)) {
            return j2a($var);
        } elseif (is_query($var)) {
            return u2a($var);
        } elseif (is_xml($var)){
            return x2a($var);
        } elseif (false !== is_explodable($var)) {
            $split = is_explodable($var);
            return explode($split, $var);
        //} elseif (is_numeric($var)) {
        //    return self::mk("[\"".$var."\"]");
        } else {
            return [ $var ];
        }
    } elseif (is_int($var) || is_float($var)) {
        //return self::mk("[\"".$var."\"]");
        return [ $var ];
    } elseif (is_object($var)) {
        $rst = [];
        foreach ($var as $k => $v) {
            if (property_exists($var, $k)) {
                $rst[$k] = $v;
            }
        }
        return $rst;
    } else {
        return [ $var ];
    }
}

function str($var = null)
{
    if (empty($var)) {
        if (is_null($var)) return "null";
        if (is_bool($var)) return $var ? "true" : "false";
        return (string)$var;
    } elseif (is_bool($var)) {
        return $var ? "true" : "false";
    } elseif (is_array($var)) {
        return a2j($var);
    } elseif (is_string($var)) {
        if (substr(strtolower($var), 0, 5) == "nonce") {    //生成8位随机字符串
            return str_nonce(8);
        }
        return $var;
    } elseif (is_object($var)) {
        return a2j(arr($var));
    } else {
        return (string)$var;
    }
}

function a2j($var = [])
{
    if (is_notempty_arr($var)) {
        return json_encode($var, JSON_UNESCAPED_UNICODE);
    }
    return "{}";
}

function j2a($var = null)
{
    if (!is_json($var)) return [];
    return json_decode($var, true);
}

//arr to querystr
function a2u($var = [])
{
    if (!is_notempty_arr($var)) {
        return "";
    } else {
        $vars = [];
        foreach ($var as $k => $v) {
            $vars[] = $k."=".urlencode(str($v));
        }
        return empty($vars) ? "" : implode("&", $vars);
    }
}

//querystr to Arr
function u2a($var = null)
{
    if (!is_notempty_str($var) || !is_query($var)) return [];
    $rst = [];
    if (false === strpos($var, "&")){
        $sarr = explode("=", $var);
        $sarr[1] = urldecode($sarr[1]);
        if (is_ntf($sarr[1])) {
            eval("\$v = ".$sarr[1].";");
            $rst[$sarr[0]] = $v;
        //} elseif (is_ArrayStr($sarr[1])) {
        //    $rst[$sarr[0]] = Arr::mk($sarr[1]);
        } else {
            $rst[$sarr[0]] = $sarr[1];
        }
    } else {
        $sarr = explode("&", $var);
        for ($i=0; $i<count($sarr); $i++) {
            $rst = array_merge($rst, u2a($sarr[$i]));
        }
    }
    return $rst;
}

//arr to xml
function a2x($var = [], $dom = null, $item = null)
{
    if (!is_associate($var, true)) return "";
    if (is_null($dom)) {
        $dom = new DOMDocument("1.0");
    }
    if (is_null($item)) {
        $item = $dom->createElement("root"); 
        $dom->appendChild($item);
    }
    foreach ($var as $key => $val) {
        $itemx = $dom->createElement(is_string($key) ? $key : "item");
        $item->appendChild($itemx);
        if (!is_array($val)) {
            $text = $dom->createTextNode($val);
            $itemx->appendChild($text);
        } else {
            a2x($val, $dom, $itemx);
        }
    }
    return $dom->saveXML();
}

//xml to arr
function x2a($var = null)
{
    if (!is_xml($var)) return [];
    $ta = [];
    $xo = simplexml_load_string($var);
    $ma = (array)$xo;
    foreach ($ma as $key => $val) {
        if (is_string($val)) {
            $ta[$key] = $val;
        } elseif (is_object($val) && !empty($val)) {
            $ta[$key] = x2a($val->asXML());
        } elseif (is_array($val) && !empty($val)) {
            foreach ($val as $zkey => $zval) {
                $zval = x2a($zval->asXML());
                if (is_numeric($zkey)) {
                    $ta[$key][] = $zval;
                }
                if (is_string($zkey)) {
                    $ta[$key][$zkey] = $zval;
                }
            }
        } else {
            if (empty($val)) {
                $ta[$key] = "";
            }
        }
    }
    return $ta;
}

//arr to html property   like:  `[pre-]data="value" [pre-]data2="value" ...`
function a2p($var = [], $pre = "")
{
    if (!is_associate($var, true) || empty($var)) return "";
    $rtn = [];
    foreach ($var as $k => $v) {
        $rtn[] = $pre.$k.'="'.str($v).'"'; 
    }
    return implode(" ", $rtn);
}

//  1024 * 1024  =>  1 Mb
function sizeToStr($size = 0)
{
    if ($size < 1024) {
        return $size . " bety";
    } elseif ($size < 1024 * 1024) {
        return round($size/1024, 2) . " Kb";
    } elseif ($size < 1024 * 1024 * 1024) {
        return round($size/(1024 * 1024), 2) . " Mb";
    } elseif ($size < 1024 * 1024 * 1024 * 1024) {
        return round($size/(1024 * 1024 * 1024), 2) . " Gb";
    } else {
        return round($size/(1024 * 1024 * 1024 * 1024), 2) . " Tb";
    }
}
