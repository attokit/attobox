<?php

/**
 * Attobox Framework / field data convertor     字段数据格式转换器
 * 
 * $conv = new Convertor($table);
 * $data = $conv->data($data)->convToDb()
 *                           ->convToTable()
 */

namespace Atto\Box\db;

use Atto\Box\Db;
use Atto\Box\Record;

class Convertor 
{
    //关联的 table 实例
    public $table = null;
    public $fields = [];
    public $field = [];

    //字段数据
    public $raw = [];       //原始数据
    public $context = [];   //转换后数据

    //字段格式
    /*public $format = [
        //在数据库中保存的格式，integer / varchar / float / ...
        "db" => [],
        //在 Record 实例中操作的格式，php 数据类型：String / Array / Boolean / Integer / Float / ...
        "ctx" => [],
        //输出到前端 form 的格式
        "form" => [],
        //输出到前端 table 的格式
        "table" => [],
        //输出到前端 show 展示的格式
        "show" => []
    ];*/

    /**
     * construct
     * @param Table $table
     * @return void
     */
    public function __construct($table)
    {
        $this->table = $table;
        $this->fields = $this->table->conf("fields");
        $this->field = $this->table->conf("field");
        //处理数据表默认值，将其转换为 todb 的类型
        $this->convDefault();
    }

    /**
     * 计算在各种场景下，各字段需要转换为何种格式数据
     * @param String $field
     * @param String $scene 要使用字段数据的场景
     * @return String 数据类型名
     */
    protected function parseFormatForScene($field, $scene="db")
    {
        $fdi = $this->field[$field];
        switch ($scene) {
            case "db":
                return $fdi["type"];
                break;
            case "form":
                return $fdi["formType"];
                break;
            case "ctx":
                if ($fdi["isSwitch"]) return "bool";
                if ($fdi["isJson"] && isset($fdi["json"])) {
                    return isset($fdi["json"]["type"]) ? $fdi["json"]["type"] : "array";
                }
                //if ($fdi["isTime"] && isset($fdi["time"])) return $fdi["time"]["type"];
                return $fdi["type"]=="varchar" ? "string" : $fdi["type"];
                break;
            case "table":
                return $fdi["type"]=="varchar" ? "string" : $fdi["type"];
                break;
            case "show":
                //return "show".$this->parseFormatForScene($field, "ctx");
                return $fdi["type"]=="varchar" ? "string" : $fdi["type"];
                break;
            default:
                return $fdi["type"]=="varchar" ? "string" : $fdi["type"];
                break;
        }
    }

    /**
     * 转换数据表默认值 为 todb 类型，varchar，integer，float
     * @return $this;
     */
    public function convDefault()
    {
        $dft = [];
        for ($i=0;$i<count($this->fields);$i++) {
            $fdn = $this->fields[$i];
            $fdi = $this->field[$fdn];
            $dft[$fdn] = $fdi["default"];
        }
        $this->table->config["default"] = $this->data($dft)->convTo("db")->export();
        return $this;
    }

    /**
     * 要转换的数据
     * @param Array $data   raw data
     * @return $this
     */
    public function data($data = [])
    {
        if ($this->table->isRecord($data)) {
            if ($data instanceof Record) {
                $data = $data->context;
            }
            $this->raw = $data;
            $this->context = [];
        }
        return $this;
    }

    /**
     * export 转换后数据
     * @return Array $this->context
     */
    public function export() 
    {
        return $this->context;
    }

    /**
     * 入口方法
     * $table->converter->data([...some data])->convTo(scene)->export()
     * $record->export(scene)
     * @param String $scene
     * @return $this
     */
    public function convTo($scene="db")
    {
        $raw = $this->raw;
        foreach ($raw as $field => $data) {
            if (!in_array($field, $this->table->conf("fields"))) {
                $this->context[$field] = $data;
                continue;
            }
            $toformat = $this->parseFormatForScene($field, $scene);
            $m = "convTo".ucfirst(strtolower($toformat));
            if (method_exists($this, $m)) {
                $this->context[$field] = $this->$m($data, $field);
                continue;
            }
            $this->context[$field] = $data;
        }
        return $this;
    }

    /**
     * 循环 fields 执行 callback 转换 field data
     * 将转换结果写入 context
     * @param Callable $callback
     * @return $this
     */
    /*protected function eachField($callback)
    {
        $fields = $this->table->conf("fields");
        $raw = $this->raw;
        for ($i=0;$i<count($fields);$i++) {
            $fdn = $fields[$i];
            if (!is_set($raw[$fdn])) continue;
            if (is_callable($callback)) {
                $rsti = $callback($fdn, $raw[$fdn]);
            } else if (method_exists($this, $callback)) {
                $rsti = call_user_func_array([$this, $callback], [$fdn, $raw[$fdn]]);
            } else {
                $rsti = $raw[$fdn];
            }
            $this->context[$fdn] = $rsti;
        }
        return $this;
    }*/



    /**
     * 转换方法
     */

    //to varchar
    protected function convToVarchar($data, $field)
    {
        if (is_array($data)) {
            if (!empty($data)) return a2j($data);
            $fdi = $this->field[$field];
            if ($fdi["isJson"]) {
                $jtp = $fdi["json"]["type"];
                return $jtp=="indexed" ? "[]" : "{}";
            }
        }
        if (is_bool($data)) return $data==true ? "1" : "0";
        if (iss($data, "int,float")) return $data."";
        if (is_string($data)) return $data;
        if (is_null($data) || empty($data)) return "";
        return "";
    }

    //to string
    protected function convToString($data, $field)
    {
        return $this->convToVarchar($data, $field);
    }

    //to integer
    protected function convToInteger($data, $field)
    {
        if (iss($data, "int,float,numeric")) {
            $data = $data*1;
            return (int)$data;
        }
        if (is_bool($data)) return $data==true ? 1 : 0;
        if (is_null($data) || empty($data)) return 0;
        return 0;
    }

    //to float
    protected function convToFloat($data, $field)
    {
        if (iss($data, "int,float,numeric")) {
            $data = $data*1;
            return (float)$data;
        }
        if (is_bool($data)) return $data==true ? 1 : 0;
        if (is_null($data) || empty($data)) return 0;
        return 0;
    }

    //to array
    protected function convToArray($data, $field)
    {
        if (iss($data, "string,json", "&&")) return j2a($data);
        if (is_array($data)) return $data;
        if (iss($data, "bool,null,int,float") || empty($data)) return [];
        return $data;
    }

    //to indexed array
    protected function convToIndexed($data, $field)
    {
        if (iss($data, "string,json", "&&")) return j2a($data);
        if (is_string($data) && $data=="") return j2a("[]");
        if (is_indexed($data) && !empty($data)) return $data;
        if (iss($data, "bool,null,int,float") || empty($data)) return j2a("[]");
        return j2a("[]");
    }

    //to associate array
    protected function convToAssociate($data, $field)
    {
        if (iss($data, "string,json", "&&")) return j2a($data);
        if (is_string($data) && $data=="") return j2a("{}");
        if (is_associate($data) && !empty($data)) return $data;
        if (iss($data, "bool,null,int,float") || empty($data)) return j2a("{}");
        return j2a("{}");
    }

    //to bool
    protected function convToBool($data, $field)
    {
        if (iss($data, "string,numeric", "&&")) {
            $data = $this->convToInteger($data, $field);
            return $data==1;
        }
        if (iss($data, "int,float")) return $data==1;
        if (is_bool($data)) return $data;
        return false;
    }




    /**
     * tools
     */

    





    /**
     * 转换为入库数据格式
     * @param String $field
     * @param Mixed $data
     * @return Mixed conved data  or  $this
     */
    public function convToDb($field = null, $data = null)
    {
        if (is_notempty_str($field)) {
            if ($this->table->hasField($field)) {
                //开始转换
                $conf = $this->table->conf($field);
                $type = $conf["type"];
                switch ($type) {
                    case "varchar" :
                        if (empty($data)) return null;
                        if ($conf["isJson"]==true) {
                            return $this->toJson($data);
                        }
                        return $data;
                        break;
                    case "integer" :
                    case "float" :
                        if (is_bool($data)) return $data ? 1 : 0;
                        if ($conf["isTime"]==true) {
                            if (empty($data)) return null;
                            if (is_numeric($data)) return $data*1;
                            if (is_string($data)) return strtotime($data);
                            return null;
                        }
                        if (is_numeric($data)) return $data*1;
                        return $data;
                        break;
                }
                return $data;
            }
            return null;
        } else {
            //return $this->eachField(function($fdn, $d) {
            //    return $this->convToDb($fdn, $d);
            //});
            return $this->eachField("convToDb");
        }
    }

    /**
     * 转换为前端数据表显示的数据格式
     * @return $this
     */
    public function convToTable()
    {

        return $this;
    }

    /**
     * 转换为前端 form 使用的数据格式
     * @return $this
     */
    public function convToForm()
    {

        return $this;
    }



    /**
     * 转换方法
     */

    protected function toJson($data)
    {
        if (is_array($data) || is_bool($data) || is_null($data)) {
            return a2j($data);
        }
        if (is_notempty_str($data) && is_json($data)) return $data;
        return "{}";
    }
}