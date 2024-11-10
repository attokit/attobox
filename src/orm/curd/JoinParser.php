<?php
/**
 * Curd 操作类 join 参数处理
 */

namespace Atto\Orm\curd;

use Atto\Orm\Orm;
use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Atto\Orm\Curd;
use Atto\Orm\curd\Parser;

class JoinParser extends Parser 
{
    /**
     * 初始参数
     */
    public $param = [];         //预设的 model::$join 参数
    public $available = true;   //join 参数是否可用
    public $use = false;        //是否每次查询都默认启用 join 关联表查询
    public $tables = [];        //关联表数组 [ "table name", ... ]
    public $field = [           //有关联表的 字段参数
        /*
        "field name" => [
            "table name" => [
                "linkto" => "关联表中字段名"
                "relate" => ">|<|<>|>< == left|right|full|inner join"
            ],
        ]
        */
        ];

    //通过 setParam() 指定的 临时参数
    public $temp = [];
    

    /**
     * 初始化 curd 参数
     * !! 子类必须实现 !!
     * @return Parser $this
     */
    public function initParam()
    {
        $conf = $this->conf;
        $join = $conf->join;
        //写入初始参数
        foreach ($join as $k => $v) {
            $this->$k = $v;
        }

        return $this;
    }

    /**
     * 设置 curd 参数
     * !! 子类必须实现 !!
     * @param Mixed $param 要设置的 curd 参数
     * @return Parser $this
     */
    public function setParam($param=null)
    {
        $args = func_get_args();
        if (empty($args)) {
            $join = true;
            $jtbs = [];
        } else {
            $join = array_shift($args);
            $jtbs = $args;
        }
        if (is_bool($join)) {
            $this->use = $join;
            $this->temp = [];
        } else {
            $this->use = true;
            if (is_string($join)) {
                $jtbs = array_unshift($jtbs, $join);
                $this->temp = $jtbs;
            } else if (is_array($join)) {
                $this->temp = $join;
            } else {
                $this->temp = [];
            }
        }

        //自动添加 join 表全部字段名 到 $this->curd->column 查询字段名数组
        $cp = $this->curd->columnParser;
        if ($cp instanceof Parser) {
            $cp->setJoinTableColumns();
        }

        return $this;
    }
    
    /**
     * 重置 curd 参数 到初始状态
     * !! 子类必须实现 !!
     * @return Parser $this
     */
    public function resetParam()
    {
        $this->temp = [];
        return $this->initParam();
    }

    /**
     * 执行 curd 操作前 返回处理后的 curd 参数
     * !! 子类必须实现 !!
     * @return Mixed curd 操作 medoo 参数，应符合 medoo 参数要求
     */
    public function getParam()
    {
        if ($this->available!=true || $this->use!=true) return [];
        $join = $this->param;
        $temp = $this->temp;
        if (empty($temp)) return $join;
        if (is_indexed($temp)) {
            //通过表名，选择要 join 的 关联表
            $nj = [];
            for ($i=0;$i<count($temp);$i++) {
                $jik = $temp[$i];
                if (isset($join[$jik])) {
                    $nj[$jik] = $join[$jik];
                }
            }
            return $nj;
        } else {
            //直接指定 join 参数
            return $temp;
        }
    }

    /**
     * 获取当前要 join 的 关联表 名称数组
     * @return Array [ "table name", ... ]
     */
    public function getJoinTables()
    {
        if ($this->use==true && empty($this->temp)) return $this->tables;
        $jps = $this->getParam();
        if (empty($jp)) return [];
        $tbs = [];
        foreach ($jps as $k => $v) {
            $tbn = preg_replace("/\[.+\]/","",$k);
            $tbn = preg_replace("/\(.+\)/","",$tbn);
            $tbn = trim($tbn);
            $tbs[] = $tbn;
        }
        return $tbs;
    }
}