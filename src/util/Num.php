<?php
/*
 *  CPHP框架  工具函数
 *  num_xxxx 数字工具函数
 */

//四舍五入
function num_round($num, $digit=2)
{
    return number_format($num,2,".","");
}

//g 转 Kg
function num_kg($num, $digit=2)
{
    $kg = $num>=1000 ? num_round($num/1000, $digit)."Kg" : $num."g";
    $kg = str_replace(".00","",$kg);
    return $kg;
}