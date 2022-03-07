<?php

//写入
function session_set(
    $key = [],
    $val = ""
) {
    if (is_array($key)) {
        foreach ($key as $k => $v) {
            $_SESSION[$k] = $v;
        }
    }else{
        $_SESSION[$key] = $val;
    }
}

//读取
function session_get(
    $key, 
    $dft = null
) {
    return isset($_SESSION[$key]) ? $_SESSION[$key] : $dft;
}

//删除
function session_del(
    $key
) {
    if (isset($_SESSION[$key])) {
        $_SESSION[$key] = null;
        unset($_SESSION[$key]);
    }
}

//判断
function session_has(
    $key
) {
    return isset($_SESSION[$key]) && $_SESSION[$key]!=null;
}