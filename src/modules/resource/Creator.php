<?php

/**
 * CPHP框架  Resource 模块
 * 资源内容生成器 creator
 * 基类
 */

namespace CPHP\resource;

class Creator
{
    //关联的resource对象
    public $resource = null;

    public function __construct($resource = null)
    {
        $this->resource = $resource;
    }

    //create
    public function create()
    {
        $this->resource->content = file_get_contents($this->resource->realPath);
    }
}