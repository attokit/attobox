<?php
/**
 * common DbApp for Attokit/Attoorm framework
 */

namespace Atto\Orm;

use Atto\Box\App;
use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Orm\Orm;
use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Atto\Orm\Api;

class DbApp extends App 
{
    //app info
    public $intr = "atto-orm通用数据库App";  //app说明，子类覆盖
    public $name = "DbApp";  //app名称，子类覆盖
    public $key = "Atto/Orm/DbApp";   //app调用路径

    /**
     * 与 当前 dbapp 关联的数据库连接参数
     * !! 可以有多个关联数据库 !!
     * 子类覆盖
     */
    protected $dbOptions = [
        //... 子类覆盖
        //必须指定 main 数据库
        //"main" => [
        //    "type" => "sqlite",
        //    "database" => "uac.db"  //保存在默认位置，如果要保存在其他位置，使用 ../ 起始为 app/appname/db/sqlite
        //],
    ];
    //默认的 sqlite 数据库保存位置
    protected $dftSqliteDir = "db/sqlite";

    /**
     * 缓存实例
     */
    //当前 dbapp 关联的数据库实例
    public $dbs = [];
    //当前登录到系统的 用户 model 实例
    public $usr = null;

    /**
     * 当前会话 要操作的 Dbo实例 / Model类 指针
     * 通过 URI 指定
     */
    protected $currentDb = null;
    protected $currentModel = "";
    //当前会话 post data
    protected $post = [];

    /**
     * 当前 dbapp 提供的数据库服务 初始化
     * 在 DbApp 实例化后立即执行
     */
    protected function init() 
    {
        //创建事件订阅，订阅者为此 DbApp
        Orm::eventRegist($this);

        //调用 Orm::Init()
        Orm::Init($this->name);

        //连接并创建数据库实例
        $this->initDb();

        //缓存这个 DbApp 实例到 Orm::$APP
        Orm::cacheApp($this);

        //触发 DbApp 实例化完成事件
        Orm::eventTrigger('app-insed', $this);

        return $this;
    }

    /**
     * 初始化工具
     * 连接数据库 创建数据库实例
     * @return Dbo instances []
     */
    protected function initDb()
    {
        $dbns = $this->dbns();
        foreach ($dbns as $i => $dbn) {
            //如果还未创建数据库实例的，连接数据库，创建并数据库实例
            $this->dbConnect($dbn);
        }
        return $this;
    }

    /**
     * __GET 方法
     * @param String $key 
     */
    public function __get($key)
    {
        /**
         * $app->Main 首字母必须大写
         * 访问当前 DbApp 下的数据库实例
         */
        if (str_upcase_start($key) && $this->hasDb(strtolower($key))) {
            return $this->db(strtolower($key));
        }

        /**
         * $app->db
         * 访问 $app->currentDb
         */
        if ($key=="db") {
            return $this->currentDb;
        }

        /**
         * $app->model
         * 访问 $app->currentDb->{$app->currentModel::$name}
         * 相当于 $app->Currentdb->Currentmodel
         */
        if ($key=="model") {
            $db = $this->db;
            if ($db instanceof Dbo) {
                $model = $this->currentModel;
                if (class_exists($model)) {
                    $tbn = ucfirst($model::$name);
                    if (is_notempty_str($tbn)) {
                        return $db->$tbn;
                    }
                }
            }
            //未指定当前会话 要操作的 Dbo实例 / Model类 操作指针 则返回 null
            return null;
        }

        /**
         * $app->mainDb
         * 获取当前 DbApp 下的数据库实例
         */
        //if (substr($key, -2)=="Db") {
        //    $dbn = substr($key, 0, -2);
        //    return $this->db($dbn);
        //}
        
    }



    /**
     * 外部访问 入口
     * https://db.cgy.design/[DbApp]/...
     *      
     * 
     * @param Array $args URI
     * @return Mixed
     */
    public function defaultRoute(...$args)
    {
        //空 URI
        if (empty($args)) {

            exit;
        }

        //post data
        $pd = Request::input("json");
        $this->post = empty($pd) ? [] : $pd;

        //URI = create 创建数据库
        

        //URI = [action|tbn]/...
        if ($this->hasDb($args[0])!==true) {
            //未指定 dbn 则默认使用 main 作为当前数据库实例 currentDb
            $this->currentDb = $this->db("main");
            //调用 foobarAction() 
            if (method_exists($this, $args[0]."Action")) {
                $m = array_shift($args)."Action";
                return $this->$m(...$args);
            }
        }

        //URI = dbn/...
        if ($this->hasDb($args[0])===true) {
            $dbn = array_shift($args);
            $this->currentDb = $this->db($dbn);
        }

        //dbn error
        $db = $this->db;    //$app->currentDb
        if (!$db instanceof Dbo) {
            //加载数据库发生错误
            trigger_error("custom::无法正确的加载数据库", E_USER_ERROR);
            exit;
        }

        //URI = dbn
        if (empty($args)) {
            //数据库默认页

            exit;
        }

        //URI = dbn/action/...
        if ($db->hasModel($args[0])!==true) {
            //访问 数据库实例方法
            $m = array_shift($args)."Action";
            if (method_exists($db, $m)) {
                return $this->$m(...$args);
            }
            Response::code(404);
        }

        //URI = dbn/tbn/...
        if ($db->hasModel($args[0])===true) {
            $model = array_shift($args);
            $this->currentModel = $db->getModel($model);
        }

        //model name error
        $model = $this->model;
        if (empty($model) || $model==null) {
            //初始化 Model 类失败
            trigger_error("custom::无法正确的初始化数据表", E_USER_ERROR);
            exit;
        }

        //URI = dbn/tbn
        if (empty($args)) {
            //数据表默认页 默认输出 数据表参数 
            $conf = $this->model->configer;
            return $conf->context;
            exit;
        }

        //URI = dbn/tbn/apiname/... 调用 Model 类 or 实例 Api
        $api = Api::init($args[0], $this);
        if ($api instanceof Api) {
            //执行 api 方法
            array_shift($args);     //api 方法名
            return $api->run(...$args);
        }

        //无 命中操作
        Response::code(404);

    }



    /**
     * 数据库工具
     */

    /**
     * 连接数据库，创建并返回数据库实例
     * @param String $dbn 数据库名称 $this->dbOptions 包含的键名
     * @return Dbo instance || null
     */
    public function dbConnect($dbn)
    {
        $dbi = $this->db($dbn);
        if (empty($dbi)) {
            //还未创建数据库实例，则连接并创建
            $opti = $this->fixDbPath($dbn);
            //var_dump($opti);
            if (empty($opti)) $opti = $this->dbOptions[$dbn];
            $dbi = Dbo::connect($opti);
            if ($dbi instanceof Dbo) {
                //创建事件订阅，订阅者为此 Dbo 实例
                Orm::eventRegist($dbi);
                //依赖注入
                $dbi->dependency([
                    //将当前 dbapp 注入 数据库实例
                    "app" => $this,
                    //注入数据库 键名
                    "keyInApp" => $dbn,
                ]);
                $this->dbs[$dbn] = $dbi;
                //触发 数据库实例化事件
                Orm::eventTrigger("dbo-insed", $dbi);
                return $dbi;
            } else {
                return null;
            }
        }
        return null;
    }

    /**
     * 获取已创建的数据库实例
     * @param String $dbn 数据库名称 $this->dbOptions 包含的键名
     * @return Dbo instance || null
     */
    protected function db($dbn="")
    {
        $dbn = $dbn=="" ? "main" : $dbn;    //默认返回 main 数据库
        if (!is_notempty_str($dbn) || !isset($this->dbOptions[$dbn])) return null;
        $dbs = $this->dbs;
        if (empty($dbs) || !isset($dbs[$dbn]) || !$dbs[$dbn] instanceof Dbo) return null;
        return $dbs[$dbn];
    }

    /**
     * 获取与此 dbapp 关联的所有 数据库键名
     * @return Array [ 键名, 键名, ... ]
     */
    protected function dbns()
    {
        return array_keys($this->dbOptions);
    }

    /**
     * 判断是否包含 Dbo 数据库
     * @param String $dbn 数据库名称 $this->dbOptions 包含的键名
     * @return Bool
     */
    public function hasDb($dbn)
    {
        return isset($this->dbOptions[$dbn]);
    }

    /**
     * 判断数据库是否已经实例化
     * @param String $dbn 数据库名称 $this->dbOptions 包含的键名
     * @return Bool
     */
    public function dbConnected($dbn)
    {
        $db = $this->db($dbn);
        return !empty($db) && $db instanceof Dbo;
    }

    /**
     * fix sqlite 数据库路径
     * 默认的存放路径：[app/appname]/[$this->dftSqliteDir]
     * @param String $dbn 数据库键名
     * @return Array 处理后的数据库连接参数
     */
    protected function fixDbPath($dbn="")
    {   
        $dbn = $dbn=="" ? "main" : $dbn;
        $dbs = $this->dbOptions;
        if (!is_notempty_str($dbn) || !isset($dbs[$dbn])) return null;
        $opt = $dbs[$dbn];
        $type = $opt["type"] ?? "sqlite";
        if ($type!="sqlite") return null;
        $database = $opt["database"] ?? null;
        $parr = [];
        $parr[] = $this->dftSqliteDir;
        if (is_notempty_str($database)) {
            $parr[] = $database;
        } else {
            $parr[] = $dbn;
        }
        $dbp = implode("/", $parr);
        if (substr($dbp, -3)!=".db") $dbp .= ".db";
        return [
            "type" => "sqlite",
            "database" => path_fix($this->path($dbp))
        ];
    }



    /**
     * event-handler 订阅事件 处理方法
     */

    /**
     * 处理 dbo-insed 事件
     * @param Dbo $db 数据库实例
     * @return void
     */
    public function ormEventHandlerDboInsed($db)
    {
        //var_dump($db->info());
        //var_dump("dbo-insed event handle by dbapp：".$this->name);
    }

    /**
     * 处理 model-inited 事件
     * @param String $model 数据表(模型) 类
     * @return void
     */
    public function ormEventHandlerModelInited($model)
    {
        //var_dump($model);
        //var_dump("model-inited event handle by dbapp：".$this->name);
    }


}