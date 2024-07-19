<?php

/**
 * Attobox Framework / Table
 */

namespace Atto\Box\db;

use Atto\Box\Db;
use Atto\Box\db\Configer;
use Atto\Box\db\Query;
use Atto\Box\db\Curd;
use Atto\Box\Record;
use Atto\Box\RecordSet;
use Atto\Box\request\Url;

use Atto\Box\Uac;

class Table 
{
    //db instance
    public $db = null;

    //curd instance
    protected $_curd = null;

    //tbn
    public $name = "";

    //call path like: dbn/tbn
    public $xpath = "";
    
    /**
     * table info config
     */
    public $config = null;

    //convertor 数据格式转器 实例
    public $convertor = null;

    //查询器，build 查询参数
    public $query = null;

    //虚拟表标记
    public $isVirtual = false;

    /**
     * construct
     */
    public function __construct()
    {
        
    }

    /**
     * 初始化 config 参数，来自 [dbpath]/config/[dbn].json
     * @param Bool $reload      强制重新生成 config 参数
     * @return $this
     */
    public function initConfig($reload = false)
    {
        //只进行一次 initConfig，或强制 reload
        if (!$reload && !is_null($this->config)) return $this;

        $tbn = $this->name;
        $conf = $this->db->conf("table/$tbn");
        $this->config = [];
        $ks = ["name","title","desc","fields","creation"];
        for ($i=0;$i<count($ks);$i++) {
            $ki = $ks[$i];
            if (isset($conf[$ki])) {
                $this->config[$ki] = $conf[$ki];
            }
        }
        $this->config["xpath"] = $this->xpath;
        $this->config["isVirtual"] = $this->isVirtual;
        $this->config["userelatedtable"] = $conf["userelatedtable"] ?? false;
        //$this->config["fastfilter"] = $conf["fastfilter"] ?? [];
        $this->config["highlight"] = $conf["highlight"] ?? [];
        $this->config["field"] = [];

        /* 在输出 _export_ 时检查 directedit 权限，否则 Uac 加载数据表时会 反复 Uac::grant() 死循环
        $dire = isset($conf["directedit"]) ? $conf["directedit"] : false;
        $udire = Uac::grant("db-".$this->db->name."-".$this->name."-directedit");
        $this->config["directedit"] = $dire || $udire;
        */

        $configer = new Configer($this->db, $this);
        $configer->parse();
        $this->config["form"] = $configer->parseTableForm();
        //$this->config["detail"] = $configer->parseTableDetail();
        $this->config["fastfilter"] = $configer->parseTableFastfilter();

        return $this;
    }

    /**
     * get table config
     * @param String $key       like: foo/bar  -->  db->conf(tbn/foo/bar)
     * @return Array table info
     */
    public function conf($key = null)
    {
        //$conf = $this->getConfig()->config;
        $conf = $this->initConfig()->config;
        if (empty($key) || !is_notempty_str($key)) return $conf;
        
        //if ($this->name=="要输出的表名") {var_dump($conf);exit;}

        switch ($key) {
            //输出到前端的 table config 通常用于 vue 组件
            case "_export_" :
                $oc = [
                    "db" => [
                        "name" => $this->db->name,
                        "title" => $this->db->title,
                        "desc" => $this->db->desc,
                    ],
                    "table" => []
                ];
                $cks = explode(",", "name,xpath,title,desc,userelatedtable,isVirtual,form,formui,detail,fields,field,virtualFields,mode,fastfilter,highlight,default");
                foreach ($cks as $ck) {
                    if (is_def($conf, $ck)) {
                        $oc["table"][$ck] = $conf[$ck];
                    }
                }
                //$oc["api"] = Url::mk("/".DB_ROUTE."/api"."/".$this->db->name."/".$this->name."/")->full;
                $oc["table"]["idf"] = $this->ik();
                //输出当前用户是否有当前表的 directedit 权限，（通过前端表格组件直接编辑 的权限）
                $cf = $this->db->conf("table/".$this->name);
                $dire = isset($cf["directedit"]) ? $cf["directedit"] : false;
                $udire = Uac::grant("db-".$this->db->name."-".$this->name."-directedit");
                //$this->config["directedit"] = $dire || $udire;
                $oc["table"]["directedit"] = $dire || $udire;
                return $oc;
                break;

            default:
                $karr = explode("/", $key);
                if (in_array($karr[0], $conf["fields"]) || in_array($karr[0], $conf["virtualFields"])) {
                    array_unshift($karr, "field");
                }
                $key = implode("/", $karr);
                return arr_item($conf, $key);
                break;
        }
    }

    /**
     * 使用默认值填满所有字段，使 $data 包含全部字段
     * @param Array $data       记录条目
     * @param Bool $fieldsOnly  仅保留在 config["fields"] 中存在的字段，默认 false
     * @return Array
     */
    public function dft($data = [], $fieldsOnly = false)
    {
        $conf = $this->conf();
        $fd = $conf["field"];
        if (empty($data)) {
            $dfts = [];
            for ($i=0;$i<count($conf["fields"]);$i++) {
                $fdi = $conf["fields"][$i];
                if (is_def($fd[$fdi], "default")) {
                    $dfts[$fdi] = $fd[$fdi]["default"];
                }
            }
            return $dfts;
            //将默认值转换为 db 写入数据库类型
            //return $this->convertor->data($dfts)->convTo("db")->export();
        }
        $dfts = $this->dft();
        if (is_notempty_str($data) && $this->hasField($data) && is_def($dfts, $data)) { 
            return $dfts[$data];
        }
        if (is_notempty_arr($data) && is_associate($data)) {
            if ($fieldsOnly) {
                $nd = [];
                for ($i=0;$i<count($conf["fields"]);$i++) {
                    $fdi = $conf["fields"][$i];
                    if (is_def($data, $fdi)) {
                        $nd[$fdi] = $data[$fdi];
                    }
                }
                $data = $nd;
            }
            return arr_extend($dfts, $data);
        }
        return null;
    }



    /**
     * curd 操作
     */
    
    /**
     * create curd instance & return
     * @param Bool $reset   
     * @return Curd instance with table param = $this->name
     */
    public function curd($reset = false)
    {
        $tbn = $this->name;
        if (is_null($this->_curd)) {
            $this->_curd = new Curd($this->db, [
                "table" => $tbn
            ]);
        }
        if ($reset) $this->_curd->reset();
        if (!$this->_curd->ready(false)) {
            $this->_curd->table($tbn);
        }
        return $this->_curd;
    }

    /**
     * 覆盖 curd->reset() 方法，默认设定 curd->table = $this->name
     * @return $this
     */
    public function reset()
    {
        $this->curd(true);
        return $this;
    }

    /**
     * call curd methods using magic method __call
     * @param String $method    method name
     * @param Array $args       method arguments
     * @return Mixed curd query result
     */
    public function __call($method, $args)
    {
        $curd = $this->curd();
        //call curd methods
        if (method_exists($curd, $method)) {
            if (in_array($method, ["select", "single"])) {     //如果调用 select 方法，则确保查询字段包含了 id，用于包裹生成 item 实例
                $curd->addField($this->pk());
            }
            $rst = $curd->$method(...$args);
            if ($rst instanceof Curd) return $this;
            if ($this->isRecord($rst)) return $this->createRecord($rst);
            if ($this->isRecordSet($rst)) return $this->createRecordSet($rst);
            
            return $rst;
        } else if ($this->hasField($method)) {
            // $table->field(">", 10)->select()
            $where = [];
            if (count($args) <= 0) {
                // $table->field()  -->  返回 field config 参数
                return $this->conf($method);
            }
            if (count($args) == 1) {
                $where[$method] = $args[0];
            } else {
                $where[$method."[".$args[0]."]"] = $args[1];
            }
            return $this->where($where);
        } else {
            // $table->whereField("~", $val)
            // $table->hasField("~", $val)
            $bw = null;
            foreach (["where","has"] as $i => $s) {
                if (str_begin($method, $s)) {
                    $field = strtolower(substr($method, strlen($s)));
                    if ($this->hasField($field)) {
                        $bw = $s;
                        break;
                    }
                }
            }
            if (!is_null($bw)) {    // whereFieldname / hasFieldname ("~","value")
                $where = [];
                if (count($args) <= 0) return null;
                if (count($args) == 1) {
                    $where[$field] = $args[0];
                } else {
                    $where[$field."[".$args[0]."]"] = $args[1];
                }
                switch ($bw) {
                    case "where" :  return $this->where($where); break;
                    case "has" :    
                        $where["enable"] = 1;
                        return $this->reset()->where($where)->has();
                        break;
                }
            } else {
                //$table->apiFoobar 通过 table 实例调用 record 实例的 api 方法
                if (str_begin($method, "api")) {
                    //var_dump($method);
                    $recordCls = Record::getRecordCls($this->xpath);
                    if (class_exists($recordCls)) {
                        //var_dump($recordCls);
                        //检查 record 类是否包含 方法 $method
                        if (method_exists($recordCls, $method)) {
                            //如果 record 类包含方法 $method 直接调用此方法
                            $rsobj = $this->new();
                            //var_dump($rsobj);
                            return $rsobj->$method(...$args);
                        }
                    }
                }
            }
        }
    }

    /**
     * Create 增
     * @param Array $data
     * @return Record
     */
    public function C($data)
    {
        $new = $this->new($data);
        return $new->save();
        //return $new;
    }

    /**
     * Update 改
     * @param Array $data
     * @param Query $query    Query 查询实例
     * @return Record
     */
    public function U($data, $query)
    {
        $record = $this->query->apply($query)->select();
        if (empty($record)) return [];
        $record = $record->setField($data, true);
        $record = $record->save();
        
        if (count($record)==1) {
            $record = $record->context[0];
        }
        return $record;
    }

    /**
     * Retrieve 查
     * @param Query $query    Query 查询实例
     * @return RecordSet
     */
    public function R($query=[])
    {
        $cq = arr_copy($query);
        $page = isset($cq["page"]) ? $cq["page"] : null;
        $limit = isset($cq["limit"]) ? $cq["limit"] : null;
        if (isset($cq["page"])) unset($cq["page"]);
        if (isset($cq["limit"])) unset($cq["limit"]);
        if (!is_null($page)) {
            $rscount = $this->query->apply($cq)->count();
            $pgsize = $page["size"];
            $pgcount = ceil($rscount/$pgsize);
            $page = arr_extend($page, [
                "rscount" => $rscount,
                "pgcount" => $pgcount
            ]);
        }
        $rs = $this->query->apply($query)->select();
        if (empty($query)) return $rs;
        $rst = [
            "query" => $cq,
            "rs" => $rs
        ];
        if (!is_null($page)) {
            $rst["page"] = $page;
        }
        return $rst;
    }

    /**
     * Delete 删
     * @param Query $query    Query 查询实例
     * @return Medoo 
     */
    public function D($query)
    {
        //$rst = $this->query->apply($query)->delete();
        //return $rst;
        $rs = $this->query->apply($query)->select();
        if (empty($rs)) return [];
        $rst = $rs->del();
        return $rst;
    }

    /**
     * 直接获取全部记录，直接 select
     * @return Array [ [ field1:'content in db', field2:'', ... ], ... ]
     */
    public function all()
    {
        $idf = $this->ik();
        $medoo = $this->curd(true)->medoo();
        $rst = $medoo->select($this->name, "*");
        /*$rs = $this->query->apply([
            "order" => [
                $idf => "ASC"
            ]
        ])->select();
        if (!empty($rs) && $rs instanceof RecordSet) {
            $rst = $rs->export("db", false);
        } else {
            $rst = [];
        }*/
        return $rst;
    }

    /**
     * 判断 array 是否一个记录条目
     * @param Array $data       associate array
     * @return Bool
     */
    public function isRecord($data = [])
    {
        if ($data instanceof Record) return true;
        if (!is_notempty_arr($data) || !is_associate($data)) return false;
        $fds = $this->conf("fields");
        $ks = array_keys($data);
        if (count($ks)>count($fds)) return false;
        $flag = true;
        for ($i=0;$i<count($ks);$i++) {
            if (!in_array($ks[$i], $fds)) {
                $flag = false;
                break;
            }
        }
        return $flag;
    }

    /**
     * 判断一个 indexed array 是否是记录集
     * @param Array $data       indexed array
     * @return Bool
     */
    public function isRecordSet($data = [])
    {
        if ($data instanceof RecordSet) return true;
        if (!is_notempty_arr($data) || !is_indexed($data)) return false;
        $flag = true;
        for ($i=0;$i<count($data);$i++) {
            $di = $data[$i];
            if (!$this->isRecord($di)) {
                $flag = false;
                break;
            }
        }
        return $flag;
    }

    /**
     * 创建活动记录实例
     * @param Array $data       记录条目数据
     * @return Record instance  or  null
     */
    public function createRecord($data = [])
    {
        if ($data instanceof Record) return $data;
        if (empty($data)) $data = $this->dft($data);
        $recordCls = Record::getRecordCls($this->xpath);
        //if (empty($recordCls)) return null;
        if (empty($recordCls)) return Record::create($this->db, $this, $data);
        return $recordCls::create($this->db, $this, $data);
    }

    /**
     * 创建记录集实例
     * @param Array $data   记录集数据
     * @return RecordSet instance  or  null
     */
    public function createRecordSet($data = [])
    {
        if ($data instanceof RecordSet) return $data;
        if (!$this->isRecordSet($data)) return null;
        $self = $this;
        $ndata = array_map(function($value) use ($self) {
            return $self->createRecord($value);
        }, $data);
        $recordSetCls = RecordSet::getRecordSetCls($this->xpath);
        //if (empty($recordSetCls)) return null;
        if (empty($recordSetCls)) return RecordSet::create($this->db, $this, $ndata);
        return $recordSetCls::create($this->db, $this, $ndata);
    }

    /**
     * 新建一条记录（未写入数据库）
     * @param Array $data   初始数据
     * @return Record instance
     */
    public function new($data = [])
    {
        $record = $this->createRecord($data);
        $record->isNew = true;
        $record->removeField($this->ik());  //新建记录不包含 id 字段
        return $record;
    }

    /**
     * 查询关联表
     * @param String $field 本表中的关联字段
     * @param Mixed $data 关联字段的值
     * @return Mixed 从关联表中查询获得的值
     */
    public function queryRelatedTable($field, $data = null)
    {
        if (empty($data)) return $data;
        $ci = $this->conf($field);
        if ($ci["isSelector"]!=true || !isset($ci["selector"]) || !isset($ci["selector"]["source"])) return $data;
        $source = $ci["selector"]["source"];
        if (isset($ci["selector"]["multiple"]) && $ci["selector"]["multiple"]==true) {
            if (!is_array($data)) $data = j2a($data);
        }
        
        $vals = [];
        if (isset($source["table"])) {
            $tb = Table::load($source["table"]);
            $rtfd = $source["value"];
            $rs = $tb->query->apply([
                "where" => [
                    $rtfd => $data
                ]
            ])->select();
            if (!$rs instanceof RecordSet) return $data;
            if ($rs->count()<=0) return $data;

            /**
             * 查询关联表，不包含虚拟字段
             * ! 包含虚拟字段将严重影响响应时间
             * 如果需要显示关联表的虚拟字段，使用 vfparser 单独生成
             */
            $rsd = $rs->export("show", false, false);
            $lfd = $source["label"];
            for ($i=0;$i<count($rsd);$i++) {
                $rsi = $rsd[$i];
                if (is_def($rsi, $lfd)) {
                    $vals[] = $rsi[$lfd];
                } else {
                    //!使用 vfparser 单独生成虚拟字段
                    $vals[] = $tb->vfparser->useCtx($rsi)->parse($lfd);
                }
                //$vals[] = strpos($lfd, "%{")!==false ? str_tpl($lfd, $rsi) : $rsi[$lfd];
            }
        } else if (isset($source["api"])) {
            //$vals[] = $data;
            $api = $source["api"];
            $api = substr($api, 0, 1)=="/" ? $api : "/".$api;
            $u = Url::mk($api)->full;
            $d = file_get_contents($u);
            $d = j2a($d);
            $d = $d["data"];
            //var_dump($d);
            if (isset($source["key"])) {
                $rs = $d[$source["key"]];
            } else {
                $rs = $d;
            }
            for ($i=0;$i<count($rs);$i++) {
                $rsi = $rs[$i];
                if (is_array($data)) {
                    if (in_array($rsi["value"], $data)) {
                        $vals[] = $rsi["label"];
                    }
                } else {
                    if ($rsi["value"]==$data) {
                        $vals[] = $rsi["label"];
                    }
                }
            }
        }
        return is_array($data) ? $vals : implode(",",$vals);
        //return $data;
    }

    /**
     * 查询关联表，返回完整的记录行
     * @param String $field 本表中的关联字段
     * @param Mixed $data 关联字段的值
     * @param Bool $queryRelatedRecord 是否将关联表记录的关联表记录一并查询输出 false 表示仅输出一层关联表，不查询关联表的关联表
     * @return Mixed 从关联表中查询获得的值
     */
    public function queryRelatedTableRecord($field, $data = null, $queryRelatedRecord = false)
    {
        if (empty($data)) return [];
        $ci = $this->conf($field);
        if ($ci["isSelector"]!=true || !isset($ci["selector"]) || !isset($ci["selector"]["source"])) return [];
        $source = $ci["selector"]["source"];
        if (isset($ci["selector"]["multiple"]) && $ci["selector"]["multiple"]==true) {
            if (!is_array($data)) $data = j2a($data);
        }
        
        if (isset($source["table"])) {
            $tb = Table::load($source["table"]);
            $rtfd = $source["value"];
            $rs = $tb->query->apply([
                "where" => [
                    $rtfd => $data
                ]
            ])->select();
            if (!$rs instanceof RecordSet) return [];
            if ($rs->count()<=0) return [];
            if (is_array($data)) {
                return $rs->export("show", true, $queryRelatedRecord);   //查询并包含虚拟字段
            } else {
                return $rs[0]->export("show", true, $queryRelatedRecord);
            }
            //$rsd = $rs->export("show", true);   
        }
        return [];
    }

    /**
     * get select options
     * @param Array $query  query options
     * @return Array
     */
    public function getSelectOptions($query=[])
    {
        $rs = $this->query->apply($query)->whereEnable(1)->order("id DESC")->select();
        if (empty($rs) || !$rs instanceof RecordSet) return [];

        $lfd = $query["label"];
        $vfd = $query["value"];

        $rso = $rs->exportFields("db", $vfd);
        $rss = $rs->exportFields("show", $lfd);
        $vals = [];
        for ($i=0;$i<count($rs);$i++) {
            $rsoi = $rso[$i];
            $rssi = $rss[$i];
            $vals[] = [
                "label" => $rssi["VFP_0"] ?? $rssi[$lfd],
                "value" => $rsoi["VFP_0"] ?? $rsoi[$vfd]
            ];
        }

        /**
         * 将某个表作为 selector 下拉菜单时，查询符合条件的记录
         * ! 不输出虚拟字段，否则严重影响响应时间
         * 如果下拉列表 label 是虚拟字段，应单独使用 vfparser 生成
         */
        //$rso = $rs->export("db", true);
        //$rss = $rs->export("show", true, false);
        /*$rso = $rs->export("db", false);
        $rss = $rs->export("show", false, false);

        $vals = [];
        $lfd = $query["label"];
        $vfd = $query["value"];
        for ($i=0;$i<count($rso);$i++) {
            $rsoi = $rso[$i];
            $rssi = $rss[$i];
            if (is_def($rsoi, $vfd)) {
                $vali = $rsoi[$vfd];
            } else {
                $vali = $this->vfparser->useCtx($rsoi)->parse($vfd);
            }
            if (is_def($rssi, $lfd)) {
                $labi = $rssi[$lfd];
            } else {
                $labi = $this->vfparser->useCtx($rssi)->parse($lfd);
            }
            $vals[] = [
                "label" => $labi,
                "value" => $vali
            ];
        }*/

        if (isset($query["special"]) && is_array($query["special"])) {
            array_unshift($vals, ...$query["special"]);
        }
        return $vals;
    }

    /**
     * get cascader options
     * @param Array $query  query options
     * @return Array
     */
    public function getCascaderOptions($query=[])
    {
        $fid = isset($query["fid"]) ? $query["fid"] : "fid";
        $id = $this->ik();
        $fidval = isset($query["fidval"]) ? $query["fidval"]*1 : 0;
        $isroot = $fidval == 0;
        $cq = ["where" => []];
        $cq["where"][$fid] = $fidval;
        $query = arr_extend($query, $cq);

        $rs = $this->query->apply($query)->whereEnable(1)->select();
        if (empty($rs) || !$rs instanceof RecordSet) return [];
        //$rs = $rs->export();
        $rso = $rs->export("db", true);
        $rss = $rs->export("show", true);

        $vals = [];
        $lfd = $query["label"];
        $vfd = $query["value"];
        for ($i=0;$i<count($rso);$i++) {
            $rsoi = $rso[$i];
            $rssi = $rss[$i];
            $vali = [
                "label" => is_def($rssi, $lfd) ? $rssi[$lfd] : $this->vfparser->useCtx($rssi)->parse($lfd),
                "value" => is_def($rsoi, $vfd) ? $rsoi[$vfd] : $this->vfparser->useCtx($rsoi)->parse($vfd)
            ];
            $query["fidval"] = $rsoi[$id];
            $children = $this->getCascaderOptions($query);
            if (!empty($children)) {
                $vali["children"] = $children;
            } else {
                $vali["leaf"] = true;
            }
            $vals[] = $vali;
        }

        if (isset($query["special"]) && is_array($query["special"]) && $isroot) {
            $spec = array_map(function($item) {
                $item["leaf"] = true;
                return $item;
            }, $query["special"]);
            array_unshift($vals, ...$spec);
        }
        return $vals;

    }

    /**
     * 将表中某一列输出为下拉列表选项
     * @param Array $query  query options
     * @param String $field 要输出的列
     * @return Array
     */
    public function getColumnOptions($query=[], $field)
    {
        if (empty($query)) $query = [];
        $rs = $this->query->apply($query)->whereEnable(1)->order($this->ik(),)->select();
        
        if (empty($rs) || !$rs instanceof RecordSet) {
            return [];
        }

        $lfd = $query["label"];
        $vfd = $query["value"];

        $rso = $rs->exportFields("db", $field);
        $rss = $rs->exportFields("show", $field);
        $vals = [];
        $chk = [];
        for ($i=0;$i<count($rs);$i++) {
            $rsoi = $rso[$i];
            $rssi = $rss[$i];
            if (!in_array($rsoi[$field], $chk)) {
                $chk[] = $rsoi[$field];
                $vals[] = [
                    "label" => $rssi["VFP_0"] ?? $rssi[$field],
                    "value" => $rsoi["VFP_0"] ?? $rsoi[$field]
                ];
            }
        }

        /*$rso = $rs->export("db", true);
        $rss = $rs->export("show", true);

        $chk = [];
        $vals = [];
        for ($i=0;$i<count($rso);$i++) {
            $rsoi = $rso[$i];
            $rssi = $rss[$i];
            if (!in_array($rsoi[$field], $chk)) {
                $chk[] = $rsoi[$field];
                $vals[] = [
                    "label" => $rssi[$field],
                    "value" => $rsoi[$field]
                ];
            }
        }*/
        //$rst = $rs->export();
        /*$rst = $rs->$field;
        $rst = array_values(array_unique($rst));
        $vals = array_map(function($i) {
            return [
                "label" => $i,
                "value" => $i
            ];
        }, $rst);*/
        return $vals;
    }



    /**
     * fields methods
     */

    /**
     * get autoincrement field name
     * @param Bool $findAll     get all autoincrement fields
     * @return String field name
     */
    public function autoIncrementKey($findAll = false)
    {
        $cr = $this->conf("creation");
        //var_dump($cr);
        $fds = [];
        foreach ($cr as $fdn => $cri) {
            if (strpos($cri, "AUTOINCREMENT")!==false) {
                if (!$findAll) return $fdn;
                $fds[] = $fdn;
            }
        }
        return $findAll ? $fds : null;
    }
    public function ik($fdn = null)
    {
        if (!is_notempty_str($fdn)) return $this->autoIncrementKey();
        $ks = $this->autoIncrementKey(true);
        return in_array($fdn, $ks);
    }

    /**
     * get primary key field name
     * @return String field name
     */
    public function primaryKey()
    {
        $cr = $this->conf("creation");
        foreach ($cr as $fdn => $cri) {
            if (strpos($cri, "PRIMARY KEY")!==false) {
                return $fdn;
            }
        }
        return null;
    }
    public function pk()
    {
        return $this->primaryKey();
    }

    /**
     * 判断是否存在字段名
     * @param String $fdn
     * @return Bool
     */
    public function hasField($fdn)
    {
        $fds = $this->conf("fields");
        return in_array($fdn, $fds);
    }

    /**
     * 对 fields 字段列表执行循环操作
     * @param Callable $callback
     * @return $this
     */
    public function eachField($callback = null)
    {
        if (!is_callable($callback)) return [];
        $fds = $this->fields;;
        $rst = [];
        for ($i=0;$i<count($fds);$i++) {
            $fdi = $fds[$i];
            $rsti = $callback($this, $fdi);
            if ($rsti===false) break;
            if ($rsti===true) continue;
            $rst[$fdi] = $rsti;
        }
        return $rst;
    }



    /**
     * static tools
     */

    /**
     * 根据 xpath 加载数据表
     * @param String $xpath
     * @return Table
     */
    public static function load($xpath)
    {
        $xarr = explode("/", $xpath);
        $tbn = array_pop($xarr);
        $dsn = implode("/", $xarr);
        $db = Db::load($dsn);
        return $db->table($tbn);
    }

    



    


    




    
}