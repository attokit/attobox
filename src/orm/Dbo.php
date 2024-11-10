<?php
/**
 * 数据库类 
 * 此类可直接操作数据库
 * 单例模式，一次会话只实例化一次
 * 
 * 创建 Db 实例：
 *      $db = Dbo::connect([ Medoo Options ])
 * 依赖注入：
 *      $db->setDbApp($app)     关联到 DbApp
 * 初始化一个 curd 操作，链式调用：
 *      $db->Model->join(false)->field("*")->where([...])->limit([0,20])->order(["foo"=>"DESC"])->select();
 * 获取数据表(模型)类：
 *      $model = $db->Model;
 *      $conf = $model::$configer;
 *      $name = $model::$name;
 * 获取数据表(模型)实例，以 实例方式 调用 类方法/类属性
 *      $table = $db->ModelTb;
 *      $conf = $table->tbConfiger;
 *      $name = $table->tbName;
 */

namespace Atto\Orm;

use Atto\Orm\Orm;
use Atto\Orm\DbApp;
use Atto\Orm\Table;
use Atto\Orm\Model;
use Atto\Orm\Curd;
use Medoo\Medoo;
use Atto\Box\Request;
use Atto\Box\Response;

class Dbo
{
    //缓存已创建的 数据库实例
    public static $CACHE = [/*
        "DB_KEY" => Dbo instance,
    */];

    //默认的数据库文件存放路径 [app/appname]/db
    protected static $DBDIR = "db";

    //缓存已初始化的 数据表(模型) 类全称
    public $initedModels = [];

    //缓存已实例化的 数据表实例
    public $TABLES = [/*
        "table name" => Table instance
    */];

    /**
     * Db config
     */
    public $type = "";      //db type
    public $connectOptions = [];    //缓存的 medoo 连接参数
    public $name = "";
    public $key = "";       //md5(path_fix($db->filepath))
    public $pathinfo = [];  //sqlite db file pathinfo
    //public $configer = [    //缓存 已初始化的 数据表(模型) 类的 configer 对象
        //"table name" => $model::configer
    //];

    //数据库 driver 类
    public $driver = "";    //数据库类型驱动类
    
    //medoo 实例
    protected $_medoo = null;

    /**
     * 数据库实例 内部指针，指向当前操作的 model 类全称
     * 后指定的 覆盖 之前指定的
     */
    protected $currentModel = "";

    /**
     * Curd 操作类实例
     * 在每次 Curd 完成后 销毁
     */
    public $curd = null;

    //此数据库实例 挂载到的 dbapp 实例，即：$dbapp->Main == $this
    public $app = null;
    //此数据库实例在 dbapp 中的 键名，如：$app->Main  -->  main
    public $keyInApp = "";

    /**
     * 构造 数据库实例
     * @param Array $options Medoo实例创建参数
     */
    public function __construct($options = [])
    {
        $this->connectOptions = $options;
        $this->medooConnect();
    }

    /**
     * 依赖注入
     * @param Array $di 要注入 数据库实例 的依赖对象，应包含：
     *  [
     *      "app" => 此 数据库实例 所关联到的 DbApp 实例
     *      "keyInApp" => 此数据库实例在 dbapp 中的 键名
     *  ]
     * @return void
     */
    public function dependency($di=[])
    {
        //注入 关联 DbApp 实例
        $app = $di["app"] ?? null;
        if ($app instanceof DbApp) {
            $this->app = $app;
        }

        //注入 此数据库实例在 dbapp 中的 键名
        $this->keyInApp = $di["keyInApp"] ?? "";


        return $this;
    }

    /**
     * 输出 db 数据库信息
     * @param String $xpath 访问数据库信息
     * @return Array
     */
    public function info($xpath="")
    {
        $ks = explode(",", "type,connectOptions,name,key,pathinfo,driver");
        $info = [];
        foreach ($ks as $i => $k) {
            $info[$k] = $this->$k;
        }
        if ($this->app instanceof DbApp) {
            $info["app"] = $this->app->name;
        }
        if ($xpath=="") return $info;
        return arr_item($info, $xpath);
    }

    /**
     * 获取当前数据库所属 DbApp 实例下 其他数据库实例
     * 调用 $app->fooDb
     * @param String $dbn 在 DbApp 中定义的 dbOptions 参数的键名
     * @return Dbo 数据库实例
     */
    public function getDbo($dbn)
    {
        if (!$this->app instanceof DbApp) return null;
        $k = strtolower($dbn)."Db";
        return $this->app->$k;
    }

    /**
     * 获取当前数据库中 数据表(模型)类 全称
     * 并对此 数据表(模型) 类 做预处理，注入依赖 等
     * @param String $model 表(模型)名称 如：Usr
     * @return String 类全称
     */
    public function getModel($model)
    {
        $mcls = $this->getModelCls($model);
        if (!class_exists($mcls)) return null;
        if ($this->modelInited($model)!==true) {
            //类全称
            $mcls::$cls = $mcls;
            //创建事件订阅，订阅者为此 数据表(模型)类
            Orm::eventRegist($mcls);
            //依赖注入
            $mcls::dependency([
                //将当前 数据库实例 注入 数据表(模型) 类
                "db" => $this
            ]);
            //解析表预设参数
            $mcls::parseConfig();
            //缓存 mcls
            $this->initedModels[] = $mcls;
            //触发 数据表(模型) 类初始化事件
            Orm::eventTrigger("model-inited", $mcls);
        }
        return $mcls;
    }

    /**
     * 根据输入的 model name 获取 类全称
     * @param String $model 名称
     * @return String 类全称
     */
    public function getModelCls($model)
    {
        if (!$this->app instanceof DbApp) return null;
        $appname = $this->app->name;
        $mpre = $appname."/model";
        $dpre = $mpre."/".$this->name;
        $mcls = Orm::cls($dpre."/".ucfirst($model));
        if (!class_exists($mcls)) return null;
        return $mcls;
    }

    /**
     * 获取所有定义的 数据表(模型) 类
     * @param Bool $init 是否初始化这些类 默认 false
     * @return Array [ 类全称, ... ]
     */
    public function getAllModels($init=false)
    {
        if (!$this->app instanceof DbApp) return [];
        $app = $this->app;
        $mdir = $app->path("model/".$this->name);
        if (!is_dir($mdir)) return [];
        $mclss = [];
        $dh = @opendir($mdir);
        while(($f=readdir($dh))!==false) {
            if ($f=="." || $f=="..") continue;
            if (is_dir($mdir.DS.$f)) continue;
            if (substr($f, -4)!=EXT) continue;
            $model = str_replace(EXT,"",$f);
            $mcls = $this->getModelCls($model);
            if (empty($mcls)) continue;
            if ($init) {
                $mclss[] = $this->getModel($model);
            } else {
                $mclss[] = $mcls;
            }
        }
        return $mclss;
    }

    /**
     * 获取所有定义的 数据表(模型) 类名称
     * @param Bool $init 是否初始化这些类 默认 false
     * @return Array [ 类名称, ... ]
     */
    public function getAllModelNames($init=false)
    {
        $mclss = $this->getAllModels($init);
        if (empty($mclss)) return [];
        $mns = array_map(function ($i) {
            $ia = explode("\\", $i);
            return array_pop($ia);
        }, $mclss);
        return $mns;
    }

    /**
     * 判断 数据表(模型) 是否存在
     * @param String $model 表(模型)名称 如：Usr
     * @return Bool
     */
    public function hasModel($model)
    {
        $mcls = $this->getModelCls($model);
        return !empty($mcls);
    }

    /**
     * 判断 数据表(模型) 类是否已经初始化
     * @param String $model 表(模型)名称 如：Usr
     * @return Bool
     */
    public function modelInited($model)
    {
        $mcls = $this->getModelCls($model);
        if (!class_exists($mcls)) return false;
        $inited = $this->initedModels;
        return in_array($mcls, $inited) && !empty($mcls::$db) && $mcls::$db instanceof Dbo;
    }

    /**
     * __get 方法
     * @param String $key
     * @return Mixed
     */
    public function __get($key)
    {
        //var_dump($key);
        /**
         * $db->Model 
         * 将数据库实例内部指针 currentModel 指向 当前的 model 类
         * 同时 初始化一个 针对此 model 的 curd 操作，准备执行 curd 操作
         */
        if ($this->hasModel($key)) {
            $mcls = $this->getModel($key);
            //指针指向 model 类全称
            $this->currentModel = $mcls;
            //准备 curd 操作
            if ($this->curdInited()!=true || $this->curd->model::$name!=$key) {
                //仅当 curd 操作未初始化，或 当前 curd 操作为针对 此 数据表(模型) 类 时，重新初始化 curd
                $this->curdInit($key);
            }
            //返回 $db 自身，准备接收下一步操作指令
            return $this;
        }

        /**
         * 如果内部指针 currentModel 不为空
         */
        if ($this->currentModel!="") {
            $model = $this->currentModel;

            /**
             * $db->Model->property 
             * 访问 数据表(模型) 类属性 静态属性
             */
            if (cls_hasp($model, $key, 'static,public')) {
                return $model::$$key;
            }

            /**
             * 如果 curd 操作已被初始化为 针对 此 model
             */
            //if ($this->curdInited && $this->curd->model == $model) {
            //    $curd = $this->curd;
                /**
                 * $db->Model->curdProperty
                 * 访问 curd 操作实例的 属性
                 * !! 不推荐，推荐：$db->Model->curd->property
                 */
            //    if (property_exists($curd, $key)) {
            //        return $curd->$key;
            //    }
            //}

        }

        /**
         * $db->api***
         * 返回所有已初始化的 数据表(模型) api 数据
         */
        if (substr($key, 0,3)==="api") {
            $api = [];
            $mclss = $this->getAllModels(true);     //初始化所有 数据表(模型) 类
            foreach ($mclss as $i => $mcls){
                if (empty($mcls::$configer)) continue;
                $mapi = $mcls::$configer->api;
                if (empty($mapi)) continue;
                $api = array_merge($api, $mapi);
            }
            //$db->api 返回所有已初始化的 数据表(模型) api 数据 数组
            if ($key=="api") return $api;
            //$db->apis 返回 api 名称数组
            if ($key=="apis") return empty($api) ? [] : array_keys($api);
        }

        return null;
    }

    /**
     * __call medoo method
     */
    public function __call($key, $args)
    {
        /**
         * 如果内部指针 currentModel 不为空
         */
        if ($this->currentModel!="") {
            $model = $this->currentModel;
            /**
             * 如果 curd 操作已被初始化为 针对 此 model
             * 优先执行 curd 操作
             */
            if ($this->curdInited() && $this->curd->model == $model) {
                $curd = $this->curd;
                /**
                 * $db->Model->where() 
                 * 执行 curd 操作
                 * 返回 curd 操作实例  or  操作结果
                 */
                if (method_exists($curd, $key) || $curd->hasWhereMethod($key) || $curd->hasMedooMethod($key)) {
                    $rst = $this->curd->$key(...$args);
                    if ($rst instanceof Curd) return $this;
                    return $rst;
                }
            }

            /**
             * $db->Model->func()
             * 调用 数据表(模型) 类方法 静态方法
             */
            if (cls_hasm($model, $key, "static,public")) {
                $rst = $model::$key(...$args);
                if ($rst == $model) {
                    //如果方法返回 数据表(模型) 类，则返回 $db 自身，等待下一步操作
                    return $this;
                } else {
                    return $rst;
                }
            }
        }
        
        return null;
    }


    /**
     * table 操作
     */
    
    /**
     * 实例化数据表
     * @param String $tbn table name
     * @return Table instance  or  null
     */
    public function __table($tbn)
    {
        if ($this->tableInsed($tbn)) return $this->TABLES[$tbn];
        $tbcls = $this->tableCls($tbn);
        if (empty($tbcls)) return null;
        $tbo = new $tbcls($this);
        $this->TABLES[$tbn] = $tbo;
        return $tbo;
    }

    /**
     * 判断数据表是否已实例化
     * @param String $tbn table name
     * @return Bool
     */
    public function __tableInsed($tbn)
    {
        if (!isset($this->TABLES[$tbn])) return false;
        $tbo = $this->TABLES[$tbn];
        return $tbo instanceof Table;
    }

    /**
     * 获取数据表 类全称
     * @param String $tbn table name
     * @return String table class name
     */
    public function __tableCls($tbn)
    {
        $dbn = $this->name;
        $app = $this->app->name;
        $cls = Orm::cls("$app/table/$dbn/".ucfirst($tbn));
        if (class_exists($cls)) return $cls;
        return null;
    }




    /**
     * medoo 操作
     */

    //创建 medoo 实例
    protected function medooConnect($opt=[])
    {
        $opt = arr_extend($this->connectOptions, $opt);
        $this->_medoo = new Medoo($opt);
        return $this;
    }

    /**
     * get medoo instance  or  call medoo methods
     * @param String $method
     * @param Array $params
     * @return Mixed
     */
    public function medoo($method = null, ...$params)
    {
        if (is_null($this->_medoo)) $this->medooConnect();
        if (!is_notempty_str($method)) return $this->_medoo;
        if (method_exists($this->_medoo, $method)) return $this->_medoo->$method(...$params);
        return null;
    }

    /**
     * 创建表
     * @param String $tbname 表名称
     * @param Array $creation 表结构参数
     * @return Bool
     */
    public function medooCreateTable($tbname, $creation=[])
    {
        if (!isset($creation["id"])) {
            //自动增加 id 字段，自增主键
            $creation["id"] = [
                "INT", "NOT NULL", "AUTO_INCREMENT", "PRIMARY KEY"
            ];
        }
        if (!isset($creation["enable"])) {
            //自动增加 enable 生效字段，默认 1
            $creation["enable"] = [
                "INT", "NOT NULL", "DEFAULT 1"
            ];
        }
        var_dump($creation);
        return $this->_medoo->debug()->create($tbname, $creation);
    }



    /**
     * CURD
     */

    /**
     * 初始化一个 curd 操作
     * @param String $tbn 表(模型) 名称
     * @return $this
     */
    public function curdInit($model)
    {
        $model = $this->getModel($model);
        //var_dump($model);
        if (!empty($model)) {
            $this->curd = new Curd($this, $model);
            //var_dump($this->curd);
        }
        return $this;
    }

    /**
     * 销毁当前 curd 操作实例
     * @return Dbo $this
     */
    public function curdUnset()
    {
        if ($this->curdInited()==true) {
            $this->curd = null;
        }
        return $this;
    }

    /**
     * 执行 curd 操作
     * @param String $method medoo method
     * @param Bool $initCurd 是否重新初始化 curd，默认 true
     * @return Mixed
     */
    public function __curdQuery($method, $initCurd=true)
    {
        if (!$this->curdInited()) return false;
        $table = $this->curd["table"];
        $field = $this->curd["field"];

        $rst = $this->medoo($method, $table, $field);
        if ($initCurd) $this->curdInit();
        
        return $rst;
    }

    /**
     * 判断 curd 是否已被 inited
     * @return Bool
     */
    public function curdInited()
    {
        return !empty($this->curd) && $this->curd instanceof Curd && $this->curd->db->key == $this->key;
    }

    



    /**
     * static
     */

    /**
     * 创建数据库实例
     * @param Array $opt 数据库连接参数
     * @return Dbo 实例
     */
    public static function connect($opt=[])
    {
        $driver = self::getDriver($opt);
        //var_dump($driver);
        if (!empty($driver) && class_exists($driver)) {
            return $driver::connect($opt);
        }
        return null;
    }

    /**
     * 创建数据库
     * @param Array $opt 数据库创建参数：
     *  [
     *      type => sqlite / mysql
     *      其他参数 由 driver 决定其结构
     *  ]
     * @return Bool
     */
    public static function create($opt=[])
    {
        $driver = self::getDriver($opt);
        if (!empty($driver) && class_exists($driver)) {
            return $driver::create($opt);
        }
        return false;
    }



    /**
     * static tools
     */

    //根据连接参数 获取 driver 类
    public static function getDriver($opt=[])
    {
        $type = $opt["type"] ?? "sqlite";
        $driver = Orm::cls("driver/".ucfirst($type));
        if (class_exists($driver)) return $driver;
        return null;
    }

    
}