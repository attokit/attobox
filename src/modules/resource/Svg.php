<?php

/**
 * Attobox Framework / Module Resource
 * Resource Extension
 * 
 * SVG
 */

namespace Atto\Box\resource;

use Atto\Box\Resource;
use Atto\Box\Response;

use Sabberworm\CSS\Parser as cssParser;

class Svg extends Resource
{
    /**
     * svg 结构化数据
     */
    protected $xml = null;
    protected $header = [
        "<?xml version=\"1.0\" standalone=\"no\"?>",
        "<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd\">",
        //"<!-- //cgy.design/icon/*  -->"
    ];
    protected $defaultIconAttributes = [
        "width"     => "512px",
        "height"    => "512px",
        "viewBox"   => "0 0 1024 1024",
        "version"   => "1.1",
        "xmlns"     => "http://www.w3.org/2000/svg"
    ];

    /**
     * svg css 处理对象
     */
    protected $css = null;
    //图形 style 名称集合
    protected $styles = [];
    protected $styleCount = 0;
    
    /**
     * after resource created
     * if necessary, derived class should override this method
     * @return Resource $this
     */
    protected function afterCreated()
    {
        $this->getContent();
        
        if ($this->needParse()) {
            $this->parseIconSvg();
        }

        return $this;
    }

    /**
     * @override export
     * export svg file
     * @return void exit
     */
    public function export($params = [])
    {
        $params = empty($params) ? $this->params : arr_extend($this->params, $params);
        
        if ($this->needParse()) {
            //根据 params 编辑 icon svg
            $pks = implode(",", array_keys($params));
            if (strpos($pks, "fill")!==false) {
                if (!isset($params["fill"])) $params["fill"] = "none";
            }
            if (strpos($pks, "opacity")!==false) {
                if (!isset($params["opacity"])) $params["opacity"] = "1";
            }
            if (strpos($pks, "gray")!==false) {
                if (!isset($params["gray"])) $params["gray"] = "0";
            }
            if (strpos($pks, "reverse")!==false) {
                if (!isset($params["reverse"])) $params["reverse"] = "yes";
            }
            foreach ($params as $p => $v) {
                $m = "editIcon".ucfirst(strtolower($p));
                if (method_exists($this, $m)) {
                    $this->$m($v);
                }
            }
            //var_dump($this->xml);exit;
            //生成 xml
            $xml = $this->createIconXml($this->xml);
        } else {
            if ($this->isIcon() && !$this->isAiSvg()) {
                $this->fixSvgHeader();
                $xml = $this->content;
            } else {
                //直接输出 svg
                $xml = $this->content;
            }
        }



        /*if (!$this->isAiSvg()) {
            //var_dump($this->tree); exit;


            //process
            $ps = $this->params;
            $fill = isset($params["fill"]) ? $params["fill"] : "";
            if (isset($params["fill"])) unset($params["fill"]);
            $keys = array_keys($this->tree);
            for ($i=0;$i<count($keys);$i++) {
                $ki = $keys[$i];
                if (isset($params[$ki])) {
                    $this->tree[$ki] = $params[$ki];
                }
            }
            if (!empty($fill)) {
                if (strpos($fill, ",")===false) {
                    $fill = array_fill(0, count($this->tree["path"]), $fill);
                } else {
                    if (strpos($fill, "rgb")!==false && strpos($fill, ",rgb")===false) {
                        $fill = array_fill(0, count($this->tree["path"]), $fill);
                    } else if (strpos($fill, "rgb")!==false && strpos($fill, ",rgb")!==false) {
                        $fill = str_replace(",rgb","|rgb", $fill);
                        $fill = explode("|",$fill);
                    } else {
                        $fill = explode(",", $fill);
                    }
                }
                for ($i=0; $i<count($this->tree["path"]); $i++) {
                    if (isset($fill[$i]) && !empty($fill[$i])) {
                        $fi = $fill[$i];
                        if (strlen($fi)==6) $fi = "#".$fi;
                        $this->tree["path"][$i]["fill"] = $fi;
                    }
                }
            }

            //待输出 xml
            $xml = $this->createXML();
        } else {
            $xml = $this->content;
        }*/

        //sent header
        Mime::header($this->rawExt, $this->rawBasename);
        Response::headersSent();

        //echo
        echo $xml;
        exit;
    }



    /**
     * process icon svg
     */

    /**
     * 处理 icon svg
     * 解析 xml 结构，为修改 svg 做准备
     * @return $this
     */
    protected function parseIconSvg()
    {
        if ($this->isIcon() && is_null($this->xml)) {
            $this->xml = x2a($this->getContent());
            $attr = $this->xml["@attributes"];
            $this->xml["@attributes"] = arr_extend($this->defaultIconAttributes, $attr);
            //fix viewBox,width,height
            $vb = $this->xml["@attributes"]["viewBox"];
            if ($vb != "0 0 1024 1024") {
                $vbs = explode(" ",$vb);
                $this->xml["@attributes"]["width"] = $vbs[2]."px";
                $this->xml["@attributes"]["height"] = $vbs[3]."px";
            }
            //fix pathes,g,...
            foreach ($this->xml as $k => $v) {
                if ($k == "@attributes" || !is_array($v)) continue;
                if (is_indexed($v)) continue;
                $this->xml[$k] = [];
                $this->xml[$k][] = $v;
            }
            //处理 style
            if (!isset($this->xml["style"])) $this->xml["style"] = [];
            if (is_notempty_str($this->xml["style"])) {
                /*$st = $this->xml["style"];
                $st = str_replace("\r\n","", trim($st));
                $st = preg_replace("/\s+/"," ", $st);
                $starr = explode("}", $st);
                $sts = [];
                for ($i=0;$i<count($starr);$i++) {
                    $sti = $starr[$i];
                    if (empty($sti)) continue;
                    $stia = explode("{", $sti);
                    $k = "cls_".str_replace(".","",trim($stia[0]));
                    $v = str_replace(",\"\"}", "}", ("{\"".str_replace(":", "\":\"", str_replace(";", "\",\"", trim($stia[1]) ) )."\"}") );
                    $v = j2a($v);
                    $sts[$k] = $v;
                }
                $this->xml["style"] = $sts;*/
                $this->xml["style"] = $this->parseCssToArr($this->xml["style"]);
            }
            //递归 style
            $this->parseIconPathStyle();

            //var_dump($this->xml);
            //exit;
        }
        return $this;
    }

    /**
     * 解析 style
     */
    /*protected function parseIconStyle()
    {
        if (!isset($this->xml["style"])) $this->xml["style"] = "";
        $st = $this-xml["style"];
        if (is_notempty_str($st)) {
            $parser = new cssParser($this->xml["style"]);
            $css = $parser->parse();
        }
    }*/

    /**
     * 递归 style，为每个 path 指定 style class，按顺序
     */
    protected function parseIconPathStyle($xmlo = null)
    {
        $isroot = is_null($xmlo);
        $xml = $isroot ? $this->xml : $xmlo;
        foreach ($xml as $k => $v) {
            if ($k === "style") continue;
            if ($k === "@attributes") {
                if (!$isroot) {
                    $_ncls = "style_".$this->styleCount;
                    $this->styles[$_ncls] = [];
                    if (isset($v["class"])) {
                        $_ocls = $v["class"];
                        $this->styles[$_ncls] = $this->xml["style"]["cls_".$_ocls];
                    } else {
                        $sks = ["fill","opacity"];
                        foreach ($sks as $i => $ski) {
                            if (isset($v[$ski])) {
                                $this->styles[$_ncls][$ski] = $v[$ski];
                                unset($xml[$k][$ski]);
                            }
                        }
                    }
                    $this->styleCount ++;
                    $xml[$k]["class"] = $_ncls;
                }
            } else if (is_array($v)) {
                $xml[$k] = $this->parseIconPathStyle($v);
            }
        }
        if (!$isroot) return $xml;
        $this->xml = $xml;
        $this->xml["style"] = $this->styles;
        return $this;
    }

    /**
     * $this->xml  -->  svg  准备输出
     * 递归方式
     * @param Array $arr    用来生成 xml 的 array
     * @return String svg content
     */
    protected function createIconXml($arr = [], $isRoot = true)
    {
        $xml = "";
        if ($isRoot) {
            $xml .= implode("", $this->header);
            $xml .= "<svg ".a2p($arr["@attributes"]).">";
            $style = $this->xml["style"];
            if (!empty($style)) {
                $xml .= "<style type=\"text/css\">";
                $xml .= $this->parseArrToCss($style);
                $xml .= "</style>";
            }
        }
        foreach ($arr as $k => $v) {
            if ($k == "@attributes" || $k === "style") continue;
            if (is_indexed($v)) {
                for ($i=0;$i<count($v);$i++) {
                    $vi = $v[$i];
                    $xml .= "<$k";
                    if (isset($vi["@attributes"])) {
                        $xml .= " ".a2p($vi["@attributes"]);
                    }
                    $xml .= ">";
                    if (!is_array($vi)) {
                        $xml .= (string)$vi;
                    } else {
                        $xml .= $this->createIconXml($vi, false);
                    }
                    $xml .= "</$k>";
                }
            } else {
                $xml .= "<$k";
                if (isset($v["@attributes"])) {
                    $xml .= " ".a2p($v["@attributes"]);
                }
                $xml .= ">";
                if (!is_array($v)) {
                    $xml .= (string)$v;
                } else {
                    $xml .= $this->createIconXml($v, false);
                }
                $xml .= "</$k>";
            }
        }
        if ($isRoot) {
            $xml .= "</svg>";
            $xml .= "<!-- //cgy.design/icon/* api -->";
        }
        //var_dump($xml);
        return $xml;
    }

    /**
     * 修改方法
     * 修改颜色
     * @param String $p     修改参数，来自 $this->params
     * @return $this
     */
    protected function editIconFill($p = null)
    {
        $kw = "fill";
        $ps = $this->params;
        $fills = [];
        foreach ($ps as $k => $v) {
            if ($k==$kw || empty($v)) continue;
            if (strpos($k, $kw)!==false && is_numeric(str_replace($kw,"",$k))) {
                $c = $this->parseColor($v);
                $cls = str_replace($kw,"style_",$k);
                if (!is_null($c)) {
                    $fills[$cls] = $c;
                }
            }
        }
        $p = $p=="none" ? null : $p;
        //if (!empty($p)) $p = $this->parseColor($p);
        if (!empty($p)) {
            $p = str_replace("，",",",$p);
            if (strpos($p, ",")!==false && strpos($p, "rgb")===false && strpos($p, "hsl")===false) {
                $pfs = explode(",", $p);
                for ($i=0;$i<count($pfs);$i++) {
                    if ($pfs[$i]!="") {
                        $fills["style_".$i] = $this->parseColor($pfs[$i]);
                    }
                }
                $p = null;
            } else {
                $p = $this->parseColor($p);
            }
        }
        if (!empty($p) || !empty($fills)) {
            $style = $this->xml["style"];
            foreach ($style as $cls => $v) {
                if (isset($fills[$cls])) {
                    $style[$cls][$kw] = $fills[$cls];
                } else if (!empty($p)) {
                    $style[$cls][$kw] = $p;
                }
                
            }
            $this->xml["style"] = $style;
        }
        return $this;
    }

    /**
     * 修改方法
     * 修改透明度
     * @param String $p     修改参数，来自 $this->params
     * @return $this
     */
    protected function editIconOpacity($p = null)
    {
        $kw = "opacity";
        $ps = $this->params;
        $opas = [];
        foreach ($ps as $k => $v) {
            if ($k==$kw || !is_numeric($v) || $v==1) continue;
            if (strpos($k, $kw)!==false && is_numeric(str_replace($kw,"",$k))) {
                $o = $v*1;
                $cls = str_replace($kw,"style_",$k);
                $opas[$cls] = $o;
            }
        }
        $noset = !is_numeric($p) || $p==1;
        if (!$noset) $p = $p*1;
        if (!$noset || !empty($opas)) {
            $style = $this->xml["style"];
            foreach ($style as $cls => $v) {
                if (isset($opas[$cls])) {
                    $style[$cls][$kw] = $opas[$cls];
                } else if (!$noset) {
                    $style[$cls][$kw] = $p;
                }
                
            }
            $this->xml["style"] = $style;
        }
        //var_dump($this->xml["style"]);
        //var_dump($this->parseArrToCss($this->xml["style"]));exit;
        return $this;
    }

    protected function editIconSize($p = null)
    {
        $w = $this->xml["@attributes"]["width"];
        $h = $this->xml["@attributes"]["height"];
        $pstr = substr($p, -2)=="px" ? $p : $p."px";
        if ($w==$h) {
            $this->xml["@attributes"]["width"] = $pstr;
            $this->xml["@attributes"]["height"] = $pstr;
        } else {
            $pnum = substr($p, -2)=="px" ? str_replace("px","",$p)*1 : $p*1;
            $wnum = substr($w, -2)=="px" ? substr($w, 0, -2)*1 : $w*1;
            $hnum = substr($h, -2)=="px" ? substr($h, 0, -2)*1 : $h*1;
            if ($wnum>$hnum) {
                $nw = $pnum;
                $nh = ($pnum*$hnum)/$wnum;
            } else {
                $nh = $pnum;
                $nw = ($pnum*$wnum)/$hnum;
            }
            $this->xml["@attributes"]["width"] = $nw."px";
            $this->xml["@attributes"]["height"] = $nh."px";
        }
        return $this;
    }

    /**
     * 修改方法
     * 将彩色 svg 转换为灰度
     * @param String $p     修改参数，来自 $this->params
     * @return $this
     */
    protected function editIconGray($p = null)
    {
        if (!is_numeric($p)) $p = 0;
        $p = $p*1;
        $kw = "fill";
        $style = $this->xml["style"];
        foreach ($style as $cls => $v) {
            if (is_array($v)) {
                if (!empty($v) && isset($v[$kw])) {
                    //var_dump($cls."==>".$v[$kw]);
                    $style[$cls][$kw] = $this->parseColorToGray($v[$kw], $p);
                } else {
                    //$style[$cls] = [];
                    //var_dump($cls."==>#000000");
                    $style[$cls][$kw] = $this->parseColorToGray("#000000", $p);
                }
            }
        }
        $this->xml["style"] = $style;
        return $this;
    }

    /**
     * 修改方法
     * 反色处理
     * @param String $p     修改参数，来自 $this->params
     * @return $this
     */
    protected function editIconReverse($p = null)
    {
        if (!is_notempty_str($p) || !in_array($p,["yes","no"])) $p = "no";
        if ($p!=="yes") return $this;
        $kw = "fill";
        $style = $this->xml["style"];
        foreach ($style as $cls => $v) {
            if (is_array($v)) {
                if (!empty($v) && isset($v[$kw])) {
                    $style[$cls][$kw] = $this->parseColorToReverse($v[$kw]);
                } else {
                    $style[$cls][$kw] = "#ffffff";
                }
            }
        }
        $this->xml["style"] = $style;
        return $this;
    }

    

    /**
     * svg 头处理
     * 如果不存在头部，则补齐
     * @return $this
     */
    protected function fixSvgHeader()
    {
        $cnt = $this->content;
        //去除原有 头部
        $cnt = "<svg" . explode("<svg", $cnt)[1];
        //添加 svg version,xmlns 等参数
        $attr = $this->defaultIconAttributes;
        $ps = "";
        if (strpos($cnt, "version=\"")===false) $ps .= "version=\"".$attr["version"]."\" "; 
        if (strpos($cnt, "xmlns=\"")===false) $ps .= "xmlns=\"".$attr["xmlns"]."\" ";
        if (strpos($cnt, "width=\"")===false) {
            if (strpos($cnt, "viewBox=\"")!==false) {
                $vb = explode("\"", explode("viewBox=\"", $cnt)[1])[0];
                if ($vb!==$attr["viewBox"]) {
                    $vba = explode(" ", $vb);
                    $ps .= "width=\"".$vba[2]."\" height=\"".$vba[3]."\" ";
                } else {
                    $ps .= "width=\"".$attr["width"]."\" height=\"".$attr["height"]."\" ";
                }
            } else {
                $ps .= "width=\"".$attr["width"]."\" height=\"".$attr["height"]."\" viewBox=\"".$attr["viewBox"]."\" ";
            }
        }
        $cnt = str_replace("<svg", "<svg ".$ps, $cnt);
        //添加 svg 头
        $hd = implode("", $this->header);
        $this->content = $hd.$cnt;
        return $this;
    }

    /**
     * 判断是否 ai 生成的 svg
     */
    protected function isAiSvg()
    {
        $cnt = $this->content;
        return strpos($cnt, "Adobe Illustrator") !== false;
    }

    /**
     * 判断是否 icon
     */
    protected function isIcon()
    {
        return isset($this->params["icontype"]) && !empty($this->params["icontype"]);
    }

    /**
     * 判断是否需要解析 xml
     * 加速输出
     */
    protected function needParse()
    {
        $ps = $this->params;
        $ks = ["icontype","content"];
        foreach ($ks as $ki) {
            if (isset($ps[$ki])) unset($ps[$ki]);
        }
        return !empty($ps) && $this->isIcon() && !$this->isAiSvg();
    }



    /**
     * parse tools
     */

    /**
     * css --> arr
     */
    protected function parseCssToArr($css = "")
    {
        if (!is_notempty_str($css)) return [];
        $css = str_replace("\r\n","", trim($css));
        $css = preg_replace("/\s+/"," ", $css);
        $cssarr = explode("}", $css);
        $ca = [];
        for ($i=0;$i<count($cssarr);$i++) {
            $cssi = $cssarr[$i];
            if (empty($cssi)) continue;
            $cssia = explode("{", $cssi);
            $k = "cls_".str_replace(".","",trim($cssia[0]));
            $v = trim($cssia[1]);
            $v = replace_all([
                [";", "\",\""],
                [":", "\":\""]
            ], $v);
            $v = "{\"$v\"}";
            $v = str_replace(",\"\"}", "}", $v);
            $v = j2a($v);
            $ca[$k] = $v;
        }
        return $ca;
    }

    /**
     * arr --> css
     */
    protected function parseArrToCss($arr = [])
    {
        if (!is_notempty_arr($arr) || !is_associate($arr)) return "";
        $css = "";
        foreach ($arr as $cls => $v) {
            if (empty($v)) {
                $css .= ".$cls{}";
            } else {
                $s = a2j($v);
                $s = replace_all([
                    ["{\"",   "{",],
                    ["\":\"", ":",],
                    ["\":",   ":",],
                    ["\",\"", ";",],
                    [",\"",   ";",],
                    ["\"}",   ";}",],
                    [")}",   ");}",],
                    //["/[^;]}/",     ";}",]
                ], $s);
                $css .= ".".$cls.$s;
            }
        }
        return $css;
    }

    /**
     * 将 $_GET 参数输入的颜色信息转为可用的色值或颜色名称
     * @param String $p
     * @return String  or  null
     */
    protected function parseColor($p = null)
    {
        if (!is_notempty_str($p)) return null;
        if (substr($p, 0, 3)=="rgb" || substr($p, 0, 3)=="hsl") return $p;
        $hex = color_names($p);
        if (!is_null($hex)) return $hex;
        if (strlen($p)==6 || strlen($p)==8) return "#".$p;
        return null;
    }

    /**
     * 将十六进制颜色值转换为灰度值
     * @param String $hex 十六进制颜色值，#开头
     * @param String $gray 灰度开始值，0~255，值越大则灰度颜色越浅，默认128
     * @return String 返回十六进制灰度色值，以#开头
     */
    protected function parseColorToGray($hex, $gray=128)
    {
        $hex = str_replace("#","",$hex);
        $gray = (int)$gray;
        $gray = $gray>255 ? 255 : ($gray<0 ? 0 : $gray);
        $r = substr($hex, 0, 2);
        $g = substr($hex, 2, 2);
        $b = substr($hex, 4, 2);
        //var_dump($r."/".$g."/".$b);
        /** 灰度值简易公式 **/
        $gr = 0.2126 * hexdec($r) + 0.7152 * hexdec($g) + 0.0722 * hexdec($b);
        $gr = (1 - $gray/255) * $gr + $gray;
        $gr = dechex(round($gr));
        $rtn = "#".$gr.$gr.$gr;
        //var_dump($rtn);
        return $rtn;
    }

    /**
     * 将颜色反转，白色减去输入色
     * @param String $hex 十六进制颜色值，#开头
     * @return String 返回十六进制灰度色值，以#开头
     */
    protected function parseColorToReverse($hex)
    {
        $hex = str_replace("#","",$hex);
        $r = substr($hex, 0, 2);
        $g = substr($hex, 2, 2);
        $b = substr($hex, 4, 2);
        $rr = 255 - hexdec($r);
        $rg = 255 - hexdec($g);
        $rb = 255 - hexdec($b);
        return "#".dechex($rr).dechex($rg).dechex($rb);
    }

}