<?php
/*
 * Attobox Framework / Response Exporter
 * export json
 */

namespace Atto\Box\response\exporter;

use Atto\Box\response\Exporter;

class Json extends Exporter
{

    public $contentType = "application/json; charset=utf-8";

    //准备输出的数据
    public function prepare()
    {
        if ($this->response->exportOnlyData) {
            $this->content = a2j($this->data["data"]);
        } else {
            $this->content = a2j($this->data);
        }
    }

}