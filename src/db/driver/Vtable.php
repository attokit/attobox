<?php

/**
 * attokit / attobox / db / driver / Vtable
 */

namespace Atto\Box\db\driver;

use Atto\Box\Db;
use Atto\Box\db\Dsn;
use Atto\Box\db\VTable as Vtb;
//use Medoo\Medoo;

class Vtable extends Db 
{
    /**
     * db type (in DB_TYPES)
     * must override by db driver
     */
    public $type = "vtable";
    
    /**
     * db connection options (structure)
     * must override by db driver
     */
    protected $connectOptions = [
        //vtable 不适用 medoo 连接
    ];



    /**
     * curd methods
     */

    /**
     * connect db by using Medoo
     * must override by db driver class
     * @param Array $opt    extra connect options
     * @return Medoo instance
     */
    protected function __medooConnect($opt = [])
    {
        if (is_null($this->_medoo)) {
            $opt = arr_extend($this->connectOptions, $opt);
            $this->_medoo = new Medoo($opt);
        }
        return $this->_medoo;
    }



    /**
     * db options & info methods
     */

    /**
     * 当数据库结构发生修改时，重建数据库，保持已有的数据记录
     * 执行此方法时，config.json 必须已经修改完成
     * use for dev
     * must override by db driver
     * @param Bool $withrs 是否保持已有数据记录，默认 true
     * @return Db instance
     */
    public function __reinstall($withrs = true)
    {
        //此时 config/dbname.json 已经修改
        $conf = $this->config;
        $tbs = $this->tables;

        //备份 db file
        $dbf = $this->dsn->connectOptions["database"];
        $dbn = $this->name;
        $dbnn = $dbn."_bak_".date("YmdHis",time());
        $dbnf = str_replace($dbn.".db", $dbnn.".db", $dbf);
        if (!copy($dbf, $dbnf)) {
            trigger_error("db/recreate/copyerr", E_USER_ERROR);
        }
        //重建数据表
        $rcok = true;
        $errtbn = "";
        for ($i=0;$i<count($tbs);$i++) {
            $tbi = $tbs[$i];
            //重建表
            $rctb = $this->recreateTable($tbi, $withrs);
            if ($rctb==false) {
                $rcok = false;
                $errtbn = $tbi;
                break;
            }
        }
        //发生错误，恢复备份
        if ($rcok==false) {
            copy($dbnf, $dbf);
            trigger_error("db/recreate/rctberr::".$errtbn, E_USER_ERROR);
        }
        //删除备份文件
        unlink($dbnf);
        //完成
        return $this;
    }



    /**
     * table methods
     */

    /**
     * 当数据表结构发生改变时，重建数据表
     * 执行此方法时，config.json 必须已经修改完成
     * use for dev
     * must override by db driver
     * @param String $tbn table name
     * @param Bool $withrs  是否保持已有数据记录，默认 true
     * @return Bool
     */
    public function __recreateTable($tbn, $withrs = true)
    {
        //此时 config/dbname.json 已经修改
        $conf = $this->config;
        $tbc = $conf["table"][$tbn];
        $fds = $tbc["fields"];
        $ct = $tbc["creation"];

        if (!$this->hasTable($tbn)) return false;
        if ($withrs) {
            //获取全部记录
            $tb = $this->table($tbn);
            $rs = $tb->all();
            //按修改后的 config 参数处理已有记录数据
            $rsn = [];
            for ($i=0;$i<count($rs);$i++) {
                $rsi = $rs[$i];
                $rsn[] = $tb->dft($rsi, true);
            }
            $rs = $rsn;
        }
        //drop table
        $drop = $this->medoo("drop", $tbn);
        //return (array)$drop;
        //根据新的 config 参数创建新表
        $sql = [];
        for ($i=0;$i<count($fds);$i++) {
            $fdi = $fds[$i];
            $sql[] = "`$fdi` ".$ct[$fdi];
        }
        $sql = "CREATE TABLE IF NOT EXISTS `$tbn` (" . implode(", ", $sql) . ")";
        $this->medoo("query", $sql);
        if ($withrs) {
            //将全部数据恢复到新表，使用 medoo transaction
            $this->medoo()->action(function ($medoo) use ($tbn, $rs) {
                for ($i=0;$i<count($rs);$i++) {
                    $medoo->insert($tbn, $rs[$i]);
                }
            });
        }
        //完成
        return true;
    }



    /**
     * static
     */

    /**
     * db initialize
     * must override by db driver class
     * @param String $dsn   Dsn instance
     * @param Array $opt    db connection options
     * @return Db instance  or  null
     */
    protected static function initialize($dsn, $opt = [])
    {
        if (!$dsn->dbNotExists) {
            $db = new Vtable();
            $db->key = $dsn->dbkey;
            $db->name = $dsn->dbname;
            $db->connectOptions = arr_extend($dsn->connectOptions, $opt);
            $db->dsn = $dsn;
            $db->isVirtual = true;  //添加一个虚拟标记，表示这个 db 中的数据表是 虚拟表
            $db->initConfig();      //读取 config/db.json 缓存 config 数据

            Db::$LIST[$db->key] = $db;
            return Db::$LIST[$db->key];
        }
        return null;
    }

    /**
     * create table instance
     * 覆盖默认的 table 实例创建方法，创建 VTable 实例
     * @param String $tbn
     * @return Table instance  or  null
     */
    public function table($tbn)
    {
        if (!is_notempty_str($tbn) || !$this->hasTable($tbn)) return null;
        if (!isset($this->table[$tbn]) || empty($this->table[$tbn])) {
            //查找是否存在预定义的虚拟表类
            $dp = $this->dsn->config["path"];
            $dbn = $this->name;
            $carr = explode("/library/db/", $dp);
            $cpre = $carr[0]=="" ? "root" : $carr[0];
            $cls_a = "$cpre/vtable/$dbn/".ucfirst(strtolower($tbn));
            $cls_b = "$cpre/library/vtable/$dbn/".ucfirst(strtolower($tbn));
            $cls = cls($cls_a);
            if (!class_exists($cls)) $cls = cls($cls_b);
            if (!class_exists($cls)) {
                $cls = "\\Atto\\Box\\db\\VTable";
            }

            $tb = new $cls();
            $tb->db = $this;
            $tb->name = $tbn;
            //$tb->xpath = $this->name."/".$tbn;
            $tb->xpath = $this->xpath."/".$tbn;
            $tb->isVirtual = true;  //虚拟表标记
            //写入基本 config
            $conf = $this->conf("table/$tbn");
            $ks = ["title","desc","fields"];
            for ($i=0;$i<count($ks);$i++) {
                $ki = $ks[$i];
                $tb->$ki = $conf[$ki];
            }
            //创建数据格式转换器
            //$tb->convertor = new Convertor($tb);
            //创建查询器
            //$tb->query = new Query($tb);
            //创建 table-setting-lang 数据库设置语言解析器
            //$tb->vfparser = new Vfparser($tb);
            //虚拟表自动执行 initConfig
            $tb->initConfig();
            $this->table[$tbn] = $tb;
        }
        return $this->table[$tbn];
    }

    /**
     * install db (dev)
     * must override by db driver
     * @param String | Dsn $dsn
     * @param Array $opt    extra db create options
     * @return Db instance  or  false
     */
    public static function __install($dsn, $opt = [])
    {
        if (is_notempty_str($dsn)) {
            $dsn = Dsn::load($dsn);
        } else if (!$dsn instanceof Dsn) {
            return false;
        }
        if ($dsn->dbNotExists) {
            $dbn = $dsn->dbname;
            $mk = $dsn->mk;
            //var_dump($dsn);exit;
            if ($dsn->config["exists"]) {   //存在 config 文件
                $conf = j2a(file_get_contents($dsn->config["file"]));
                $conf = arr_extend($conf, $opt);
            } else {    //不存在 config 文件，从 $opt 创建
                if (empty($opt)) return false;
                $conf = $opt;
                //创建 config/dbn.json
                path_mkdir($mk["config"]);
                $cp = path_fix(path_find($mk["config"]));
                $cf = $cp.DS.$dbn.".json";
                $cfh = @fopen($cf, "w");
                @fclose($cfh);
                file_put_contents($cf, a2j($conf));
                //更新 $dsn
                $dsn->config = [
                    "exists" => true,
                    "path" => $cp,
                    "file" =>$cf
                ];

            }
            //创建数据库文件夹
            path_mkdir($mk["dbpath"]);
            $dbp = path_fix(path_find($mk["dbpath"]));
            //创建 db 文件
            $dbf = $dbp.DS.$dbn.".db";
            $fh = @fopen($dbf, "w");
            if (!$fh) trigger_error("db/create::无法创建文件 [ ".$dbf." ]", E_USER_ERROR);
            fclose($fh);
            //更新 $dsn
            $dsn->dbNotExists = false;
            $dsn->connectOptions = arr_extend($dsn->connectOptions, [
                "database" => $dbf
            ]);
            $dsn->dbkey = md5($dbf);
            $dsn->query = $dbf;
            $dsn->dsn = $dsn->dbtype.":".$dsn->query;
            //pdo 连接数据库
            $tdb = new \PDO("sqlite:".$dbf);
            //创建表 在 config 中预设的表
            $sql = [];
            $tbs = $conf["table"];
            foreach ($tbs as $tbn => $tbc) {
                $sql = [];
                $fds = $tbc["fields"];
                $ct = $tbc["creation"];
                for ($i=0;$i<count($fds);$i++) {
                    $fdi = $fds[$i];
                    $sql[] = "`$fdi` ".$ct[$fdi];
                }
                $sql = "CREATE TABLE IF NOT EXISTS `$tbn` (" . implode(", ", $sql) . ")";
                try {
                    $rst = $tdb->query($sql);
                } catch(\Exception $e) {
                    trigger_error("db/create::无法创建表 [ ".$tbn." ]", E_USER_ERROR);
                }
            }

            return self::load($dsn);
        }
    }

    /**
     * get all installed db
     * must override by db driver
     * @return Array
     */
    public static function __existentDb()
    {
        //db file path: DB_PATH = [webroot]/library/db
        $dir = path_find(DB_PATH);
        if (is_dir($dir)) {
            $dbs = [];
            $dh = opendir($dir);
            while (($f = readdir($dh)) !== false) {
                if (is_dir($dir.DS.$f)) continue;
                if (strpos($f, ".db")===false) continue;
                $dbs[] = str_replace(".db","", $f);
            }
            closedir($dh);
            return $dbs;
        }
        return [];
    }
}