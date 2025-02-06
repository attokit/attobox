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

        return isset($pt["unpause"]) ? (is_bool($pt["unpause"]) ? $pt["unpause"] : false) : false;
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
     * 是否 app route
     * @return Bool
     */
    public function isAppRoute()
    {
        $route = strtolower($this->route);
        return strpos($route, "\\atto\\box\\app\\")!==false;
    }

    /**
     * 从 route 中获取 app name （如果存在）
     * @return String app name  or  null
     */
    public function getAppFromRoute()
    {
        if (!$this->isAppRoute()) return null;
        $route = strtolower($this->route);
        return explode("\\", explode("\\app\\", $route)[1])[0];
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
            if (class_exists(cls("route/Web"))) return [cls("route/Web"), "emptyMethod", []];
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
                $cls_web = $clspre."Web";
                $cls_base = $clspre."Base";
                $page = ROOT_PATH.DS."page".DS.implode(DS, $path).EXT;
                if (class_exists($cls)) {
                    array_shift($path);
                    return array_merge([$cls], self::seekMethod($cls, $path));
                } 
                if (class_exists($cls_web)) {
                    $rcls = new \ReflectionClass($cls_web);
                    if ($rcls->hasMethod($path[0])) {
                        return [$cls_web, array_shift($path), $path];
                    }
                }
                if (class_exists($cls_base)) {
                    $rcls = new \ReflectionClass($cls_base);
                    if ($rcls->hasMethod($path[0])) {
                        return [$cls_base, array_shift($path), $path];
                    }
                }
                if (file_exists($page)) {
                    //return ["\\CPHP\\route\\Base", "page", [$page]];
                    if (!is_null(cls("route/Web"))) {
                        return [cls("route/Web"), "page", [$page]];
                    }
                    return [cls("route/Base"), "page", [$page]];
                }
                if (!class_exists($cls_web)) {
                    return array_merge([$cls_base], self::seekMethod($cls_base, $path));
                }
                return array_merge([$cls_web], self::seekMethod($cls_web, $path));
                
                /*else if (file_exists($page)) {
                    //return ["\\CPHP\\route\\Base", "page", [$page]];
                    return [cls("route/Base"), "page", [$page]];
                } else {
                    //check base route
                    if (class_exists($cls_web)) {
                        $rcls = new ReflectionClass($cls_web);
                        if ($rcls->hasMethod($path[0]))
                    }
                    if (!class_exists($cls_web)) {
                        return array_merge([$cls_base], self::seekMethod($cls_base, $path));
                    } else {
                        return array_merge([$cls_web], self::seekMethod($cls_web, $path));
                    }
                }*/
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
        $callParams = [
            "route" => $route,
            "method" => $method,
            "args" => $args
        ];
        $route = new $route();
        //缓存 router call params
        $route->routerCallParams = $callParams;
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

    /**
     * 手动调用某个路由，用于在路由劫持
     * 仅支持劫持 App 路由
     * @param Array $args 用于 seek 路由的 URI 参数数组
     * @param Mixed $caller 劫持者，通常在 app_A 实例中劫持 app_B 的路由，则此处 $caller = app_A 实例
     * @return Array 返回路由响应
     */
    public static function manual($args = ["index"], $caller = null)
    {
        $route = self::seek($args);
        if (($route[0]=="\\Atto\\Box\\route\\Web" || $route[0]=="\\Atto\\Box\\route\\Base") && $route[1]=="defaultMethod") {
            //未查找到有效 route
            Response::code(404);
        } else {
            //var_dump($route);
            //执行劫持到的 Route 方法
            //return self::exec(...$route);

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
            if ($caller instanceof App) {
                $route->appname = $caller->name;
            }
            //缓存 router call params
            $route->routerCallParams = $callParams;
            //执行路由方法
            return call_user_func_array([$route,$method],$args);
        }

    }

}