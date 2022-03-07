<?php

/**
 * Attobox Framework / Router 
 * seeker for Route
 */

namespace Atto\Box;

use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Box\Route;
use Atto\Box\App;
use Atto\Box\request\Url;
use Atto\Box\traits\staticCurrent;

class Router
{
    //引入trait
    use staticCurrent;

    /**
     * current
     */
    public static $current = null;

    //current request
    public $req = null;

    //route参数
    public $route = "\\Atto\\Box\\route\\Base";
    public $method = "defaultMethod";
    public $args = [];
    public $unpause = false;

    //不受 WEB_PAUSE 影响的 route 基类
    //protected $unPausedRoutes = ["\\Atto\\Box\\route\\Src"];


    /**
     * 构造
     */
    public function __construct()
    {
        //$this->req = empty($req) || !($req instanceof Request) ? Request::current() : $req;
        $this->req = Request::current();
        $route = self::seek($this->req->url->path);
        if (empty($route)) {
            $this->route = null;
        } else {
            $this->route = $route[0];
            $this->method = $route[1];
            $this->args = $route[2];
        }
        $this->unpause = $this->pauseCheck();
    }

    /**
     * 调用当前 route，生成处理结果，用于生成 response
     * @return Array
     */
    public function run()
    {
        return self::exec($this->route, $this->method, $this->args);
    }

    /**
     * 判断当前 route 是否受 WEB_PAUSE 预设影响
     */
    protected function pauseCheck()
    {
        $route = $this->route;
        $rf = new \ReflectionClass($route);
        $pt = $rf->getDefaultProperties();
        return is_bool($pt["unpause"]) ? $pt["unpause"] : false;
    }

    /**
     * 获取当前 route 信息
     * @return Array
     */
    public function info()
    {
        return [
            "route" => $this->route,
            "method" => $this->method,
            "args" => $this->args,
            "unpause" => $this->unpause
        ];
    }


    /**
     * 根据 request 解析获取 route 类
     * 同时返回 route方法 以及 参数
     * @return Array [route, method, [args]]
     */
    public static function seek($path = [])
    {
        $r = cls("route/Base");
        $m = "defaultMethod";
        $a = [];

        if (empty($path)) {     //空路由
            return [$r, "emptyMethod", []];
        }
        
        if (App::has($path[0])) {   //app route
            $appn = strtolower(array_shift($path));
            $appcls = cls("App/".ucfirst($appn));    //"\\CAPP\\".ucfirst($appn);
            $clspre = clspre("App/".$appn."/route");    //"\\CAPP\\".$appn."\\route\\";
            if (empty($path)) {
                $cls = $clspre."Base";
                if (!class_exists($cls)) {
                    return [$appcls, "defaultRoute", $a];
                }
                return [$cls, $m, $a];
            } else {
                $cls = $clspre.ucfirst(strtolower($path[0]));
                $page = APP_PATH.DS.$appn.DS."page".DS.implode(DS,$path).EXT;
                if (class_exists($cls)) {
                    array_shift($path);
                    return array_merge([$cls], self::seekMethod($cls, $path));
                } else if (method_exists($appcls, $path[0])) {
                    $m = array_shift($path);
                    return [$appcls, $m, $path];
                } else if (file_exists($page)) {
                    //return ["\\CPHP\\route\\Base", "page", [$page]];
                    return [cls("route/Base"), "page", [$page]];
                } else {
                    $cls = $clspre."Base";
                    if (!class_exists($cls)) {
                        return [$appcls, "defaultRoute", $path];
                    } else {
                        return array_merge([$cls], self::seekMethod($cls, $path));
                    }
                }
            }
        } else {    //cphp route
            $clspre = clspre("route"); //"\\CPHP\\route\\";
            if (empty($path)) {
                $cls = $clspre."Base";
                return [$cls, $m, $a];
            } else {
                $cls = $clspre.ucfirst(strtolower($path[0]));
                $page = ROOT_PATH.DS."page".DS.implode(DS, $path).EXT;
                if (class_exists($cls)) {
                    array_shift($path);
                    return array_merge([$cls], self::seekMethod($cls, $path));
                } else if (file_exists($page)) {
                    //return ["\\CPHP\\route\\Base", "page", [$page]];
                    return [cls("route/Base"), "page", [$page]];
                } else {
                    $cls = $clspre."Base";
                    return array_merge([$cls], self::seekMethod($cls, $path));
                }
            }
        }
    }

    /**
     * 根据 path 在已存在的 route 类中查找 method
     * 返回找到的 method 以及 args
     * @return Array [method, [args]]
     */
    public static function seekMethod($route, $path = [])
    {
        if (is_notempty_arr($path)) {
            if (method_exists($route, $path[0])) {
                $m = array_shift($path);
            } else {
                $m = "defaultMethod";
            }
            $a = $path;
        } else {
            $m = "defaultMethod";
            $a = [];
        }
        return [$m, $a];
    }

    /**
     * 调用指定的 route
     * 返回值用于生成 response
     * @return Array
     */
    public static function exec($route, $method = "defaultMethod", $args = [])
    {
        if (!class_exists($route)) {
            return [
                "status" => 500,
                "data" => "undefined route"
            ];
        }
        $route = new $route();
        //if($route->allNeedAuthority === true) $route->authVerify();
        return call_user_func_array([$route,$method],$args);
    }

    /**
     * 解析 url 获取 route，执行 route
     * 返回值用于生成新的 response
     * @return Array
     */
    public static function url($url = "")
    {
        $url = Url::mk($url);
        $routeParams = self::seek($url->path);
        if (empty($routeParams)) return [];
        return self::exec(...$routeParams);
    }

    
}