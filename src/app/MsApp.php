<?php

namespace Atto\Box\app;

use Atto\Box\App;
use Atto\Box\Request;
use Atto\Box\request\Url;
use Atto\Box\Uac;
use Atto\Box\Router;
use Atto\Box\Response;
use Atto\Box\Db;
use Atto\Box\db\Dsn;
use Atto\Box\db\Table;
use Atto\Box\Record;
use Atto\Box\Ms;

class MsApp extends App
{
    //app info
    public $intr = "*MS系统基类，扩展自App系统类";  //app说明，子类覆盖
    public $name = "MsApp";  //app名称，子类覆盖
    public $key = "Atto/Box/MsApp";   //app调用路径

    /**
     * 覆盖系统 App 类的 defaultRoute 入口方法
     * *ms 管理器入口
     * 管理页面入口：   [host]/*ms
     * 调用 api：      [host]/*ms/api/dbn/tbn/foobar  -->  Record->apiFoobar()
     * 劫持其他路由：   [host]/*ms/dbm/foo/bar/jaz  -->  相当于访问 [host]/dbm/foo/bar/jaz
     */
    public function defaultRoute(...$args)
    {
        if ($args[0]=="api") {
            //执行api
            array_shift($args); //api
            $apin = $args[0];
            $apim = "api".ucfirst(strtolower($apin));
            if (method_exists($this, $apim)) {
                //直接访问在 *ms-app 类中定义的 api，*ms-->apiFoobar
                if ($this->uacGrant($apin)===true) {
                    array_shift($args);
                    return $this->$apim(...$args);
                }
            } else {
                //在指定的 table record 实例中查找 api
                $apiarr = $this->getRecordApi(...$args);
                if (is_null($apiarr)) Response::code(404);
                $oprName = $apiarr["oprName"];
                if ($this->uacGrant($oprName)===true) {
                    $apim = $apiarr["api"];
                    $record = $apiarr["record"];
                    $args = $apiarr["args"];
                    return call_user_func_array([$record, $apim], $args);
                }
            }
        } else if (empty($args)) {
            /**
             * *ms 管理器入口
             * !!! spa 单页应用，所有管理器功能都通过此入口
             */
            //首先查找 spa 入口文件
            //入口页面通过 app config 定义 
            $app = strtolower($this->name);
            $cnst = "APP_".strtoupper($app)."_SPA_INDEX";
            $spaf = defined($cnst) ? constant($cnst) : "app/$app/page/spa.php";
            $index = path_find($spaf);
            if (!file_exists($index)) Response::code(404);

            /** 
             * 默认情况下 *ms 管理器需要 uac 用户权限验证
             * 用户数据库预设应在 app/[appname]/library/config.php 中定义
             * 预设的用户数据库可以通过常量 APP_[APPNAME]_USR_DB 访问到
             * 用户数据库结构应与默认结构一致 必须包含 usr/usr,role 两张表
             * 数据库保存路径应与 config.php 中指定的位置一致
             * 通常应保存在
             *      app/[appname]/library/db 路径下 
             */
            //获取 app config 中指定的 uac 参数
            $uac_ctrl = Uac::getUacConfig("ctrl");
            if ($uac_ctrl!==false) {
                //默认启用 uac 权限控制
                if (Uac::islogin()===true) {
                    //用户已登录，跳转到 spa 入口 page

                    /**
                     * 跳转 spa 入口页面前，执行初始化方法，生成 入口页面参数
                     */
                    $uac = Uac::start();
                    $usr = $uac->usr;   //usr record
                    $pps = [
                        "uac" => Uac::start(),
                    ];
                    //读取当前用户有权限的 nav 导航列表


                    Response::page($index, $pps);
                } else {
                    //用户还未登录，跳转登录页面
                    return $this->loginPage();
                }
            } else {
                //默认不启用 uac 权限控制，直接启动 单页应用 spa 入口
                Response::page($index);
            }
        } else {
            //尝试劫持路由，调用 Router 的构造方法，查找目标 route
            return $this->hijack($args);
            /*$route = Router::seek($args);
            $emptyRoutes = [
                "\\Atto\\Box\\route\\Base",
                "\\Atto\\Box\\route\\Web"
            ];
            if (in_array($route[0], $emptyRoutes) && $route[1]=="defaultMethod") {
                //未查找到有效 route
                Response::code(404);
            } else {
                //var_dump($route);
                //$_route = $route[0];
                //$_method = $route[1];
                //$_args = $route[2];
                //执行劫持到的 Route 方法
                return Router::exec(...$route);
            }*/
        }
        exit;
    }



    /**
     * tools
     */

    /**
     * 根据给定的 $args 尝试获取 record api 方法
     * 可通过 https://domain/*ms/api/dbn/tbn/foobar 访问 record 实例的 apiFoobar 方法
     * @param String $args
     * @return Array  or  null
     */
    protected function getRecordApi(...$args)
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
    }

    /**
     * tools
     * Uac 权限判断，调用 Uac::grant($opr)
     * 在 *ms-app 类内部，调用 Uac::grant($opr) 方法判断用户权限
     * @param String $apin
     * @param Bool $throwError 是否输出错误，默认输出
     * @return Bool
     */
    protected function uacGrant($apin, $throwError = true)
    {
        $uacOprName = "api-app-".strtolower($this->name)."-".strtolower($apin);
        if (Uac::requiredAndReady() && Uac::grant($uacOprName)!==true) {
            if ($throwError) {
                trigger_error("uac/denied::".$uacOprName, E_USER_ERROR);
                exit;
            }
            return false;
        }
        return true;
    }

    /**
     * 输出预定义的下拉列表选项内容，通常用于 管理器表单中相应字段显示
     * 预定义的下拉列表内容必须保存在 app/[appname]/assets/selectOptions.json 中
     * @return Array
     */
    public function selectOptions(...$args)
    {
        if (empty($args)) return [];
        $json = $this->path("assets/selectOptions.json");
        if (!file_exists($json)) return [];
        $ja = j2a(file_get_contents($json));
        if (!is_notempty_arr($ja) || !is_associate($ja)) return [];
        $key = implode("/", $args);
        $opts = arr_item($ja, $key);
        if (empty($opts) || !is_indexed($opts)) return [];
        $oi = $opts[0];
        if (isset($oi["value"]) && isset($oi["label"])) {
            return $opts;
        } else if (is_associate($oi)) {
            return $opts;
        } else if (is_string($oi) || is_numeric($oi)) {
            $os = [];
            $os[] = [
                "label" => "请选择",
                "value" => ""
            ];
            for ($i=0;$i<count($opts);$i++) {
                $os[] = [
                    "label" => $opts[$i],
                    "value" => $opts[$i]
                ];
            }
            return $os;
        }
        return [];
    }

    /**
     * 显示登录界面
     * 通过 Uac->jumpToUsrLogin() 方法调用
     * @param Array $extra 在登录界面显示的额外信息
     * @return void
     */
    public function __loginPage($extra=[])
    {
        //指定 Uac 用户数据库信息，并写入 session
        session_set("uac_db", "app/".strtolower($this->name)."/usr");
        Uac::$current->initUsrDb();
        //var_dump(session_get("uac_db"));
        //下发登录界面
        Response::page($this->path("page/login.php"), arr_extend([
            "from" => Request::$current->url->full
        ], $extra));
    }

}