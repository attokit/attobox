<?php
/*
 * Attobox Framework / Response Exporter
 * export through var_dump method
 */

namespace Atto\Box\response\exporter;

use Atto\Box\response\Exporter;

class Dump extends Exporter
{
    //准备输出的数据
    public function prepare()
    {
        return $this;
    }

    //改写 parent->export() 方法
    public function export()
    {
        print("<pre>".print_r($this->data, true)."</pre>");
        exit;
    }
}