<?php

/**
 * Attobox Framework / Module Resource
 * route "src"
 * response to url "//host/src/foo/bar/..."
 */

namespace Atto\Box\route;

use Atto\Box\Resource;

class Src extends Base
{
    //route info
    public $intr = "Resource模块路由";       //路由说明，子类覆盖
    public $name = "Src";                   //路由名称，子类覆盖
    public $appname = "";                   //App路由所属App名称，子类覆盖
    public $key = "Atto/Box/route/Src";     //路由调用路径

    //此路由是否 不受 WEB_PAUSE 设置 影响
    public $unpause = true;


    /**
     * Resource 模块
     * 资源调用统一入口
     * 直接输出资源
     * @return void
     */
    public function defaultMethod()
    {
        $args = func_get_args();
        $resource = Resource::create($args);

        //未找到资源
        if (is_null($resource)) {
            return [
                "status" => 404
            ];
        }

        //输出资源
        $resource->export();
        //var_dump($resource->info());
        
        exit;
    }
    
}