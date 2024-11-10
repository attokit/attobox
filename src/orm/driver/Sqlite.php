<?php
/**
 * sqlite 类型数据库 驱动
 */

namespace Atto\Orm\driver;

use Atto\Orm\Dbo;
use Atto\Orm\Driver;
use Atto\Orm\Configer;
use Medoo\Medoo;

class Sqlite extends Driver 
{
    //数据库文件后缀名
    public static $ext = ".db";

    //默认 数据库文件 保存路径，默认 [webroot | app/appname]/db
    public static $DBDIR = "db";



    /**
     * !! 必须实现 !!
     */
    
    /**
     * 数据库连接方法
     * @param Array $opt medoo 连接参数
     * @return Dbo 数据库实例
     */
    public static function connect($opt=[])
    {
        //数据库文件
        $dbf = self::getDbPath($opt);
        //var_dump($dbf);
        if (!file_exists($dbf)) return null;
        $dbf = path_fix($dbf);
        $pathinfo = pathinfo($dbf);
        $dbname = $pathinfo["filename"];
        $dbkey = "DB_".md5(path_fix($dbf));
        //检查是否存在缓存的数据库实例
        if (isset(Dbo::$CACHE[$dbkey]) && Dbo::$CACHE[$dbkey] instanceof Dbo) {
            return Dbo::$CACHE[$dbkey];
        }
        //创建数据库实例
        $db = new Dbo([
            "type" => "sqlite",
            "database" => $dbf
        ]);
        //写入参数
        $db->type = "sqlite";
        $db->name = $dbname;
        $db->key = $dbkey;
        $db->pathinfo = $pathinfo;
        $db->config = Configer::parse($db);
        $db->driver = cls("db/driver/Sqlite");
        //缓存
        Dbo::$CACHE[$dbkey] = $db;
        return $db;
    }

    /**
     * 创建数据库
     * @param Array $opt 数据库创建参数
     *  [
     *      type => sqlite
     *      database => 数据库文件完整路径
     *      table => [
     *          表名 => [
     *              recreate => 是否重新创建(更新表结构)，默认 false
     *              fields => [ 字段名数组 ]
     *              creation => [
     *                  字段名 => SQL
     *              ]
     *          ]
     *          ...
     *      ]
     *  ]
     * @return Bool
     */
    public static function create($opt=[])
    {
        $dbt = $opt["type"] ?? null;
        $dbf = $opt["database"] ?? null;
        if (!is_notempty_str($dbt) || $dbt!="sqlite" || !is_notempty_str($dbf)) return false;
        $tbs = $opt["table"] ?? [];
        if (empty($tbs)) return false;
        if (!file_exists($dbf)) {
            //db 文件不存在则创建
            $fh = @fopen($dbf, "w");
            fclose($fh);
        }
        //medoo 连接
        $db = new Medoo([
            "type" => "sqlite",
            "database" => $dbf
        ]);
        //创建表
        foreach ($tbs as $tbn => $tbc) {
            $c = [];
            $fds = $tbc["fields"] ?? [];
            $cfd = $tbc["creation"] ?? [];
            for ($i=0;$i<count($fds);$i++) {
                $fdn = $fds[$i];
                if (!isset($cfd[$fdn])) continue;
                $c[] = "`".$fdn."` ".$cfd[$fdn];
            }
            $c = implode(",",$c);
            $sql = "CREATE TABLE IF NOT EXISTS `".$tbn."` (".$c.")";
            $db->query($sql);
        }
        return true;
    }



    /**
     * tools
     */

    //根据 连接参数中 获取 数据库文件路径
    public static function getDbPath($opt=[])
    {
        return $opt["database"];

        
        $database = $opt["database"] ?? null;
        if (empty($database) || !is_notempty_str($database)) return null;
        //路径分隔符设为 DS
        $database = str_replace("/", DS, trim($database, "/"));
        //统一添加 后缀名
        if (strtolower(substr($database, strlen(self::$ext)*-1))!==self::$ext) $database .= self::$ext;
        //获取数据库路径
        $path = $opt["path"] ?? null;
        if (is_notempty_str($path)) {
            $path = path_find($path, ["checkDir"=>true]);
            if (empty($path)) {
                $path = self::dftDbDir();
            }
        } else {
            $path = self::dftDbDir();
        }
        //数据库文件
        $dbf = $path.DS."sqlite".DS.$database;
        return $dbf;
    }

    //获取默认数据库文件存放位置
    public static function dftDbDir()
    {
        return __DIR__.DS."..".DS."..".DS.trim(self::$DBDIR);
    }
}