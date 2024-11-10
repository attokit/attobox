<?php
/**
 * 数据库 config.json 解析器
 * 解析得到 Configer 实例对象
 */

namespace Atto\Orm;

class Configer 
{
    //关联的 数据库实例
    public $db = null;

    //__construct
    public function __construct($conf=[])
    {

    }


    /**
     * 读取 json file
     */
    public static function parse($db=null)
    {
        $dbname = $db->name;
        $pathinfo = $db->pathinfo;
        $confp = self::getConfPath($pathinfo["dirname"]);
        $conf = $confp.DS.$dbname.".json";
        if (file_exists($conf)) {
            $conf = j2a(file_get_contents($conf));
        } else {
            $conf = [];
        }
        $cfg = new self($conf);
        $cfg->db = $db;
        return $cfg;
    }

    /**
     * static tools
     */
    //从数据库路径，解析 config 路径
    protected static function getConfPath($dbpath="")
    {
        $dpa = explode(DS, $dbpath);
        $l = array_pop($dpa);
        if (strtolower($l)=="db") $dpa[] = "db";
        $dpa[] = "config";
        $confp = implode(DS, $dpa);
        return $confp;
    }
}