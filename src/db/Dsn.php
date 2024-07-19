<?php

/**
 * Attobox Framework / dsn parser
 */

namespace Atto\Box\db;

class Dsn 
{
    //解析后的完整 dsn 语句
    public $dsn = "";

    //解析 dsn 获得的 dsn 参数
    public $dbtype = DB_TYPE;
    public $query = "";

    //解析获得的 connect options
    //使用 Medoo 作为数据库连接方式
    public $connectOptions = [];
    //不同数据库类型的 connect option 数据结构
    protected $coptStructure = [
        "sqlite" => [
            "type" => "sqlite",
            "database" => ""    //db file path | :memory: | ''
        ],
        "mysql" => [
            "type" => "mysql",
            "host" => "",       //localhost | ip
            "database" => "",   //db name
            "username" => "",   //username
            "password" => "",   //password
        ],

        //虚拟表
        "vtable" => [],     //不适用 medoo 连接
    ];

    //db infos
    //db driver
    public $driver = "";        //full db driver class name
    //db unique key
    public $dbkey = "";
    //db name
    public $dbname = "";

    //if db not exists
    public $dbNotExists = false;
    //创建路径以及文件的参数
    public $mk = [
        "dbpath" => "",
        "confpath" => ""
    ];

    //config file path info
    public $config = [
        "exists" => true,
        "path" => "",
        "file" => ""
    ];

    /**
     * construct
     * @param String $dsn
     * @return void
     */
    public function __construct($dsn)
    {
        if (!is_notempty_str($dsn) || strpos($dsn, ":") === false) {
            $dbtype = DB_TYPE;
            $query = $dsn;
        } else {
            $darr = explode(":", $dsn);
            if ($darr[0]=="") {
                $dbtype = DB_TYPE;
                $query = $dsn;
            } else {
                $dbtype = strtolower(array_shift($darr));
                $query = implode(":",$darr);
            }
        }
        if (!self::support($dbtype)) trigger_error("db/unsupport",E_USER_ERROR);
        $this->dbtype = $dbtype;
        $this->driver = cls("db/driver/".ucfirst($dbtype));

        $m = "parse".ucfirst($dbtype)."Query";
        if (method_exists($this, $m)) {
            $this->$m($query);
        }

        if (!$this->dbNotExists) {

        }
    }



    /**
     * for sqlite db
     */

    /**
     * 解析 dsn query
     * dbtype = sqlite
     * @param String $query
     * @return $this
     */
    protected function parseSqliteQuery($query)
    {
        if (strpos(strtolower($query), "memory")!==false) {         //sqlite3 IN-MEMORY mode
            return $this->parseSqliteSpecialMode("memory");
        } else if(strtolower($query)=="temp" || strtolower($query)=="")  {    //sqlite3 TEMPDB mode
            return $this->parseSqliteSpecialMode("temp");
        } else {
            if (strpos($query, ".db")!==false) $query = str_replace(".db","",$query);
            $dbf = path_find($query.".db",["inDir"=>DB_DIRS]);
            //var_dump($dbf);
            if (is_file($dbf)) {
                $dbf = path_fix($dbf);
                $coptStructure = $this->coptStructure["sqlite"];
                $this->connectOptions = arr_extend($coptStructure, [
                    "database" => $dbf
                ]);
                $this->query = $dbf;
                $this->dsn = $this->dbtype.":".$this->query;
                $this->dbkey = md5($dbf);
                $pi = pathinfo($dbf);
                $this->dbname = $pi["filename"];
                $conf = str_replace("library/db/", "library/db/config/", $dbf);
                $conf = str_replace(".db", ".json", $conf);
                $cpi = pathinfo($conf);
                $this->config = arr_extend($this->config, [
                    "path" => $cpi["dirname"],
                    "file" => $conf
                ]);
            } else {
                $this->dbNotExists = true;
                if (strpos($query, "library/db/")!==false) {
                    $path = str_replace("library/db/", "", $query);
                } else {
                    $path = $query;
                }
                
                $parr = explode("/", $path);
                $this->dbname = array_pop($parr);
                if ($parr[0]=="app") {
                    array_splice($parr, 2, 0, "library/db");
                } else {
                    array_unshift($parr, "library/db");
                }
                $dp = implode("/", $parr);
                $this->mk = arr_extend($this->mk, [
                    //"dbpath" => "library/db".$dp,
                    //"config" => "library/db/config".$dp
                    "dbpath" => $dp,
                    "config" => $dp."/config"
                ]);
                //$conf = path_find("library/db/config/".$query.".json");
                $conf = path_find($this->mk["config"]."/".$this->dbname.".json");
                if (empty($conf)) {
                    $this->config["exists"] = false;
                } else {
                    $cpi = pathinfo($conf);
                    $this->config = arr_extend($this->config, [
                        "path" => $cpi["dirname"],
                        "file" => $conf
                    ]);
                }
            }
        }
        
        return $this;
    }

    /**
     * 处理 sqlite 特殊类型数据库 memory | temporary
     * @param String $mode special mode
     * @return $this
     */
    protected function parseSqliteSpecialMode($mode = "memory")
    {
        if ($mode=="memory") {
            $key = "memory";
            $file = ":memory:";
        } else if ($mode=="temp") {
            $key = "temp";
            $file = "";
        }
        $coptStructure = $this->coptStructure["sqlite"];
        $this->connectOptions = arr_extend($coptStructure, [
            "database" => $file
        ]);
        $this->query = $file;
        $this->dsn = $this->dbtype.":".$this->query;
        $this->dbkey = $key;
        $this->dbname = $key;
        $this->config = arr_extend($this->config, [
            "path" => path_find("library/db/config",["checkDir"=>true]),
            "file" => path_find("library/db/config/$mode.json")
        ]);
        return $this;
    }

    /**
     * 根据数据库路径，推算出 webroot 路径
     * 数据库必须在 library 路径下
     * @return String webroot path
     */
    public function getWebRootPath()
    {
        $confp = $this->config["path"];
        $cparr = explode("library", $confp);
        return $cparr[0];
    }



    /**
     * for vtable
     */

    /**
     * 解析 dsn query
     * dbtype = vtable
     * @param String $query
     * @return $this
     */
    protected function parseVtableQuery($query)
    {
        if (strpos($query, ".json")!==false) $query = str_replace(".json","",$query);
        $cf = path_find($query);
        if (!is_file($cf)) {
            $qarr = explode("/", $query);
            $dbn = array_slice($qarr, -1)[0];
            if (empty($qarr)) {
                $cf = "root/library/db/config/vtable/".$dbn.".json";
            } else {
                $cf = implode("/", array_slice($qarr, 0, -1))."/library/db/config/vtable/".$dbn.".json";
            }
            $cf = path_find($cf);
        }
        if (is_file($cf)) {
            $conf = path_fix($cf);
            $coptStructure = $this->coptStructure["vtable"];
            $this->connectOptions = arr_extend($coptStructure, [
                //"database" => $dbf
            ]);
            $this->query = $conf;
            $this->dsn = $this->dbtype.":".$this->query;
            $this->dbkey = md5($conf);
            $pi = pathinfo($conf);
            $this->dbname = $pi["filename"];
            $this->config = arr_extend($this->config, [
                "path" => $pi["dirname"],
                "file" => $conf
            ]);
        } else {
            $this->dbNotExists = true;
            //vtable 不支持 mk
        }
        return $this;
    }



    /**
     * static tools
     */

    /**
     * 创建 Dsn 实例
     * @param String | instance $dsn
     * @return Dsn instance
     */
    public static function load($dsn)
    {
        if ($dsn instanceof Dsn) return $dsn;
        return new Dsn($dsn);
    }

    /**
     * 判断 $dsn 是否指向一个存在的 db
     * @param String $dsn
     * @return Bool
     */
    public static function exists($dsn)
    {
        if (!is_notempty_str($dsn)) return false;
        $dsn = Dsn::load($dsn);
        return !$dsn->dbNotExists;
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