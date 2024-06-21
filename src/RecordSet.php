<?php

/**
 * Attobox Framework / Active RecordSet
 * 活动记录模式
 * 将数据表中的一行封装为一个 Record 对象
 * 将数据表中的多行封装为一个 RecordSet 对象
 */

namespace Atto\Box;

use Atto\Box\Db;
use Atto\Box\Record;
use Atto\Box\db\Table;
use Atto\Box\Uac;

use Atto\Box\traits\arrayIterator;

class RecordSet implements \ArrayAccess, \IteratorAggregate, \Countable
{
    //引入trait
    //可 for 循环此类，可用 $rs[idx] 访问 $context 数组，增加 each 方法
    use arrayIterator;

    //db instance
    public $db = null;
    //tb instance
    public $table = null;

    //for/each 方法针对的数组，记录集，每个元素均为 Record 实例
    public $context = [];

    /**
     * __call 方法，对所有 context 中的 record 对象执行操作
     */
    public function __call($key, $args)
    {
        $r = $this->context[0];
        //array 相关方法
        $arr_funcs = [
            "shift","unshift",
            "pop","push",
            "splice","slice"
        ];
        if (method_exists($r, $key)) {
            /*$rst = [];
            for ($i=0;$i<count($this->context);$i++) {
                $ri = $this->context[$i];
                $rst[] = $ri->$key(...$args);
            }
            return $rst;*/
            $rst = $this->map(function($record) use ($key, $args) {
                return $record->$key(...$args);
            });
            if (empty($rst)) return [];
            if ($rst[0] instanceof Record) {
                //如果每个 context-record 执行结果返回 record 本身
                array_splice($this->context, 0);
                $this->context = $rst;
                //返回 recordSet 本身
                return $this;
            } else {
                return $rst;
            }
        } else if (in_array($key, $arr_funcs)) {
            //增加 array 相关方法，操作 $this->context
            $af = "array_".$key;
            if (function_exists($af)) {
                if (in_array($key, ["shift", "pop"])) {
                    return $af($this->context);
                } else if (in_array($key, ["unshift","push"])) {
                    if (!empty($args)) {
                        $ags = [];
                        for ($i=0;$i<count($args);$i++) {
                            if ($args[$i] instanceof Record) {
                                $ags[] = $args[$i];
                            }
                        }
                        $af($this->context, ...$ags);
                    }
                    return $this;
                } else if ($key=="slice") {
                    //$dbrs = $this->export("db");
                    //$sl = array_slice($dbrs, ...$args);
                    //return $this->table->createRecordSet($sl);
                    $ctx = array_slice($this->context, ...$args);
                    return static::createFromRecordArray($ctx);
                } else if ($key=="splice") {
                    //$dbrs = $this->export("db");
                    //$sl = array_splice($dbrs, ...$args);
                    //$ctx = array_splice($this->context, ...$args);
                    //return $this->table->createRecordSet($sl);
                    array_splice($this->context, ...$args);
                    return $this;
                }
            }
        }

        return $this;
    }

    /**
     * __get 调用 record 对象的属性
     */
    public function __get($key) 
    {
        if ($this->table->hasField($key)) {
            return $this->map(function($record) use ($key) {
                return $record->context[$key];
            });
        } else if ($key=="_") {
            return $this->map(function($record) use ($key) {
                return $record->context;
            });
        }
    }

    /**
     * __set 设置全部 context record 对象的属性
     */
    public function __set($key, $value)
    {
        if ($this->table->hasField($key)) {
            return $this->map(function($record) use ($key, $value) {
                return $record->setField($key, $value);
            });
        }
    }

    /**
     * 是否空记录集
     */
    public function isEmpty()
    {
        return empty($this->context);
    }

    /**
     * 在 recordset 中筛选
     * @param Function $callback 筛选方法，返回 true or false
     * @return RecordSet
     */
    public function filter($callback)
    {
        if (!is_callable($callback)) return $this;
        $rs = [];
        for ($i=0;$i<count($this->context);$i++) {
            $ctx = $this->context[$i];
            $rst = $callback($ctx);
            if ($rst===true) {
            //if ($callback($ctx)===true) {
                //$rs[] = $ctx->export("db", false, false);
                $rs[] = $ctx;
            }
        }
        if (empty($rs)) return [];
        //$recordSetCls = static;
        $nrs = new static();
        $nrs->db = $this->db;
        $nrs->table = $this->table;
        $nrs->context = $rs;
        return $nrs;
        //return $this->table->createRecordSet($rs);
    }

    /**
     * 
     */






    /**
     * static
     */

    /**
     * 创建 RecordSet 记录集实例
     * @param Db $db        db instance
     * @param Table $tb     table instance
     * @param Array $data   recordset data 记录集
     * @return RecordSet  or  null
     */
    public static function create($db, $tb, $data = [])
    {
        if ($db instanceof Db && $tb instanceof Table) {
            if (!$tb->isRecordSet($data)) return null;
            $cls = static::class;
            $rcls = Record::getRecordCls($db->name."/".$tb->name);
            $rs = new $cls();
            $rs->db = $db;
            $rs->table = $tb;
            $rs->context = $data;
            /*if (empty($data)) {
                $rs->context = [];
            } else {
                $rs->context = array_map(function($di) use ($rcls, $db, $tb) {
                    return $rcls::create($db, $tb, $di);
                },$data);
            }*/
            return $rs;
        }
        return null;
    }

    /**
     * 根据 $dbtbn 获取 recordset 类全称
     * @param String $dbtbn     like: dbn/tbn
     */
    public static function getRecordSetCls($dbtbn)
    {
        if (!is_notempty_str($dbtbn) && strpos($dbtbn, "/")===false) trigger_error("db/record/needdbtbn", E_USER_ERROR);
        $arr = explode("/", $dbtbn);
        $tbn = array_pop($arr);
        $clsfn = Uac::prefix("record/".implode("/", $arr)."/".ucfirst($tbn).EXT, "path");
        $clsf = path_find($clsfn);
        if (empty($clsf)) return NS."RecordSet";
        require_once($clsf);
        $clsn = Uac::prefix("record/".implode("/", $arr)."/".ucfirst($tbn)."Set", "class");
        $cls = cls($clsn);
        if (is_null($cls)) return NS."RecordSet";
        return $cls;
    }

    /**
     * 从 [ Record, ... ] 数组创建 RecordSet
     * @param Array $recordArr 包含 record 实例的数组
     * @return RecordSet
     */
    public static function createFromRecordArray($recordArr = [])
    {
        if (empty($recordArr)) return [];
        $ri = $recordArr[0];
        $rs = new static();
        $rs->db = $ri->db;
        $rs->table = $ri->table;
        $rs->context = $recordArr;
        return $rs;
    }
}