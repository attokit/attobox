<?php

/**
 * Attobox Framework / db table query
 * query 查询参数 解析 与 应用到 curd 操作
 * 
 *      $table->query->apply($queryArray);  已将 query 参数写入 curd 操作对象，可直接 select / update / delete
 *      $rs = $table->select()
 */

namespace Atto\Box\db;

use Atto\Box\Db;
use Atto\Box\db\Table;
use Atto\Box\Request;
use Atto\Box\RecordSet;

class Query
{
    /**
     * 关联的 table 实例
     */
    public $table = null;

    //支持的 query 参数名称
    public $props = [
        "filter",   // [ field1 => [ logic=> (> < <= != ~ !~ ... ), value=> ** ], ... ]
        "sort",     // [ field1 => asc | desc | '', ... ]
        "sk",       // [ sk1, sk2, ... ]
        "page",     // [ ipage=> 1, size=> 100 ]
        "field",    // 'field1, field2, ...'  or  *
        "where",    // [ 'field[~]' => **, ... ]
        "order",    // [ field1 => 'desc', ... ]
        "limit",    // 1  or  'start, length'
    ];

    /**
     * construct
     * @param Table $tb
     * @return void
     */
    public function __construct($tb)
    {
        $this->table = $tb;
    }

    /**
     * 解析 query 参数，并应用到 curd 操作对象
     * @param Array $option     传入的查询参数
     * @param Bool $reset       是否重置上一次的 curd 操作参数，默认重置
     * @return $this->table
     */
    public function apply($option = [], $reset = true)
    {
        if (empty($option)) {
            $post = Request::input("json");
            if (isset($post["query"]) && !empty($post["query"])) {
                $option = $post["query"];
            } else {
                $option = [];
            }
        }
        
        $this->table->curd($reset);

        $props = $this->props;
        for ($i=0;$i<count($props);$i++) {
            $propi = $props[$i];
            if (!isset($option[$propi])) continue;
            $opt = $option[$propi];
            $m = "parse".ucfirst($propi);
            if (method_exists($this, $m)) {
                $this->$m($opt);
            }
        }
        return $this->table;
    }



    /**
     * 解析 query 参数 方法
     */

    /**
     * parseFilter
     * @param Array $filter 参数
     * @return void
     */
    protected function parseFilter($filter = [])
    {
        if (!empty($filter)) {
            $fds = $this->table->conf("fields");
            $fd = $this->table->config["field"];
            $ctx = [];

            foreach ($filter as $fdn => $fti) {
                if (!isset($fti["logic"]) || $fti["logic"]=="" || empty($fti["logic"]) || !isset($fti["value"]) || is_null($fti["value"])) continue;
                $logic = $fti["logic"];
                //$logic = $logic=="" ? "=" : $logic;
                $val = $fti["value"];
                $key = $logic=="=" ? $fdn : $fdn."[".$logic."]";
                if (in_array($fdn, $fds)) {
                    if (isset($fd[$fdn]["isTime"]) && $fd[$fdn]["isTime"]==true) {
                        //如果字段为日期时间类型，处理 可能存在的 jsTimestamp
                        $val = $this->jsTampToUnixTamp($val);
                    }
                    $ctx[$key] = $val;
                }
            }

            if (!empty($ctx)) {
                $this->table->where($ctx);
            }
        }
    }

    /**
     * parseSort
     * @param Array $sort
     * @return void
     */
    protected function parseSort($sort = [])
    {
        if (!empty($sort)) {
            $rst = [];
            foreach ($sort as $fdn => $sby) {
                if (!is_notempty_str($sby) || !in_array(strtolower($sby), ["asc","desc"])) continue;
                $rst[$fdn] = strtoupper($sby);
            }
            if (!empty($rst)) {
                $this->table->order($rst);
            }
        }
    }

    protected function parseSk($sk = [])
    {
        if (!empty($sk)) {
            $fds = $this->table->conf("field");
            $where = [];
            foreach ($fds as $fdn => $fdi) {
                if (!isset($fdi["searchable"]) || $fdi["searchable"]!=true) continue;
                if (isset($fdi["isSelector"]) && $fdi["isSelector"]==true) {
                    $sei = $fdi["selector"];
                    if (isset($sei["source"]) && isset($sei["source"]["table"])) {
                        $srci = $sei["source"];
                        $tbi = $srci["table"];
                        $vfi = $srci["value"];
                        //$lbi = $srci["label"];
                        $tba = explode("/", $tbi);
                        $tbin = array_pop($tba);
                        $dbin = implode("/", $tba);
                        $dbi = Db::load($dbin);
                        $tbi = $dbi->table($tbin);
                        $rsi = $tbi->query->apply([
                            "sk" => $sk
                        ])->select();
                        //$tbci = $tbi->conf();
                        /*$wi = [];
                        if ($tbci["field"][$vfi]["isVirtual"]!=true) {
                            $wi[$vfi."[~]"] = $sk;
                        }
                        if ($lbi!=$vfi && $tbci["field"][$lbi]["isVirtual"]!=true) {
                            $wi[$lbi."[~]"] = $sk;
                        }
                        if (!empty($wi)) {
                            $wi = ["OR" => $wi];
                            $rsi = $tbi->query->apply($wi)->select();
                            $valsi = [];*/
                            if (!empty($rsi) && $rsi instanceof RecordSet) {
                                $rsi = $rsi->export("ctx");
                                for ($i=0;$i<count($rsi);$i++) {
                                    $valsi[] = $rsi[$i][$vfi];
                                }
                            }
                            if (!empty($valsi)) {
                                $where[$fdn] = $valsi;
                            }
                        //}
                    }
                }
                $where[$fdn."[~]"] = $sk;
            }
            if (!empty($where)) {
                $this->table->where([
                    "OR" => $where
                ]);
            }
        }
    }

    protected function parsePage($page = [])
    {
        if (!empty($page)) {
            $ipage = (isset($page["ipage"]) && is_numeric($page["ipage"])) ? $page["ipage"]*1 : 1;
            $size = (isset($page["size"]) && is_numeric($page["size"])) ? $page["size"]*1 : 100;
            if ($ipage>0) {
                $start = ($ipage-1) * $size;
                $this->table->limit($start, $size);
            }
        }
    }

    protected function parseField($field = [])
    {
        if (!empty($field)) {
            $this->table->field($field);
        }
    }

    protected function parseWhere($where = [])
    {
        $w = [];
        $issub = false;
        foreach ($where as $key => $wv) {
            if (is_array($wv) && isset($wv["_subquery_"]) && $wv["_subquery_"]==true) {
                $issub = true;
                $xpath = $wv["table"];
                $field = $wv["field"];
                unset($wv["_subquery_"]);
                unset($wv["table"]);
                $xarr = explode("/", $xpath);
                $tbn = array_pop($xarr);
                $db = Db::load(implode("/", $xarr));
                $tb = $db->table($tbn);
                $rs = $tb->query->apply($wv)->select();
                if ($rs instanceof RecordSet) {
                    $w[$key] = $rs->$field;
                }
            } else {
                $w[$key] = $wv;
            }
        }
        if (!empty($w)) {
            //if ($issub) var_dump($w);
            $this->table->where($w);
        }
        /*if (!empty($where)) {
            $this->table->where($where);
        }*/
    }
    
    protected function parseOrder($order = [])
    {
        $this->parseSort($order);
    }

    protected function parseLimit($limit = [])
    {
        if (is_notempty_arr($limit) && is_indexed($limit)) {
            $this->table->limit(...$limit);
        } else if (is_numeric($limit)) {
            $this->table->limit($limit);
        }
    }


    /**
     * tools
     */

    //处理时间戳类型的字段值，将可能的 js 时间戳 转换为 php 时间戳
    protected function jsTampToUnixTamp($tamp)
    {
        if (is_array($tamp)) {
            $nt = [];
            for ($i=0;$i<count($tamp);$i++) {
                $ti = $this->jsTampToUnixTamp($tamp[$i]);
                if (!empty($ti)) {
                    $nt[] = $ti;
                }
            }
            return $nt;
        } else if (is_int($tamp) || is_numeric($tamp)) {
            $tstr = (string)$tamp;
            $ln = strlen($tstr);
            if ($ln==10) {
                //php 时间戳
                return $tamp*1;
            } else if ($ln>10) {
                //js 时间戳，包含毫秒
                $nt = substr($tstr, 0, 10);
                return $nt*1;
            } else {
                return null;
            }
        }
    }

}