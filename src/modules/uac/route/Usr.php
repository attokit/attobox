<?php

/**
 * Attobox Framework / Module Resource
 * route "usr"
 * response to url "//host/usr/foo/bar/..."
 * 
 * 用户相关操作 api
 * 正常情况下应该由 需要 uac 权限控制的 app 通过路由劫持来访问并操作此路由
 * url like:  [host]/[appname]/usr/...
 * 因为此路由需要 UAC_DB 预设参数，这个参数通常由 app 指定(保存在 app/appname/library/config.php)
 * 
 * !!! 如果直接访问 [host]/usr/... 则必须在 webroot/index.php 给出 UAC_DB 参数
 */

namespace Atto\Box\route;

use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Box\Resource;
use Atto\Box\resource\Mime;
use Atto\Box\App;
use Atto\Box\Uac;
use Atto\Box\Jwt;
use Atto\Box\db\Dsn;
use Atto\Box\Record;

class Usr extends Base
{
    //route info
    public $intr = "UAC 权限控制模块 Usr 路由";       //路由说明，子类覆盖
    public $name = "Usr";                   //路由名称，子类覆盖
    public $appname = "";                   //App路由所属App名称，子类覆盖
    public $key = "Atto/Box/route/Usr";     //路由调用路径

    /**
     * tools
     */

    /**
     * 获取当前的 appname
     */
    protected function getAppName()
    {
        if ($this->appname!="") return strtolower($this->appname);
        $app = Request::$current->app;
        if (!empty($app)) return strtolower($app);
        return null;
    }

    /**
     * 获取 Uac 实例
     * @return Uac 实例
     */
    public function uo()
    {
        return Uac::start();
    }

    /**
     * 获取登录用户 Record
     * 未登录则返回 null
     * @return Record | null
     */
    protected function ur()
    {
        $uo = $this->uo();
        if (!$uo->isLogin) return null;
        return $uo->usr;
    }

    /**
     * tools
     * 根据给定的 $args 尝试获取 record api 方法
     * @param String $args
     * @return Array  or  null
     */
    /*public function getRecordApi(...$args)
    {
        if (count($args)<3) return null;
        $dbn = strtolower($args[0]);
        $tbn = strtolower($args[1]);
        $apin = strtolower($args[2]);
        $dsn = "app/".strtolower($this->name)."/".$dbn;
        $dsno = Dsn::load($dsn);
        if ($dsno->dbNotExists) return null;
        $db = Db::load($dsno);
        if (!$db->hasTable($tbn)) return null;
        $tb = $db->table($tbn);
        $record = $tb->new();
        if (!$record instanceof Record) return null;
        $apim = "api".ucfirst($apin);
        if (!method_exists($record, $apim)) return null;
        $args = array_slice($args, 3);
        return [
            "db" => $db,
            "table" => $tb,
            "record" => $record,
            "api" => $apim,
            "oprName" => "$dbn-$tbn-$apin",
            "args" => $args
        ];
    }*/

    /**
     * tools
     * Uac 权限判断，调用 Uac::grant($opr)
     * @param String $opr
     * @param Bool $throwError 是否输出错误，默认输出
     * @return Bool
     */
    /*public function grant($opr, $throwError = true)
    {
        //$this->setUacDb();
        if (Uac::grant($opr)!==true) {
            if ($throwError) {
                trigger_error("uac/denied::".$opr, E_USER_ERROR);
                exit;
            }
            return false;
        }
        return true;
    }*/

    /**
     * tools
     * api 权限判断
     * @param String $apin
     * @param Bool $throwError 是否输出错误，默认输出
     * @return Bool
     */
    /*public function grantApi($apin, $throwError = true)
    {
        $appn = $this->getAppName();
        $apipre = !empty($appn) ? "api-app-".strtolower($appn)."-" : "api-";
        $uacOprName = $apipre.strtolower($apin);
        return $this->grant($uacOprName, $throwError);
    }*/



    /**
     * apis
     * url:
     *      [host]/[appname/]usr
     *                          [empty]         获取登录用户的信息，未登录报错
     *                          /login          相应前端 login 动作，用户输入账号密码 或 扫码 后提交到此
     *                          /logout         前端登出动作
     *                          /nav            获取用户有权限的所有 nav 菜单
     *                          /grant[/opr]    检查 opr 或 postdata[oprs] 是否有权限
     *                          /role           检查用户是否拥有 postdata[role] 角色
     */

    /**
     * 默认 api
     * 获取登录用户的信息，未登录报错
     */
    public function defaultMethod(...$args)
    {
        $uac = $this->uo();
        $usr = $this->ur();
        if (empty($usr)) {
            //用户还未登录
            return [
                "isLogin" => false
            ];
        }
        if (empty($args)) {
            //获取登录用户的信息
            if ($usr instanceof Record) {
                //$ud_ctx = $ur->export("ctx", true);
                $ud_show = $usr->export("show", true);
                $ud_raw = $usr->export("db");
                $ud = [
                    "info" => $ud_show, //$ud_ctx,
                    "raw" => $ud_raw,
                    "roles" => j2a($ud_raw["role"]),
                    "auths" => $uac->getUsrAuth()
                ];
                //$ud = $ur instanceof Record ? $ur->export("show") : [];
                return $ud;
            } else{
                trigger_error("uac/nousr", E_USER_ERROR);
            }
        }
        return [
            "status" => 500,
            "msg" => "Undefined Request Params"
        ];
    }

    /**
     * login api
     * 用户登录
     * 响应前端提交的 用户账号，密码，扫码结果
     */
    public function login(...$args)
    {
        $uac = $this->uo();
        if (!$uac->isLogin) {
            return $uac->usrLogin(...$args);
        } else {
            //已登录，直接重新返回 token
            $usr = $uac->usr;
            //创建 jwt Token 
            $tk = Jwt::generate([
                "uid" => $usr->_id
            ]);
            if (isset($tk["token"])) {
                $uac->token = $tk["token"];
            }
            //下发 token，前端刷新页面
            Response::json([
                "isLogin" => true,
                "token" => $uac->token
            ]);
        }
        //return false;
    }

    /**
     * logout api
     * 用户登出
     * 响应前端用户登出动作
     */
    public function logout(...$args)
    {
        $uac = $this->uo();
        if ($uac->isLogin) {
            return $uac->usrLogout(...$args);
        }
        return false;
    }

    /**
     * nav api
     * 获取用户可用的 nav 导航菜单列表
     * 用户可用的 nav 导航菜单是预先定义好的一系列 vue 页面组件
     * 保存位置：
     *      如果在 app 中：     app/[appname]/assets/nav
     *      如果不在 app 中：   root/assets/nav
     * 
     * OperationsParser 类：
     *      如果在 app 中：     App/appname/uac/appOperationsParser
     *      如果不在 app 中：   uac/OperationsParser
     *          
     */
    public function nav(...$args)
    {
        //获取当前用户的有权限 nav 列表
        $uac = $this->uo();
        if (!$uac->isLogin) return [];
        $app = $this->getAppName();
        if (empty($app)) {
            $args = array_merge(["root","assets","nav"], $args);
            $pcls = cls("uac/OperationsParser");
        } else {
            $args = array_merge(["app",$app,"assets","nav"], $args);
            $pcls = cls("App/$app/uac/".ucfirst($app)."OperationsParser");
            if (!class_exists($pcls)) $pcls = cls("uac/OperationsParser");
        }
        $dir = path_find(implode("/", $args), ["checkDir"=>true]);
        if (!is_dir($dir)) return [];
        $oParser = new $pcls();
        return $oParser->getNavOperations($dir, $uac);
        /*$navs = $oParser->getNavOperations($dir, $uac);
        var_dump($navs);exit;
        if ($uac->isSuperUser) return $navs;
        $navvue = $navs["navvue"];
        $navmth = $navs["navmethod"];
        $oprs = oUac::operations(...$args);
        //$oprs = $this->operations(...$args);

        //$oprs = Uac::operations($app, "nav");
        //return $oprs;

        if ($uac->isSuperUsr()) {
            return $oprs;
        }
        $all = $oprs["operations"];
        $usrm = $uac->getUsrAuthByType("nav");
        $rtn = [];
        for ($i=0;$i<count($all);$i++) {
            if (in_array($all[$i]["name"], $usrm)) {
                $rtn[] = $all[$i];
            }
        }
        return [
            "otypes" => ["nav"],
            "operations" => $rtn
        ];*/
    }

    /**
     * grant api
     * 检查用户是否拥有 某个 opr 的 权限
     */
    public function grant(...$args)
    {
        $postdata = Request::input("json");
        $opr = $postdata["opr"] ?? null;
        $oprs = $postdata["oprs"] ?? [];
        $uac = $this->uo();
        if (!empty($args)) {
            $oprn = $args[0];
            return [
                "operation" => $oprn,
                "grant" => Uac::grant($oprn)
            ];
        }
        if (!is_null($opr)) {
            return [
                "operation" => $opr,
                "grant" => Uac::grant($opr)
            ];
        }
        if (is_notempty_arr($oprs) && is_indexed($oprs)) {
            $or = $postdata["or"] ?? true;
            return [
                "operation" => $oprs,   //implode(",",$oprs),
                "grant" => $or==true ? $uac->checkUsrAuthAny(...$oprs) : $uac->checkUsrAuth(...$oprs)
            ];
        }
        return [
            "operation" => "not provided",
            "grant" => false
        ];
    }

    /**
     * role api
     * 检查用户是否属于某个 role 角色
     */
    public function role(...$args)
    {
        $postdata = Request::input("json");
        $roles = $postdata["roles"] ?? [];
        if (is_notempty_str($roles)) $roles = arr($roles);
        if (!is_indexed($roles)) $roles = [];
        if (empty($roles)) {
            if (!empty($args)) {
                $roles = array_merge($roles, $args);
            }
        }
        $or = $postdata["or"] ?? true;
        if (!empty($roles)) {
            return [
                "roles" => $roles,
                "has" => $or==true ? $uac->checkUsrRoleAny(...$roles) : $uac->checkUsrRole(...$roles)
            ];
        }
        return [
            "roles" => [],
            "has" => false
        ];
    }
    
}