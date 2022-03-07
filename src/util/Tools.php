<?php
/*
 *  CPHP框架  工具函数
 *  其他工具函数
 */

//身份证号解析
function parse_idcard($idcard = "")
{
    if (is_notempty_str($idcard) && strlen($idcard)==18) {
        $birth = substr($idcard, 6,4)."-".substr($idcard, 10,2)."-".substr($idcard, 12,2);
        $birth_y = substr($idcard, 6,4);
        $now_y = date("Y",time());
        $sex = substr($idcard, 16,1);
        $sex = (int)$sex;
        $sex = $sex%2;
        return [
            "birthday" => $birth,
            "sex" => $sex==0 ? 2 : 1,   // 1 男，2 女
            "age" => (int)$now_y - (int)$birth_y
        ];
    }
    return null;
}

//调试输出
function break_dump($getkey="", $varToDump=null)
{
    $key = isset($_GET[$getkey]) ? $_GET[$getkey] : null;
    if ($key=="yes") {
        if (is_indexed($varToDump)) {
            foreach ($varToDump as $i => $var) {
                var_dump($var);
            }
        } else {
            var_dump($varToDump);
        }
        //exit;
    }
}