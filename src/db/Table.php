<?php

/**
 * Attobox Framework / Db Table class
 */

namespace Atto\Box\db;

use Atto\Box\Db;

class Table
{
    /**
     * belong to Db instance
     * @var Db
     */
    public $db;

    /**
     * table config
     */
    public $name = "";          //table name
    public $creation = [];      //creation option array
    public $default = [];       //default field value array
    public $fields = [];        //field name list
    public $field = [];         //field instance list

    /**
     * row instance list
     */
    public $row = [];

    /**
     * construct
     */
    public function __construct($db, $tbn)
    {
        $this->db = $db;
        $this->name = strtolower($tbn);
    }

    /**
     * table init
     * @return $this
     */
    public function init()
    {
        $this->curd();
        //fix table config
        if (empty($this->primaryfield)) $this->primaryfield = $this->id();
        if (empty($this->idfield)) $this->idfield = $this->id();
        if (empty($this->exportfield)) $this->exportfield = $this->exp();

        return $this;
    }

    /**
     * get table info
     * @return Array table all config
     */
    public function info()
    {
        $cf = $this->db::$_CONF["table"];
        $info = [];
        foreach ($cf as $k => $v) {
            if (property_exists($this, $k)) {
                $info[$k] = $this->$k;
            }
        }
        $ps = ["creation","default","fields","field"];
        for ($i=0;$i<count($ps);$i++) {
            $psi = $ps[$i];
            $info[$psi] = $this->$psi;
        }
        return $info;
    }



    /**
     * get(create) field instance
     */



    /**
     * magic method
     */
    public function __get($key)
    {
        if (substr($key, 0, 4) == "col_") {
            $fdn = substr($key, 4);
            if (isset($this->field[$fdn])) return $this->field[$fdn];
            return null;
        }
        return null;
    }

    /**
     * call curd method
     */
    public function __call($key, $args)
    {
        $curd = $this->curd(false);
        if (method_exists($curd, $key)) {
            if ($key == "select") {     //如果调用 select 方法，则确保查询字段包含了 id，用于包裹生成 item 实例
                $curd->addField($this->id());
            }
            $rst = $curd->$key(...$args);
            //包裹 curd 方法返回的结果
            if ($rst instanceof Curd) {     //如果返回 curd 实例，则改为返回此 table 实例
                return $this;
            } else if ($this->isRowsData($rst) || $this->isRowData($rst)) {  //如果返回 rs 记录条目 或 记录集，则包裹为 item 实例（数组）
                //var_dump($rst);
                return $this->row($rst);
            } else {     //返回其他类型数据，则直接返回
                return $rst;
            }
        } else if (substr($key, 0, 9)=="getItemBy" || substr($key, 0, 10)=="getItemsBy") {   //getItem(s)ByField("~", "value")

        } else {
            $bw = null;
            foreach (["where","has"] as $i => $s) {
                if (str_begin($key, $s)) {
                    $field = strtolower(substr($key, strlen($s)));
                    if ($this->hasField($field) || $field=="id") {
                        $bw = $s;
                        if (!$this->hasField($field) && $field=="id") $field = $this->id();
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
                    case "has" :    return $this->curd()->where($where)->has(); break;
                }
            }
        }
        return $this;
    }



    /**
     * curd methods
     */

    /**
     * init or reset curd operate
     * 
     * $this->curd()->where()->order()->select()  -->  row(s) instance
     * $this->curd(false)  -->  $this->db->curd()
     * $this->curd(ORIGINAL)->where()->order()->select()  -->  recordset array
     * 
     * @param Bool $reset       if reset db->curd
     * @return $this  or  Curd instance db->curd
     */
    public function curd($reset = true)
    {
        if ($reset!==false) {
            $this->db->curd($this->name);
            if ($reset===true) return $this;
            return $this->db->curd();
        } else {
            return $this->db->curd();
        }
    }

    /**
     * create (new) row instance
     * @param Mixed $arg
        //  1、 自增主键 id 的值，自动查询并返回 row 实例 or null
        //  2、 通过 curd 操作获得的 记录条目 或 记录集 数组，返回包裹成 row or rows 的实例
        //  3、 新建记录条目的 字段值，返回 新建的 row 实例
        //  4、 row 实例，直接返回
        //  5、 SQL 语句，直接调用 curd->query()，返回经过包裹的 row 实例
        //  6、 boolean，此时本方法将等价于 $this->select(true/false)，通过已有 curd 参数，查询并返回 row 实例
        //  7、 null，新建一个空 row，用于 insert
     * @return Row | Rows | null
     */
    public function row($arg = null)
    {
        $cls = $this->rowCls();
        $id = $this->id();
        if (is_null($arg)) {     //空参数，新建空 item 实例
            return new $cls($this);
        } else if (is_bool($arg)) {     //boolean参数，作为 select() 方法使用，返回 item 实例数组
            return $this->select($arg);
        } else if (is_numeric($arg) || (is_notempty_str($arg) && strpos($arg, " ")===false)) {   //参数为 自增主键 键值
            $w = [];
            $w[$id] = $arg;
            if ($this->hasId($arg)) {   //id 存在，则返回此 row
                $rs = $this->curd(ORIGINAL)->where($w)->single();
                return new $cls($this, $rs);
            } else {    //否则新建 row，id 值为 $arg
                return new $cls($this, $w);
            }
        } else if (is_associate($arg)) {     //参数为 对象
            if ($this->isRowData($arg)) {     //参数为 rs 记录条目
                return new $cls($this, $arg);
                /*if (isset($arg[$id]) && !empty($arg[$id]) && $this->hasId($arg[$id])) {    //现有记录，返回 item 实例
                    return new $cls($this, $arg);
                } else {    //新建 记录
                    if (isset($arg[$id])) unset($arg[$id]);
                    return new $cls($this, ["input"=>$arg, "isNewItem"=>true]);
                }*/
            } else {    //参数为 row 构造参数
                return new $cls($this, ["option"=>$arg]);
            }
        } else if ($this->isRowsData($arg)) {   //参数为 rs 记录集，输出 row 实例数组
            $rows = [];
            for ($i=0; $i<count($arg); $i++) {
                $rows[] = $this->row($arg[$i]);
            }
            return new Rows($this, $rows);
        } else if ($arg instanceof $cls) {   //参数为 row 实例
            return $arg;
        } else if (is_notempty_str($arg)) {
            return $this->query($arg);
        } else {
            return null;
        }
    }

    /**
     * add curd condition: where id = $id
     * @param Integer $id   id number
     * @return $this
     */
    public function whereId($id = 0)
    {
        $where = [];
        $where[$this->id()] = $id;
        $this->where($where);
        return $this;
    }

    /**
     * add curd condition: order by id $type
     * @param String $type      order type: DESC or ASC
     * @return $this
     */
    public function sortId($type = "DESC")
    {
        $this->order($this->id()." ".$type);
        return $this;
    }
    
    /**
     * curd has(): id = $id 
     * @param Integer $id   id number
     * @return Bool
     */
    public function hasId($id)
    {
        return $this->curd()->whereId($id)->has();
    }



    /**
     * tools
     */

    /**
     * check if has field
     * @param String $field     field name
     * @return Bool
     */
    public function hasField($field)
    {
        return in_array($field, $this->fields);
    }

    /**
     * check if is AUTOINCREMENT field
     * @param String $field     field name
     * @return Bool
     */
    public function isAutoincrement($field)
    {
        if (!$this->hasField($field) || !isset($this->creation[$field])) return false;
        return str_has($this->creation[$field], "AUTOINCREMENT");
    }

    /**
     * check if is row data array
     * @param Mixed $row    anything
     * @return Bool
     */
    public function isRowData($row)
    {
        if (!is_array($row) || empty($row) || !is_associate($row)) return false;
        return empty(array_diff(array_keys($row), $this->fields));
    }

    /**
     * check if is rows data array
     * @param Mixed $rows    anything
     * @return Bool
     */
    public function isRowsData($rows)
    {
        if (!is_indexed($rows)) return false;
        if (is_array($rows) && empty($rows)) return true;
        foreach ($rows as $i => $rowsi) {
            if (!$this->isRowData($rowsi)) return false;
        }
        return true;
    }

    /**
     * get id field
     * @param Bool $checkConfig     if check config setted idfield
     * @return String field name
     */
    public function id($checkConfig = true)
    {
        if (empty($this->idfield) || !$checkConfig) {
            $ct = $this->creation;
            foreach ($ct as $f => $c) {
                if (str_has(strtoupper($c), "AUTOINCREMENT")) return $f;
            }
            return null;
        }
        return $this->idfield;
    }

    /**
     * get regular export field, title / label / name / ...
     * @return String field name
     */
    public function exp() 
    {
        $expfs = ["name","title","label",$this->name];
        if (empty($this->exportfield)) {
            foreach ($expfs as $i => $key) {
                if ($this->hasField($key)) return $key;
            }
            return $this->id();
        }
        return $this->exportfield;
    }

    /**
     * get table row full classname
     * regularly call this method inside extended Model instance
     * @return String class name or key, like foo/bar/SomeRow
     */
    public function rowCls()
    {
        $selfCls = static::class;
        $cls = $selfCls."Row";
        if (class_exists($cls)) return $cls;
        $cls = $selfCls."Item";
        if (class_exists($cls)) return $cls;
        $carr = explode("\\", $selfCls);
        array_pop($carr);
        $carr[] = "Row";
        return implode("\\", $carr);
    }



    /**
     * load table entrence
     * @param String $path      like foo/bar/db/tbn
     * @param Bool $nocache     if table instance not cached, default false
     * @param Array $conf       custom table config
     * @return Table  or  null
     */
    public static function load($path, $nocache = false, $conf = [])
    {
        if (!is_notempty_str($path) || (strpos($path, "/")===false && strpos($path, "_")===false)) {
            trigger_error("db/errortbkey::".$path, E_USER_ERROR);
        }
        $parr = explode("/", trim(str_replace("_", "/", $path), "/"));
        $tbn = array_pop($parr);
        $dsn = implode("/", $parr);
        $db = Db::load($dsn);
        if ($nocache) return $db->unCachedTable($tbn, $conf);
        return $db->table($tbn, $conf);
    }
}






/**
 * Table Row
 */

class Row
{
    /**
     * belong to table instance
     */
    public $table = null;
    public $fields = [];
    public $idfield = null;

    /**
     * row data
     */
    protected $data = [];
    protected $dataCache = [];

    /**
     * new row sign
     */
    public $isNewRow = false;

    /**
     * construct
     */
    public function __construct($table, $option = null)
    {
        $this->table = $table;
        $this->fields = $table->fields;
        $this->idfield = $table->id();
        $this->initData();

        //set option
        if (empty($option)) {
            $this->isNewRow = true;
        } else if ($table->isRowData($option)) {
            $id = $this->idfield;
            $this->set($option, false);
            if (!isset($option[$id])) { //new row
                $this->isNewRow = true;
            } else {    //exists row
                $this->setId($option[$id], false);
            }
        } else if (isset($option["option"])) {

        }
    }



    /**
     * operate row data methods
     */

    /**
     * init row data with default field value
     * @return $this
     */
    protected function initData()
    {
        $tb = $this->table;
        $fds = $tb->fields;
        $dft = $tb->default;
        for ($i=0;$i<count($fds);$i++) {
            $fd = $fds[$i];
            $this->data[$fd] = isset($dft[$fd]) ? $dft[$fd] : null;
        }
        return $this;
    }

    /**
     * cache data when data been changed
     * @param Bool $cache
     * @return $this | cache array
     */
    protected function cacheData($cache = true)
    {
        if ($cache) {
            $this->dataCache[] = $this->data;
        }
        return $this;
    }

    /**
     * set row data
     * @param Array $data   row data array
     * @param Bool $cache   if cache this change
     * @return $this
     */
    public function set($data = [], $cache = true)
    {
        $this->cacheData($cache);

        //unset id field, cannot set idfield value
        $id = $this->idfield;
        if (isset($data[$id])) unset($data[$id]);

        $data = array_merge($this->data, $data);
        $this->data = $this->fix($data);
        return $this;
    }

    /**
     * set id data
     * @param String | Number $id   idfield value
     * @param Bool $cache   if cache this change
     * @return $this
     */
    public function setId($id = null, $cache = true)
    {
        if (is_null($id)) return $this;
        $this->cacheData($cache);
        $this->data[$this->idfield] = $id;
        return $this;
    }

    /**
     * fix row data, delete field not in table->fields
     * @param Array $data       data need to be fixed
     * @return Array fixed data
     */
    public function fix($data = [])
    {
        $fds = $this->fields;
        return array_filter($data, function($key) use ($fds) {
            return in_array($key, $fds);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * get idfield value
     * @return Mixed idfield value
     */
    public function getId()
    {
        return $this->data[$this->idfield];;
    }

    /**
     * unset id field from row data when row->save()
     * @param String $extra     other field need to be unsetted   
     * @return Array fixed data
     */
    public function unsetId(...$extra)
    {
        $data = arr_copy($this->data);
        array_unshift($extra, $this->idfield);
        $tb = $this->table;
        return array_filter($data, function($key) use ($extra, $tb) {
            return !in_array($key, $extra) && !$tb->isAutoincrement($key);
        }, ARRAY_FILTER_USE_KEY);
    }



    /**
     * curd methods
     */

    /**
     * save data to db
     * @param Closure $before       call this before save
     * @param Closure $after        call this after save
     * @return $this
     */
    public function save($before = null, $after = null)
    {
        $inp = $this->parseInput($this->unsetId());

        if ($this->isNewItem) {     //insert，一次新建操作后，isNewItem 自动转为 false
            if (is_null($before) || $before===true) {
                $this->beforeInsert();
            } else if ($before instanceof \Closure) {
                $before($this);
            }
            $curdRst = $this->table->curd()->insert($inp);
            $this->setId($this->table->lastInsertId());
            $this->isNewItem = false;
            //$this->sync();
            if (is_null($after) || $after===true) {
                $this->afterInsert($curdRst);
            } else if ($after instanceof \Closure) {
                $after($this, $curdRst);
            }
        } else {
            if (is_null($before) || $before===true) {
                $this->beforeUpdate();
            } else if ($before instanceof \Closure) {
                $before($this);
            }
            $curdRst = $this->table->curd()->whereId($this->getId())->update($inp);
            //$this->sync();
            if (is_null($after) || $after===true) {
                $this->afterUpdate($curdRst);
            } else if ($after instanceof \Closure) {
                $after($this, $curdRst);
            }
        }
        return $this;
    }

    /**
     * auto process methods
     * must override by sub class
     */
    //在 input 前处理 data
    protected function parseInput($data = []) { return $data; }
    //在 insert 前处理 input 数据，子类覆盖
    protected function beforeInsert() { return $this; }
    //在 insert 后的后续操作，子类覆盖
    protected function afterInsert($rst = null) { return $this; }
    //在 Update 前处理 input 数据，子类覆盖
    protected function beforeUpdate() { return $this; }
    //在 Update 后的后续操作，子类覆盖
    protected function afterUpdate($rst = null) { return $this; }
    //在 Delete 前的自定义操作，子类覆盖
    protected function beforeDelete() { return $this; }
    //在 Delete 后的后续操作，子类覆盖
    protected function afterDelete($rst = null) { return $this; }



    /**
     * magic method __get
     * get row data
     */
    public function __get($key)
    {
        if (in_array($key, $this->fields)) {
            return $this->data[$key];
        } else {
            switch ($key) {
                case "all" :    return $this->data; break;
                case "cache" :  return $this->dataCache; break;
                default:        return null; break;
            }
        }
    }

    /**
     * magic method __call
     *  1,  set field value
     */
    public function __call($key, $args)
    {
        if (in_array($key, $this->fields)) {
            if (!empty($args)) {
                $d = [];
                $d[$key] = array_shift($args);
                array_unshift($args, $d);
                return $this->set(...$args);
            }

        }
        return $this;
    }


}






/**
 * Table Rows
 */

class Rows
{

}