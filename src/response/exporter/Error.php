<?php
/*
 *  CPHP框架  Exporter数据输出类
 *  输出error数据
 */

namespace CGY\CPhp\response\exporter;

use CGY\CPhp\response\Exporter;
use CGY\CPhp\Response;

class Error extends Exporter
{

    public $contentType = "application/json; charset=utf-8";

    //准备输出的数据
    public function prepare()
    {
        $this->_prepare();
        $err = array_pop($this->data["errors"]);
        $this->data["error"] = true;
        foreach ($err as $k => $v) {
            $this->data[$k] = $v;
        }
        $this->data["data"] = $err["title"]." ".$err["msg"]." in ".$err["file"]." at line ".$err["line"];
    }

    //准备要输出的字符串
    protected function parse()
    {
        //$this->content = a2j($this->data);
    }

    //改写export方法
    public function export()
    {
        $format = Response::exportFormat();
        $exp = Response::exporterClass($format);
        $exporter = new $exp($this->response);
        $exporter->prepare();
        $exporter->data = $this->data;
        $exporter->export();
        exit;
    }

}