<?php

/**
 * Attobox Framework / Module Resource
 * Resource Creator
 * 
 * Local resource create by route methods
 */

namespace Atto\Box\resource\creator;

use Atto\Box\resource\Creator;
use Atto\Box\request\Curl;
use Atto\Box\resource\Mime;

class Export extends Creator
{
    //根据资源的 Mime::processableType 读取内容，保存到content
    public function create()
    {
        $url = $this->resource->realPath;
        $this->resource->content = Curl::get($url);
    }


    /*
     *  静态工具
     */

    //以纯文本形式输出文件内容
    public static function plainFile($content = "")
    {
        
    }
}