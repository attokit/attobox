<?php

/**
 * Attobox Framework / Route
 * route/Base
 */

namespace Atto\Box\route;

use Atto\Box\Route;
use Atto\Box\Request;
use Atto\Box\request\Url;
use Atto\Box\request\Curl;
use Atto\Box\Uac;
use Atto\Box\Response;
use Atto\Box\Db;
use Atto\Box\db\Dsn;
use Atto\Box\db\Manager as DbManager;
use Atto\Box\resource\Mime;

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
        //trigger_error("test::foo,bar", E_USER_ERROR);
        var_dump($this->routerCallParams);
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
    public function page($page = "", $p = [])
    {
        $page = path_find($page);
        if (empty($page)) {
            return [
                "status" => 404
            ];
        }
        return arr_extend([
            "data" => $page,
            "format" => "page"
        ], $p);
    }

    /**
     * 默认显示登录界面的方法，
     * !!! 子路由必须覆盖
     * @return void
     */
    public function loginPage($extra = [])
    {
        
    }

    /**
     * 用户登录入口
     * [host]/login[/...]
     * 调用 Uac->usrLogin() 方法响应登录动作
     * @return void
     */
    public function login(...$args)
    {
        if (UAC_CTRL) { //如果开启 UAC 权限控制器，则由 Uac->login() 方法接管登录动作
            return Uac::$current->usrLogin(...$args);
        } else {    //手动处理登录动作，可由子类路由实现
            //子类路由 Web 跟路由实现逻辑
            //...

            return true;
        }
    }

    /**
     * 用户登出入口
     * [host]/logout[/...]
     * 调用 Uac->usrLogout() 方法响应登出动作
     * @return void
     */
    public function logout(...$args)
    {
        if (UAC_CTRL) { //如果开启 UAC 权限控制器，则由 Uac->logout() 方法接管登出动作
            return Uac::$current->usrLogout(...$args);
        } else {    //手动处理登出动作，可由子类路由实现
            //子类路由 Web 跟路由实现逻辑
            //...
            
            return true;
        }
    }



    /**
     * attovue Vue组件相关
     */
    //获取所有 atto-** 组件，返回 js ，用于 前端自动加载组件
    public function vuecomps($package="")
    {
        $package = !is_notempty_str($package) ? "" : "/$package";
        $cpath = "vue/components".$package;
        $comps = [];
        $dir = path_find("box/assets/$cpath", ["inDir"=>"", "checkDir"=>true]);
        if (is_dir($dir)) {
            $dh = opendir($dir);
            while (($f = readdir($dh))!==false) {
                if ($f=="." || $f==".." || is_dir($dir.DS.$f) || strpos($f, ".vue")===false) continue;
                $fn = str_replace(".vue","",$f);
                $comps[$fn] = "//cgy.design/src/atto/$cpath/$fn.vue?export=js&name=$fn";
            }
            closedir($dh);
        }
        $export = Request::get("export", null);
        if (empty($export)) return $comps;
        if ($export="js") {
            $js = "export default {";
            foreach ($comps as $compn => $compu) {
                $js .= "'".$compn."':'".$compu."',";
            }
            $js .= "}";
            Mime::header("js");
            Response::headersSent();
            echo $js;
            exit;
        }
    }
    //获取所有 vue mixins，返回 js，用于前端加载
    public function vuemixins($package="")
    {
        $package = !is_notempty_str($package) ? "" : "/$package";
        $mpath = "vue/mixins".$package;
        $mixins = [];
        $dir = path_find("box/assets/$mpath", ["inDir"=>"", "checkDir"=>true]);
        if (is_dir($dir)) {
            $dh = opendir($dir);
            while (($f = readdir($dh))!==false) {
                if ($f=="." || $f==".." || is_dir($dir.DS.$f) || strpos($f, ".js")===false) continue;
                $fn = str_replace(".js","",$f);
                $mixins[$fn] = "//cgy.design/src/atto/$mpath/$fn.js";
            }
            closedir($dh);
        }
        $export = Request::get("export", null);
        if (empty($export)) return $mixins;
        if ($export="js") {
            $js = "export default {";
            foreach ($mixins as $mn => $mu) {
                $js .= "'".$mn."':'".$mu."',";
            }
            $js .= "}";
            Mime::header("js");
            Response::headersSent();
            echo $js;
            exit;
        }
    }

    public function ttt()
    {
        $c = cls("WxApp");
        var_dump($c);
    }




    /**
     * tools
     */

    /**
     * 调用 Utils 方法
     */
    public function utils(...$args)
    {
        if (empty($args)) Response::code(404);
        $func = array_shift($args);
        if (function_exists($func)) {
            return $func(...$args);
        }
        Response::code(404);
        exit;
    }

    public function timestamp($timestr = null)
    {
        if (is_notempty_str($timestr)) {
            $t = strtotime($timestr);
            return $t;
        } else {
            return time();
        }
    }

    public function clss()
    {
        $apis = self::getApis();
        var_dump($apis);
    }

    public function calctime()
    {
        $dstr = "2023-07-21 14:38:30";
        $dh = 19;
        $line = calcWorkingTimestamp(
            strtotime($dstr),
            $dh*60*60
        );
        echo $dstr." + ".$dh."h = ";
        var_dump(date("Y-m-d H:i:s", $line));
    }

    public function base64()
    {
        $query = Request::input("json");
        $subject = $query["subject"];
        return [
            "base64" => base64_encode($subject)
        ];
    }

    /*public function imgview(...$args)
    {
        Response::page("/data/wwwroot/img.cgy.design/page/viewimg.php", [
            "src" => "//img.cgy.design/".implode("/", $args)
        ]);
    }*/



    /**
     * private tools
     */

    /**
     * 根据 route 的类名，得出 dir path
     */
    protected function getPath()
    {
        $clsn = get_class($this);
        $clsn = str_replace("\\","/", $clsn);
        $clsn = str_replace("Atto/Box/","",$clsn);
        $clsn = str_replace("route/".$this->name,"",$clsn);
        $clsn = trim($clsn, "/");
        return $clsn;
    }

    /**
     * redirect to url
     */
    protected function redirectTo($u = "/")
    {
        $u = Url::mk($u);
        Response::redirect($u->full);
        exit;
    }

}