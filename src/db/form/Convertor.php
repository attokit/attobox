<?php

/**
 * Attobox Framework / convert field data type
 */

namespace Atto\Box\db\form;

class Convertor
{
    /**
     * belong to field instance
     */
    public $field = null;

    /**
     * before or after these operation need conversion
     */
    public static $_OPRS = [
        "insert",   // c, before insert data to db
        "update",   // u, before update data to db
        "select",   // r, after select data from db

        "export",   // before export data to frontend
    ];



    /**
     * construct
     */
    public function __construct($field)
    {
        $this->field = $field;
    }



    /**
     * config methods
     */

    /**
     * call field->conf()
     * @param String $path      config path, foo/bar  -->  field->foo[bar]
     * @return Mixed config
     */
    public function conf($path = null)
    {
        return $this->field->conf($path);
    }

    /**
     * export convertor option
     * @return Array convertor option
     */
    public function exportOption()
    {
        $opt = get_object_vars($this);
        unset($opt["field"]);
        return $opt;
    }



    /**
     * enternce methods
     */

    /**
     * convert field data before $opr ( in_array(Convertor::$_OPRS) )
     * using magic method
     * @param String $opr       operation in_array Convertor::$_OPRS
     * @param Mixed $data       field data
     * @return Mixed converted data
     */
    public function __call($key, $args)
    {
        if (in_array($key, self::$_OPRS)) {
            return $this->callConvMethod($key, ...$args);
        }
        return null;
    }

    /**
     * call specific method
     * @param String $convType      convert type
     */
    protected function callConvMethod($convType, $data = null)
    {
        if (is_null($data)) return null;
        $type = $this->conf("type");
        $m = strtolower($convType).ucfirst(strtolower($type));
        //var_dump($m);
        if (!method_exists($this, $m)) $m = "undefineConvMethod";
        return $this->$m($data);
    }

    /**
     * call undefined conv method
     * @param Mixed $data   field data
     * @return Mixed converted data
     */
    protected function undefineConvMethod($data)
    {
        return $data;
    }



    /**
     * tools
     * can be override by sub class
     */
    protected function selectVarchar($data = null)
    {
        return is_null($data) ? null : (string)$data;
    }
    protected function selectInteger($data = null)
    {
        return is_null($data) ? null : (int)$data;
    }
    protected function selectFloat($data = null)
    {
        return is_null($data) ? null : (float)$data;
    }
    protected function exportVarchar($data = null)
    {
        if (is_null($data)) return null;
        $data = (string)$data;
        if ($this->conf("multival")==true) {
            $data = explode(",", $data);
        }

        return $data;
    }


    protected function insertVarchar($data = null)
    {
        return $data;
    }

    

    /**
     * conv data from DB, read data
     * @param Table $table      table instance
     * @param Array $rowdata    data need to conv
     * @return Array conved data
     */
    public static function fromDb($table, $rowdata = [])
    {
        $fds = $table->fields;
        $fdcs = $table->field;
        $convdata = [];
        for ($i=0;$i<count($fds);$i++ ) {
            $fd = $fds[$i];
            $fdc = $fdcs[$fd];
            if (!isset($rowdata[$fd]) || is_null($rowdata[$fd])) continue;
            $od = $rowdata[$fd];
            $m = "to".ucfirst(strtolower($fdc["type"]));
            $nd = self::$m($od);
            $fdc = $fdcs[$fd];
            $ftp = $fdc["type"];
            $finp = $fdc["inptype"];
        }
    }

    /**
     * conv data to DB, save data
     * @param Table $table      table instance
     * @param Array $rowdata    data need to conv
     * @return Array conved data
     */
    public static function toDb($table, $rowdata = [])
    {

    }



    /**
     * conv data from DB, anyting --> inptype
     */

    /**
     * conv from 
     */



    /**
     * conv to varchar,integer,float
     */

    /**
     * conv to varchar
     * @param Mixed $val    field data original
     * @return String field data
     */
    public static function toVarchar($val = null)
    {
        if (is_null($val)) return null;
        return (string)$val;
    }

    /**
     * conv to integer
     * @param Mixed $val    field data original
     * @return String field data
     */
    public static function toInteger($val = null)
    {
        if (is_null($val)) return null;
        return (int)$val;
    }

    /**
     * conv to float
     * @param Mixed $val    field data original
     * @return String field data
     */
    public static function toFloat($val = null)
    {
        if (is_null($val)) return null;
        return (float)$val;
    }



    /**
     * conv from inptypes
     *  types includes: []
     */
    public static function inptypes()
    {
        return [
            "select", "cascader",
            "switch",
            "date", "datetime",
            "keyvalue",
            "upload"
        ];
    }

}