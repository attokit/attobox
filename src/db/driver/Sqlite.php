<?php

/**
 * attokit / attobox / db / driver / Sqlite
 */

namespace Atto\Box\db\driver;

use Atto\Box\Db;
use Atto\Box\db\Dsn;
use Medoo\Medoo;

class Sqlite extends Db 
{
    /**
     * db type (in DB_TYPES)
     * must override by db driver
     */
    public $type = "sqlite";
    
    /**
     * db connection options (structure)
     * must override by db driver
     */
    protected $connectOptions = [
        "type" => "sqlite",
        "database" => ""
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
    protected function medooConnect($opt = [])
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
    public function reinstall($withrs = true)
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
    public function recreateTable($tbn, $withrs = true)
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
     * 备份数据库
     * 将数据库文件备份到当前目录的 backup 路径下
     * must override by db driver class
     * @param Array $opt 备份参数
     * @return Bool
     */
    public function backup($opt = [])
    {
        $full = $opt["fullbackup"] ?? false;
        $bopt = $opt["options"] ?? [];

        $dsn = $this->dsn;
        $dbc = $dsn->connectOptions["database"];
        $pi = pathinfo($dbc);
        $dir = $pi["dirname"];
        $backdir = $dir.DS."backup";
        $ext = $pi["extension"];
        $t = time();
        $tstr = date("YmdHis", $t);
        if (!$full) {
            //进备份当前数据库
            $fn = $pi["filename"];
            $ndbc = $backdir.DS.$fn."_".$tstr.".".$ext;
            $cp = false;
            $cp = @copy($dbc, $ndbc);
            if ($cp===false) trigger_error("db/backup/copy::".$fn, E_USER_ERROR);
            return $cp;
        } else {
            $action = $bopt["action"] ?? "backup";
            if (isset($bopt["action"])) unset($bopt["action"]);
            //获取同路径下的所有 $ext 类型的数据库文件
            $dbs = [];
            $dh = opendir($dir);
            while(($dbf = readdir($dh))!==false) {
                //不处理子文件夹
                if ($dbf=="." || $dbf==".." || is_dir($dir.DS.$dbf)) continue;
                if (strpos(strtolower($dbf), ".".$ext)===false) continue;
                $dbs[] = $dbf;
            }
            //获取 json 文件
            $jsonf = $backdir.DS."backlog.json";
            $json = j2a(file_get_contents($jsonf));
            if (!isset($json["fullbackup"]) || !is_array($json["fullbackup"])) $json["fullbackup"] = [];
            if ($action=="backup") {
                //完整备份，即 将同目录下所有数据库被分到 目录下的 backup/[YmdHis]/ 文件夹下
                //同时编辑 backup/full.json，创建备份记录
                //创建备份文件夹
                $backdir =  $backdir.DS.$tstr;
                if (!is_dir($backdir)) {
                    //创建备份文件夹
                    $mk = @mkdir($backdir, 0777);
                    if ($mk===false) trigger_error("db/backup/mkdir::".$backdir, E_USER_ERROR);
                }
                //复制文件
                $cperr = [];
                for ($i=0;$i<count($dbs);$i++) {
                    $odbf = $dir.DS.$dbs[$i];
                    $ndbf = $backdir.DS.$dbs[$i];
                    $cp = @copy($odbf, $ndbf);
                    if ($cp===false) {
                        $cperr[] = $dbs[$i];
                    }
                }
                //写入 json
                $bcsi= arr_extend($bopt, [
                    "timestamp" => $t,
                    "timestr" => date("Y-m-d H:i:s", $t),
                    "timeprefix" => $tstr,
                    "errors" => $cperr
                ]);
                array_unshift($json["fullbackup"], $bcsi);
                file_put_contents($jsonf, a2j($json));

                return [
                    "backup" => empty($cperr),
                    "db" => $dbs,
                    "result" => $bcsi
                ];
            } else if ($action=="del") {
                //删除某个完整备份记录
                $idx = $bopt["idx"] ?? -1;
                if ($idx<0 || $idx>=count($json["fullbackup"])) return false;
                $bci = $json["fullbackup"][$idx];
                $backdir = $backdir.DS.$bci["timeprefix"];
                if (is_dir($backdir)) {
                    $delerr = [];
                    //删除备份文件
                    for ($i=0;$i<count($dbs);$i++) {
                        $fi = $backdir.DS.$dbs[$i];
                        $ul = @unlink($fi);
                        if ($ul===false) {
                            $delerr[] = $dbs[$i];
                        }
                    }
                    //删除备份文件夹
                    $rd = false;
                    if (empty($delerr)) $rd = @rmdir($backdir);
                    if ($rd===false) {
                        trigger_error("db/backup/del::".implode("/",$delerr), E_USER_ERROR);
                    }
                }
                //修改 json
                array_splice($json["fullbackup"], $idx, 1);
                file_put_contents($jsonf, a2j($json));
                return true;
            } else if ($action=="restore") {
                //恢复某个备份
                $idx = $bopt["idx"] ?? -1;
                if ($idx<0) {
                    //未指定要恢复的备份记录序号，则使用最新备份
                    $idx = 0;
                }
                if ($idx<0 || $idx>=count($json["fullbackup"])) return false;
                $bci = $json["fullbackup"][$idx];
                $backdir = $backdir.DS.$bci["timeprefix"];
                if (is_dir($backdir)) {
                    $cperr = [];
                    //复制备份的文件
                    for ($i=0;$i<count($dbs);$i++) {
                        $from = $backdir.DS.$dbs[$i];
                        $to = $dir.DS.$dbs[$i];
                        $cp = @copy($from, $to);
                        if ($cp===false) {
                            $cperr[] = $dbs[$i];
                        }
                    }
                    //报错
                    if (!empty($cperr)) {
                        trigger_error("db/backup/restore::".implode("/",$cperr), E_USER_ERROR);
                    }
                }
                //修改 json 添加一个 已恢复 的标记
                if (!isset($json["fullbackup"][$idx]["restore"])) $json["fullbackup"][$idx]["restore"] = [];
                $json["fullbackup"][$idx]["restore"][] = arr_extend([
                    "timestamp" => $t,
                    "timestr" => date("Y-m-d H:i:s", $t)
                ], $bopt);
                file_put_contents($jsonf, a2j($json));
                return true;
            }
            
        }
    }

    /**
     * 恢复数据库
     * 将已备份的数据库文件恢复到当前目录下
     * must override by db driver class
     * @param Array $opt 恢复参数
     * @return Bool
     */
    public function restore($opt = [])
    {
        $opt = arr_extend([
            "latest" => true,   //默认恢复最后一次备份的文件
            "specific" => "",   //还可以特别指定要恢复的备份版本， YmdHis 格式
        ], $opt);

        $spec = $opt["specific"];
        $latest = $opt["latest"];
        $dbn = $this->name;
        $dsn = $this->dsn;
        $dbc = $dsn->connectOptions["database"];
        $pi = pathinfo($dbc);
        $dbp = $pi["dirname"];
        $dbbp = $dbp.DS."backup";

        if ($spec=="") {
            //查找最后一次备份的文件
            $dh = opendir($dbbp);
            $dbbfs = [];
            while (($f = readdir($dh))!==false) {
                if ($f=="." || $f==".." || is_dir($dbbp.DS.$f) || strpos($f,$dbn."_")===false) continue;
                $dbbfs[] = str_replace(".".$pi["extension"], "", str_replace($dbn."_", "", $f));
            }
            closedir($dh);
            if (empty($dbbfs)) return false;
            rsort($dbbfs);
            $dbbf = $dbbp.DS.$dbn."_".$dbbfs[0].".".$pi["extension"];
        } else {
            $dbbf = $dbbp.DS.$dbn."_".$spec.".".$pi["extension"];
        }
        if (!file_exists($dbbf)) return false;
        $cp = false;
        $cp = @copy($dbbf, $dbc);
        return $cp;
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
            $db = new Sqlite();
            $db->key = $dsn->dbkey;
            $db->name = $dsn->dbname;
            $db->connectOptions = arr_extend($dsn->connectOptions, $opt);
            $db->dsn = $dsn;
            $db->initConfig();  //读取 config/db.json 缓存 config 数据

            Db::$LIST[$db->key] = $db;
            return Db::$LIST[$db->key];
        }
        return null;
    }

    /**
     * install db (dev)
     * must override by db driver
     * @param String | Dsn $dsn
     * @param Array $opt    extra db create options
     * @return Db instance  or  false
     */
    public static function install($dsn, $opt = [])
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
    public static function existentDb()
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