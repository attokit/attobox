<?php
/*
 *  CPHP框架  工具函数
 *  num_xxxx 数字工具函数
 */

//四舍五入
function num_round($num, $digit=2)
{
    $d = 10 ** $digit;
    return round($num*$d)/$d;
    //return number_format($num,2,".","");
}


//整数数字转为 <1 浮点数，max 为数字最大值，超过此值则返回 1  dig 为保留小数位数
function num_fl($n, $max=255, $dig=2) {
    if ($n>$max) return 1;
    if ($n<0) return 0;
    $d = 10 ** $dig;    //10 的 dig 次方
    return round($n*$d/$max)/$d;
}
//<1 浮点数转为 整数
function num_it($n, $max=255) {
    if ($n>1) return $max;
    if ($n<0) return 0;
    return round($n*$max);
}
//保留 dig 位小数
function num_rd($n, $dig=2) {
    $d = 10 ** $dig;
    return round($n*$d)/$d;
}

//g 转 Kg
function num_kg($num, $digit=2)
{
    $kg = $num>=1000 ? num_round($num/1000, $digit)."Kg" : $num."g";
    $kg = str_replace(".00","",$kg);
    return $kg;
}

//一直显示 kg 
function num_kg_always($num, $digit=2)
{
    $kg = num_round($num/1000, $digit)."Kg";
    return $kg;
}

//bety <--> KB <--> MB <--> GB
function num_file_size($fsz)
{
    if (!is_numeric($fsz)) return $fsz;
    if ($fsz<1000) {
        return $fsz." Bety";
    } else if ($fsz<1000*1000) {
        return (round(($fsz/1000)*100)/100)." KB";
    } else if ($fsz<1000*1000*1000) {
        return (round(($fsz/(1000*1000))*100)/100)." MB";
    } else {
        return (round(($fsz/(1000*1000*1000))*100)/100)." GB";
    }
}