<?php

/**
 * CPHP框架  Resource 模块
 * 资源内容生成器
 * 本地动态资源，通过 require 文件 动态生成内容
 */

namespace CPHP\resource\creator;

use CPHP\resource\Creator;

class Required extends Creator
{
    //根据资源的 Mime::processableType 读取内容，保存到content
    public function create()
    {
        $file = $this->resource->realPath;
        require($file);
        $this->resource->content = ob_get_contents();
        ob_clean();
    }


    /*
     *  静态工具
     */

    
}