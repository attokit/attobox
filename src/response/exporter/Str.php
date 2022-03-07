<?php
/*
 * Attobox Framework / Response Exporter
 * export by echo string
 */

namespace Atto\Box\response\exporter;

use Atto\Box\response\Exporter;

class Str extends Exporter
{

    public $contentType = "text/plain; charset=utf-8";

    //准备输出的数据
    public function prepare()
    {
        $this->content = str($this->data["data"]);
        return $this;
    }

}