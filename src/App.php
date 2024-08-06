<?php
/*
 *  Attobox Framework / App base class
 * 
 */

namespace Atto\Box;

use Atto\Box\traits\staticCreate;
use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Box\Uac;

class App 
{
    //引入trait
    use staticCreate;

    //app info
    public $intr = "";  //app说明，子类覆盖
    public $name = "";  //app名称，子类覆盖
    public $key = "";   //app调用路径

    //此 app 是否需要 UAC 权限控制，
    //如仅部分方法需要控制权限，设为 false，在需要控制权限的方法内部 if (Uac::grant("$app->key/method")===true) { 方法逻辑 }
    //如所有方法都需要控制权限，设为 true
    public $uac = false;

    //缓存 通过 router->seek 得到的本 app 的调用参数，仅用于 debug
    public $routerCallParams = [];

    //构造
    public function __construct()
    {
        //如果此 app 所有方法都需要控制权限，首先检查权限
        if (Uac::required() && $this->uac===true/* && Uac::requiredAndReady()*/) {
            $opr = "app-".strtolower($this->name);
            if (!Uac::isLogin()) {
                return $this->loginPage();
            } else if (Uac::grant($opr)!==true) {
                trigger_error("auth::无操作权限 [ OPR= $opr ]", E_USER_ERROR);
                return false;
            }
        }
        //初始化动作
        $this->init();
    }

    //init，子类覆盖
    protected function init()
    {
        //初始化动作，在构造后执行，子类覆盖

        return $this;   //要返回自身
    }

    //path
    public function path($path, $params = [])
    {
        $path = str_replace(["/", "\\"], DS, $path);
        return APP_PATH.DS.strtolower($this->name).DS.$path;
    }

    //读取 app/[appname]/library/config.php 中指定的预设参数
    //这些参数可以通过 常量 APP_[APPNAME]_[KEY] 访问
    //这些预设参数应在 [attobox]/modules/uac/config.php
    public function conf()
    {
        $conf = "";
    }

    //默认路由方法
    public function defaultRoute(...$args)
    {
        /**
         * App 类默认入口
         * 子类必须实现各自的方法
         */

        Response::code(404);

    }

    //访问 page/foo.php
    public function p($page)
    {
        $pg = $this->path("page/$page".EXT);
        if (file_exists($pg)) Response::page($pg);
        Response::code(404);
    }

    //根据 key 获取 app 下级类全称
    public function cls($key = "")
    {
        if (!is_notempty_str($key)) return null;
        $key = trim($key, "/");
        return cls("App/".$thid->name."/".$key);
    }

    /**
     * 劫持路由
     * 通过劫持方式调用其他路由方法
     * @return Mixed
     */
    public function hijack($args)
    {
        $route = Router::seek($args);
        $emptyRoutes = [
            "\\Atto\\Box\\route\\Base",
            "\\Atto\\Box\\route\\Web"
        ];
        if (in_array($route[0], $emptyRoutes) && $route[1]=="defaultMethod") {
            //未查找到有效 route
            Response::code(404);
        } else {
            //var_dump($route);
            //执行劫持到的 Route 方法
            //return Router::exec(...$route);

            $method = $route[1];
            $args = $route[2];
            $route = $route[0];
            if (!class_exists($route)) {
                return [
                    "status" => 500,
                    "data" => "undefined route"
                ];
            }
            $callParams = [
                "route" => $route,
                "method" => $method,
                "args" => $args
            ];
            $route = new $route();
            //设置 route 所属 appname
            $route->appname = $this->name;
            //缓存 router call params
            $route->routerCallParams = $callParams;
            //执行路由方法
            return call_user_func_array([$route,$method],$args);
        }
    }



    /**
     * 为 app 增加 usr 登录以及 uac 权限控制
     * 
     */

    /**
     * 用户操作统一入口 api
     * url:     //[host]/[app]/uac
     *                              /login          执行登录，用户前台输入账号密码，提交到此
     *                              /login/scan     执行登录，用户通过扫码登录，数据提交到此
     *                              /logout         执行登出
     *                              /oprs           返回用户权限列表
     *                              /nav            返回用户有权访问的 nav 列表
     */
    /*public function uac(...$args)
    {
        
    }*/

    /**
     * 显示登录界面
     * 通过 Uac->jumpToUsrLogin() 方法调用
     * 当全局启用 UAC_CTRL 时，需要用户登录的时候 检查 Uac::grant(opr) 方法，未登录则会跳转到此路由
     * 需要在 [webroot]/page  or  app/[appname]/page  创建 login.php 登录页面
     * 如果在上述位置未找到 login.php 则会显示 vendor/attokit/attobox/page/login.php 此页面通常会出错
     * @param Array $extra 在登录界面显示的额外信息
     * @return void
     */
    public function loginPage($extra=[])
    {
        $app = strtolower($this->name);
        //指定 Uac 用户数据库信息，并写入 session
        //用户数据库通常在 app/[appname]/library/config.php 中指定
        //当出现非 app 路由访问的情况时，通过 Request::current()->appname 无法获取 appname
        //因此指定默认的 用户数据库 为 app/[appname]/usr
        $uacc = Uac::getAppUacConfig();
        $uac_db = $uacc["uac_db"] ?? "app/".$app."/usr";
        session_set("uac_db", $uac_db);
        Uac::$current->initUsrDb();
        //var_dump(session_get("uac_db"));

        //下发登录界面，登录界面路径通常在 app config 中定义
        //默认为 app/[appname]/page/login.php
        $lgf = $uacc["uac_login"] ?? "app/$app/page/login.php";
        $lgf = path_find($lgf);
        if (empty($lgf)) {
            //如果 app 下没有 login page，则使用 attobox 默认的
            //$lgf = path_find("box/page/login.php");

            //如果 app 下没有 login page 则返回 未登录错误，由前端决定如何登录
            trigger_error("uac/notlogin", E_USER_ERROR);
            exit;
        }
        if (empty($lgf)) Response::code(404);
        
        //跳转登录界面
        Response::page($lgf, arr_extend([
            "from" => Request::$current->url->full
        ], $extra));
        
    }








    /*
     *  static
     */

    //判断是否存在此app
    public static function has($appname = "")
    {
        if (!is_notempty_str($appname)) return false;
        $ap = APP_PATH.DS.strtolower($appname);
        if (!is_dir($ap)) return false;
        $acls = cls("App/".ucfirst($appname));
        return !is_null($acls);
    }


    //根据 key 生成 app 实例
    public static function load($key = "", ...$params)
    {
        if (!is_notempty_str($key)) return null;
        $cls = self::_class($key);
        if (!is_null($cls)) return $cls::create(...$params);
        return null;
    }

    //根据 appname 解析 app 类全称
    public static function _class($appname = "")
    {
        if (!is_notempty_str($appname)) return null;
        if (is_lower($appname)) $appname = ucfirst($appname);
        $cls = CP::class($appname);
        //var_dump($cls);
        return is_subclass_of($cls, self::class) ? $cls : null;
    }

    /**
     * 如果当前 route 解析为 app 则将解析得到的 app 类实例化到 Request::$current
     * @param String $app route 解析获得的 app name
     * @return App 实例
     */
    public static function useApp($app = null)
    {
        if (!is_null($app) && !empty($app)) {
            $appcls = cls("app/".ucfirst(strtolower($app)));
            if (!empty($appcls)) {

            }
        }
    }

    

}