<?php

/**
 * Attobox Framework / Module Resource
 * Resource Extension
 * 
 * Audio
 * 
 * temporary support: mp3
 */

namespace Atto\Box\resource;

use Atto\Box\Resource;
//use Atto\Box\Response;
//use Atto\Box\resource\Mime;
use Atto\Box\resource\Stream;

class Audio extends Resource
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
     * export audio using Stream
     * @return void exit
     */
    public function export($params = [])
    {
        $stream = Stream::create($this->realPath);
        $stream->init();
        exit;
    }

}