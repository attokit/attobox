<?php

/**
 * CPHP框架  Resource 模块
 * 资源输出器 Exporter
 * 基类
 */

namespace CPHP\resource;

use CPHP\Response;

class Exporter
{
    //关联的资源对象
    public $resource = null;

    public function __construct($resource = null)
    {
        $this->resource = $resource;
    }


    //export
    public function export()
    {
        $this->header()->sent();
        echo $this->resource->content;
        exit;
    }

    //根据 resource->rawExt 输出 header
    protected function header()
    {
        $res = $this->resource;
        Mime::header($res->rawExt, $res->rawBasename);
        return $this;
    }

    //输出 Response::$current->headers
    protected function sent()
    {
        Response::headersSent();
        return $this;
    }
}