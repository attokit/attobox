<?php
/**
 *  Attobox Framework / 可复用的类特征
 *  app/Uac
 * 
 *  使 app 类拥有 uac 权限控制能力
 *  如果需要对 app 进行权限控制，应在类实现时 use trait
 * 
 * 为 app 增加 uac 功能的步骤：
 *  1   创建 app/[appname]/library/config.php 配置文件，配置项参照 [box]/modules/uac/config.php
 *          根据 usr 数据库实际存放位置来配置 UAC_DB，建议存放在 app 路径下，除非有多个 usr 数据库
 *  2   app 类 use UacTrait
 *  3   建立 app/[appname]/library/db 或 [webroot]/library/db 文件夹，777权限
 *      建立 app/[appname]/library/db/config 或 [webroot]/library/db/config 文件夹，777权限
 *  4   复制一份 usr.db 到相应的 library/db 文件夹，根据 $usrDsn 设置来修改数据库名称
 *          建议使用默认的名称 usr，除非有多个 usr 数据库
 *  5   建立 app/[appname]/library/jwt 文件夹
 *      建立 app/[appname]/library/jwt/secret 文件夹，777权限
 *      建立 app/[appname]/library/uac 文件夹
 *  6   复制一份 JwtAudParser.php 到 library/jwt
 *          根据需要 修改 JwtAudParser.php
 *      复制一份 *msOperationsParser.php 到 library/uac ，将名称改为 [Appname]OperationsParser.php
 *          根据实际需要，在 [Appname]OperationsParser.php 类内部 实现 根据用户权限获取用户可用 操作/nav菜单/api 的方法
 *  7   建立 app/[appname]/assets/nav 文件夹，其中放置用于 spa 单页应用的 各 nav 对应的 vue 组件页面
 *          此类 vue 组件需要在头部指定 <profile> 段，内含 json 数据格式如下
 *              <profile>{
 *                  "sort": 102,    //navid
 *                  "name": "cv-install",   //nav name 可不填
 *                  "title": "cVue 开发框架的安装与部署",   //nav 说明
 *                  "label": "安装与部署",  //nav 标题
 *                  "icon": "vant-download"     //nav icon
 *              }</profile>
 *  8   在 [webroot]/route 文件夹下创建 Db.php 继承 [box]/route/Dbm.php 路由
 *          此路由用来生成 api-db-*** 列表，仅继承即可，不需要做任何修改
 * 
 * !!! uac 设置均位于 app 下，因此所有系统级路由都必须通过 app 劫持后才能使 uac 设置生效
 * !!! 如：uac、dbm 等路由必须通过 [host]/[appname]/uac、[host]/[appname]/db 访问
 *  
 *  //6   访问： [host]/[appname]/dev/install/uac 将自动 根据设置 创建 library/db/config/[dbname].json
 *  //7   访问： [host]/[appname]/dev/test/uac 将测试连接 usr 数据库，检查 数据库 api 是否正常
 *    
 */

namespace Atto\Box\traits\app;

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
use Atto\Box\Resource;

trait UacTrait
{
    /**
     * 默认的 nav vue 组件 profile
     */
    public $dftNavProfile = [
        "sort"      => 999999,
        /*"import"    => [
            "url" => "",
            "name" => "",
            "camel" => ""
        ],*/
        "app"       => "",
        "name"      => "",
        "opr"       => "",
        "title"     => "",
        "label"     => "",
        "icon"      => ""
    ];



    /**
     * tools
     */

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
     * Uac 权限判断，调用 Uac::grant($opr)
     * @param String $opr
     * @param Bool $throwError 是否输出错误，默认输出
     * @return Bool
     */
    public function grant($opr, $throwError = true)
    {
        if (Uac::grant($opr)!==true) {
            if ($throwError) {
                trigger_error("uac/denied::".$opr, E_USER_ERROR);
                exit;
            }
            return false;
        }
        return true;
    }

    /**
     * tools
     * api 权限判断
     * @param String $apin
     * @param Bool $throwError 是否输出错误，默认输出
     * @return Bool
     */
    public function apiGrant($apin, $throwError = true)
    {
        $uacOprName = "api-app-".strtolower($this->name)."-".strtolower($apin);
        return $this->grant($uacOprName, $throwError);
    }

    /**
     * tools
     * 设定 uac_db
     * 默认的 usr 数据库位于 app/[appname]/library/db/usr.db
     * @param String $dsn 用作 uac 控制的 usr 数据库的连接 dsn，不指定则使用 $this->usrDsn 设置值，仍为空 则使用默认的 app/appname/usr
     * @return Bool
     * 
     * !!! 无论是否使用默认数据库，数据库的结构应与默认一致
     */
    /*public function setUacDb($dsn=null)
    {
        $dsn = !is_notempty_str($dsn) ? $this->usrDsn : $dsn;
        $dsn = !is_notempty_str($dsn) ? "usr" : $dsn;
        $appn = strtolower($this->name);
        if (strpos($dsn, "root/")===false) {
            $dsn = "app/$appn/$dsn";
        } else {
            $dsn = str_replace("root/","",$dsn);
        }
        $dsno = Dsn::load($dsn);
        if ($dsno->dbNotExists) return false;
        //写入 session
        session_set("uac_db", $dsn);
        return true;
    }*/

    /**
     * tools for dev
     * 自动安装 uac 权限控制必须的 数据库以及文件
     * @return Bool
     */
    protected function devInstallUac()
    {

    }



    /**
     * uac 相关功能入口
     * 通过访问这些入口，实现 uac 管理功能
     */

    /**
     * uac dev
     * !!! 用于开发阶段
     * url:
     *      [host]/[appname]/dev
     */
    public function dev(...$args)
    {
        session_del("uac_db");
        //临时设置 session("uac_uid")
        session_set("uac_uid", "1");
        //session_del("uac_uid");
    }

    /**
     * 提供默认的 spa 单页应用入口
     * 直接调用 cVue 框架生成应用界面，拥有 uac 完整功能
     */
    public function spa(...$args)
    {
        //调用的页面文件位于 app/[appname]/page/spa.php
        //如果不存在，则使用 box/page/spa.php
        $pg = $this->path("page/spa.php");
        if (!file_exists($pg)) $pg = path_find("box/page/spa.php");
        Response::page($pg, [
            "app" => $this,
            "args" => $args
        ]);
    }

    /**
     * 调用 api
     * url：
     *      [host]/[appname]/api/foo/bar/jaz        访问 app->apiFoo(bar,jaz)
     *      [host]/[appname]/api/dbn/tbn/foobar     访问 Record->apiFoobar()
     */
    public function api(...$args)
    {
        $apin = $args[0];
        $apim = "api".ucfirst(strtolower($apin));
        if (method_exists($this, $apim)) {
            if ($this->apiGrant($apin)===true) {
                array_shift($args);
                return $this->$apim(...$args);
            }
        } else {
            $apiarr = $this->getRecordApi(...$args);
            if (is_null($apiarr)) Response::code(404);
            $oprName = $apiarr["oprName"];
            if ($this->apiGrant($oprName)===true) {
                $apim = $apiarr["api"];
                $record = $apiarr["record"];
                $args = $apiarr["args"];
                return call_user_func_array([$record, $apim], $args);
            }
        }
    }

    /**
     * tools
     * 根据给定的 $args 尝试获取 record api 方法
     * @param String $args
     * @return Array  or  null
     */
    public function getRecordApi(...$args)
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
     * 调用用户有权限的 nav 页面 
     * !!! 仅针对 vue 组件页面，用于 spa 单页应用中 用户点击相对应的 nav 菜单，调用 vue 组件
     * !!! 普通 nav 页面 应在对应的 route 方法中直接加上 if($app->grant(权限名称, 是否抛出错误)===true) { ...方法逻辑 }
     * url:
     *      [host]/[appname]/nav/foo/bar    访问 assets/nav/foo/bar.vue 输出为 js，组件 name = foo-bar
     *      [host]/[appname]/nav            查找当前用户拥有权限的所有 nav 列表
     */
    public function nav(...$args)
    {
        if (empty($args)) {
            //劫持 usr/nav 路由，获取用户权限的 nav 列表
            $args = ["usr","nav"];
            return $this->hijack($args);
        }
        $navdir = $this->path("assets/nav");
        $file = str_replace(".vue","",$navdir.DS.implode(DS, $args)).".vue";
        $fn = str_replace(".vue","",implode("-", $args));
        if (file_exists($file)) {
            $res = Resource::create($file, [
                "export" => "js",
                "name" => $fn
            ]);
            $res->returnContent();
            //var_dump($res->profile);exit;
            $dft = $this->dftNavProfile;
            $dft["app"] = strtolower($this->name);
            $dft["name"] = $fn;
            $dft["opr"] = "nav-".$fn;
            $set = $res->profile;
            $profile = arr_extend($dft, $set);
            //输出 vue 为 js 前注入 处理过的 profile
            $res->export([
                "profile" => $profile
            ]);
            exit;
        }
        Response::code(404);
    }

    /**
     * 劫持 usr 路由
     * url:
     *      [host]/[appname]/usr/foo/bar    相当于访问 [host]/usr/foo/bar 但是拥有 app usr config
     */
    public function usr(...$args)
    {
        array_unshift($args, "usr");
        return $this->hijack($args);
    }

    /**
     * 劫持 dbm 路由
     * url:
     *      [host]/[appname]/db/dbn/tbn/..      相当于访问 [host]/dbm/dbn/tbn/.. 但是拥有 app uac config
     */
    public function db(...$args)
    {
        array_unshift($args, "dbm");
        return $this->hijack($args);
    }


    /**
     * 输出下拉列表选项
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
    /*public function loginPage($extra=[])
    {
        //指定 Uac 用户数据库信息，并写入 session
        session_set("uac_db", "app/".strtolower($this->name)."/usr");
        Uac::$current->initUsrDb();
        //下发登录界面
        Response::page($this->path("page/login.php"), arr_extend([
            "from" => Request::$current->url->full
        ], $extra));
    }*/



    /**
     * navs
     */

    /**
     * nav
     * <%nav: 这是一个 nav 测试%>
     */
    public function navtst(...$args)
    {
        console.log($args);
    }
}