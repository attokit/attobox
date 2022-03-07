<?php

/**
 * CPHP框架  Resource 模块
 * 资源输出器
 * 输出视频流
 */

namespace CPHP\resource\exporter;

use CPHP\resource\Exporter;
use CPHP\Response;
use CPHP\resource\Mime;
use CPHP\resource\Stream;

class Video extends Exporter
{
    //根据 resource->rawExt 输出
    public function export()
    {
        $stream = Stream::create($this->resource->realPath);
        $stream->start();
        exit;

        /*$file = $this->resource->realPath;
        $ext = $this->resource->rawExt;
        $self = self::class;
        $m = "export".ucfirst($ext);
        if (method_exists($self, $m)) {
            call_user_func_array([$self, $m], [ $file ]);
        } else {
            $this->header()->sent();
            echo $this->resource->content;
        }
        exit;*/
    }



    /*
     *  静态工具方法，不同 ext 的视频流输出方法
     */

    public static function exportMp4($file = "")
    {
        $stream = Stream::create($file);
        $stream->start();
        exit;
    }

    public static function exportMp4_old($file = "")
    {
        if (!file_exists($file)) Response::code(404);
        $ext = Mime::ext($file);
        //ob_start();
        $size = filesize($file);
        header("Content-type: ".Mime::get($ext)); 
        header("Accept-Ranges: bytes"); 
        if(isset($_SERVER['HTTP_RANGE'])){ 
            header("HTTP/1.1 206 Partial Content"); 
            list($name, $range) = explode("=", $_SERVER['HTTP_RANGE']); 
            list($begin, $end) =explode("-", $range); 
            if($end == 0){ 
                $end = $size - 1; 
            } 
        }else { 
            $begin = 0; $end = $size - 1; 
        } 
        header("Content-Length: " . ($end - $begin + 1)); 
        header("Content-Disposition: filename=".basename($file)); 
        header("Content-Range: bytes ".$begin."-".$end."/".$size); 
        try {
            $fp = fopen($file, 'r');
        } catch (\Exception $e) {
            echo $e->getTraceAsString();
            exit;
        }
        //$fp = fopen($file, 'rb'); 
        fseek($fp, $begin); 
        $content = "";
        while(!feof($fp)) { 
            $p = min(1024, $end - $begin + 1); 
            //$begin += $p; 
            //echo fread($fp, $p); 
            $content .= fread($fp, $p);
        }
        ob_end_clean();
        ob_clean();
        fclose($fp);
        exit($content);
    }
}