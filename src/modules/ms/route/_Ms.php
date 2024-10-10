<?php

/**
 * Attobox Framework / Route
 * route/ms
 * 任意 *ms 管理器 访问入口
 * 
 * 通过 URI 访问管理器各子页面，例如：
 *      [host]/ms/pms           --> 访问 pms/index 页面
 *      [host]/ms/pms/foo/bar   --> 访问 pms/foo/bar 页面
 * 
 * 管理器子页面均为 *.vue 组件，保存路径： [webroot]/assets/ms/[*ms]/  如： 
 *      [host]/ms/pms           --> [webroot]/assets/ms/pms/index.vue
 *      [host]/ms/pms/foo/bar   --> [webroot]/assets/ms/pms/foo-bar.vue
 */

namespace Atto\Box\route;

use Atto\Box\Route;
use Atto\Box\Ms as xMs;
use Atto\Box\Request;
use Atto\Box\Uac;
use Atto\Box\Response;

class Ms extends Route
{
    //route info
    public $intr = "*ms管理器访问入口";   //路由说明，子类覆盖
    public $name = "ms";                //路由名称，子类覆盖
    public $appname = "";               //App路由所属App名称，子类覆盖
    public $key = "Atto/Box/route/Ms";  //路由调用路径

    //需要 UAC 权限管理
    public $uac = false;

    //ms 管理器类型列表
    protected $msns = ["pms", "oms"];

    // ms 管理器 name
    public $msn = null;

    // ms 管理器子页面组件信息
    public $comp = null;

    /**
     * 获取 msn
     * @param String $args URI
     * @return Array 剩余的 $args
     */
    protected function getMsn(...$args)
    {
        if (!is_null($this->msn)) return $args;
        if (empty($args) || substr($args[0],-2)!="ms") {
            $this->msn = $this->msns[0];
        } else {
            $this->msn = array_shift($args);
        }
        return $args;
    }

    /**
     * 获取 *ms 管理器入口页面
     * 页面路径： [webroot]/page/ms/$msn.php
     * @return String file path
     */
    protected function getMsPage()
    {
        $msn = is_null($this->msn) ? $this->msns[0] : $this->msn;
        return path_find("root/page/ms/$msn.php");
    }

    /**
     * 根据 URI 获取 *ms 管理器子页面 vue 文件信息
     * @param String $args
     * @return Array vue 文件信息
     */
    protected function getMsFile(...$args)
    {
        $fn = empty($args) ? "index" : implode("-",$args);
        $msn = is_null($this->msn) ? $this->msns[0] : $this->msn;
        return xMs::load("$msn-$fn");
    }



    /**
     * 入口方法
     */

    /**
     * 管理器主页面
     * [host]/ms/xms[/foo/bar]  -->  打开 xms 管理器，并加载子页面 foo-bar.vue
     */
    public function defaultMethod(...$args)
    {
        $args = $this->getMsn(...$args);
        $page = $this->getMsPage();
        $comp = $this->getMsFile(...$args);
        //检查用户是否拥有访问此管理器页面的权限
        $popr = "menu-".$comp->profile()["name"];
        if (Uac::grant($popr)==true) {
            Response::page($page, [
                "manager" => $comp
            ]);
        } else {
            /*Response::errpage([
                "title" => "无权访问",
                "msg" => "你没有权限，不能访问此页面"
            ]);*/
            trigger_error("uac/denied::".$popr, E_USER_ERROR);
        }
    }

    /**
     * ms 管理器 api
     * 基类实现
     */
    /*public function api(...$args)
    {
        
    }*/

    /**
     * api 方法
     * <%*ms api 测试%>
     */
    protected function apiTester()
    {
        
    }

    public function tst()
    {

        $ms = xMs::load("pms-admin-usr");
        var_dump($ms->src->script);
    }

}