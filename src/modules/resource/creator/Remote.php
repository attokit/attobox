<?php

/**
 * Attobox Framework / Module Resource
 * Resource Creator
 * 
 * Remote resource, get content by using cURL tool
 */

namespace Atto\Box\resource\creator;

use Atto\Box\resource\Creator;
use Atto\Box\request\Curl;
use Atto\Box\resource\Mime;

class Remote extends Creator
{
    //根据资源的 Mime::processableType 读取内容，保存到content
    public function create()
    {
        $url = $this->resource->realPath;
        $this->resource->content = Curl::get($url);
        //var_dump($this->resource->content);
    }


    /*
     *  静态工具
     */
}