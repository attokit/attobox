<?php

/**
 * Attobox Framework / UAC 通用权限控制
 * 验证用户是否拥有权限
 * 权限类型：route，route/method(api)，*ms-operations，...
 * 
 * UAC 应随 Request 一起实例化，并附加到 Request::$current->uac
 * 
 * UAC 权限验证流程：
 *      1   前端访问某页面（fpg），页面 UAC 尝试获取 JWT：
 *          1.1     不存在 JWT，跳转到 login 页面：
 *                  1.1.1   用户输入密码或扫码验证
 *                          1.1.1.1     验证成功后，将用户信息缓存至 UAC 实例，然后下发生成的 JWT Token 到前端
 *                                      1.1.1.1.1   前端缓存 Token，并带 Token 跳转回 fpg 页面，流程转到 1.2
 *          1.2     存在 JWT，开始 Jwt::validate()：
 *                  1.2.1   Token 错误：过期、来源不一致、被篡改、等，流程转到 1.1 要求重新登录
 *                  1.2.2   Token 验证通过，开始检查是否拥有 fpg 页面权限:
 *                          1.2.2.1 有权限，展示页面
 *                          1.2.2.2 无权限，返回错误信息
 *
 *      2   前端访问某路由（api），路由 或 要访问的路由方法 要求 UAC 权限验证，尝试 获取 JWT：
 *          2.1     不存在 JWT，跳转到 login 页面：
 *                  2.1.1   用户输入密码或扫码验证
 *                          2.1.1.1     验证成功后，将用户信息缓存至 UAC 实例，然后下发生成的 JWT Token 到前端
 *                                      2.1.1.1.1   前端缓存 Token，并带 Token 跳转回 路由（api） 页面，流程转到 2.2
 *          2.2     存在 JWT，开始 Jwt::validate()：
 *                  2.2.1   Token 错误：过期、来源不一致、被篡改、等，流程转到 2.1 要求重新登录
 *                  2.2.2   Token 验证通过，开始检查是否拥有 路由（api）权限:
 *                          2.2.2.1 有权限，展示页面
 *                          2.2.2.2 无权限，返回错误信息
 *          
 * 
 * 
 */

namespace Atto\Box;

use Atto\Box\Event;
use Atto\Box\Request;
use Atto\Box\request\Url;
use Atto\Box\Router;
use Atto\Box\Response;
use Atto\Box\Jwt;
use Atto\Box\Db;
use Atto\Box\db\Dsn;
use Atto\Box\db\Table;
use Atto\Box\Record;
use Atto\Box\RecordSet;

class Uac
{
    //单例
    public static $current = null;

    //用户数据库信息
    public $usrDb = null;
    public $usrTable = null;
    public $usrTableName = "usr";   //用户表名必须是 usr
    public $usrTableRole = "role";  //用户角色表名必须是 role

    //用户信息
    public $usrId = 0;      //当前会话登录用户 id
    public $usr = null;     //当前会话登录用户信息，Record 实例
    public $isLogin = false;

    //缓存的 Jwt Token
    public $token = "";

    /**
     * construct
     */
    public function __construct($db = null)
    {
        //用户数据库初始化
        $this->initUsrDb($db);
        //获取登录用户信息
        $this->initUsr();
        //var_dump($this->usrDb);
    }

    /**
     * 连接用户数据库，建立 table 实例
     * @param String $db   用户数据库 实例 或 名称
     * @param String $table 用户数据表 name
     * @return $this
     */
    public function initUsrDb($db = null)
    {
        if (is_null($db)) { //如果未指定用户数据库
            //获取可用的 uac_db
            $db = self::getUacConfig("db");
            //var_dump($db);

            //尝试读取 $_SESSION
            //if (session_has("uac_db")) {
            if (!empty($db)) {
                //$db = session_get("uac_db");    //$_SESSION 中应保存 dsn
                $dsn = Dsn::load($db);
                if (!$dsn->dbNotExists) {
                    $this->usrDb = Db::load($db);
                    $this->usrTable = $this->usrDb->table($this->usrTableName);
                    return $this;
                }
            } else {    //如果无 $_SESSION，保持未登录状态，直到需要登录权限时，跳转登录界面
                return $this;
            }
        } else if ($db instanceof Db) { //指定了数据库实例
            $this->usrDb = $db;
            $this->usrTable = $this->usrDb->table($this->usrTableName);
            session_set("uac_db", $db->dsn->dsn);
            return $this;
        } else if (is_notempty_str($db)) {  //指定了数据库 dsn
            $dsn = Dsn::load($db);
            if (!$dsn->dbNotExists) {
                $this->usrDb = Db::load($db);
                $this->usrTable = $this->usrDb->table($this->usrTableName);
                session_set("uac_db", $db);
                return $this;
            }
        }
        //未找到初始化数据库的，保持未登录状态
        return $this;
    }

    /**
     * 获取登录信息，检查 Jwt Token 与 $_SESSION["uid"]
     * @param Record $usr 当前登录用户在用户表中的数据记录 Record 实例，如提供，则直接设置 Uac->usr
     * @return $this
     */
    public function initUsr($usr = null)
    {
        //如果用户数据处于未初始化状态，则不检查用户登录状态，保持未登录
        if (is_null($this->usrDb)) return $this;

        //开始检查用户登录状态
        //如果指定了登录用户信息（通常是扫码登录的情况）
        if ($usr instanceof Record && !$usr->isNew) {
            $this->usr = $usr;
            $this->usrId = $usr->_id;   //$usr->context[$usr->idf()];
            $this->isLogin = true;
            return $this;
        } else {

            /**** 开始获取登录信息 ****/

            // 1    尝试获取 JWT Token
            $jwt = Jwt::validate();
            if ($jwt["success"]===true) {
                $payload = $jwt["payload"];
                if (isset($payload["uid"]) && $payload["uid"]!=0) {     //前端提交的 jwtToken 中必须包含 uid 信息
                    $rs = $this->getUsr($payload["uid"]);
                    if ($rs instanceof Record) {
                        $this->token = Jwt::getToken();
                        return $this->initUsr($rs);
                    }
                }
            } else if ($jwt["status"]!="emptyToken") {
                $errmsg = $jwt["msg"];
                if (!empty($jwt["payload"])) {
                    $errmsg .= "&".a2u($jwt["payload"]);
                }
                trigger_error("uac/jwterror::".$errmsg, E_USER_ERROR);
            }

            // 2    读取 $_SESSION 信息
            if (session_has("uac_uid")) {
                $uid = session_get("uac_uid");
                $rs = $this->getUsr($uid);
                if ($rs instanceof Record) {
                    return $this->initUsr($rs);
                }
            }

            /**** 获取登录信息 end ****/

        }
        //未获取到用户登录状态的，保持未登录状态
        return $this;
    }

    /**
     * 从 Uac 卸载用户信息，initUsr 的反向操作
     * @return $this
     */
    public function unloadUsr()
    {
        $this->usr = null;
        $this->usrId = 0;
        $this->isLogin = false;
        return $this;
    }

    /**
     * 按 uid 查询用户信息
     * @param String | Integer $uid
     * @return Record usr record  or  null
     */
    protected function getUsr($uid=null)
    {
        if (is_null($uid)) return $this->usr;   //$uid = $this->usrId;
        $idf = $this->usrTable->ik();
        $rs = $this->usrTable->query->apply([
            "where" => [
                $idf => $uid
            ]
        ])->single();
        if ($rs instanceof Record) return $rs;
        return null;
    }

    /**
     * 获取当前登录用户的所有权限 
     * @param String | Integer $uid  or  $this->usrId
     * @return Array 用户权限数组
     */
    public function getUsrAuth($uid=null)
    {
        $usr = $this->getUsr($uid);
        $oprs = $usr->auth;
        $role = $usr->role;
        if (is_string($oprs)) $oprs = j2a($oprs);
        if (is_string($role)) $role = j2a($role);
        if (empty($auth)) $auth = [];
        if (empty($role)) $role = [];
        if (empty($auth) && empty($role)) return [];     //此用户没有任何权限
        //var_dump($oprs);
        //var_dump($role);
        $roletb = $this->usrDb->table($this->usrTableRole); //(UAC_ROLE);
        //var_dump($roletb);
        $idf = $roletb->ik();
        $roles = $roletb->query->apply([
            "where" => [
                $idf => $role
            ]
        ])->whereEnable(1)->select();
        $auths = [];
        if ($roles instanceof RecordSet) {
            $aus = $roles->auth;
            //var_dump($aus);
            for ($i=0;$i<count($aus);$i++) {
                $aui = $aus[$i];
                if (is_string($aui)) {
                    $aui = j2a($aui);
                }
                if (is_array($aui) && count($aui)>0) {
                    $auths = array_merge($auths, $aui);
                }
            }
        }
        if (count($auths)>0) {
            $oprs = array_merge($oprs, $auths);
        }
        //去除 0
        $oprns = [];
        for ($i=0;$i<count($oprs);$i++) {
            if (/*$oprs[$i]==0*/is_numeric($oprs[$i])) continue;
            //if (in_array($oprs[$i], $oprns)) continue;
            $oprns[] = $oprs[$i];
        }
        //去重
        $oprns = array_merge(array_flip(array_flip($oprns)));
        return $oprns;
    }

    /**
     * 按权限类型获取当前用户所拥有权限列表
     * @param String $otype 权限类型，如 menu 菜单权限，api 权限 等
     * @param String | Integer $uid  or  $this->usrId
     * @return Array 用户菜单数组
     */
    public function getUsrAuthByType($otype, $uid=null)
    {
        $auth = $this->getUsrAuth($uid);
        if (empty($auth)) return [];
        $ot = strtolower($otype)."-";
        $otlen = strlen($ot);
        $menus = [];
        for ($i=0;$i<count($auth);$i++) {
            $aui = $auth[$i];
            if (is_numeric($aui)) continue;
            if (substr($aui, 0, $otlen)!=$ot) continue;
            $menus[] = $aui;
        }
        return $menus;
    }

    /**
     * 判断用户是否 SUPER 用户
     * @param String | Integer $uid  or  $this->usrId
     * @return Bool
     */
    public function isSuperUsr($uid=null)
    {
        $auth = $this->getUsrAuth($uid);
        if (empty($auth)) return false;
        return in_array("sys-super", $auth);
    }

    /**
     * 检查已登录用户的权限
     * @param String $oprs 要检查权限的 opr 操作名称，如果有多个 opr ，只有所有 opr 都有权限才返回 true
     * @return Bool
     */
    public function checkUsrAuth(...$oprs)
    {
        if (empty($oprs)) return false;
        $auth = $this->getUsrAuth();
        //var_dump($auth);
        if (empty($auth)) return false; //当前登录用户没有任何权限
        if (in_array("sys-super", $auth)) return true;  //超级管理员拥有所有权限

        //系统更新时临时中断除 SUPER 外所有权限
        if (UAC_PAUSED) {
            trigger_error("uac/uacpause", E_USER_ERROR);
            return false;
        }
        
        $hasAuth = true;
        for ($i=0;$i<count($oprs);$i++) {
            $hasAuth = $hasAuth && in_array(strtolower($oprs[$i]), $auth);
        }

        return $hasAuth;
        //return true;
    }

    /**
     * 检查已登录用户的权限，如果有多个 opr ，只要有一个 opr 有权限即返回 true
     * @param String $oprs 要检查权限的 opr 操作名称
     * @return Bool
     */
    public function checkUsrAuthAny(...$oprs)
    {
        if (empty($oprs)) return false;
        if ($this->isSuperUsr()) return true;
        //系统更新时临时中断除 SUPER 外所有权限
        if (UAC_PAUSED) {
            trigger_error("uac/uacpause", E_USER_ERROR);
            return false;
        }
        for ($i=0;$i<count($oprs);$i++) {
            $grant = $this->checkUsrAuth($oprs[$i]);
            if ($grant==true) return true;
        }
        return false;
    }

    /**
     * 检查用户角色
     * @param String $roles 要检查用户是否拥有的 role，如果有多个 role，只有拥有所有 role 才返回 true
     * @return Bool
     */
    public function checkUsrRole(...$roles)
    {
        if (empty($roles)) return false;
        if ($this->isSuperUsr()) return true;
        //系统更新时临时中断除 SUPER 外所有权限
        if (UAC_PAUSED) {
            trigger_error("uac/uacpause", E_USER_ERROR);
            return false;
        }
        $usr = $this->getUsr();
        $role = $usr->role;
        if (!is_indexed($role) || empty($role)) return false;
        for ($i=0;$i<count($roles);$i++) {
            if (!in_array($roles[$i], $role)) return false;
        }
        return true;
    }
    
    /**
     * 检查用户角色，如果有多个 role，只要拥有所有一个 role 即返回 true
     * @param String $roles 要检查用户是否拥有的 role
     * @return Bool
     */
    public function checkUsrRoleAny(...$roles)
    {
        if (empty($roles)) return false;
        if ($this->isSuperUsr()) return true;
        //系统更新时临时中断除 SUPER 外所有权限
        if (UAC_PAUSED) {
            trigger_error("uac/uacpause", E_USER_ERROR);
            return false;
        }
        $usr = $this->getUsr();
        $role = $usr->role;
        if (!is_indexed($role) || empty($role)) return false;
        for ($i=0;$i<count($roles);$i++) {
            if (in_array($roles[$i], $role)) return true;
        }
        return false;
    }

    /**
     * 处理用户登录操作，响应前端的登录动作
     * 由 base-route 或 Web-route 或 app-route 实现 login 方法
     * @return Mixed
     */
    public function usrLogin(...$args)
    {
        $action = empty($args) ? 'pwd' : array_shift($args);

        if ($action=="scan") {  //扫码登录
            //只有用户扫码成功并通过权限验证后，才会执行此步骤，
            //查找用户信息，initUsr(usrRecord)
            $query = Request::input('json');
            //$rs = $this->usrTable->query->apply($query)->single();
            //var_dump($this->usrTable);
            $rs = $this->usrTable->query->apply($query)->whereEnable(1)->single();
            if ($rs instanceof Record) {
                $this->initUsr($rs);
                //创建 jwt Token 
                $tk = Jwt::generate([
                    "uid" => $this->usr->_id
                ]);
                if (isset($tk["token"])) {
                    $this->token = $tk["token"];
                }
            }
        } else if ($action=="pwd") {    //验证用户名密码
            //检查用户提交的 用户名 和 密码
            $query = Request::input('json');
            $err = [
                "isLogin" => false,
                "msg" => ""
            ];
            if (empty($this->usrTable)) {
                $err["msg"] = "用户数据表无法访问，请确定是否连接到合法的登录接口";
                Response::json($err);
                exit;
            }
            $uname = $query["name"] ?? null;
            $pwd = $query["pwd"] ?? null;
            if (!is_notempty_str($uname) || !is_notempty_str($pwd)) {
                $err["msg"] = "用户名和密码不能为空";
                Response::json($err);
                exit;
            }
            $upwd = md5($pwd);
            $usr = $this->usrTable->whereName($uname)->wherePwd($upwd)->whereEnable(1)->single();
            //检查是否登录成功
            if ($usr instanceof Record) {
                //登录成功
                $this->initUsr($usr);
                //创建 jwt Token 
                $tk = Jwt::generate([
                    "uid" => $this->usr->_id
                ]);
                if (isset($tk["token"])) {
                    $this->token = $tk["token"];
                }
            } else {
                $err["msg"] = "用户名或密码不正确";
                Response::json($err);
                exit;
            }
        } else if ($action=="mp") {     //小程序 qypms 业务登录
            $usr = Request::input('json');
            $uid = $usr["uid"] ?? null;
            $openid = $usr["wx_openid"] ?? null;
            if (!empty($uid) && !empty($openid)) {
                $rs = $this->usrTable->whereUid($uid)->whereOpenid($openid)->whereEnable(1)->single();
                if ($rs instanceof Record) {
                    $this->initUsr($rs);
                    //创建 jwt Token 
                    $tk = Jwt::generate([
                        "uid" => $this->usr->_id
                    ]);
                    if (isset($tk["token"])) {
                        $this->token = $tk["token"];
                    }
                }
            }
        }

        if ($this->isLogin) {   //登录成功
            //写入 $_SESSION
            session_set("uac_uid", $this->usr->_id);
            //触发用户登录成功事件
            Event::trigger("usr-login", $this->usr, time());
            //下发 token，前端刷新页面
            Response::json([
                "isLogin" => true,
                "token" => $this->token
            ]);
        } else {    //登录失败
            Response::json([
                "isLogin" => false,
                "msg" => "用户没有登录权限，无法登录系统"
            ]);
        }
    }

    /**
     * 用户统一登出入口，响应前端登出动作
     * [host]/logout[/...]
     * 由 base-route 或 Web-route 实现 logout 方法
     */
    public function usrLogout(...$args)
    {
        //session_del
        session_del("uac_uid");
        session_del("uac_db");

        Response::json([
            "isLogout" => true
        ]);

        //return true;
        //Response::redirect(Request::$current->url->full, true);
        //Response::str('用户已登出');
        //exit;
    }

    /**
     * 跳转到 login 页面
     * 执行当前 Route 的 loginPage 方法，下发登录界面
     * 各业务路由应根据实际需求实现各自的 loginPage 方法，
     * 并且必须在方法中指定 用户数据库信息，并写入 $_SESSION["uac_db"]
     * @param Array $extra login page options
     * @return void
     */
    public function jumpToUsrLogin($extra = [])
    {
        $route = Router::$current->route;
        //var_dump($route);exit;
        $ro = new $route();
        if (method_exists($ro, "loginPage")) {
            return $ro->loginPage($extra);
        }

        //查找 login page 预设
        $lgp = self::getUacConfig("login");
        if (!empty($lgp)) {
            $page = path_find($lgp);
            //var_dump($page);
            if (file_exists($page)) {
                Response::page($page, arr_extend([
                    "from" => Request::$current->url->full
                ], $extra));
            }
        }

        //使用默认 login page
        $pathes = array_map(function($dir) {
            return $dir."/login".EXT;
        }, [
            //$this->usrDb->dsn->getWebRootPath()."/page",
            //UAC_LOGIN,
            "root/page",
            "box/page"
        ]);
        $page = path_exists($pathes);
        //var_dump($page);
        if (file_exists($page)) {
            Response::page($page, arr_extend([
                "from" => Request::$current->url->full
            ], $extra));
        }
        Response::code(404);
    }

    /**
     * 输出 Uac 信息，用于 debug
     * @return Array
     */
    public function info()
    {
        $info = [
            "usrId" => $this->usrId,
            "usr" => ($this->usr instanceof Record) ? $this->usr->export() : null,
            "dsn" => is_null($this->usrDb) ? null : $this->usrDb->dsn->dsn,
            "table" => is_null($this->usrTable) ? null : $this->usrTable->xpath,
            "isLogin" => $this->isLogin
        ];
        return $info;
    }



    /**
     * static tools
     */

    /**
     * 如果 app 定义了 uac 相关 config 获取值
     * @return Array
     */
    public static function getAppUacConfig()
    {
        $app = Request::$current->app;
        //var_dump($app);
        if (empty($app)) return [];
        $cnst = "APP_".strtoupper($app)."_UAC_";
        $conf = [];
        if (defined($cnst."CTRL")) $conf["uac_ctrl"] = constant($cnst."CTRL");
        if (defined($cnst."DB")) {
            $cdb = constant($cnst."DB");
            //默认添加 app/[appname]/ 作为数据库前缀
            if (substr($cdb, 0,4)!=="app/") {
                $cdb = "app/".strtolower($app)."/".$cdb;
            }
            $conf["uac_db"] = $cdb;
        }
        //if (defined($cnst."TABLE")) $conf["uac_table"] = constant($cnst."TABLE");
        //if (defined($cnst."ROLE")) $conf["uac_role"] = constant($cnst."ROLE");
        if (defined($cnst."LOGIN")) $conf["uac_login"] = constant($cnst."LOGIN");
        if (defined($cnst."WX")) $conf["uac_wx"] = constant($cnst."WX");
        if (defined($cnst."PAUSED")) $conf["uac_paused"] = constant($cnst."PAUSED");
        if (defined($cnst."ROLE")) {
            $conf["uac_role"] = arr(constant($cnst."ROLE"));
        }
        if (defined($cnst."ROLE_NAME")) {
            $conf["uac_role_name"] = arr(constant($cnst."ROLE_NAME"));
        }
        return $conf;
    }

    /**
     * 获取可用的 预设的 uac_db
     * @return String
     */
    public static function getUacConfig($key="db")
    {
        //先检查 session
        if ($key=="db" && session_has("uac_db")) return session_get("uac_db");
        //在检查 app 设置
        $auc = self::getAppUacConfig();
        if (isset($auc["uac_$key"])) return $auc["uac_$key"];
        //最后使用 UAC_DB 默认设置
        $cnst = "UAC_".strtoupper($key);
        if (defined($cnst) && constant($cnst)!="") return constant($cnst);
        return null;
    }

    /**
     * 全局判断 Uac 是否启用
     * @return Bool
     */
    public static function required()
    {
        //return UAC_CTRL===true;
        $uac_ctrl = self::getUacConfig("ctrl");
        return is_bool($uac_ctrl) && $uac_ctrl===true;
    }

    /**
     * 全局判断 Uac 是否已经初始化，用户数据库连接正常
     * @return Bool
     */
    public static function ready()
    {
        return !is_null(\Box::$uac) && !is_null(\Box::$uac->usrDb);
    }

    /**
     * 全局判断 Uac 是否启用，并且 可用
     * @return Bool
     */
    public static function requiredAndReady()
    {
        $required = self::required();
        $ready = self::ready();
        return $required && $ready;
    }

    /**
     * 启动 UAC 权限控制，应随 Request 一并启动
     * @return Uac instance
     */
    public static function start($db = null)
    {
        if (is_null(self::$current)) {
            self::$current = new Uac($db);
        } else {
            $uac = self::$current;
            if (is_null($uac->usrDb) && !is_null($db)) {
                $uac->initUsrDb($db);
                $uac->initUsr();
            }
        }
        return self::$current;
    }

    /**
     * 获取当前登录用户
     * @return Record  or  null
     */
    public static function loginUsr()
    {
        if (self::isLogin()) {
            $u = self::start()->usr;
            if ($u instanceof Record) return $u;
        }
        return null;
    }

    /**
     * 获取全部可指定权限的操作列表
     * @param String $ms 用作操作列表的 namespace，例如 pms, oms, ... 用以区分不同的管理系统
     * @param String $otype 可以指定要获取的操作列表的操作类型，如 menu 或 menu-admin
     * @return Array 包含所有 operations 的数组
     * 
     * 如果有自定义的获取方法，应：
     *      在 [webroot]/library/uac/ 路径下建立与 $ms 对应的 OperationsParser 类，如： PmsOperationsParser 类
     *      这个 Parser 类继承自 \Atto\Box\uac\OperationsParser 并应该：
     *          定义操作的类型（即权限的类型），如：menus 菜单类型操作(权限)，curd 数据操作(权限)，atom 按钮等原子操作(权限)，等
     *          定义获取这些类型操作列表的方法，应这样命名：getMenusOperations(), getCurdOperations(), getAtomOperations(), 等
     *          定义一个总入口方法，来获取所有操作，并输出为数组，方法命名为：getOperations()
     *          获取的操作列表数组结构应为：
                //操作名称 like：category-group-oprname-atomopr，
                //如：menus-admin-doc-publish 表示一个原子操作（发布通知，属于 admin 菜单组，doc 文档管理项下）
                {
                    //操作类型数组
                    category: [sys, menus, curd, atom],
                    //操作列表，有序数组
                    operations: [
                        {
                            name: 'sys-all',    //必选
                            label: 'SUPER',     //menus类型必选参数
                            title: 'SUPER',     //必选
                            //可选参数
                            icon: '',
                            desc: '',
                            role: '',   //此操作需要的特别的 role 角色
                            ...
                        },
                        ...
                    ]
                }
     */
    public static function operations($ms=null, $otype=null)
    {
        $pre = self::prefix();
        //var_dump($dsn);
        if ($pre!="") {
            $ms = strtolower(array_slice(explode("/", $pre), -1)[0]);
            $uacpre = "App/".ucfirst($ms)."/"."uac/";
        } else {
            $ms = (is_null($ms) || $ms=="null") ? "pms" : $ms;
            $uacpre = "uac/";
        }
        $oprs = [];
        $pcls = cls($uacpre.ucfirst(strtolower($ms))."OperationsParser");
        //var_dump($pcls);
        if (!empty($pcls)) {
            $parser = new $pcls();
            //此 Parser 类必须实现此方法
            $oprs = $parser->getOperations($otype);
        }
        //var_dump($oprs);
        return $oprs;
    }

    /**
     * grant 权限检查静态入口
     * if (Uac::grant($oprs)===true) { do sth ... }
     * @param String $oprs  要验证权限的 opr-name  or  api(page) xpath  or  scan-scene  or  ...
     * @return Bool
     */
    public static function grant(...$oprs)
    {
        $uac = self::start();
        if (!$uac->isLogin) {
            return $uac->jumpToUsrLogin();
        }
        //var_dump($oprs);
        return $uac->checkUsrAuth(...$oprs);
    }
    
    /**
     * 检查用户是否已登录
     * @return Bool
     */
    public static function islogin()
    {
        $uac = self::start();
        return $uac->isLogin;
    }

    /**
     * 根据当前的用户数据库 dsn 获取 路径/类 前缀
     * @param String $any 要附加前缀的内容
     * @param String $type 前缀类型 path 路径(全小写前缀)、class 类前缀
     * @return String prefix
     */
    public static function prefix($any = "", $type = "class")
    {
        //直接读取 $_SESSION["uac_db"]
        $dsn = self::getUacConfig("db");
        //var_dump($dsn);
        //if (!session_has("uac_db")) return $any;
        //$dsn = session_get("uac_db");
        if (strpos($dsn,"app/")===false) return $any;
        $arr = explode("app/", $dsn);
        
        /*$webroot = array_slice(explode(":", rtrim($arr[0],"/")), -1)[0];
        $domain = array_slice(explode("/", $webroot), -1)[0];
        $ms = strtolower(explode("/", $arr[1])[0]);
        if ($type=="class") {
            $pre = "App/".ucfirst($ms);
        } else {
            $pre = "app/".$ms;
        }
        if ($pre!="") $any = str_replace($pre."/", "", $any);
        //var_dump("domain");
        //var_dump($domain);
        //var_dump("WEB_DOMAIN");
        //var_dump(WEB_DOMAIN);
        if (WEB_DOMAIN != $domain) {
            if ($type=="class") {

            } else {
                if ($webroot!="") {
                    $any = str_replace($webroot."/", "", $any);
                    $pre = $webroot."/".$pre;
                }
            }
            //var_dump($webroot);
        }

        $rtn = $any=="" ? $pre : $pre."/".ltrim($any, "/");
        //var_dump($rtn);
        return $rtn;*/

        $arr = explode("/", $arr[1]);
        $ms = strtolower($arr[0]);
        if ($type=="class") {
            $pre = "App/".ucfirst($ms);
        } else {
            $pre = "app/".$ms;
        }
        if (strpos($any,"app/$ms/")!==false) {
            $any = str_replace("app/$ms/","",$any);
        }
        return $any=="" ? $pre : $pre."/".ltrim($any,"/");

        

        /*if (!Uac::requiredAndReady()) return $any;
        $dsn = Uac::$current->usrDb->dsn->dsn;
        //var_dump($dsn);
        if (strpos($dsn,"app/")!==false) {
            $darr = explode("app/", $dsn);
            $darr = explode("/", $darr[1]);
            $ms = strtolower($darr[0]);
            if ($type=="class") {
                $pre = "App/".ucfirst($ms);
            } else {
                $pre = "app/".$ms;
            }
            return $any=="" ? $pre : $pre."/".ltrim($any,"/");
        }
        return $any;*/
    }

}