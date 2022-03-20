<?php

/**
 * Attobox Framework / Route
 * route/Base
 */

namespace Atto\Box\route;

use Atto\Box\Route;
use Atto\Box\Response;
use Atto\Box\request\Url;

class Base extends Route
{
    //route info
    public $intr = "Attobox基础路由";   //路由说明，子类覆盖
    public $name = "Base";          //路由名称，子类覆盖
    public $appname = "";           //App路由所属App名称，子类覆盖
    public $key = "Atto/Box/route/Base";    //路由调用路径

    /**
     * 默认路由方法
     */
    public function defaultMethod()
    {
        //var_dump("\\Atto\\Box\\route\\Base::defaultMethod()");
        //var_dump(func_get_args());
        trigger_error("test::foo,bar", E_USER_ERROR);
    }

    /**
     * 空路由，自动跳转到 domain/index
     * @return void
     */
    public function emptyMethod()
    {
        $url = Url::mk("index");
        Response::redirect($url->full);
    }

    /**
     * 直接调用本地 php 页面
     * @return void
     */
    public function page($page = "")
    {
        $page = path_find($page);
        if (empty($page)) {
            return [
                "status" => 404
            ];
        }
        return [
            "data" => $page,
            "format" => "page"
        ];
    }
}