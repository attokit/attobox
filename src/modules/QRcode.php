<?php

/**
 * Attobox Framework / Module QRcode
 * QRcode generator
 */

namespace Atto\Box;

use Atto\Box\Request;
//use Atto\Box\Response;
use Atto\Box\qrcode\QRcode as QR;

define('QR_ECLVL_L', 0);
define('QR_ECLVL_M', 1);
define('QR_ECLVL_Q', 2);
define('QR_ECLVL_H', 3);

class QRcode
{
    public static function create($msg = null)
    {
        $msg = Request::get("msg", $msg); //要编码的内容，经过urlencode
        $msg = urldecode($msg);
        $tp = Request::get("type", "string");
        switch($tp){
            case "url" :
                $msg = str_replace("|","/",$msg);
                break;
        }
        $f = Request::get("file", "false");         //保存为文件名，不带后缀，false为不保存
        $e = Request::get("ec", QR_ECLVL_H);        //容错级别  L/M/Q/H
        $s = Request::get("size", 6);               //图片大小 1-10
        $m = Request::get("margin", 1);             //二维码与白背景的边距
        $p = Request::get("print", "false");        //是否在保存的同时输出
        $f = is_bool($f) ? $f : (is_ntf($f) ? $f!=="false" : $f);
        $p = is_bool($p) ? $p : $p!=="false";

        $png = self::createQrCodeImg($msg, $f, $e, $s, $m, $p);
        header("Content-type:image/png; charset=utf-8");
        echo $png;
        exit;
    }

    public static function createQrCodeImg($msg, $f=false, $e=QR_ECLVL_L, $s=6, $m=2, $p=false){
        //文件保存目标位置
        $fp = ASSET_PATH.DS."qrcode";
        if($f!==false){
            $f = $fp.DS.(empty($f) ? "" : $f."_").date("YmdHis",time()).".png";
        }
        return QR::png($msg,$f,$e,$s,$m,$p);
    }

}




