<?php

/**
 * Attobox Framework / Module Resource
 * Resource Creator
 * 
 * Local dynamic resource, created by requiring local php file
 */

namespace Atto\Box\resource\creator;

use Atto\Box\resource\Creator;

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