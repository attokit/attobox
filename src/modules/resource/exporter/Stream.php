<?php

/**
 * Attobox Framework / Module Resource
 * Resource Exporter
 * 
 * export unsupported file, to download
 * mime = application/octet-stream
 */

namespace Atto\Box\resource\exporter;

use Atto\Box\resource\Exporter;
use Atto\Box\Response;
use Atto\Box\resource\Stream as rStream;

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