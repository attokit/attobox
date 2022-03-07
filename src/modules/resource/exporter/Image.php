<?php

/**
 * Attobox Framework / Module Resource
 * Resource Exporter
 * 
 * Image exporter
 */

namespace Atto\Box\resource\exporter;

use Atto\Box\resource\Exporter;
use Atto\Box\Request;
use Atto\Box\request\Url;
use Atto\Box\Response;
use Atto\Box\Resource;
use Atto\Box\resource\Mime;

class Image extends Exporter
{
    public $mime = "";
    public $size = 0;
    public $sizestr = "";
    public $width = 0;
    public $height = 0;
    public $ratio = 1;  //宽高比，width/height
    public $bit = 8;

    //image处理句柄
    public $source = null;
    public $im = null;  //从source创建的临时图片

    //process queue 处理操作序列，必须按顺序执行
    public $queue = [
        ["zoom", 100],
        ["resize", "256,192"],
        ["thumb", "96,96"],
        ["watermark", "dommyphp,right,bottom,25"]    //水印
    ];

    public function prepare($params = [])
    {
        $this->getInfo();
        $this->getImage();
        $this->process($params);
    }

    //获取文件信息
    protected function getInfo()
    {
        $this->mime = Mime::get($this->resource->rawExt);
        $imgPath = $this->resource->realPath;
        $this->size = filesize($imgPath);
        $this->sizestr = sizeToStr($this->size);

        $is = getimagesize($imgPath);
        $this->width = $is[0];
        $this->height = $is[1];
        $this->ratio = $is[0]/$is[1]; 
        $this->bit = $is["bits"];
    }

    //从原图像生成新图像，准备处理
    protected function getImage()
    {
        $method = "imagecreatefrom" . str_replace("image/", "", $this->mime);
        if (function_exists($method)) {
            $this->source = @$method($this->resource->realPath);
        }
    }

    //根据get参数处理图片
    protected function process($params = [])
    {
        //检查当前图片是否由URL调用
        //url调用，则从 $_GET 中获取 process 参数
        //否则从 params 参数中获取
        $url = Url::current();
        if (strpos(strtolower(implode("/", $url->path)), strtolower($this->resource->rawBasename)) !== false) {
            $params = $_GET;
        }
        $que = $this->queue;
        for ($i=0; $i<count($que); $i++) {
            $qi = $que[$i];
            $pm = $qi[0];
            $arg = $qi[1];
            $g = isset($params[$pm]) ? $params[$pm] : null; //Request::get($pm, null);
            if (!is_null($g)) {
                $g = $g == "auto" ? $arg : $g;
                $m = "process" . ucfirst($pm);
                if (method_exists($this, $m)) {
                    $this->$m($g);
                }
            }
        }
        
    }



    /*
     *  process主要方法
     */
    //processZoom %比例缩放
    public function processZoom($opt = 100)
    {
        $opt = empty($opt) || !is_numeric($opt) ? 100 : (int)$opt;
        $this->im = $this->resizeImage($opt);
        return $this;
    }
    //processResize 指定宽高，当指定的宽高比不等于原图片时，保证缩放后的图片不超出指定的宽高
    public function processResize($opt = "256,192")
    {
        $opt = explode(",", $opt);
        $this->im = $this->resizeImage((int)$opt[0], (int)$opt[1]);
        return $this;
    }
    //processThumb 自动生成缩略图，裁切原图，满足缩略图宽高比
    public function processThumb($opt = "128,128")
    {
        $opt = explode(",", $opt);
        $this->im = $this->thumbImage((int)$opt[0], (int)$opt[1]);
        return $this;
    }
    //processWatermark 添加水印
    public function processWatermark($opt = "cphp,right,bottom,25")
    {
        $opt = explode(",", $opt);
        $mn = $opt[0];
        $mp = path_exists([
            "icon/watermark/$mn.jpg",
            "icon/watermark/$mn.png",
            "entry/icon/watermark/$mn.jpg",
            "entry/icon/watermark/$mn.png"
        ]);
        if (is_null($mp)) return $this;
        $mp = Resource::create($mp);
        //var_dump($mp); exit;
        $mpo = $mp->exporter;
        //当前图像
        if (is_null($this->im)) $this->im = imagecreatefromstring(file_get_contents($this->fullpath));
        //当前图像尺寸
        $w = imagesx($this->im);
        $h = imagesy($this->im);
        //水印应缩放到的尺寸
        $ww = $w * (int)$opt[3] / 100;
        $wh = $ww / $mpo->ratio;
        //缩放水印
        $wim = imagecreatetruecolor($ww, $wh);
        imagecopyresampled($wim, $mpo->source, 0, 0, 0, 0, $ww, $wh, $mpo->width, $mpo->height);
        //水印位置
        list($x, $y) = $this->watermarkPosition($w, $h, $ww, $wh, $opt[1], $opt[2]);
        //水印copy到当前图像
        imagecopy($this->im, $wim, $x, $y, 0, 0, $ww, $wh);
        
        return $this;
    }



    /*
     *  工具
     */
    //缩放到% 或 指定大小，等比缩放
    public function resizeImage($w = null, $h = null)
    {
        if (empty($w) && empty($h)) return $this->source;
        $ow = $this->width;
        $oh = $this->height;
        $ro = $this->ratio;
        if ($w <= 100 && empty($h)) {   //zoom
            $zoom = $w;
            $w = $zoom * $ow / 100;
            $h = $zoom * $oh / 100;
        } else {    //resize to w & h
            if (empty($w)) {
                $w = $ro * $h;
            } elseif (empty($h)) {
                $h = $w / $ro;
            } else {
                $r = $w/$h;
                if ($r >= $ro) {
                    $w = $ro * $h;
                } else {
                    $h = $w / $ro;
                }
            }
        }
        $im = imagecreatetruecolor($w, $h);
        imagecopyresampled($im, $this->source, 0, 0, 0, 0, $w, $h, $ow, $oh);
        return $im;
    }
    //将原图缩放并裁剪，生成thumb
    public function thumbImage($w = 64, $h = 64)
    {
        $ow = $this->width;
        $oh = $this->height;
        $ro = $this->ratio;
        $r = $w / $h;
        if ($r >= $ro) {
            $tw = $ow;
            $th = $tw / $r;
        } else {
            $th = $oh;
            $tw = $th * $r;
        }
        //先裁剪到目标宽高比
        $im = imagecreatetruecolor($tw, $th);
        imagecopy($im, $this->source, 0, 0, ($ow-$tw)/2, ($oh-$th)/2, $tw, $th);
        //再缩放到目标尺寸
        $newim = imagecreatetruecolor($w, $h);
        imagecopyresampled($newim, $im, 0, 0, 0, 0, $w, $h, $tw, $th);
        return $newim;
    }
    //根据图片尺寸，水印尺寸，水印位置，计算水印坐标
    public function watermarkPosition($w, $h, $ww, $wh, $xpos = "left", $ypos = "top")
    {
        $sep = 5;   //水印边距 5%
        $x = 0;
        $y = 0;
        switch ($xpos) {
            case "left" :       $x = $w * $sep / 100; break;
            case "right" :      $x = ($w * (100-$sep) / 100) - $ww; break;
            case "center" :     $x = ($w - $ww) / 2; break;
        }
        switch ($ypos) {
            case "top" :        $y = $h * $sep / 100; break;
            case "bottom" :     $y = ($h * (100-$sep) / 100) - $wh; break;
            case "center" :     $y = ($h - $wh) / 2; break;
        }
        return [$x, $y];
    }

    //默认的输出方法，子类覆盖
    public function export($params = [])
    {
        $this->prepare($params);
        $im = !empty($this->im) ? $this->im : $this->source;
        $m = str_replace("/", "", $this->mime);   // imagejpeg, imagepng, imagegif, imagewebp, imagebmp
        if (function_exists($m)) {
            $this->header()->sent();
            if ($this->resource->rawExt == "png") {
                $m($im);
            } else {
                $m($im, null, 100);
            }
            imagedestory($im);
        }
        exit;
    }

    //保存图片
    public function save($savepath = null, $params = [])
    {
        $this->prepare($params);
        $im = !empty($this->im) ? $this->im : $this->source;
        $m = str_replace("/", "", $this->mime);   // imagejpeg, imagepng, imagegif, imagewebp, imagebmp
        $savepath = empty($savepath) ? $this->path.DS.str_replace(".".$this->ext, "", $this->name)."_".time().".".$this->ext : $savepath;
        //var_dump($this->path); exit;
        if (function_exists($m)) {
            if ($this->resource->rawExt == "png") {
                $m($im, $savepath);
            } else {
                $m($im, $savepath, 100);
            }
        }
    }
}