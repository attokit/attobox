<?php

/**
 * Attobox Framework / Module Doc
 * route for https://host/doc/...
 */

namespace Atto\Box\route;

use Atto\Box\Doc as DocCls;
use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Box\doc\parser\Md;

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
        /*$args = func_get_args();
        if (empty($args)) {
            return DocCls::index();
        }
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
        exit;*/

        $args = func_get_args();
        $dir = implode("/", $args);
        $doc = DocCls::create($dir);
        if (!is_null($doc)) {
            return [
                "data" => path_find("module/doc/page.php"),
                "format" => "page",
                "doc" => $doc
            ];
        }
        return [
            "status" => 404
        ];

        exit;
    }

    /**
     * get doc file
     */
    public function file(...$args)
    {
        $doc = DocCls::create(implode("/", $args));
        if (!is_null($doc)) {
            $fn = Request::get("file");
            $fp = $doc->file($fn);
            if (!is_null($fp)) {
                $md = new Md(file_get_contents($fp));
                return $md->parse();
            } else {
                return "$fn 未找到！";
            }
        }
        Response::code(404);
    }
}