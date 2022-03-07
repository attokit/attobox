<?php
/*
 * Attobox Framework / Response Exporter
 * export html
 */

namespace Atto\Box\response\exporter;

use Atto\Box\response\Exporter;
use Atto\Box\Response;

class Html extends Exporter
{
    public $contentType = "text/html; charset=utf-8";

    //准备输出的数据
    public function prepare()
    {
        if (is_associate($this->data["data"])) {
            $this->content = a2j($this->data["data"]);
        } else {
            $this->content = str($this->data["data"]);
        }
        return $this;
    }


}