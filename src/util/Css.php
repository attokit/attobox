<?php
/*
 *  CPHP框架  工具函数
 *  css工具函数，用于动态输出css文件内容
 */

// CP::class
function css_class()
{
    $cls = "\\CGY\\CPhp\\CP";
    return call_user_func_array([$cls, "class"], func_get_args());
}

// Path
function css_path()
{
    $args = func_get_args();
    if (empty($args)) return css_class("util/Path");
    return call_user_func_array([css_class("util/Path"), "make"], $args);
}

//css var
function css_var($var, $val = "")
{
    if (empty($val)) return "var(--$var)";
    return "--$var:$val";
}

//css set
function css_set($selector, $option = [])
{
    
}



/*
 *  color 函数
 */

//255 -> ff
function css_color_dechex($num = 0)
{
    $num = round($num);
    $s = dechex($num);
    if ($num<16) $s = "0".$s;
    return $s;
}

//#ffffff -> [255, 255, 255]
function css_color_rgb($colorHex = "#000000")
{
    $ch = ltrim($colorHex, "#");
    return [    //[r,g,b]
        hexdec(substr($ch, 0, 2)),
        hexdec(substr($ch, 2, 2)),
        hexdec(substr($ch, 4, 2))
    ];
}

//[255, 255, 255] -> #ffffff
function css_color_hex($rgb = [0,0,0])
{
    $s = [];
    for ($i=0; $i<3; $i++) {
        $s[$i] = css_color_dechex($rgb[$i]);
    }
    return "#".implode("",$s);
}

//色值计算，darker or lighter 加深 或 减淡
//$level > 0 加深；   $level < 0 减淡；   百分比
function css_color_shift($colorHex = "#000000", $level = 10)
{
    $rgb = css_color_rgb($colorHex);
    $lvl = $level / 100;
    $nrgb = [];
    for ($i=0; $i<3; $i++) {
        $ni = $rgb[$i];
        $nni = $ni + (255 * $lvl);
        if ($nni > 255) $nni = 255;
        if ($nni <0) $nni = 0;
        $nrgb[$i] =  $nni;
    }
    return css_color_hex($nrgb);
}

//计算背景色值的亮度，用于确定前景色 #000 or #fff
function css_color_brightness($hex = "#ffffff")
{
    $rgb = css_color_rgb($hex);
    $bright = 0.299*$rgb[0] + 0.587*$rgb[1] + 0.114*$rgb[2];
    return $bright;
}



/*
 *  缩写函数
 */

//[class*="p1"][class*="p2"]...
function css_with()
{
    $args = func_get_args();
    if (!empty($args)) {
        $s = [];
        for ($i=0; $i<count($args); $i++) {
            $s[] = "[class*=\"".$args[$i]."\"]";
        }
        return implode("", $s);
    }
    return "";
}