<?php

/**
 * CPHP框架  Resource 模块
 * 资源输出器
 * 输出不支持的文件，mime = application/octet-stream
 */

namespace CPHP\resource\exporter;

use CPHP\resource\Exporter;
use CPHP\Response;
use CPHP\resource\Stream as rStream;

class Stream extends Exporter
{
    //改写export方法
    public function export()
    {
        $stream = rStream::create($this->resource->realPath);
        $stream->down();
        //$this->header()->sent();
        //echo $this->resource->content;
        exit;
    }
}