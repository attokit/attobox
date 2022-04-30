<?php

/**
 * Attobox Framework / Db Driver
 */

namespace Atto\Box\db\driver;

use Atto\Box\Db;
use Atto\Box\db\Table;

class Sqlite extends Db
{
    /**
     * DB attributes
     */
    public $type = "sqlite";

    /**
     * connect options
     * Medoo Version 2.1.4
     * must override by sub class
     */
    protected $connectOption = [
        "type" => "sqlite",
        "database" => ""
    ];



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
        $dbf = self::getDbPath($dsn);
        if (is_null($dbf)) trigger_error("db/notexists::$dsn",E_USER_ERROR);
        $dbf = path_fix($dbf);
        $key = self::getKey($dsn);
        $dbfi = pathinfo($dbf);
        $this->connectOption["database"] = $dbf;
        $this->dsn = "sqlite:".$dbf;
        $this->key = $key;
        $this->name = $dbfi["filename"];
        $this->pathinfo = $dbfi;
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
        $this->initDbConfig()->setManualDbConfig($conf);
        return $this;
    }



    /**
     * common db method of sqlite dbtype
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
        if ($this->hasTable($tbn)) {
            $tbn = strtolower($tbn);
            $tbo = new Table($this, $tbn);
            $tbo = $this->initTbConfig($tbo, $conf);
            $tbo->init();
            return $tbo;
        }
        trigger_error("db/unknowntable::".$tbn, E_USER_ERROR);
    }



    /**
     * specific methods of sqlite dbtype
     */

    /**
     * init db config from sqlite_master
     * @return $this
     */
    protected function initDbConfig()
    {
        $rs = $this->medoo()->select("sqlite_master", "*", [
            "type" => "table",
            "name[!~]" => "sqlite",
            "ORDER" => "name"
        ]);
        for ($i=0;$i<count($rs);$i++) {
            $rsi = $rs[$i];
            $this->tables[] = $rsi["name"];
            $this->creation[$rsi["name"]] = $rsi["sql"];
        }
        return $this;
    }
    
    /**
     * set db config from manual (if exists, dbpath/dbname.php)
     * @param String | Array $conf    config file path  or  config array
     * @return $this
     */
    protected function setManualDbConfig($conf = null)
    {
        $cfg = [];
        $pi = $this->pathinfo;
        $cf = $pi["dirname"].DS.$pi["filename"].EXT;
        if (file_exists($cf)) $cfg = require($cf);
        if (is_notempty_str($conf)) {
            if (file_exists($conf)) {
                $mcfg = require($conf);
                $cfg = arr_extend($cfg, $mcfg);
            }
        } else if (is_notempty_arr($conf)) {
            $cfg = arr_extend($cfg, $conf);
        }

        if (is_array($cfg) && !empty($cfg)) {
            $this->config = arr_extend($this->config, $cfg);
            $dbconf = isset($this->config["db"]) && is_associate($this->config["db"]) ? $this->config["db"] : null;
            if (is_array($dbconf)) {
                $dbconf = arr_extend(self::$_CONF["db"], $dbconf);
                foreach ($dbconf as $k => $v) {
                    if (property_exists($this, $k)) {
                        $this->$k = $v;
                    }
                }
            }
        }
        return $this;
    }

    /**
     * init tb config after table instance created
     * @param Table $tbo    table instance
     * @param Array $conf   custom table config
     * @return Table $tbo
     */
    protected function initTbConfig($tbo, $conf = [])
    {
        $tbn = $tbo->name;
        $cfg = $this->setManualTbConfig($tbn, $conf);
        foreach ($cfg as $k => $i) {
            $tbo->$k = $i;
        }
        return $tbo;
    }

    /**
     * get tb config from PRAGMA table_info when db->table($tbn) called
     * @param String $tbn   table name
     * @return Array table config
     */
    protected function getInitTbConfig($tbn)
    {
        $cfg = [
            "creation" => $this->getTbCreationSql($tbn),
            "default" => [],
            "fields" => [],
            "field" => [],
            "primaryfield" => ""
        ];
        $rs = $this->query("PRAGMA table_info(`".$tbn."`)")->fetchAll();
        for($i=0;$i<count($rs);$i++){
            $rsi = $rs[$i];
            $fdn = $rsi["name"];
            $dft = is_null($rsi["dflt_value"]) || is_numeric($rsi["dflt_value"]) ? $rsi["dflt_value"] : str_replace("'","",$rsi["dflt_value"]);
            if ($rsi["pk"]!=1 && !is_null($dft)) $cfg["default"][$fdn] = $dft;
            $cfg["fields"][] = $fdn;
            $cfg["field"][$fdn] = [
                "name" => $fdn,
                "type" => $rsi["type"],
                "cid" => $rsi["cid"],
                "notnull" => $rsi["notnull"]==1,
                "pk" => $rsi["pk"]==1,
                "default" => $dft
            ];
            if ($rsi["pk"]==1) $cfg["primaryfield"] = $fdn;
        }
        return $cfg;
    }

    /**
     * set table config from manual when db->table($tbn) called
     * @param String $tbn   table name
     * @param Array $conf   custom table config
     * @return Array table config
     */
    protected function setManualTbConfig($tbn, $conf = [])
    {
        $ocfg = self::$_CONF;
        $mcfg = $this->config;
        $icfg = $this->getInitTbConfig($tbn);
        foreach ($icfg["field"] as $fdn => $fdcfg) {
            $icfg["field"][$fdn] = arr_extend($ocfg["field"], $fdcfg);
        }
        $icfg = arr_extend($ocfg["table"], $icfg);
        if (isset($mcfg["table"]) && isset($mcfg["table"][$tbn])) {
            $icfg = arr_extend($icfg, $mcfg["table"][$tbn]);
        }
        if (is_notempty_arr($conf)) {
            $icfg = arr_extend($icfg, $conf);
        }
        return $icfg;
    }

    /**
     * get table creation sql when db->table($tbn) called
     * @param String $tbn   table name
     * @return Array creation array like  [field => varchar NOT NULL DEFAULT '']
     */
    protected function getTbCreationSql($tbn)
    {
        $csql = $this->creation[$tbn];
        $carr = explode("(", $csql);
        $carr = explode(",", trim($carr[1], ")"));
        $csqls = [];
        for ($i=0;$i<count($carr);$i++) {
            $sql = trim($carr[$i]);
            $sarr = explode("`", trim($sql, "`"));
            $csqls[$sarr[0]] = trim($sarr[1]);
        }
        return $csqls;
    }



    /**
     * static
     */

    /**
     * get db key from dsn
     * must override by sub class
     * @param String $dsn   dsn string, sqlite:foo/bar/db.db  or  foo/bar/db
     * @return String unique db key  or  null
     */
    public static function getKey($dsn)
    {
        $dbf = self::getDbPath($dsn);
        if (is_null($dbf)) return null;
        $key = str_replace(".db", "", $dbf);
        if (strpos($key, DB_PATH.DS."sqlite".DS)!==false) $key = str_replace(DB_PATH.DS."sqlite".DS, "", $key);
        $key = trim(str_replace(DS, "_", $key), "_");
        return $key;
    }

    /**
     * get dbfile path
     * @param String $dsn   dsn string, sqlite:foo/bar/db.db  or  foo/bar/db
     * @return String dbfile path  or  null
     */
    public static function getDbPath($dsn)
    {
        if (self::isLegalDsn($dsn)) {
            if (str_has($dsn, ":")) $dsn = explode(":",$dsn)[1];
            $dbf = explode(";", trim($dsn, ";"))[0];
            if (strpos($dbf, "=")!==false) $dbf = explode("=", $dbf)[1];
            if (substr($dbf, 0, 1)=="/") {
                $dbf = str_replace("/", DS, $dbf);
            } else {
                $dbf = DB_PATH.DS."sqlite".DS.str_replace("/", DS, $dbf);
            }
            if (strpos($dbf, ".db")===false) $dbf .= ".db";
            //$dbf = path_fix($dbf);
            if (!file_exists($dbf)) trigger_error("db/notexists::$dsn",E_USER_ERROR);
            return $dbf;
        }
        trigger_error("db/errordsn::$dsn",E_USER_ERROR);
        //return null;
    }

    /**
     * check if dsn is legal
     * @param String $dsn   dsn string, sqlite:foo/bar/db.db  or  foo/bar/db
     * @return Bool
     */
    public static function isLegalDsn($dsn)
    {
        if (!is_notempty_str($dsn)) return false;
        if (str_has($dsn, ":")) {
            if (!str_has($dsn, "sqlite:")) return false;
        }
        return true;
    }
}