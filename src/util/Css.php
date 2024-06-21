<?php
/**
 *  Attobox Framework Utils
 *  css工具函数，用于动态输出css文件内容
 */

/**
 * css  <--->  array
 */
function css2arr($css = "")
{
    if (!is_notempty_str($css)) return [];
    $css = replace_all([
        ["\r\n",        ""],
        ["/\s+/",       " "],
        ["/\s*:\s*/",   "\":\""],
        ["/\s*{\s*/",      "\":{\""],
        ["/;\s*/",      "\",\""],
        ["/\s*}\s*./",      "\"},\"_dot_"],
        ["/\s*}\s*#/",      "\"},\"_hash_"],
        ["/\s*}\s*/",      "\"},\""],
        [",\"\"}",      "}"]
    ], $css);
    $css = trim($css);
    $css = trim($css, "\"");
    $css = trim($css, ",");
    $css = trim($css, "\"");
    $css = "{\"".trim(rtrim($css,","))."}";
    var_dump($css);
    $css = j2a($css);
    var_dump($css);
    return $css;
}
function arr2css($arr = [])
{
    if (!is_notempty_arr($arr)) return "";
}




/*
 *  color 函数
 */

//255 -> ff
function color_dechex($num = 0)
{
    $num = round($num);
    $s = dechex($num);
    if ($num<16) $s = "0".$s;
    return $s;
}

//#ffffff -> [255, 255, 255]
function color_rgb($colorHex = "#000000")
{
    $ch = ltrim($colorHex, "#");
    return [    //[r,g,b]
        hexdec(substr($ch, 0, 2)),
        hexdec(substr($ch, 2, 2)),
        hexdec(substr($ch, 4, 2))
    ];
}

//[255, 255, 255] -> #ffffff
function color_hex($rgb = [0,0,0])
{
    $s = [];
    for ($i=0; $i<3; $i++) {
        $s[$i] = color_dechex($rgb[$i]);
    }
    return "#".implode("",$s);
}

//色值计算，darker or lighter 加深 或 减淡
//$level > 0 加深；   $level < 0 减淡；   百分比
function color_shift($colorHex = "#000000", $level = 10)
{
    $rgb = color_rgb($colorHex);
    $lvl = $level / 100;
    $nrgb = [];
    for ($i=0; $i<3; $i++) {
        $ni = $rgb[$i];
        $nni = $ni + round(255 * $lvl);
        if ($nni > 255) $nni = 255;
        if ($nni <0) $nni = 0;
        $nrgb[$i] =  $nni;
    }
    return color_hex($nrgb);
}

//计算背景色值的亮度，用于确定前景色 #000 or #fff
function color_brightness($hex = "#ffffff")
{
    $rgb = color_rgb($hex);
    $bright = 0.299*$rgb[0] + 0.587*$rgb[1] + 0.114*$rgb[2];
    return $bright;
}

/**
 * 浏览器支持的颜色名称
 * 147种
 * @param String $cn    color name
 * @return String 16进制色值  or  color names array  or  null
 */
function color_names($cn = null)
{
    $cns = [
        "AliceBlue" => "#F0F8FF",
        "AntiqueWhite" => "#FAEBD7",
        "Aqua" => "#00FFFF",
        "Aquamarine" => "#7FFFD4",
        "Azure" => "#F0FFFF",
        "Beige" => "#F5F5DC",
        "Bisque" => "#FFE4C4",
        "Black" => "#000000",
        "BlanchedAlmond" => "#FFEBCD",
        "Blue" => "#0000FF",
        "BlueViolet" => "#8A2BE2",
        "Brown" => "#A52A2A",
        "BurlyWood" => "#DEB887",
        "CadetBlue" => "#5F9EA0",
        "Chartreuse" => "#7FFF00",
        "Chocolate" => "#D2691E",
        "Coral" => "#FF7F50",
        "CornflowerBlue" => "#6495ED",
        "Cornsilk" => "#FFF8DC",
        "Crimson" => "#DC143C",
        "Cyan" => "#00FFFF",
        "DarkBlue" => "#00008B",
        "DarkCyan" => "#008B8B",
        "DarkGoldenRod" => "#B8860B",
        "DarkGray" => "#A9A9A9",
        "DarkGreen" => "#006400",
        "DarkKhaki" => "#BDB76B",
        "DarkMagenta" => "#8B008B",
        "DarkOliveGreen" => "#556B2F",
        "DarkOrange" => "#FF8C00",
        "DarkOrchid" => "#9932CC",
        "DarkRed" => "#8B0000",
        "DarkSalmon" => "#E9967A",
        "DarkSeaGreen" => "#8FBC8F",
        "DarkSlateBlue" => "#483D8B",
        "DarkSlateGray" => "#2F4F4F",
        "DarkTurquoise" => "#00CED1",
        "DarkViolet" => "#9400D3",
        "DeepPink" => "#FF1493",
        "DeepSkyBlue" => "#00BFFF",
        "DimGray" => "#696969",
        "DodgerBlue" => "#1E90FF",
        "FireBrick" => "#B22222",
        "FloralWhite" => "#FFFAF0",
        "ForestGreen" => "#228B22",
        "Fuchsia" => "#FF00FF",
        "Gainsboro" => "#DCDCDC",
        "GhostWhite" => "#F8F8FF",
        "Gold" => "#FFD700",
        "GoldenRod" => "#DAA520",
        "Gray" => "#808080",
        "Green" => "#008000",
        "GreenYellow" => "#ADFF2F",
        "HoneyDew" => "#F0FFF0",
        "HotPink" => "#FF69B4",
        "IndianRed " => "#CD5C5C",
        "Indigo  " => "#4B0082",
        "Ivory" => "#FFFFF0",
        "Khaki" => "#F0E68C",
        "Lavender" => "#E6E6FA",
        "LavenderBlush" => "#FFF0F5",
        "LawnGreen" => "#7CFC00",
        "LemonChiffon" => "#FFFACD",
        "LightBlue" => "#ADD8E6",
        "LightCoral" => "#F08080",
        "LightCyan" => "#E0FFFF",
        "LightGoldenRodYellow" => "#FAFAD2",
        "LightGray" => "#D3D3D3",
        "LightGreen" => "#90EE90",
        "LightPink" => "#FFB6C1",
        "LightSalmon" => "#FFA07A",
        "LightSeaGreen" => "#20B2AA",
        "LightSkyBlue" => "#87CEFA",
        "LightSlateGray" => "#778899",
        "LightSteelBlue" => "#B0C4DE",
        "LightYellow" => "#FFFFE0",
        "Lime" => "#00FF00",
        "LimeGreen" => "#32CD32",
        "Linen" => "#FAF0E6",
        "Magenta" => "#FF00FF",
        "Maroon" => "#800000",
        "MediumAquaMarine" => "#66CDAA",
        "MediumBlue" => "#0000CD",
        "MediumOrchid" => "#BA55D3",
        "MediumPurple" => "#9370DB",
        "MediumSeaGreen" => "#3CB371",
        "MediumSlateBlue" => "#7B68EE",
        "MediumSpringGreen" => "#00FA9A",
        "MediumTurquoise" => "#48D1CC",
        "MediumVioletRed" => "#C71585",
        "MidnightBlue" => "#191970",
        "MintCream" => "#F5FFFA",
        "MistyRose" => "#FFE4E1",
        "Moccasin" => "#FFE4B5",
        "NavajoWhite" => "#FFDEAD",
        "Navy" => "#000080",
        "OldLace" => "#FDF5E6",
        "Olive" => "#808000",
        "OliveDrab" => "#6B8E23",
        "Orange" => "#FFA500",
        "OrangeRed" => "#FF4500",
        "Orchid" => "#DA70D6",
        "PaleGoldenRod" => "#EEE8AA",
        "PaleGreen" => "#98FB98",
        "PaleTurquoise" => "#AFEEEE",
        "PaleVioletRed" => "#DB7093",
        "PapayaWhip" => "#FFEFD5",
        "PeachPuff" => "#FFDAB9",
        "Peru" => "#CD853F",
        "Pink" => "#FFC0CB",
        "Plum" => "#DDA0DD",
        "PowderBlue" => "#B0E0E6",
        "Purple" => "#800080",
        "Red" => "#FF0000",
        "RosyBrown" => "#BC8F8F",
        "RoyalBlue" => "#4169E1",
        "SaddleBrown" => "#8B4513",
        "Salmon" => "#FA8072",
        "SandyBrown" => "#F4A460",
        "SeaGreen" => "#2E8B57",
        "SeaShell" => "#FFF5EE",
        "Sienna" => "#A0522D",
        "Silver" => "#C0C0C0",
        "SkyBlue" => "#87CEEB",
        "SlateBlue" => "#6A5ACD",
        "SlateGray" => "#708090",
        "Snow" => "#FFFAFA",
        "SpringGreen" => "#00FF7F",
        "SteelBlue" => "#4682B4",
        "Tan" => "#D2B48C",
        "Teal" => "#008080",
        "Thistle" => "#D8BFD8",
        "Tomato" => "#FF6347",
        "Turquoise" => "#40E0D0",
        "Violet" => "#EE82EE",
        "Wheat" => "#F5DEB3",
        "White" => "#FFFFFF",
        "WhiteSmoke" => "#F5F5F5",
        "Yellow" => "#FFFF00",
        "YellowGreen" => "#9ACD32"
    ];
    if (!is_notempty_str($cn)) return $cns;
    foreach ($cns as $c => $hex) {
        if (strtolower($c) === strtolower($cn)) {
            return $hex;
            break;
        }
    }
    return null;
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