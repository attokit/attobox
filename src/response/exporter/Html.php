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
        $d = $this->data["data"];
        if (!empty($d) && isset($d["type"]) && $d["type"]=="Error") {
            //输出错误提示
            $error = $d;
            //调用 box/page/error.php
            require(path_find("box/page/error.php"));
            //从 输出缓冲区 中获取内容
            $this->content = ob_get_contents();
            //清空缓冲区
            ob_clean();
        } else {
            if (empty($d)) {
                $this->content = "";
            } else {
                if (is_associate($d)) {
                    $this->content = a2j($d);
                } else {
                    $this->content = str($d);
                }
            }
            //var_dump($this->content);
        }
        return $this;
    }


}