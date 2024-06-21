<?php

/**
 * Attobox Framework / Db Manager
 */

namespace Atto\Box\db;

use Atto\Box\Db;

class Manager 
{
    //关联的数据库实例
    public $db = null;

    //全部数据表实例
    public $table = [];

    //当前数据表，管理操作的目标数据表
    public $activeTable = null;

    

    /**
     * construct
     * @param Db $db db instance
     * @return void
     */
    public function __construct($db)
    {
        $this->db = $db;
        //加载数据表
        $this->table = $this->db->eachTable(function($_db, $_tbn) {
            return $_db->table($_tbn);
        });
    }

    /**
     * 设置当前要管理的数据表
     * @param String $tbn
     * @return $this | table instance | null
     */
    public function active($tbn = null)
    {
        if (!is_notempty_str($tbn)) {
            if (is_null($this->activeTable)) return null;
            return $this->table[$this->activeTable];
        }
        if (isset($this->table[$tbn])) {
            $this->activeTable = $tbn;
        }
        return $this;
    }

    /**
     * 判断 tbn 是否当前操作对象
     * @param String $tbn
     * @return Bool
     */
    public function isActive($tbn)
    {
        if (!is_notempty_str($tbn)) return false;
        return $this->activeTable == $tbn;
    }

    /**
     * 输出 DbManager 页面
     */
    public function exportPage()
    {
        //主框架
        $page = path_find("box/db/manager/frame.php");

        return [
            "format"    => "page",
            "data"      => $page,
            "db"        => $this->db,
            "table"     => $this->table,
            "manager"   => $this
        ];
    }


}