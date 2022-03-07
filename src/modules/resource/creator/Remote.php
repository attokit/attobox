<?php

/**
 * CPHP框架  Resource 模块
 * 资源内容生成器
 * 远程资源，cURL读取远程内容
 */

namespace CPHP\resource\creator;

use CPHP\resource\Creator;
use CPHP\request\Curl;
use CPHP\resource\Mime;

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