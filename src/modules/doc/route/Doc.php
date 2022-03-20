<?php

/**
 * Attobox Framework / Module Doc
 * route for https://host/doc/...
 */

namespace Atto\Box\route;

use Atto\Box\Doc as DocCls;
use Atto\Box\Response;

class Doc extends Base
{
    //route info
    public $intr = "Doc模块路由";       //路由说明，子类覆盖
    public $name = "Doc";                   //路由名称，子类覆盖
    public $appname = "";                   //App路由所属App名称，子类覆盖
    public $key = "Atto/Box/route/Doc";     //路由调用路径

    //此路由是否 不受 WEB_PAUSE 设置 影响
    public $unpause = false;

    /**
     * defaultMethod
     */
    public function defaultMethod()
    {
        $args = func_get_args();
        $docarr = DocCls::parse($args);
        if (!is_null($docarr)) {
            $doc = DocCls::create($docarr[0]);
            if (!is_null($doc)) {
                return [
                    "data" => path_find("module/doc/page.php"),
                    "format" => "page",
                    "doc" => [
                        "instance" => $doc,
                        "content" => $doc->getPage($docarr[1])
                    ]
                ];
            }
        }
        return [
            "status" => 404
        ];
        exit;
    }
}