<?php

/**
 * Attobox Framework / CURD operate class
 */

namespace Atto\Box\db;

use Atto\Box\Db;
use Medoo\Medoo;

class Curd
{
    //关联的 DB 实例
    protected $db = null;

    //medoo 实例
    protected $_medoo = null;

    //curd 操作参数
    protected $table = "";
    protected $join = null;
    protected $field = null;
    protected $where = null;
    protected $limit = null;
    protected $order = null;
    protected $match = null;    //全文匹配
    protected $group = null;
    protected $having = null;
    protected $debug = false;
    protected $sql = "";

    //不同逻辑关系的 where 条件计数器
    protected $cdtCount = [
        "AND" => 0,
        "OR" => 0
    ];

    //transactions 事务队列
    protected $transactions = [];

    //curd 参数解析后得到的 medoo 查询参数
    protected $option = [];

    //本次 curd 操作结果
    protected $rs = [];

    //构造
    public function __construct($db, $params = [])
    {
        $this->db = $db;
        $this->_medoo = $this->db->medoo();
        $this->set($params);
    }

    //set 参数
    protected function set($params = [])
    {
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                if (method_exists($this, $k)) {
                    if (!is_indexed($v)) $v = [$v];
                    $this->$k(...$v);
                } else if (property_exists($this, $k)) {
                    $this->$k = $v;
                }
            }
        }
        return $this;
    }

    //

    //返回 curd 参数内容
    public function info($key = "")
    {
        if ($key=="") {
            return [
                "join" => $this->join,
                "field" => $this->field,
                "where" => $this->where,
                "limit" => $this->limit,
                "order" => $this->order
            ];
        }
        return property_exists($this, $key) ? $this->$key : null;
    }
    
    //返回medoo对象，或调用medoo方法
    public function medoo($method = "", $param = [])
    {
        if (empty($method)) return $this->_medoo;
        if ($this->debug) return call_user_func_array([$this->_medoo->debug(),$method], $param);
        return call_user_func_array([$this->_medoo,$method], $param);
    }

    //判断 curd 是否已经初始化
    public function ready($throwError = true)
    {
        if (!$this->db instanceof Db || !$this->_medoo instanceof Medoo || !is_notempty_str($this->table)) {
            if ($throwError) trigger_error("db/nocurd::".$this->db->info("name"),E_USER_ERROR);
            return false;
        }
        return true;
    }

    //初始化一个curd操作，指定目标数据表
    public function table($tablename = "")
    {
        if (!$this->ready(false)) {
            if (!str_has($tablename, "/")) {
                $this->table = $tablename;
                return $this;
            } else {
                $tarr = explode("/", $tablename);
                $this->table = $tarr[0];
                return $this->field($tarr[1]);
            }
        }else{
            trigger_error("db/incurd::".$this->db->info("name"), E_USER_ERROR);
        }
    }

    //重置curd操作
    public function reset($params = null)
    {
        $this->table = "";
        $this->join = null;
        $this->field = null;
        $this->where = null;
        $this->limit = null;
        $this->order = null;
        $this->match = null;
        $this->group = null;
        $this->having = null;
        $this->debug = false;
        $this->sql = "";
        $this->cdtCount = [
            "AND" => 0,
            "OR" => 0
        ];
        $this->option = [];
        $this->rs = [];
        
        if (is_notempty_str($params)) {
            $this->table($params);
        } else if (is_associate($params)) {
            $this->set($params);
        }
        return $this;
    }

    //根据输入解析 join 查询条件，多表查询
    public function join($join = [])
    {
        if (!$this->ready()) return $this;
        if (empty($join)) return $this;
        if (is_null($this->join)) $this->join = [];
        if (is_associate($join)) $this->join = arr_extend($this->join, $join);
        return $this;
    }

    //根据输入解析field查询字段，
    public function field($field = [])
    {
        if (!$this->ready()) return $this;
        if (empty($field)) return $this;
        if ($field == "*") {
            $this->field = null;
            return $this;
        }
        if (is_notempty_str($field)) {
            $field = arr($field);
        } else if (!is_indexed($field)) {
            $field = [];
        }
        
        if (empty($field)) return $this;
        if (is_null($this->field) || !is_indexed($this->field) || (is_indexed($this->field) && empty($this->field))) {
            $this->field = $field;
        } else {
            $this->field = array_unique(array_merge($this->field, $field));
        }
        return $this; 
    }

    //在现有查询字段基础上，增加字段
    public function addField($field = [])
    {
        if (!$this->ready()) return $this;
        if (
            empty($field) || 
            is_null($this->field) || 
            !is_indexed($this->field) || 
            (is_indexed($this->field) && empty($this->field))
        ) {
            return $this;
        }
        if (is_notempty_str($field) && $field != "*") {
            $field = arr($field);
        } else if (!is_indexed($field)) {
            $field = [];
        }
        if (empty($field)) return $this;
        $this->field = array_unique(array_merge($this->field, $field));
        return $this;
    }

    //where
    public function where($where = [])
    {
        if (!$this->ready()) return $this;
        if (empty($where)) return $this;
        if (is_null($this->where)) $this->where = [];
        if (isset($where["OR"])) {
            $this->cdtCount["OR"] += 1;
            $this->where["OR #condition_".$this->cdtCount["OR"]] = $where["OR"];
            unset($where["OR"]);
        }
        if (isset($where["AND"])) {
            $where = arr_extend($where, $where["AND"]);
            unset($where["AND"]);
        }
        //var_dump("\r\n\r\nfoo\r\n",$this->where, $where);
        if (!empty($where)) $this->where = arr_extend($this->where, $where);
        return $this;
    }

    //直接使用sql作为where条件
    public function sql($sql = "")
    {
        if (!$this->ready()) return $this;
        if (!str_has($sql, "WHERE")) $sql = "WHERE ".$sql;
        $this->where = Medoo::raw($sql);
        return $this;
    }

    //limit
    public function limit()
    {
        if (!$this->ready()) return $this;
        $args = func_get_args();
        if (!empty($args)) {
            if (count($args) == 1) {
                $this->limit = $args[0];
            } else {
                $this->limit = array_slice($args,0,2);
            }
        }
        return $this;
    }

    //order
    public function order()
    {
        if (!$this->ready()) return $this;
        $args = func_get_args();
        $odr = [];
        for ($i=0; $i<count($args); $i++) {
            $arg = $args[$i];
            if (is_string($arg)) {
                if (is_query($arg)) {
                    $arr = u2a($arg);
                    foreach ($arr as $k => $v) {
                        if (str_has($v, ",")) $v = arr($v);
                        $odr[$k] = $v;
                    }
                } else if (str_has($arg, " ") || str_has($arg, ",")) {
                    $arr = explode(",", $arg);
                    for ($j=0; $j<count($arr); $j++) {
                        if (!str_has($arr[$j], " ")) {
                            $odr[] = $arr[$j];
                        } else {
                            $kv = explode(" ", $arr[$j]);
                            $odr[$kv[0]] = $kv[1];
                        }
                    }
                } else if (in_array($arg, ["DESC", "ASC"])) {
                    $id = $this->db->autoIncrementField($this->table);
                    $odr[$id] = $arg;
                } else {
                    $odr[] = $arg;
                }
            } else if (is_array($arg)) {
                if (is_indexed($arg)) {
                    $odr = array_merge($odr, $arg);
                } else {
                    foreach ($arg as $k => $v) {
                        $odr[$k] = $v;
                    }
                }
            }
        }
        $this->order = is_array($this->order) ? array_merge($this->order, $odr) : $odr;
        return $this;
    }

    //match
    public function match($match = [])
    {
        if (!$this->ready()) return $this;
        if (is_associate($match) && !empty($match)) {
            if (is_null($this->match)) {
                $this->match = arr_extend([
                    "mode" => "natural" // natural,natural+query,boolean,query
                ], $match);
            } else {
                $this->match = arr_extend($this->match, $match);
            }
        }
        return $this;
    }

    //group
    public function group($group = [])
    {
        if (!$this->ready()) return $this;
        if (empty($group)) return $this;
        if (is_notempty_str($group)) $group = arr($group);
        if (!is_indexed($group)) $group = [];
        if (empty($group)) return $this;
        if (!is_indexed($this->group) || empty($this->group)) {
            $this->group = $group;
        } else {
            $this->group = array_unique(array_merge($this->group, $group));
        }
        return $this;
    }

    //having
    public function having($having = [])
    {
        if (!$this->ready()) return $this;
        if (is_associate($having) && !empty($having)) {
            if (is_null($this->having)) {
                $this->having = $having;
            } else {
                $this->having = arr_extend($this->having, $having);
            }
        }
        return $this;
    }

    //debug
    public function debug()
    {
        if (!$this->ready()) return $this;
        $this->debug = true;
        return $this;
    }

    //处理 where 参数数组，将其中包含的 :: 标记的值进行预处理
    protected function prepareWhere($w = [])
    {
        $w = empty($w) ? $this->where : $w;
        if (empty($w)) return [];
        $outw = [];
        foreach ($w as $k => $v) {
            if (is_notempty_str($v)) {
                $outw[$k] = $this->doPrepareWhere($v);
            } else if (is_indexed($v) && !empty($v)) {
                $outw[$k] = $this->doPrepareWhere(...$v);
            } else if (is_associate($v) && !empty($v)) {
                $outw[$k] = $this->prepareWhere($v);
            } else {
                $outw[$k] = $v;
            }
        }
        return $outw;
    }
    protected function doPrepareWhere(...$args)
    {
        if (empty($args)) return null;
        if (count($args)==1) {
            $str = $args[0];
            if (is_notempty_str($str) && strpos($str, "::")!==false) {
                $arr = explode("::",$str);
                switch ($arr[0]) {
                    case "raw" :
                        return Medoo::raw($arr[1]);
                        break;
                    case "eval" :
                        eval("\$ev = ".$arr[1].";");
                        return $ev;
                        break;
                }
            }
            return $str;
        }
        $out = [];
        for ($i=0;$i<count($args);$i++) {
            $out[] = $this->doPrepareWhere($args[$i]);
        }
        return $out;
    }

    //解析生成 where option
    protected function whereOption()
    {
        if(!$this->ready()) return [];
        $where = !is_null($this->where) ? $this->where : [];

        //处理 where 中 :: 标记
        $where = $this->prepareWhere($where);
        
        if (!is_null($this->limit)) $where["LIMIT"] = $this->limit;
        if (!is_null($this->order)) $where["ORDER"] = $this->order;
        if (is_associate($this->match) && !empty($this->match)) $where["MATCH"] = $this->match;
        if (is_indexed($this->group) && !empty($this->group)) $where["GROUP"] = $this->group;
        if (is_associate($this->having) && !empty($this->having)) $where["HAVING"] = $this->having;
        return $where;
    }

    //解析生成medoo查询参数
    protected function option()
    {
        if(!$this->ready()) return [];
        $opt = [];
        $opt[] = $this->table;
        if (is_associate($this->join) && !empty($this->join)) {
            $opt[] = $this->join;
        }
        $tfd = $this->field;
        if (is_null($tfd) || !is_indexed($tfd) || (is_indexed($tfd) && empty($tfd))) {
            $fds = "*";
        } else {
            $fds = $tfd;
        }
        $opt[] = $fds;
        $where = $this->whereOption();
        //var_dump($where);
        if (!empty($where)) $opt[] = $where;
        //var_dump($opt);
        $this->option = $opt;
        //var_dump($opt);
        return $opt;
    }

    //解析生成各medoo方法的参数
    protected function parseMedooOptions()
    {
        if(!$this->ready()) return [];
        $args = func_get_args();
        $method = array_shift($args);
        switch ($method) {
            case "select" :
            case "get" :
            case "rand" :
            case "max" :
            case "min" :
            case "avg" :
            case "sum" :
                $opt = $this->option();
                break;
            case "insert" :
                $opt = [$this->table, $args[0]];
                break;
            case "update" :
            case "replace" :
                $opt = [$this->table, $args[0]];
                $where = $this->whereOption();
                if (!empty($where)) $opt[] = $where;
                break;
            case "delete" :
            case "has" :
                $opt = [$this->table];
                $where = $this->whereOption();
                if (!empty($where)) $opt[] = $where;
                break;
            case "count" :
                $opt = [];
                if (is_null($this->field)) {
                    $opt[] = $this->table;
                    $where = $this->whereOption();
                    if (!empty($where)) $opt[] = $where;
                }else{
                    $opt = $this->option();
                }
                break;
        }
        return $opt;
    }

    //执行各curd方法
    protected function exec()
    {
        if(!$this->ready()) return null;
        $args = func_get_args();
        $reset = is_bool(array_slice($args,-1)[0]) ? array_pop($args) : true;
        //$opt = call_user_func_array([$this, "parseMedooOptions"], $args);
        $opt = $this->parseMedooOptions(...$args);
        $rs = $this->medoo($args[0], $opt);
        $this->rs = $rs;
        if($reset) $this->reset($this->table);
        //var_dump($rs);
        return $rs;
    }

    //curd方法，包装medoo方法，方便链式调用
    public function select($reset=true){return $this->exec("select",$reset);}
    public function insert($data=[], $reset=true){return $this->exec("insert",$data,$reset);}
    public function update($data=[], $reset=true){return $this->exec("update",$data,$reset);}
    public function delete($reset=true){return $this->exec("delete",$reset);}
    public function replace($data=[], $reset=true){return $this->exec("replace",$data,$reset);}
    public function get($reset=true){return $this->exec("get",$reset);}
    public function has($reset=true){return $this->exec("has",$reset);}
    public function rand($reset=true){return $this->exec("rand",$reset);}
    public function count($reset=true){return $this->exec("count",$reset);}
    public function max($reset=true){return $this->exec("max",$reset);}
    public function min($reset=true){return $this->exec("min",$reset);}
    public function avg($reset=true){return $this->exec("avg",$reset);}
    public function sum($reset=true){return $this->exec("sum",$reset);}
    public function lastInsertId(){return $this->_medoo->id();}

    //single，查询单条记录
    public function single($reset=true)
    {
        if(!$this->ready()) return null;
        
        //$pk = $this->db->autoIncrementField($this->table);
        $tb = $this->db->table($this->table);
        $pk = $tb->id(false);

        $this->order($pk." DESC")->limit(1);
        $rs = $this->select($reset);
        if (!empty($rs)) return $rs[0];
        return null;
    }
    
    //事务操作，db->table()->trans('insert',[data])->where()->trans('select')->commit()
    //将当前curd操作加入事务队列
    public function trans()
    {
        if(!$this->ready()) return $this;
        $args = func_get_args();
        $param = call_user_func_array([$this,"parseMedooOptions"], $args);
        $this->transactions[] = [$args[0],$param];
        $this->reset($this->table);
        return $this;
    }
    //执行事务队列
    public function commit()
    {
        $trans = $this->transactions;
        $out = $this->_medoo->action(function($medoo) use ($trans) {
            $result = null;
            for ($i=0; $i<count($trans); $i++){
                $result = call_user_func_array([$medoo,$trans[$i][0]], $trans[$i][1]);
            }
            return $result;
        });
        $this->reset();
        $this->transactions = [];
        return $out;
    }
    //直接使用medoo::action方法执行事务
    public function action()
    {
        return $this->medoo("action", func_get_args());
    }

    //medoo->query  执行原生sql
    public function query(...$sql)
    {
        return $this->_medoo->query(...$sql);
    }

    //输出 sql 用于 debug
    public function echosql()
    {
        $this->debug()->select(false);
        //从缓冲区读取 sql
        $sql = ob_get_contents();
        //清空缓冲区
        ob_clean();

        return $sql;
    }



    /*
     *  static
     */
    //根据 $key 查找可用的 curd 操作类，返回类全称，未找到返回 null
    public static function cls($key = "")
    {
        if (!is_notempty_str($key)) return null;
        // TODO ...
    }



}