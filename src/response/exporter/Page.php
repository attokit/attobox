<?php
/*
 * Attobox Framework / Response Exporter
 * export by require php page in page dir
 */

namespace Atto\Box\response\exporter;

use Atto\Box\response\Exporter;
use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Box\Router;

class Page extends Exporter
{
    public $contentType = "text/html; charset=utf-8";

    //准备输出的数据
    public function prepare()
    {
        $page = $this->data["data"];
        if (!is_notempty_str($page)) {
            var_dump($page);
            exit;
        }
        if (!file_exists($page)) {
            http_response_code(404);
            exit;
        }
        
        $_Request = Request::current();
        $_Response = $this->response;
        $_Router = Router::current();
        $_Params = [];

        $vars = get_object_vars($this->response);
        $dps = Response::getDefaultParams();
        foreach ($vars as $k => $v) {
            if (!isset($dps[$k])) {
                $_Params[$k] = $v;
                $$k = $v;
            }
        }

        //调用页面
        require($page);
        //从 输出缓冲区 中获取内容
        $this->content = ob_get_contents();
        //清空缓冲区
        ob_clean();

        return $this;
    }
    
}