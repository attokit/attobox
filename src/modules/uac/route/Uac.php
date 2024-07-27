<?php

/**
 * Attobox Framework / Module Resource
 * route "uac"
 * response to url "//host/uac/foo/bar/..."
 */

namespace Atto\Box\route;

use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Box\Resource;
use Atto\Box\resource\Mime;
use Atto\Box\App;
use Atto\Box\Uac as oUac;
use Atto\Box\Jwt;
use Atto\Box\db\Dsn;
use Atto\Box\Record;

class Uac extends Base
{
    //route info
    public $intr = "UAC权限控制模块路由";       //路由说明，子类覆盖
    public $name = "Uac";                   //路由名称，子类覆盖
    public $appname = "";                   //App路由所属App名称，子类覆盖
    public $key = "Atto/Box/route/Uac";     //路由调用路径

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
     * @return oUac 实例
     */
    protected function uo($dsn=null, $table=null)
    {
        return oUac::start($dsn, $table);
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
     * 输出 operations 列表
     * [host]/uac/operations[/*ms]
     * @return void
     */
    public function operations(...$args)
    {
        //if (!$this->uo()->isLogin) trigger_error("uac/notlogin", E_USER_ERROR);
        $msn = empty($args) ? null : array_shift($args);
        //$msn = empty($args) ? "pms" : array_shift($args);
        $otype = null;
        if (!empty($args)) {
            if (strpos($args[0],"-")!==false) {
                $otype = array_shift($args);
            } else {
                $pcls = cls("uac/".ucfirst(strtolower($msn))."OperationsParser");
                if (!empty($pcls)) {
                    $parser = new $pcls();
                    if (in_array($args[0], $parser->otypes)) {
                        $otype = array_shift($args);
                    } 
                }
            }
        }
        $oprs = oUac::operations($msn, $otype);

        if (empty($args)) {
            Response::json($oprs);
        } else {
            $action = array_shift($args);
            if ($action == "values") {      // host/uac/operations/pms/values
                if (isset($oprs["operations"])) {
                    $os = $oprs["operations"];
                } else {
                    $os = $oprs;
                }
                $vals = [];
                for ($i=0;$i<count($os);$i++) {
                    $vals[] = [
                        "label" => "<".$os[$i]["name"]."> ".(isset($os[$i]["label"]) ? $os[$i]["label"] : $os[$i]["title"]),
                        "value" => $os[$i]["name"]
                    ];
                }
                //var_dump($vals);
                Response::json($vals);
            }
            Response::json([]);
        }
        exit;
    }

    /**
     * 输出 usr menus 列表，获取登录用户的可用 menus 项
     * [host]/uac/menus[/pms[/...]]
     * @return void
     */
    public function menus(...$args)
    {
        $uac = $this->uo();
        if (!$uac->isLogin) {
            return $uac->jumpToUsrLogin();
        }
        if (empty($args)) {
            $args = ["pms","menu"];
        } else {
            array_splice($args, 1, 0, "menu");
        }
        //$oprs = $this->operations(...$args);
        $oprs = oUac::operations(...$args);
        if ($uac->isSuperUsr()) {
            return $oprs;
        }
        $all = $oprs["operations"];
        $usrm = $uac->getUsrAuthByType("menu");
        $rtn = [];
        for ($i=0;$i<count($all);$i++) {
            if (in_array($all[$i]["name"], $usrm)) {
                $rtn[] = $all[$i];
            }
        }
        return [
            "otypes" => ["menu"],
            "operations" => $rtn
        ];
    }
    


    /**
     * Uac 用户操作接口
     * [host]/uac/usr[/...]
     * @return void
     */
    public function usr(...$args)
    {
        $uac = $this->uo();
        if (!$uac->isLogin) trigger_error("uac/notlogin", E_USER_ERROR);
        if (empty($args)) {
            $ur = $this->ur();
            //var_dump($ur->role);
            if ($ur instanceof Record) {
                //$ud_ctx = $ur->export("ctx", true);
                $ud_show = $ur->export("show", true);
                $ud_raw = $ur->export("db");
                $ud = [
                    "data" => $ud_show, //$ud_ctx,
                    "raw" => $ud_raw,
                    "roles" => j2a($ud_raw["role"]),
                    "auths" => $uac->getUsrAuth(),
                    "table" => $uac->usrTable->xpath,
                ];
                //$ud = $ur instanceof Record ? $ur->export("show") : [];
                Response::json($ud);
            } else{
                trigger_error("uac/nousr", E_USER_ERROR);
            }
        } else {
            $action = strtolower($args[0]);
            $postdata = Request::input("json");
            switch ($action) {
                case "grant":
                    //判断用户是否拥有权限
                    if (isset($postdata["opr"])) {
                        Response::json([
                            "operation" => $postdata["opr"],
                            "grant" => oUac::grant($postdata["opr"])
                        ]);
                        exit;
                    }
                    if (isset($postdata["oprs"]) && is_indexed($postdata["oprs"])) {
                        $oprs = $postdata["oprs"];
                        $or = isset($postdata["or"]) && $postdata["or"]==true;
                        Response::json([
                            "operation" => implode(",",$oprs),
                            "grant" => $or==true ? $uac->checkUsrAuthAny(...$oprs) : $uac->checkUsrAuth(...$oprs)
                        ]);
                        exit;
                    }
                    Response::json([
                        "operation" => "not provided",
                        "grant" => false
                    ]);
                    exit;
                    break;
                case "role":
                    //判断用户是否属于角色
                    $roles = isset($postdata["roles"]) ? $postdata["roles"] : [];
                    if (is_notempty_str($roles)) $roles = arr($roles);
                    if (!is_indexed($roles)) $roles = [];
                    $or = isset($postdata["or"]) && $postdata["or"]==true;
                    if (!empty($roles)) {
                        Response::json([
                            "roles" => $roles,
                            "has" => $or==true ? $uac->checkUsrRoleAny(...$roles) : $uac->checkUsrRole(...$roles)
                        ]);
                        exit;
                    }
                    Response::json([
                        "roles" => [],
                        "has" => false
                    ]);
                    exit;
                    break;

                default:
                    //调用其他 用户接口
                    $m = "usr".ucfirst($action);
                    array_shift($args); //nav
                    if (method_exists($this, $m)) {
                        return $this->$m($uac, $postdata, ...$args);
                    }
                    break;

            }

        }

        exit;
    }
    //获取用户拥有权限的 nav 导航列表
    protected function usrNav($uac, $post = [], ...$args)
    {
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
        var_dump($dir);
        if (!is_dir($dir)) return [];
        $oParser = new $pcls();
        return $oParser->getNavOperations($dir, $uac);
    }




    /**
     * Uac 用户登录，ajax 访问，网页访问地址：[host]/login
     * [host]/uac/logout
     * @return void
     */
    public function login(...$args)
    {
        //return Uac::$current->usrLogin(...$args);
        session_del("uac_db");
        $uac = oUac::start();
        if (!$uac->isLogin) {
            return $uac->usrLogin(...$args);
        }
        exit;
    }

    /**
     * Uac 用户登出，ajax 访问，网页访问地址：[host]/logout
     * [host]/uac/logout
     * @return void
     */
    public function logout(...$args)
    {
        $uac = oUac::start();
        if ($uac->isLogin) {
            return $uac->usrLogout(...$args);
        }
        exit;
    }

    /**
     * 判断用户是否拥有某项权限
     * [host]/uac/grant/[auth-name]
     * @return Bool
     */
    public function grant($oprn="sys-login")
    {
        $rst = oUac::grant($oprn);
        Response::json([
            "operation" => $oprn,
            "grant" => $rst
        ]);
        exit;
    }



    /**
     * debug tools
     */

    /**
     * debug
     * [host]/uac/debug
     */
    public function debug(...$args)
    {
        var_dump("foobar");
        //$oprs = Uac::operations();
        $auc = oUac::getAppUacConfig();
        var_dump($auc);
        exit;
    }

    /**
     * ajax jwt token 测试 
     * ajax 访问：[host]/uac/ajaxjwt
     */
    public function ajaxjwt()
    {
        if (oUac::requiredAndReady()) {
            $uac = oUac::$current;
            Response::json([
                "token" => $uac->token,
                "usr" => $uac->usr->export()
            ]);
        }
        Response::json([
            "error" => "Ajax JWT 未通过验证"
        ]);
    }



}