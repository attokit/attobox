<?php

/**
 * Attobox Framework / Module Resource
 * Resource Exporter
 * 
 * export audio file
 */

namespace Atto\Box\resource\exporter;

use Atto\Box\resource\Exporter;
use Atto\Box\Response;
use Atto\Box\resource\Mime;
use Atto\Box\resource\Stream;

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