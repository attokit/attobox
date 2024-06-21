<?php

/**
 * Attobox Framework / Route base class
 */

namespace Atto\Box;

use Atto\Box\Response;
use Atto\Box\Uac;
use Atto\Box\Jwt;

class Route
{
    //route info
    public $intr = "";      //路由说明，子类覆盖
    public $name = "";      //路由名称，子类覆盖
    public $appname = "";   //App路由所属App名称，子类覆盖
    public $key = "";       //路由调用路径

    //此路由是否需要 UAC 权限控制，
    //如仅部分方法需要控制权限，设为 false，在需要控制权限的方法内部 if (Uac::grant("$route->key/method")===true) { 方法逻辑 }
    //如所有方法都需要控制权限，设为 true
    public $uac = false;

    //此路由是否需要 jwt 权限验证，如果仅部分方法需要验证，此处为 false
    //public $needJwt = false;
    //protected $jwtUsr = null;

    //此路由是否不受 WEB_PAUSE 设置影响
    public $unpause = false;

    //接受的参数
    public $option = [];  //外部调用通过post传入的参数，Arr类型实例

    //由 router 创建的本次会话的 response 响应对象，即 Response::$current
    public $response = null;

    //缓存 通过 router->seek 得到的本路由的调用参数，仅用于 debug
    public $routerCallParams = [];

    //此路由全部方法都需要权限验证
    //public $allNeedAuthority = false;
    //此路由的权限验证器
    //public $authVerifier = null;

    public function __construct($option = [])
    {
        $this->key = str_replace("/", "\\", $this->key);
        $this->setOption($option);
        $this->response = Response::$current;

        //如果此路由所有方法都需要控制权限
        if ($this->uac===true && Uac::requiredAndReady()) {
            //if (Uac::islogin()!==true) trigger_error("uac/notlogin", E_USER_ERROR);
            if (Uac::grant("route-".strtolower($this->name))!==true) {
                return false;
            }
        }

        //如果此路由所有方法都需要 jwt 验证
        //if ($this->needJwt) {
        //    $this->jwtValidate();
        //}
    }

    /**
     * 访问 api 路由方法
     * 可由子类 override
     */
    public function api(...$args)
    {
        if (empty($args)) Response::code(404);
        $api = array_shift($args);
        $m = "api".ucfirst($api);
        if (!method_exists($this,$m)) Response::code(404);
        //api 调用必须使用 UAC 权限控制
        if ($this->apiUacCtrl($api)===true) {
            return $this->$m(...$args);
        }
        exit;
    }

    /**
     * api 权限控制方法
     * 子类路由如果需要权限控制的，应调用此方法
     * @param String $api
     * @return Bool
     */
    protected function apiUacCtrl($api)
    {
        $uacOperationName = "api-". cls_no_ns($this) ."-". $api;
        //if (UAC_CTRL && Uac::grant($uacOperationName)!==true) {
        if (Uac::requiredAndReady() && Uac::grant($uacOperationName)!==true) {
            trigger_error("uac/denied::".$uacOperationName, E_USER_ERROR);
            return false;
        }
        return true;
    }

    /**
     * jwt 权限验证
     * @param String[] $methods 要验证权限的 route methods
     * @return Bool
     */
    /*protected function jwtValidate(...$methods)
    {
        if (empty($methods)) {
            $methods = [ strtolower($this->name) ];
        } else {
            $methods = array_map(function($method) {
                return strtolower($this->name)."/".$method;
            });
        }
        $jwt = Jwt::validate(...$methods);
        if ($jwt===true) return true;
        trigger_error("auth::".$jwt["msg"], E_USER_ERROR);
        //exit;
    }*/

    /**
     * call other exists route method
     * @param String $uri       like foo/bar/jaz/...
     * @return Mixed return data
     */
    public static function exec($uri)
    {
        $uarr = arr($uri);
        $route = Router::seek($uarr);
        return Router::exec($route);
    }

    public function setOption()
    {
        
    }



    /**
     * tools
     */

    /**
     * 获取所有 定义的路由
     * @param Bool $includeBaseClass 是否包含基类，即由框架定义的路由类
     * @return Array 名称数组，不包含 namespace
     */
    public static function getRoutes($includeBaseClass = true)
    {
        //先查看已使用的类
        $clss = get_declared_classes();
        $routes = array_filter($clss, function($cls) {
            return strpos($cls, "Atto\\Box\\route")!==false;
        });
        
        if ($includeBaseClass) {
            //再检查 route 路径下的文件
            $rdir = path_find("box/route",["inDir"=>"","checkDir"=>true]);
            path_traverse($rdir, function($path, $file) use (&$routes) {
                if (is_file($path.DS.$file) && substr(strtolower($file), -4)==EXT) {
                    $routes[] = str_replace(EXT,"",strtolower($file));
                }
            });
            //再检查 modules/*/route 路径下
            $mdir = path_find("box/modules",["inDir"=>"", "checkDir"=>true]);
            path_traverse($mdir, function($path, $file) use (&$routes) {
                if (is_dir($path.DS.$file) && $file!="." && $file!=".." && is_dir($path.DS.$file.DS."route")) {
                    $sroutes = [];
                    path_traverse($path.DS.$file.DS."route", function($spath, $sfile) use (&$sroutes) {
                        if (is_file($spath.DS.$sfile) && substr(strtolower($sfile), -4)==EXT) {
                            $sroutes[] = str_replace(EXT,"",strtolower($sfile));
                        }
                    });
                    array_push($routes, ...$sroutes);
                }
            });
        }

        //再检查 [webroot]/route 路径下
        $rdir = path_find("root/route",["inDir"=>"","checkDir"=>true]);
        if (!empty($rdir)) {
            path_traverse($rdir, function($path, $file) use (&$routes) {
                if (is_file($path.DS.$file) && substr(strtolower($file), -4)==EXT) {
                    $routes[] = str_replace(EXT,"",strtolower($file));
                }
            });
        }

        //再检查 app/[Request::$current->app]/route 路径下
        $app = Request::$current->app;
        if (!empty($app)) {
            $adir = path_find("app/$app/route", ["inDir"=>"","checkDir"=>true]);
            if (!empty($adir)) {
                path_traverse($adir, function($path, $file) use (&$routes) {
                    if (is_file($path.DS.$file) && substr(strtolower($file), -4)==EXT) {
                        $routes[] = str_replace(EXT,"",strtolower($file));
                    }
                });
            }
        }

        $routes = array_map(function($route) {
            $route = str_replace("Atto\\Box\\route", "", $route);
            return strtolower(trim($route,"\\"));
        }, $routes);
        //去重
        $routes = array_merge(array_flip(array_flip($routes)));
        if (!$includeBaseClass) {
            //再次去除基类
            /***** 此处手动编码，可能需要需改要去除的[] *****/
            $nr = array_diff($routes, ["base","dbm","src","ms","uac","usr"]);
            $nr = array_merge($nr, []);
            return $nr;
        }
        return $routes;
    }

    /**
     * 获取所有 api 列表
     * @param String $route 如不指定 route 则获取所有已定义的 api，返回 api name
     * @return Array 结构如下：
        [
            [name=>"", title=>""],
            ...
        ]
     */
    public static function getApis($route=null)
    {
        if (is_notempty_str($route)) {
            $rtn = NS."route\\".ucfirst($route);
            if (!class_exists($rtn)) return [];
            $route = new \ReflectionClass($rtn);
            $methods = $route->getMethods(\ReflectionMethod::IS_PROTECTED);
            $methods = array_filter($methods, function($m) {
                return substr($m->name, 0, 3)=="api";
            });
            $methods = array_merge($methods, []);
            $apis = [];
            for ($i=0;$i<count($methods);$i++) {
                $mi = $methods[$i];
                $cmt = $mi->getDocComment();
                if (strpos($cmt,"<%")===false) continue;
                $name = strtolower(substr($mi->name, 3));
                $tit = explode("%>",explode("<%",$cmt)[1])[0];
                $apis[] = [
                    "name" => $name,
                    "title" => $tit
                ];
            }
            return $apis;
        } else {
            $routes = self::getRoutes(false);   //不检查基类路由 api
            $routes = array_merge($routes,[]);
            $apis = [];
            for ($i=0;$i<count($routes);$i++) {
                $ri = $routes[$i];
                if (empty($ri)) continue;
                $apii = self::getApis($ri);
                if (empty($apii)) continue;
                $apii = array_map(function($ai) use ($ri) {
                    return [
                        "name" => $ri."-".$ai["name"],
                        "title" => $ai["title"]
                    ];
                }, $apii);
                array_push($apis, ...$apii);
            }
            return $apis;
        }
    }

    /**
     * 获取所有 nav 方法 列表
     * nav 方法是路由中 包含 <% nav:**** %> 格式注释的 public 方法
     * @param String $route 如不指定 route 则获取所有已定义的 api，返回 api name
     * @return Array 结构如下：
     *   [
     *       [name=>"", title=>""],
     *       ...
     *   ]
     */
    public static function getNavs($route=null)
    {
        if (is_notempty_str($route)) {
            $rtn = NS."route\\".ucfirst($route);
            if (!class_exists($rtn)) return [];
            $route = new \ReflectionClass($rtn);
            $methods = $route->getMethods(\ReflectionMethod::IS_PUBLIC);
            $navs = [];
            for ($i=0;$i<count($methods);$i++) {
                $mi = $methods[$i];
                $cmt = $mi->getDocComment();
                if (strpos($cmt,"<%nav:")===false) continue;
                $name = strtolower($mi->name);
                $tit = explode("%>",explode("<%nav:",$cmt)[1])[0];
                $navs[] = [
                    "name" => $name,
                    "title" => $tit
                ];
            }
            return $navs;
        } else {
            $routes = self::getRoutes(false);   //不检查基类路由 api
            $routes = array_merge($routes,[]);
            $navs = [];
            for ($i=0;$i<count($routes);$i++) {
                $ri = $routes[$i];
                if (empty($ri)) continue;
                $navi = self::getNavs($ri);
                if (empty($navi)) continue;
                $navi = array_map(function($ai) use ($ri) {
                    return [
                        "name" => $ri."-".$ai["name"],
                        "title" => $ai["title"]
                    ];
                }, $navi);
                array_push($navs, ...$navi);
            }
            return $navs;
        }
    }
    

    
}