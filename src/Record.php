<?php

/**
 * Attobox Framework / Active Record
 * 活动记录模式
 * 将数据表中的一行封装为一个 Record 对象
 * 
 * $record = Record::load(sql);     //创建活动记录
 * 
 * 数据库操作方法：
 *      Record::
 */

namespace Atto\Box;

use Atto\Box\Request;
use Atto\Box\request\Curl;
use Atto\Box\Db;
use Atto\Box\db\Table;
use Atto\Box\Uac;

use Atto\Box\traits\arrayIterator;

class Record implements \ArrayAccess, \IteratorAggregate
{
    //引入trait
    //可 for 循环此类，可用 $r[field] 访问 $context 数组，增加 each 方法
    use arrayIterator;

    //db instance
    public $db = null;
    //tb instance
    public $table = null;

    //是否新建记录
    public $isNew = false;

    /**
     * record data
     */
    //记录条目原始数据，for/each 方法针对此数组
    //数据格式为 从数据库 获取的格式 varchar / integer / float
    public $context = [];

    public $raw = [];   //未经变化的原始数据

    /**
     * construct
     */
    public function __construct()
    {
        
    }

    /**
     * get record class full name
     */
    public function cls() 
    {
        return static::class;
    }

    /**
     * 处理 context 数组，准备输出
     * @param Array $data 初始数据，可能来自数据库，可能来自新建数据
     * @return $this
     */
    public function prepare($data = [])
    {
        $data = $this->table->dft($data);
        $this->raw = $data;
        $conv = $this->table->convertor;
        $this->context = $conv->data($this->raw)->convTo("ctx")->export();
        return $this;
    }

    /**
     * 输出数据
     * @param String $to    要使用数据的位置  table / form
     * @param Bool $withVirtual 是否连带 virtual 虚拟字段一起输出
     * @param Bool $queryRelatedRecord 是否将关联表记录的关联表记录一并查询输出 false 表示进输出一层关联表，不查询关联表的关联表
     * @return Array
     */
    public function export($to="db", $withVirtual=false, $queryRelatedRecord=true)
    {
        $ctx = $this->table->convertor->data($this->context)->convTo($to)->export();
        $conf = $this->table->conf("field");
        if ($to=="show") {  //输出为前端展示，将所有 查询关联表字段 
            
            $this->eachField(function($field) use (&$ctx, $conf, $queryRelatedRecord) {
                $ci = $conf[$field];
                $di = $this->context[$field];
                if (
                    $ci["isSelector"]==true && 
                    is_def($ci, "selector") && 
                    is_def($ci["selector"], "source") && 
                    is_def($ci["selector"]["source"], "table")
                    //$ci["selector"]["useTable"]!=true
                ) {
                    $ctx[$field] = $this->table->queryRelatedTable($field, $di);
                    if ($queryRelatedRecord) {
                        $related_rs = $this->table->queryRelatedTableRecord($field, $di);
                        if (!empty($related_rs)) {
                            $ctx[$field."_related_rs"] = $related_rs;
                            if (is_associate($related_rs)) {
                                foreach ($related_rs as $fdn => $fval) {
                                    $ctx[$field."_".$fdn] = $fval;
                                }
                            }
                        }
                    }
                }
            });
        }
        //return !$withVirtual ? $this->context : arr_extend($this->context, $this->virtual());
        if (!$withVirtual) return $ctx;
        $vctx = $this->virtual($ctx);
        return arr_extend($ctx, $vctx);
        //return !$withVirtual ? $ctx : arr_extend($ctx, $this->virtual());
    }

    /**
     * 与 export 方法相似，仅输出部分字段，而不是所有字段
     * @param String $to    要使用数据的位置  table / form
     * @param Array $fds 要输出的字段名，可以是虚拟字段，或字段组合表达式（可由 vfparser 解析的表达式）
     * @return Array
     */
    public function exportFields($to="db", ...$fds)
    {
        if (empty($fds)) return [];
        $ctx = $this->table->convertor->data($this->context)->convTo($to)->export();
        $afds = $this->table->conf("fields");
        //$vfds = $this->table->conf("virtualFields");
        //$conf = $this->table->conf("field");
        $rst = [];
        $nsvfds = [];
        for ($i=0;$i<count($fds);$i++) {
            $fi = $fds[$i];
            if (in_array($fi, $afds)) {
                if ($to=="show" && $this->isRelatedField($fi)) {
                    $rst[$fi] = $this->table->queryRelatedTable($fi, $this->context[$fi]);
                } else {
                    $rst[$fi] = $ctx[$fi];
                }
            } else {
                $nsvfds[] = $fi;
            }
        }
        if (!empty($nsvfds)) {
            $vf = $this->virtual([],...$nsvfds);
            //var_dump($vf);
            if (!empty($vf)) {
                foreach ($vf as $k => $v) {
                    $rst[$k] = $v;
                }
            }
        }
        return $rst;
    }

    /**
     * 输出 virtual 虚拟字段数据
     * @param Array $ctx 经过处理的 context 上下文
     * @param Array $vfds 如果指定，则仅输出指定的虚拟字段，也可以是虚拟字段表达式（vfparser 可解析的表达式，类似虚拟字段的 value 定义式）
     * @return Array
     */
    public function virtual($ctx=[], ...$vfds)
    {
        $fds = $this->table->config["virtualFields"];
        if (!empty($vfds)) $fds = $vfds;
        //var_dump($fds);
        $fd = $this->table->config["field"];
        $ctx = empty($ctx) ? $this->export("show", false, false)/*$this->context*/ : $ctx;
        if (empty($fds)) return [];
        $vd = [];
        for ($i=0;$i<count($fds);$i++) {
            $fi = $fds[$i];
            $fdi = $fd[$fi] ?? null;
            if (!is_null($fdi)) {
                $tpl = $fdi["virtual"]; //虚拟字段的 value 形式，%{***}%
                $mi = "parseVirtualField".ucfirst($fi)."Value";
                if (method_exists($this, $mi)) {
                    $vd[$fi] = $this->$mi($tpl, $ctx);
                } else {
                    //调用 vfparser 虚拟字段解析器，解析虚拟字段设置 value，并计算返回虚拟字段值
                    $vd[$fi] = $this->table->vfparser->useCtx($ctx)->parse($tpl);
                }
            } else {    //不是字段，而是表达式
                $fdn = 'VFP_'.$i;   //指定一个临时字段名
                //解析这个表达式
                $vd[$fdn] = $this->table->vfparser->useCtx($ctx)->parse($fi);
            }
            //if (!empty($vfds) && !in_array($fi, $vfds)) continue;
            //$vd[$fi] = str_tpl($tpl, $ctx);
            //$vd[$fi] = $this->parseVirtualValue($fi, $tpl, $ctx);
        }
        return $vd;
    }

    /**
     * 根据虚拟字段的 value 设置，计算虚拟字段的值
     * @param String $vfield 虚拟字段名
     * @param String $value 虚拟字段的设置 value
     * @param Array $ctx 当前 record 的 context 
     */


    /**
     * 从数据库拉取数据，一般用于在 insert、update 后更新 context
     * @return $this
     */
    public function reload()
    {
        if ($this->isNew) return $this;
        $idf = $this->idf();
        $curd = $this->table->curd();
        $data = $curd->where([
            $idf => $this->context[$idf]
        ])->single();
        $this->prepare($data);
        return $this;
    }

    /**
     * 比较 context 与 raw
     * 获取经过变化的 字段和字段值，一般用于 update
     * @return Array 用于 update 的数据
     */
    public function updatedData() 
    {
        $ud = [];
        $raw = $this->raw;
        foreach ($this->context as $fdn => $val) {
            if (is_def($raw, $fdn) && $val == $this->raw[$fdn]) continue;
            $ud[$fdn] = $val;
        }
        return $ud;
    }



    /**
     * 获取/编辑数据
     */

    /**
     * __get 方法，$record->field
     */
    public function __get($key)
    {
        if ($this->table->hasField($key)) {
            return $this->context[$key];
        } else if ($key=="_") {
            return $this->context;
        } else if ($key=="_id") {   //获取自增序号
            $idf = $this->table->ik();
            if ($this->isNew) return null;
            return $this->context[$idf];
        } else if (substr($key, 0, 1)=="_") {
            $vd = $this->virtual([], $key);
            return $vd[$key];
        }
    }

    /**
     * __set 方法，$record->field = value
     */
    public function __set($key, $value)
    {
        if ($this->table->hasField($key)) {
            //$this->context[$key] = $value;
            $this->setField($key, $value);
        }
    }

    /**
     * 魔术方法调用 table 方法
     */
    public function __call($key, $args) 
    {
        if (method_exists($this->table, $key)) {
            return $this->table->$key(...$args);
        }
    }

    /**
     * 设置某个字段值，默认执行 convToDb(field, data)
     * @param String $field
     * @param Mixed $data
     * @param Bool $extend 是否合并，当字段值为 associate 时，是否合并新值，默认否，直接覆盖
     * @return $this
     */
    public function setField($field, $data = null, $extend = false)
    {
        if ($this->table->hasField($field)) {
            $od = $this->context[$field];
            if ($extend && is_array($data) && is_array($od)) {
                $conf = $this->table->conf($field);
                if (isset($conf["json"]) && isset($conf["json"]["type"])) {
                    $jtp = $conf["json"]["type"];
                    if ($jtp=="associate") {
                        $this->context[$field] = arr_extend($od, $data);
                    } else {
                        //indexed 数组，直接用新值覆盖旧值
                        $this->context[$field] = $data;
                        /*
                        $this->context[$field] = array_merge($od, $data);
                        //去重
                        $this->context[$field] = array_merge(array_flip(array_flip($this->context[$field])));
                        */
                    }
                } else {
                    $this->context[$field] = $data;
                }
            } else {
                $this->context[$field] = $data;
            }
        } else if (is_notempty_arr($field) && is_associate($field)) {
            $extend = (is_null($data) || !is_bool($data)) ? false : $data;
            $datas = $field;
            $idf = $this->table->ik();
            foreach ($datas as $fdn => $val) {
                if ($fdn==$idf) continue;
                if ($this->table->conf("$fdn/isGenerator")==true) continue;
                $this->setField($fdn, $val, $extend);
            }
        }
        return $this;
    }

    /**
     * 删除某个字段
     * @param String $field
     * @param Array $data   target data, 默认 $this->context
     * @return $this  or  data[]
     */
    public function removeField($field, $data = null)
    {
        if (is_notempty_arr($data)) {
            if (is_def($data, $field)) {
                unset($data[$field]);
            }
            return $data;
        }

        if (is_def($this->context, $field)) {
            unset($this->context[$field]);
        }
        return $this;
    }

    /**
     * 判断某个字段是否有关联表
     * @param String $field
     * @return Bool
     */
    public function isRelatedField($field) 
    {
        $fds = $this->table->conf("fields");
        if (!in_array($field, $fds)) return false;
        $conf = $this->table->conf("field")[$field];
        return (
            $conf["isSelector"]==true && 
            is_def($conf, "selector") && 
            is_def($conf["selector"], "source") && 
            is_def($conf["selector"]["source"], "table")
        );
    }

    /**
     * 获取 idf
     * @return String
     */
    public function idf()
    {
        return $this->table->ik();
    }

    /**
     * 
     */



    /**
     * 写入数据库
     * 执行顺序
     *  1   调用 GenerateField() 创建 generator 字段值，需要各 record 子类实现此方法
     *  2   调用 自定义的 beforeInsert / beforeUpdate 方法，根据 $this->isNew 来判断，子类覆盖
     *  3   生成要 save 的 data，删除自增主键，convToDb()
     *  4   $this->table->insert/update( data )，根据 $this->isNew 来判断
     *  5   调用 自定义的 afterInsert / afterUpdate 方法，根据 $this->isNew 来判断，子类覆盖
     *  6   $this->isNew  true --> false
     */
    public function save()
    {
        $isNew = $this->isNew;
        $idf = $this->idf();
        //自动生成字段值
        if ($isNew) $this->generateFields();
        //调用 before 方法
        if ($isNew) {
            $this->beforeInsert();
        } else {
            $this->beforeUpdate();
        }
        //生成要 save 的 data
        $data = $isNew ? $this->context : $this->updatedData();
        $data = $this->removeField($idf, $data);    //id 字段不可编辑
        if (!$isNew) {
            //update 时不可编辑 generate 字段
            $data = $this->removeGenerateFields($data);   
        }
        //数据类型转换为 todb
        $data = $this->table->convertor->data($data)->convTo("db")->export();
        //执行写入
        if ($isNew) {
            $result = $this->table->insert($data);
            $id = $this->table->db->medoo()->id();
        } else {
            $id = $this->context[$idf];
            $where = [
                $idf => $id
            ];
            $result = $this->table->where($where)->update($data);
        }
        //调用 after 方法
        if ($isNew) {
            $this->afterInsert($result);
        } else {
            $this->afterUpdate($result);
        }
        //变更当前 record 状态
        if ($isNew) {
            $this->context[$idf] = $id;
            $this->isNew = false;
            $this->reload();
        } else {
            $this->reload();
        }

        return /*$isNew ? $id : */$this;
    }

    /**
     * 删除数据
     * 从数据库中删除此条记录
     */
    public function del()
    {
        $this->beforeDelete();
        $idf = $this->idf();
        $id = $this->context[$idf];
        $rst = $this->table->where([
            $idf => $id
        ])->delete();
        $this->afterDelete($rst);
        return $rst;
    }

    /**
     * 调用 各字段的 generator 方法（如果 conf(field/isGenerator) == true）
     * 各 record 子类必须实现 对应 field 的 自动生成方法
     * @return $this
     */
    protected function generateFields() {
        $this->eachField(function($field) {
            if ($this->table->conf("$field/isGenerator")==true) {
                $gen = $this->table->conf("$field/generator");
                $m = "generate".ucfirst($gen);
                if (method_exists($this, $m)) {
                    //return $this->$m();
                    $d = $this->$m();
                    $this->setField($field, $d);
                } else {
                    trigger_error("db/curd/generator:: $m 方法不存在,".$this->table->name, E_USER_ERROR);
                }
            }
            return true;
        });
        return $this;
    }

    /**
     * update 时不可编辑 generate 字段
     * 从 context 中删除 generate 字段
     * @param Array $data  要处理的字段值数组
     * @return $this
     */
    protected function removeGenerateFields($data = null) {
        if (is_notempty_arr($data)) {
            $isCtx = false;
        } else {
            $data = $this->context;
            $isCtx = true;
        }
        $this->eachField(function($field) use (&$data) {
            if ($this->conf("$field/isGenerator")==true) {
                if (is_def($data, $field)) {
                    unset($data[$field]);
                }
            }
        });
        return $isCtx ? $this : $data;
    }
    
    /**
     * before / after 方法，各 record 子类根据需要覆盖
     */
    protected function beforeInsert() { return $this; }
    protected function afterInsert($result) { return $this; }
    protected function beforeUpdate() { return $this; }
    protected function afterUpdate($result) { return $this; }
    protected function beforeDelete() { return $this; }
    protected function afterDelete($result) { return $this; }



    /**
     * record 通用 api 
     */

    /**
     * api
     * <%数据记录状态数据判断%>
     */
    public function apiStatusis()
    {
        $query = Request::input("json");
        $query = isset($query["query"]) ? $query["query"] : $query;
        $status = $query["status"] ?? null;
        if (isset($query["status"])) {
            unset($query["status"]);
        }
        $rst = [
            "result" => false,
            "query" => $query,
            "status" => $status
        ];
        if (empty($status)) return $rst;
        $logic = $status["logic"] ?? "==";
        $value = $status["value"] ?? [];
        if (empty($value)) return $rst;
        $rs = $this->table->query->apply($query)->whereEnable(1)->single();
        if (!$rs instanceof Record) return $rst;
        $rst["result"] = $rs->statusIs($logic, ...$value);
        return $rst;
    }

    /**
     * api
     * <%记录状态回退操作%>
     */
    public function apiStatusback() 
    {
        if (!$this->table->hasField("status")) return ["success"=>false, "msg"=>"当前记录表不支持状态字段 status"];
        $pd = Request::input("json");
        $query = $pd["query"] ?? null;
        $steps = $pd["steps"] ?? 1;
        if (empty($query)) trigger_error("custom::缺少必要参数，无法执行记录状态回退操作", E_USER_ERROR);
        $rs = $this->table->query->apply($query)->whereEnable(1)->single();
        if (empty($rs)) trigger_error("custom::无法找到要回退状态的记录", E_USER_ERROR);
        $origin = $rs->status;

        $back = $rs->statusBack($steps);
        if (is_string($back)) {
            trigger_error("custom::".$back, E_USER_ERROR);
        } else if ($back===false || !$back instanceof Record) {
            trigger_error("custom::状态回退不成功，可能状态字段不正确", E_USER_ERROR);
        }

        return [
            "originStatus" => $origin,
            "newStatus" => $back->status,
            "rs" => $back->export("show", true, false)
        ];
    }



    /**
     * tools
     */

    /**
     * eachField 
     * @param Callable $callback
     * @return Array [ field => callback result, ... ]
     */
    public function eachField($callback)
    {
        if (!is_callable($callback)) return [];
        $fds = $this->table->fields;;
        $rst = [];
        for ($i=0;$i<count($fds);$i++) {
            $fdn = $fds[$i];
            $rsti = $callback($fdn);
            if ($rsti===false) break;
            if ($rsti===true) continue;
            $rst[$fdn] = $rsti;
        }
        return $rst;
    }



    /**
     * related tools
     * 关联表 操作
     */

    /**
     * 获取关联表数据
     * @param Array $query 关联表查询
     * @return RecordSet or []
     */
    public function getRelatedRs($query = [])
    {

    }



    /**
     * status tools
     * 记录状态 status 字段操作
     */

    /**
     * 获取 status 字段 可选值列表
     * 字段名默认 status
     * @return Array [ statusValue1, statusValue2, ... ]
     */
    public function statusList()
    {
        if (!$this->table->hasField("status")) return [];
        $conf = $this->table->conf("field/status");
        if (!isset($conf["selector"]) || !isset($conf["selector"]["source"]) || !isset($conf["selector"]["source"]["api"])) {
            return [];
        }
        $api = $conf["selector"]["source"]["api"];
        $api = Request::$current->url->domain."/".trim($api, "/");
        $rtn = Curl::get($api, "ssl");
        $rtn = j2a($rtn);
        $sts = [];
        for ($i=0;$i<count($rtn);$i++) {
            if ($rtn[$i]["value"]!="") {
                $sts[] = $rtn[$i]["value"];
            }
        }
        return $sts;
    }

    /**
     * 获取指定 status 值在 statusList 中的 idx
     * @param String $status 状态值
     * @return Int idx
     */
    public function statusIdx($status = null, $statusList = [])
    {
        $status = is_null($status) ? $this->context["status"] : $status;
        if (!is_notempty_str($status)) return -1;
        $sl = empty($statusList) ? $this->statusList() : $statusList;
        if (empty($sl)) return -1;
        $idx = -1;
        for ($i=0;$i<count($sl);$i++) {
            if ($sl[$i]==$status) {
                $idx = $i;
                break;
            }
        }
        return $idx;
    }

    /**
     * each 循环 statusList
     * @param Callable $callable 对每一个 status 执行的方法函数
     * @return Array [ result1, result2, ... ]
     */
    public function statusEach($callable, ...$args)
    {
        if (!is_callable($callable)) return [];
        $sl = $this->statusList();
        if (empty($sl)) return [];
        $rst = [];
        for ($i=0;$i<count($sl);$i++) {
            $sli = $sl[$i];
            $rsti = call_user_func($callable, $sli, ...$args);
            if ($rsti===true) continue;
            if ($rsti===false) break;
            $rst[] = $rsti;
        }
        return $rst;
    }

    /**
     * 状态值判断
     * @param String $logic 比较方式 >,<,>=,<=,==,!=
     * @param String $status 状态值 或 状态idx，可以有多个，根据 比较方式
     * @return Bool
     */
    public function statusIs($logic = "==", ...$status)
    {
        if (!$this->table->hasField("status")) return false;
        if (empty($status)) return false;
        $idx = $this->statusIdx();
        $sl = $this->statusList();
        $ist = $this->context["status"];
        if (in_array($logic, [">","<",">=","<="])) {
            $cidx = is_numeric($status[0]) ? $status[0] : $this->statusIdx($status[0], $sl);
            switch ($logic) {
                case ">": return $idx>$cidx; break;
                case "<": return $idx<$cidx; break;
                case ">=": return $idx>=$cidx; break;
                case "<=": return $idx<=$cidx; break;
            }
        } else if (in_array($logic, ["==","!="])) {
            if (is_numeric($status[0])) {
                $chk = $idx;
            } else {
                $chk = $ist;
            }
            $inarr = in_array($chk, $status);
            return $logic=="==" ? $inarr : !$inarr;
        }
    }

    /**
     * 记录状态 status 字段 回退到上一状态
     * 用于特殊情况处理
     * super 权限
     */
    public function statusBack($steps = 1)
    {
        if (!$this->table->hasField("status")) return false;
        $conf = $this->table->conf("field/status");
        if (isset($conf["selector"]) && isset($conf["selector"]["source"]) && isset($conf["selector"]["source"]["api"])) {
            $api = $conf["selector"]["source"]["api"];
            $api = Request::$current->url->domain."/".trim($api, "/");
            $rtn = Curl::get($api, "ssl");
            $rtn = j2a($rtn);
            $current = $this->status;
            $curidx = -1;
            for ($i=1;$i<count($rtn);$i++) {
                if ($rtn[$i]["value"]==$current) {
                    $curidx = $i;
                    break;
                }
            }
            if ($curidx>=0) {
                $newidx = $curidx + ($steps * -1);
                if ($newidx>=1) {
                    $newstu = $rtn[$newidx]["value"];
                    $this->setField("status", $newstu);
                    $this->save();
                    return $this;
                }
            }
            return "当前状态不支持后退 $steps 步";
        }
        return false;
    }



    /**
     * extra 额外信息字段操作
     * 其他 json 格式字段也可以操作
     */

    /**
     * 查找 json 格式字段数据
     * @param String $field JSON 格式字段的字段名
     * @param String $keys 要查找的数据 key，可以有多个，默认返回找到的第一个数据
     * @return String
     */
    public function getJsonFieldValue($field, ...$keys)
    {
        if (!$this->table->hasField($field)) return null;
        $fdv = $this->context[$field] ?? null;
        if (is_null($fdv)) return null;
        if (is_string($fdv)) $fdv = j2a($fdv);
        if (empty($fdv) || !is_array($fdv)) return null;
        $rst = null;
        foreach ($keys as $key) {
            if (isset($fdv[$key])) {
                $rst = $fdv[$key];
                break;
            }
        }
        return $rst;
    }

    /**
     * 查找 extra 数据，未找到返回 null
     * @param String $keys extra 数据的键名
     * @return Mixed  or  null
     */
    public function getExtra(...$keys)
    {
        return $this->getJsonFieldValue("extra", ...$keys);
    }





    /**
     * static
     */

    /**
     * 创建 Active Record 实例
     * @param Db $db        db instance
     * @param Table $tb     table instance
     * @param Array $data   record data 记录条目
     * @return Record  or  null
     */
    public static function create($db, $tb, $data = [])
    {
        if ($db instanceof Db && $tb instanceof Table) {
            if (!$tb->isRecord($data)) return null;
            $cls = static::class;
            $r = new $cls();
            $r->db = $db;
            $r->table = $tb;
            //$r->context = $data;
            $r->prepare($data);
            return $r;
        }
        return null;
    }

    /**
     * 根据 $dbtbn 获取 record 类全称
     * @param String $dbtbn     like: dbn/tbn
     */
    public static function getRecordCls($dbtbn)
    {
        if (!is_notempty_str($dbtbn) && strpos($dbtbn, "/")===false) trigger_error("db/record/needdbtbn", E_USER_ERROR);
        $arr = explode("/", $dbtbn);
        $tbn = array_pop($arr);
        $clsn = Uac::prefix("record/".implode("/", $arr)."/".ucfirst($tbn), "class");
        $cls = cls($clsn);
        //if (is_null($cls)) trigger_error("db/record/nocls::".$dbtbn, E_USER_ERROR);
        if (is_null($cls)) return NS."Record";
        return $cls;
    }

    /**
     * 获取当前 Record 所在 table 的全部记录
     * @param String $xpath 表xpath like app/appname/dbname/tbname
     * @param Int $enable 是否要求 enable 默认 1，可选 0/1/-1，-1 时不筛选 enable
     * @return RecordSet
     */
    public static function all($xpath, $enable = 1)
    {
        $tb = Table::load($xpath);
        if (!is_numeric($enable)) $enable = 1;
        $enable = $enable>0 ? 1 : ($enable<0 ? -1 : 0); 
        if ($enable!=-1) $tb->whereEnable($enable);
        $rs = $tb->select();
        if (empty($rs)) return [];
        return $rs;
    }

    /**
     * 获取当前 Record 子类对应的 RecordSet 类的全名
     * 如：Usr::recordSetCls()  -->  "\\Atto\\Box\\record\\UsrSet"
     */
    public static function recordSetCls()
    {
        $cls = static::class;
        //var_dump($cls);
        $scls = $cls."Set";
        //var_dump($scls);
        //var_dump(class_exists($scls));
        return $scls;
        //exit;
    }
}