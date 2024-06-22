<?php

/**
 * Attobox Framework / Db
 * connect to db & return current db instance
 */

namespace Atto\Box;

use Medoo\Medoo;
use Atto\Box\db\Convertor;
use Atto\Box\db\Query;
use Atto\Box\db\Vfparser;
//use Atto\Box\db\Curd;
use Atto\Box\db\Dsn;
use Atto\Box\db\Table;

class Db 
{
    /**
     * db instance array
     */
    public static $LIST = [];

    /**
     * Dsn instance
     */
    public $dsn = null;

    /**
     * db type (in DB_TYPES)
     * must override by db driver
     */
    public $type = "";
    
    /**
     * db connection options (structure)
     * must override by db driver
     */
    protected $connectOptions = [
        
    ];

    /**
     * Db params
     */
    public $key = "";   //db key
    public $name = "";  //db name
    public $xpath = "";
    public $title = "";
    public $desc = "";

    /**
     * medoo instance
     */
    public $_medoo = null;

    /**
     * db config cache
     */
    public $config = null;

    /**
     * loaded table instance
     */
    public $tables = [];
    public $table = [];

    //虚拟标记，true 表示此数据库中的表均为 虚拟表
    public $isVirtual = false;



    /**
     * db config methods
     */

    /**
     * init db config from config/dbname.json
     * @return $this
     */
    protected function initConfig()
    {
        $cf = $this->dsn->config;
        if ($cf["exists"]) {
            $f = $cf["file"];
            $conf = j2a(file_get_contents($f));
            $this->config = $conf;
            $ks = ["xpath","title","desc"];
            for ($i=0;$i<count($ks);$i++) {
                $ki = $ks[$i];
                $this->$ki = $conf[$ki];
            }
            $this->tables = array_keys($conf["table"]);
        }
        return $this;
    }

    /**
     * get db or tb configs
     * @param String $tbn   if null return db config, or  [property]  or  [tbname]/[property]
     */
    public function conf($tbn = null)
    {
        if (is_null($this->config)) $this->initConfig();
        $conf = $this->config;
        if (!is_notempty_str($tbn)) return $conf;
        $arr = explode("/", $tbn);
        if ($this->hasTable($arr[0])) {
            $tbn = array_shift($arr);
            $tb = $this->table($tbn);
            return $tb->conf(implode("/",$arr));
        }
        return arr_item($conf, implode("/", $arr));
    }

    /**
     * get db driver class full name
     * @return String
     */
    public function driver()
    {
        //return self::_driver($this->dsn());
        return $this->dsn->driver;
    }

    /**
     * 获取 兄弟数据库
     * @return Array config data
     */
    public function sibling()
    {
        $dsn = $this->dsn;
        $conf = $dsn->config;
        $cp = $conf["path"];
        //var_dump($cp);
        if (!is_dir($cp)) return [];
        $sdb = [];
        $dh = opendir($cp);
        while(($cf = readdir($dh))!==false) {
            //if (strpos($cf, ".json")===false) continue;
            if (substr($cf, -5)!==".json") continue;
            $cfp = $cp.DS.$cf;
            //var_dump($cfp);
            $ca = j2a(file_get_contents($cfp));
            //var_dump(array_keys($ca["table"]));
            $sdb[$ca["name"]] = [
                "name" => $ca["name"],
                "title" => $ca["title"],
                "xpath" => $ca["xpath"],
                "tables" => array_keys($ca["table"])
            ];
        }
        closedir($dh);
        return $sdb;
    }

    /**
     * 当数据库结构发生修改时，重建数据库，保持已有的数据记录
     * 执行此方法时，config.json 必须已经修改完成
     * use for dev
     * must override by db driver
     * @return Db instance
     */
    public function reinstall()
    {
        //...

        return $this;
    }

    /**
     * 备份数据库
     * must override by db driver class
     * @param Array $opt 备份参数
     * @return Bool
     */
    public function backup($opt = [])
    {
        //override by db driver class
        //...
        return true;
    }

    /**
     * 恢复数据库
     * must override by db driver class
     * @param Array $opt 恢复参数
     * @return Bool
     */
    public function restore($opt = [])
    {
        //override by db driver class
        //...
        return true;
    }



    /**
     * table methods
     */

    /**
     * create table instance
     * @param String $tbn
     * @return Table instance  or  null
     */
    public function table($tbn)
    {
        if (!is_notempty_str($tbn) || !$this->hasTable($tbn)) return null;
        if (!isset($this->table[$tbn]) || empty($this->table[$tbn])) {
            $tb = new Table();
            $tb->db = $this;
            $tb->name = $tbn;
            //$tb->xpath = $this->name."/".$tbn;
            $tb->xpath = $this->xpath."/".$tbn;
            //写入基本 config
            $conf = $this->conf("table/$tbn");
            $ks = ["title","desc","fields"];
            for ($i=0;$i<count($ks);$i++) {
                $ki = $ks[$i];
                $tb->$ki = $conf[$ki];
            }
            //创建数据格式转换器
            $tb->convertor = new Convertor($tb);
            //创建查询器
            $tb->query = new Query($tb);
            //创建 table-setting-lang 数据库设置语言解析器
            $tb->vfparser = new Vfparser($tb);
            $this->table[$tbn] = $tb;
        }
        return $this->table[$tbn];
    }

    /**
     * 遍历 tables 执行 callback
     * @param Callable $callback 对每一个数据表要执行的方法
     * @return Array result
     */
    public function eachTable($callback = null)
    {
        if (!is_callable($callback)) return [];
        $tbs = $this->tables;
        $rst = [];
        for ($i=0;$i<count($tbs);$i++) {
            $tbi = $tbs[$i];
            $rsti = $callback($this, $tbi);
            if ($rsti===false) break;
            if ($rsti===true) continue;
            $rst[$tbi] = $rsti;
        }
        return $rst;
    }

    /**
     * get default value  or  arr_extend($dftval, $data)
     * @param String $tbfdn     tbn  or  tbn/fdn
     * @param Array $data       data need tobe modified by dftval
     * @return Mixed
     */
    public function dft($tbfdn, $data = [])
    {
        if (!is_notempty_str($tbfdn)) return null;
        $arr = explode("/", $tbfdn);
        $tbn = $arr[0];
        $cr = $this->conf("$tbn/creation");
        if (count($arr)>1) {
            $fdn = $arr[1];
            $cri = $cr[$fdn];
            if (strpos($cri, "DEFAULT ")===false) {
                if (strpos($cri, " NOT NULL ")===false) return null;
                return "";
            }
            $dft = explode("DEFAULT ", $cri)[1];
            if (strpos($dft,"'")!==false) {
                $dft = str_replace("'","",$dft);
            } else {
                $dft = $dft*1;
            }
            return $dft;
        } else {
            $dft = [];
            foreach ($cr as $fdn => $cri) {
                if (strpos($cri, "AUTOINCREMENT")!==false) {
                    //跳过自增主键 id
                    continue;
                }
                $dft[$fdn] = $this->dft("$tbn/$fdn");
                /*$dfti = $this->dft("$tbn/$fdn");
                if (!is_null($dfti)) {
                    $dft[$fdn] = $dfti;
                }*/
            }
        }
        if (!is_notempty_arr($data)) return $dft;
        return arr_extend($dft, $data);
    }

    /**
     * get table autoincrement field name
     * @param String $tbn
     * @return String field name  or  null
     */
    public function autoIncrementKey($tbn)
    {
        if (!$this->hasTable($tbn)) return null;
        $cr = $this->conf("$tbn/creation");
        foreach ($cr as $fdn => $cri) {
            if (strpos($cri, "AUTOINCREMENT")!==false) {
                return $fdn;
            }
        }
        return null;
    }

    /**
     * get table primary key field
     * @param String $tbn
     * @return String field name  or  null
     */
    public function primaryKey($tbn)
    {
        if (!$this->hasTable($tbn)) return null;
        $cr = $this->conf("$tbn/creation");
        foreach ($cr as $fdn => $cri) {
            if (strpos($cri, "PRIMARY KEY")!==false) {
                return $fdn;
            }
        }
        return null;
    }

    /**
     * has table
     * @param String $tbn
     * @return Bool
     */
    public function hasTable($tbn = null)
    {
        if (!is_notempty_str($tbn)) return false;
        return in_array($tbn, $this->tables);
    }

    /**
     * has table field
     * @param String $tbfdn     like: tablename/fieldname
     * @return Bool
     */
    public function hasField($tbfdn = null)
    {
        if (!is_notempty_str($tbfdn)) return false;
        $arr = explode("/", $tbfdn);
        if (count($arr)<2) return false;
        $tbn = $arr[0];
        $fdn = $arr[1];
        if (!$this->hasTable($tbn)) return false;
        $fds = $this->conf($tbn)["fields"];
        return in_array($fdn, $fds);
    }

    /**
     * 删除库中某数据表
     * for dev
     * override in db driver if necessary
     * @param String $tbn table name
     * @return Bool
     */
    public function dropTable($tbn)
    {
        return $this->medoo("drop", $tbn);
    }

    /**
     * 当数据表结构发生改变时，重建数据表，保持已有数据记录
     * 执行此方法时，config.json 必须已经修改完成
     * use for dev
     * must override by db driver
     * @param String $tbn table name
     * @return Bool
     */
    public function recreateTable($tbn)
    {
        //...

        return true;
    }



    /**
     * curd methods
     */

    /**
     * connect db by using Medoo
     * must override by db driver class
     * @param Array $opt    extra connect options
     * @return Medoo instance
     */
    protected function medooConnect($opt = [])
    {
        //override by db driver class
        //...
        return null;
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
     * create a CURD operation, return Curd instance
     */
    /*public function curd($params = [])
    {
        if (is_null($this->_curd)) {
            $params = $this->curdParams($params);
            $this->_curd = new Curd($this, $params);
            return $this->_curd;
        } else {
            if (empty($params)) return $this->_curd;
            $params = $this->curdParams($params);
            return $this->_curd->reset($params);
        }
    }
    protected function curdParams($params)
    {
        if (is_notempty_str($params)) {
            if (!$this->hasTable($params)) trigger_error("db/curd/needtb::".$this->co("originalDsn"), E_USER_ERROR);
            $params = [
                "table" => $params
            ];
        } else if (is_associate($params) && is_notempty_arr($params)) {
            if (!isset($params["table"])) trigger_error("db/curd/needtb::".$this->co("originalDsn"), E_USER_ERROR);
        } else {
            trigger_error("db/curd/needtb::".$this->co("originalDsn"), E_USER_ERROR);
        }
        return $params;
    }*/

    



    /**
     * static
     */

    /**
     * create Db instance
     * @param string | Dsn $dsn     db connection string or instance
     * @param array $opt            db connection options
     * @return Db instance  or  trigger error
     */
    public static function load($dsn = "", $opt = []) 
    {
        $odsn = $dsn;
        $dsn = Dsn::load($dsn);
        if ($dsn->dbNotExists) {
            trigger_error("db/dsn/illegal::".$odsn, E_USER_ERROR);
        }
        $key = $dsn->dbkey;
        if (isset(self::$LIST[$key]) && self::$LIST[$key] instanceof $dsn->driver) return self::$LIST[$key];
        return $dsn->driver::initialize($dsn, $opt);
    }

    /**
     * check support db types
     * @param String $dbtype    db type
     * @return Boolean
     */
    public static function support($dbtype = "sqlite")
    {
        return Dsn::support($dbtype);
    }

    /**
     * db initialize
     * must override by db driver class
     * @param String $dsn   Dsn instance
     * @param Array $opt    db connection options
     * @return Db instance  or  null
     */
    protected static function initialize($dsn, $opt = [])
    {
        //override by db driver class
        //...
        return null;
    }

    /**
     * install db (dev)
     * must override by db driver
     * @param String | Dsn $dsn
     * @param Array $opt    extra db create options
     * @return Bool
     */
    public static function install($dsn, $opt = [])
    {
        //override by db driver
        //...
        return true;
    }

    /**
     * get all installed db
     * must override by db driver
     * @return Array | null
     */
    public static function existentDb()
    {
        //override by db driver
        //...
        return [];
    }

    /**
     * 检查 $dsn 是否存在某个数据库
     * @param String $dsn
     * @return Bool
     */
    public static function exists($dsn)
    {
        return Dsn::exists($dsn);
    }
}