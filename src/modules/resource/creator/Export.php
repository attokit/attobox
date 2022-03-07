<?php

/**
 * CPHP框架  Resource 模块
 * 资源内容生成器
 * 本地动态资源，由 route 动态生成内容
 */

namespace CPHP\resource\creator;

use CPHP\resource\Creator;
use CPHP\request\Curl;
use CPHP\resource\Mime;

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