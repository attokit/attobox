<?php

/**
 * Attobox Framework / Module Resource
 * Resource Extension
 * 
 * Video
 * 
 * temporary support: mp4
 */

namespace Atto\Box\resource;

use Atto\Box\Resource;
use Atto\Box\resource\Stream;

class Video extends Resource
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
        $stream->start();
        exit;
    }

}