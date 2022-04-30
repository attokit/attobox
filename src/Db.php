<?php

/**
 * Attobox Framework / Db
 */

namespace Atto\Box;

use Atto\Box\db\Table;
use Atto\Box\db\Curd;
use Medoo\Medoo;

class Db
{
    //loaded DB instances list
    public static $_DBS = [];

    /**
     * DB config structure
     */
    public static $_CONF = [
        "db" => [
            "title" => "",
            "desc" => "",
            "maintable" => null
        ],
        "table" => [
            "title" => "",
            "desc" => "",
            "creation" => [],   //表结构
            "view" => [         //用于前端组件生成的预设参数
                "form" => [     //生成前端表单界面
                    "copy" => [],       //从其他记录复制信息
                    "fieldGroup" => [   //字段分组
                        /*[
                            "label" => "生效以及时间信息",
                            "info" => "设置货品的生效以及时间信息",
                            "fields" => ["ctime","mtime","enable"]
                        ],*/
                    ],
                    "header" => [       //表单头参数
                        "info" => [
    
                        ],
                    ],
                ],
                "list" => [     //生成前端数据表界面
                    "hashpre" => "",    //hash 跳转的 hash 前缀
                    "exp" => "",        //列表输出名称 "\${sku} - \${gid}",
                    "fields" => [       //列表输出的其他列
                        /*[
                            "field" => "category",
                            "style" => "width:10%",
                            "label" => "类型",
                            "value" => "row.category.slice(-1)[0]"
                        ]*/
                    ],
                    "sign" => [         //列表输出的 sign 列
                        "width" => "20%",   //列宽
                        "fields" => [       //sign 的内容
                            "enable" => ["\${enable}==0","失效"],
                            /*"buyonline" => ["==1","网购"],
                            "storage" => ["!='常温保存'","\${storage}"],
                            "redline" => [">0","\${redline}"]*/
                        ]
                    ],
                ],
                "item" => [     //生成前端详情页
    
                ],
            ],
            "operater" => [     //前端 operater 组件的 config 参数
                "form" => [],       //表单组件参数
                "lister" => [],     //列表组件参数
                "detail" => []      //详情页组件参数
            ],
            /*"detail" => [       //前端详情页组件参数
                "times" => null,
                "exptitle" => null,
                "ctrl" => null,
                "links" => null,
                "signs" => null,
                "norms" => null,
                "info" => null,
                "img" => null,
                "header" => [],
            ],*/
            "exportfield" => null,
            "primaryfield" => null,
            "idfield" => null,
            "hash" => null,
            //"computed" => [],   //计算字段（vue计算属性）
            "search" => [],
            //"filter" => [],
            //"sort" => [],
            //"showfields" => [],    //前端显示的字段
            //"subtable" => [],
            //"mastertable" => "self",
            //"default" => [],
            //"fields" => [],
            //"field" => []
        ],
        "field" => [
            "name" => "",
            "title" => "",
            "desc" => "",
            //"savetype" => "",
            //"vartype" => "default",  //字段值在 输出前、写入前 根据此参数调用 dp\model\Parser类 进行处理
            "link" => [
                "model" => null,
                "field" => null,
                "export" => null,
                "enable" => true,
                "where" => null
            ],
            "editable" => true,     //字段值是否可手动修改
            "inptype" => "default",     // =false则在form中不显示
            "inpoption" => [],          //针对inptype的参数
            "inputer" => null,          //处理后的前端输入组件参数
            "values" => [           //字段可选值
                "values" => [
                    //["label"=>"", "value"=>""],...
                ],
                "type" => null,     //可选 settings / tree / query
                "model" => null,    //来源的数据模型
                "field" => [
                    "label" => null,
                    "value" => null
                ],
                "where" => null,     //来源 sql
                "enable" => true
            ],
            "multival" => false,
            "multiadd" => false,
            "multiflt" => false,
            "computed" => false,   //计算字段（vue计算属性）
            "conv" => "",
            "default" => null,  //字段默认值
            "showintable" => true,
            "placeholder" => "",
            "validate" => [],   //表单验证
            "width" => 0    //在前端显示时，此字段占据的宽度，%
        ]
    ];

    /**
     * DB attributes
     */
    public $type = "";          //DB driver, mysql  or  sqlite  or  ...
    protected $dsn = "";        //Data Source Name, mysql:host=127.0.0.1;dbname=dbname  or  sqlite:/foo/bar/db.sqlite
    public $key = "";           //unique DB key, host_127_0_0_1_dbname  or  foo_bar_db
    public $name = "";          //DB name, dbname  or  dbfile name
    public $pathinfo = [];      //DB pathinfo, [host,port,username,password]  or  pathinfo(dbfile path)

    /**
     * DB config
     */
    public $title = "";         //DB title
    public $desc = "";          //DB desc
    public $creation = [];      //db table creation sql, [tbn => sql, ...]
    public $tables = [];        //table name list
    public $table = [];         //table instance list
    public $maintable = null;   //default table instance

    /**
     * DB config (manual config in file dbpath/dbname.php)
     */
    protected $config = [];

    /**
     * connect options for Medoo
     * Medoo Version 2.1.4
     * must override by sub class
     */
    protected $connectOption = [];

    /**
     * instance
     */
    protected $pdo = null;      //PDO instance
    protected $medoo = null;  //Medoo instance
    protected $curd = null;     //current curd operate

    /**
     * construct
     */
    public function __construct($dsn, $conf = null)
    {
        $this->getConnectOption($dsn);
        $this->connect()->init($conf);
    }



    /**
     * connect methods
     */

    /**
     * get connect options for Medoo
     * must override by sub class
     * @param String $dsn   dsn string
     * @return $this
     */
    protected function getConnectOption($dsn)
    {
        //must override by sub class
        //...
        return $this;
    }

    /**
     * connect db by using Medoo
     * @return $this
     */
    protected function connect()
    {
        $this->medoo = new Medoo($this->connectOption);
        return $this;
    }

    /**
     * init db instance: get db info, create table instances, ...
     * must override by sub class
     * @param String | Array $conf      config file path  or  config array
     * @return $this
     */
    protected function init($conf = null)
    {
        //must override by sub class
        //...
        return $this;
    }



    /**
     * curd & medoo methods
     */

    /**
     * create curd operate instance
     * db->curd(table)->field()->where()->order()->limit()->select();
     * @param Array | String $params        curd creation params
     * @return Curd instance
     */
    public function curd($params = null)
    {
        if (is_null($this->curd)) {
            if (is_notempty_str($params) || (is_associate($params) && isset($params["table"]))) {
                if (is_string($params)) $params = ["table"=>$params];
                $this->curd = new Curd($this, $params);
                return $this->curd;
            } else {
                trigger_error("db/needtable", E_USER_ERROR);
            }
        } else {
            if (is_null($params)) return $this->curd;
            return $this->curd->reset($params);
        }
    }

    /**
     * get Medoo instance  or  call Medoo methods
     * @param String $method    Medoo method name
     * @param Array $param      Medoo method needed param
     * @return Mixed
     */
    public function medoo($method = "", $param = [])
    {
        if (empty($method)) return $this->medoo;
        return call_user_func_array([$this->_medoo,$method], $param);
    }

    /**
     * call medoo->query()
     */
    public function query(...$args)
    {
        return $this->medoo()->query(...$args);
    }



    /**
     * common db methods
     */

    /**
     * create table instance
     * must override by sub class
     * @param String $tbn       table name
     * @param Array $conf       table config array
     * @return Table | null
     */
    protected function createTableInstance($tbn, $conf = [])
    {
        //must override by sub class
        //...
        return null;
    }

    /**
     * get(create) table instance
     * @param String $tbn   table name
     * @param Array $conf   custom table load config
     * @param Bool $reload  if force reload
     * @return Table
     */
    public function table($tbn, $conf = [], $reload = false)
    {
        $tbn = strtolower($tbn);
        if ($reload || !isset($this->table[$tbn]) || is_null($this->table[$tbn])) {
            $this->table[$tbn] = $this->createTableInstance($tbn, $conf);
        }
        return $this->table[$tbn];
    }

    /**
     * create not-cached table instance, not cached in db->table[tbn]
     * @param String $tbn   table name
     * @param Array $conf   custom table load config
     * @return Table
     */
    public function unCachedTable($tbn, $conf = [])
    {
        return $this->createTableInstance($tbn, $conf);
    }

    /**
     * check if has table
     * @param String $tbn   table name
     * @return Bool
     */
    public function hasTable($tbn)
    {
        if (!is_notempty_str($tbn)) return false;
        return in_array(strtolower($tbn), $this->tables);
    }



    /**
     * magic method
     */

    /**
     * __get
     * get  table, info
     */
    public function __get($key)
    {
        if ($this->hasTable($key)) {
            return $this->table($key);
        }

        return null;
    }



    /**
     * load DB entrence
     * @param String $dsn       dsn or dbfile path
     * @return Db instance or null
     */
    public static function load($dsn = "")
    {
        $darr = str_has($dsn, ":") ? explode(":", $dsn) : [DB_TYPE, $dsn];
        $dbtype = strtolower($darr[0]);
        if (!self::support($dbtype)) trigger_error("db/unsupport",E_USER_ERROR);
        $cls = cls("db/driver/".ucfirst($dbtype));
        $dbkey = $cls::getKey($dsn);
        if (is_notempty_str($dbkey)) {
            if (!isset(Db::$_DBS[$dbkey]) || is_null(Db::$_DBS[$dbkey])) {
                Db::$_DBS[$dbkey] = new $cls($darr[1]);
            }
            return Db::$_DBS[$dbkey];
        }
        return null;
    }
    
    /**
     * create db
     * must override by sub class
     * @param String $option    
     */



    /**
     * static
     */

    /**
     * get db key from dsn
     * must override by sub class
     * @param String $dsn   dsn string
     * @return String unique db key  or  null
     */
    public static function getKey($dsn)
    {
        //must override by sub class
        //...
        return null;
    }

    /**
     * check support db types
     * @param String $dbtype    db type
     * @return Boolean
     */
    public static function support($dbtype = "sqlite")
    {
        $dbtypes = explode(",", strtolower(DB_TYPES));
        return in_array(strtolower($dbtype), $dbtypes) && !is_null(cls("db/driver/".ucfirst($dbtype)));
    }
}