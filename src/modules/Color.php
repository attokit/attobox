<?php

/**
 * Attobox Framework / Color 颜色类
 * 用于输出不同形式的颜色，对颜色进行计算
 */

namespace Atto\Box;

class Color 
{
    /**
     * 颜色参数
     * 所有颜色参数，内部均为 0~1 浮点数
     * 默认颜色为 #FF0000 红色
     */
    private $r = 1;
    private $g = 0;
    private $b = 0;
    private $h = 0;
    private $s = 1;
    private $l = 0.5;
    private $a = 1;

    /**
     * 可用于输出的颜色参数
     */
    public $rgb = [];
    public $hsl = [];
    public $alpha = 100;

    /**
     * 原始参数
     */
    private $origin = [];

    public function __construct($anyColorStr="")
    {
        $opt = self::parse($anyColorStr);
        if ($opt===false) return null;
        $this->sets($opt);
        if (!isset($opt["r"])) {
            $rgb = self::hsl2rgb(...$this->varr("h,s,l"));
            $this->sets($rgb);
        }
        if (!isset($opt["h"])) {
            $hsl = self::rgb2hsl(...$this->varr("r,g,b"));
            $this->sets($hsl);
        }
        //保存原始颜色参数
        $ks = "r,g,b,h,s,l,a";
        $ksarr = explode(",", $ks);
        $cps = $this->varr($ks);
        for ($i=0;$i<count($ksarr);$i++) {
            $this->origin[$ksarr[$i]] = $cps[$i];
        }
        //准备输出数据
        $this->outd();
    }

    //输出 rgb hsl a 值数组，排序按给定的 arg 参数数组
    public function varr(...$args)
    {
        if (count($args)==1 && is_notempty_str($args[0]) && strpos($args[0], ",")!==false) {
            $args = explode(",", $args[0]);
        }
        $self = $this;
        return array_map(function($i) use ($self) {
            return $self->$i;
        }, $args);
    }

    //将 opt 中的键值附加到 $this
    public function sets($opt=[])
    {
        //var_dump($opt);
        foreach ($opt as $k => $v) {
            if (is_notempty_str($k)) {
                $this->$k = $v;
            }
        }
        return $this;
    }

    //将内部参数转化为可用于输出的外部参数
    public function outd()
    {
        $self = $this;
        $rgb = $this->varr("r,g,b");
        $this->rgb = array_map(function ($i) {
            return self::it($i, 255);
        }, $rgb);
        $hsl = $this->varr("h,s,l");
        $this->hsl = [
            self::it($hsl[0], 360),
            self::it($hsl[1], 100),
            self::it($hsl[2], 100),
        ];
        $this->alpha = self::it($this->a, 100);
        return $this;
    }

    //重置颜色参数 为 初始参数
    public function reset()
    {
        return $this->sets($this->origin);
    }

    /**
     * 输入颜色变换后的 rgb hsl a
     */
    public function setRgb($rgb=[])
    {
        if (empty($rgb)) return $this;
        $ks = ["r","g","b"];
        for ($i=0;$i<3;$i++) {
            $ki = $ks[$i];
            if (!isset($rgb[$ki])) continue;
            $vi = $rgb[$ki];
            if ($vi>1) $vi = self::fl($vi, 255);
            $this->$ki = $vi;
        }
        $hsl = self::rgb2hsl(...$this->varr("r,g,b"));
        $this->sets($hsl);
        $this->outd();
        return $this;
    }
    public function setHsl($hsl=[])
    {
        if (empty($hsl)) return $this;
        $ks = ["h","s","l"];
        for ($i=0;$i<3;$i++) {
            $ki = $ks[$i];
            if (!isset($hsl[$ki])) continue;
            $vi = $hsl[$ki];
            if ($vi>1) $vi = self::fl($vi, $i>0 ? 100 : 360);
            $this->$ki = $vi;
        }
        $rgb = self::hsl2rgb(...$this->varr("h,s,l"));
        $this->sets($rgb);
        $this->outd();
        return $this;
    }
    public function setAlpha($a=null)
    {
        if (!is_numeric($a)) return $this;
        if ($a>1) $a = self::fl($a, 100);
        $this->a = $a;
        $this->outd();
        return $this;
    }

    /**
     * 输出
     */
    //自动输出 css 颜色，带透明度的输出 rgb()，否则输出 #hex
    public function css()
    {
        return $this->a<1 ? $this->rgb() : $this->hex();
    }
    public function rgb()
    {
        $a = $this->a;
        $prefix = $a < 1 ? "rgba" : "rgb";
        $out = array_merge([], $this->rgb);
        if ($a < 1) {
            $out[] = self::rd($a, 2);
        }
        //旧语法
        //return "$prefix(".implode(",", $out).")";
        //新语法
        return "rgb(".implode(" ",array_slice($out, 0, 3)).($a<1? " / $out[3]" : "").")";
        
    }
    public function hex()
    {
        $rgb = $this->rgb;
        $a = $this->a;
        $hex = array_map(function ($i) {
            return ($i<16 ? "0" : "").dechex($i);
        }, $rgb);
        if ($a < 1) {
            $hex[] = ($i<16 ? "0" : "").dechex(self::it($a, 255));
        }
        return "#".implode("",$hex);
    }
    public function hsl()
    {
        $hsl = $this->hsl;
        $a = $this->a;
        $prefix = "hsl";
        $out = [];
        $out[] = $hsl[0];
        $out[] = $hsl[1]."%";
        $out[] = $hsl[2]."%";
        if ($a < 1) {
            $prefix = "hsla";
            $out[] = $this->alpha."%";
        }
        //旧语法
        //return "$prefix(".implode(",",$out).")";
        //新语法
        return "hsl(".implode(" ", array_slice($out, 0,3)).($a<1?" / $out[3]":"").")";
    }

    /**
     * 颜色变化计算
     */

    //计算颜色的明度，不同于亮度，brightness，0~1
    public function getBrightness() {
        $r = $this->r;
        $g = $this->g;
        $b = $this->b;
        $bright = 0.299*$r + 0.587*$g + 0.114*$b;
        return self::rd($bright);
    }

    /**
     * 加深
     * @param Int $lvl 加深百分比
     * @return $this
     */
    public function turnDark($lvl=10)
    {
        $this->reset();
        $lvl = $lvl/100;
        $l = $this->l;
        $s = $this->s;
        $l = self::rd($l-$lvl);
        $s = self::rd($s-$lvl/2);
        $l = $l<0 ? 0 : $l;
        $s = $s<0 ? 0 : $s;
        return $this->setHsl(["l"=>$l, "s"=>$s]);
    }

    /**
     * 减淡
     * @param Int $lvl 减淡百分比
     * @return $this
     */
    public function turnLight($lvl=10)
    {
        $this->reset();
        $lvl = $lvl/100;
        $l = $this->l;
        $s = $this->s;
        $l = self::rd($l+$lvl);
        $s = self::rd($s+$lvl/2);
        $l = $l>1 ? 1 : $l;
        $s = $s>1 ? 1 : $s;
        return $this->setHsl(["l"=>$l,"s"=>$s]);
    }

    /**
     * 计算当前颜色作为背景时的前景色
     * @return Color 新的 Color 实例
     */
    public function getFrontColor()
    {
        $br = $this->getBrightness();
        if ($br>0.5) {
            $rgb = "rgb(0,0,0)";
        } else {
            $rgb = "rgb(255,255,255)";
        }
        return self::load($rgb);
    }






    /**
     * static tools
     */

    /**
     * 加载颜色字符串，创建 Color 实例
     */
    public static function load($anyColorStr)
    {
        $color = new Color($anyColorStr);
        if (is_null($color)) return null;
        return $color;
    }

    /**
     * 根据输入的任意颜色字符串，解析出正确的 颜色参数
     * @param String $anyColorStr 颜色字符串，like: #ff0000 / rgb(255,255,255) / rgba(255,0,0,.3) / hsl(120,50%,50%,30%) / hsl(120,50,100) / ...
     * @return Mixed 解析失败时（输入不正确的颜色字符串时）返回 false，否则返回经过解析的 颜色参数 array
     */
    public static function parse($anyColorStr="hsl(0,100%,50%,100%)")
    {
        if (!is_notempty_str($anyColorStr)) return false;
        //禁止输入 none 参数，如：hsl(none,50%,50%)
        if (strpos($anyColorStr, "none")!==false) return false;
        $str = trim(strtolower($anyColorStr));
        //正则匹配
        $regs = [
            //hex:  #fa0 | #fa07 | #ffaa00 | #ffaa0077
            "hex" =>    "/^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/",
            //rgb =>  rgb(255,128,0) | rgb(100%,50%,0) | rgba(255,128,0,.5) | 新语法 rgb(255 128 0 / .5) | rgb(100% 50% 0 / 50%)
            "rgb" =>    "/^rgba?\(\s*(((\d|\d{2}|1\d{2}|2[0-5]{2})|(\d+(\.\d+)?%)|none)\s*(,|\s)\s*){2}((\d|\d{2}|1\d{2}|2[0-5]{2})|(\d+(\.\d+)?%)|none)((\s*,\s*|\s+\/\s+)((\.|0\.)\d+|\d+(\.\d+)?%|none))?\s*\)$/",
            //hsl =>  hsl(120,75,65) | hsl(120deg,75%,65%) | hsla(120,75,65,.5) | 新语法 hsl(120deg 75% 65% / 50%)
            "hsl" =>    "/^hsla?\(\s*(\d+(\.\d+)?(deg|grad|rad|turn)?|\d+(\.\d+)?%|(\.|0\.)\d+|none)(\s*,\s*|\s+)(\d+(\.\d+)?%?|(\.|0\.)\d+|none)(\s*,\s*|\s+)(\d+(\.\d+)?%?|(\.|0\.)\d+|none)((\s*,\s*|\s+\/\s+)(\d+(\.\d+)?%|(\.|0\.)\d+|none))?\s*\)$/"
        ];
        $m = null;
        $opt = false;
        foreach ($regs as $k => $reg) {
            $mt = preg_match($reg, $str);
            if ($mt!==0 && $mt!==false) {
                $m = "parse".ucfirst($k)."String";
                if (method_exists(__CLASS__, $m)) {
                    $opt = call_user_func([__CLASS__,$m], $str);
                    if ($opt!==false) {
                        break;
                    }
                }
                $m = null;
                $opt = false;
            }
        }
        return $opt;
    }

    /**
     * 按字符串颜色类型解析，hex rgb hsl ...
     * 返回结果 like： {r:0,g:0,b:0,a:1} 所有数值均为 <1 浮点数，2位小数
     */
    // #fa0 | #fa07 | #ffaa00 | #ffaa0077
    protected static function parseHexString($str) 
    {
        $str = str_replace("#","", $str);
        if (strlen($str)==3 || strlen($str)==4) {
            $str = "#" . implode(
                "", 
                array_map(function ($i) {
                    return $i."".$i;
                }, explode("", $str))
            );
        }
        $rgb = [
            "r" => self::fl(hexdec(substr($str, 0,2)), 255), 
            "g" => self::fl(hexdec(substr($str, 2,2)), 255), 
            "b" => self::fl(hexdec(substr($str, 4,2)), 255),
            "a" => 1
        ];
        if (strlen($str)==8) $rgb["a"] = self::fl(hexdec(substr($str, 6,2)), 255);
        return $rgb;
    }
    //rgb(255,128,0) | rgb(100%,50%,0) | rgba(255,128,0,.5) | 新语法 rgb(255 128 0 / .5) | rgb(100% 50% 0 / 50%)
    protected static function parseRgbString($str) 
    {
        $rps = ["rgb(","rgba(",")"];
        array_walk($rps, function($i) use (&$str) {
            $str = str_replace($i,"", $str);
        });
        $str = trim($str);
        if (strpos($str, ",")!==false) {
            if (strpos($str,"/")!==false) $str = str_replace("/",",",$str);
            $str = preg_replace("/\s+/", ",", $str);
        }
        if (strpos($str, ".")!==false && strpos($str, "0.")===false) $str = str_replace(".","0.",$str);
        $arr = explode(",",$str);
        $rgb = [];
        for ($i=0;$i<count($arr);$i++) {
            $n = trim($arr[$i]);
            $max = $i>2 ? 100 : 255;
            if (strpos($n, "%")!==false) {
                $n = str_replace("%","",$n);
                $rgb[] = self::fl($n*1, $max);
            } else {
                $n = $n*1;
                if ($n>1) {
                    $rgb[] = self::fl($n, $max);
                } else {
                    $rgb[] = $n;
                }
            }
        }
        $opt = [
            "r" => $rgb[0],
            "g" => $rgb[1],
            "b" => $rgb[2],
            "a" => count($rgb)>3 ? $rgb[3] : 1
        ];
        return $opt;
    }
    //hsl:  hsl(120,75,65) | hsl(120deg,75%,65%) | hsla(120,75,65,.5) | 新语法 hsl(120deg 75% 65% / 50%)
    protected static function parseHslString($str) 
    {
        $rps = ["hsl(","hsla(",")"];
        array_walk($rps, function($i) use (&$str) {
            $str = str_replace($i,"", $str);
        });
        $str = trim($str);
        if (strpos($str, ",")!==false) {
            if (strpos($str,"/")!==false) $str = str_replace("/",",",$str);
            $str = preg_replace("/\s+/", ",", $str);
        }
        $arr = explode(",",$str);
        $hsl = [];
        for ($i=0;$i<count($arr);$i++) {
            $n = trim($arr[$i]);
            $max = $i>0 ? 100 : 360;
            if (substr($n, 0,1)==".") $n = "0$n";
            if (strpos($n, "deg")!==false) $n = str_replace("deg","", $n);
            if (strpos($n, "%")!==false) {
                $n = str_replace("%","",$n);
                $hsl[] = self::fl($n*1, $max);
            } else {
                if (strpos($n, "grad")!==false) {   //角度为百分度，1圆==400grad，max=400
                    $n = str_replace("grad","", $n);
                    $hsl[] = self::fl($n*1, 400);
                } else if (strpos($n, "rad")!==false) { //角度为弧度，1rad = 180/Π度，n*180/PI max=360
                    $n = str_replace("rad","", $n);
                    $pi = pi();
                    $hsl[] = self::fl($n*1*180/$pi, 360);
                } else if (strpos($n, "turn")!==false) {    //角度按旋转圈数，1圈=360
                    $n = str_replace("turn","", $n);
                    $n = $n*1; $n = $n>1 ? $n-1 : $n;
                    $hsl[] = self::fl($n, 360);
                } else {    //默认按角度，0-360
                    $n = $n*1;
                    if ($n>1) {
                        $hsl[] = self::fl($n, $max);
                    } else {
                        $hsl[] = $n;
                    }
                }
            }
        }
        $opt = [
            "h" => $hsl[0],
            "s" => $hsl[1],
            "l" => $hsl[2],
            "a" => count($hsl)>3 ? $hsl[3] : 1
        ];
        return $opt;
    }

    /**
     * rgb <--> hsl
     * 输入的参数 均为 <1 浮点数
     */
    public static function rgb2hsl($r,$g,$b)
    {
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max == $min) {
            $h = 0;
            $s = 0; // achromatic
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r: $h = ($g - $b) / $d + ($g < $b ? 6 : 0); break;
                case $g: $h = ($b - $r) / $d + 2; break;
                case $b: $h = ($r - $g) / $d + 4; break;
            }
            $h = $h / 6;
        }
        return self::rda([
            "h" => $h,
            "s" => $s,
            "l" => $l
        ]);
    }
    public static function hsl2rgb($h,$s,$l)
    {
        if ($s == 0) {
            $r = $g = $b = $l; // achromatic
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hue2rgb($p, $q, $h + 1 / 3);
            $g = self::hue2rgb($p, $q, $h);
            $b = self::hue2rgb($p, $q, $h - 1 / 3);
        }
        return self::rda([
            "r" => $r,
            "g" => $g,
            "b" => $b
        ]);
    }
    public static function hue2rgb($p, $q, $t)
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    }

    /**
     * 颜色计算
     */
    
    


    //整数数字转为 <1 浮点数，max 为数字最大值，超过此值则返回 1  dig 为保留小数位数
    public static function fl($n, $max=255, $dig=4) {
        if ($n>$max) return 1;
        if ($n<0) return 0;
        $d = 10 ** $dig;    //10 的 dig 次方
        return round($n*$d/$max)/$d;
    }
    //<1 浮点数转为 整数
    public static function it($n, $max=255) {
        if ($n>1) return $max;
        if ($n<0) return 0;
        return round($n*$max);
    }
    //保留 dig 位小数
    public static function rd($n, $dig=4) {
        $d = 10 ** $dig;
        return round($n*$d)/$d;
    }
    //对 {} 中所有数字保留 dig 位小数
    public static function rda($o=[], $dig=4) {
        $oo = [];
        foreach ($o as $i => $oi) {
            if (is_numeric($oi)) {
                $oo[$i] = self::rd($oi, $dig);
            }
        }
        return $oo;
    }
    //确保输入的数值在给定范围内，输出确定在范围内的数字
    public static function en($n, $max=255, $min=0)
    {
        if (!is_numeric($n)) return $min;
        $n = $n*1;
        return $n>$max ? $max : ($n<$min ? $min : $n);
    }
}