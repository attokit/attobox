<?php

/**
 * CPHP框架  Resource 模块
 * 资源输出器
 * 输出音频 audio 文件
 */

namespace CPHP\resource\exporter;

use CPHP\resource\Exporter;
use CPHP\Response;
use CPHP\resource\Mime;
use CPHP\resource\Stream;

class Audio extends Exporter
{
    //根据 resource->rawExt 输出
    public function export()
    {
        $stream = Stream::create($this->resource->realPath);
        $stream->init();
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

}