<?php

/**
 * Attobox Framework / Module Resource
 * Resource Extension
 * 
 * unsupported file, to download
 * mime = application/octet-stream
 */

namespace Atto\Box\resource;

use Atto\Box\Resource;
use Atto\Box\Response;
use Atto\Box\resource\Stream;

class Download extends Resource
{
    /**
     * @override getContent
     * @return null
     */
    protected function getContent(...$args)
    {
        return null;
    }

    /**
     * @override export
     * export video using Stream
     * @return void exit
     */
    public function export($params = [])
    {
        $stream = Stream::create($this->realPath);
        $stream->down();
        exit;
    }
}