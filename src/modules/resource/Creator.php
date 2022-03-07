<?php

/**
 * Attobox Framework / Module Resource
 * Resource Creator
 * base class
 */

namespace Atto\Box\resource;

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