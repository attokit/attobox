<?php

/**
 * Attobox Framework / Table Form field Inputer
 * handle frontend input creation
 * base class
 */

namespace Atto\Box\db\form;

use Atto\Box\Db;
use Atto\Box\db\Table;

class Inputer
{
    /**
     * legal input type
     * must override by sub class (different UI-framework)
     */
    public static $types = [
        
    ];

    /**
     * belong to
     */
    public $field = null;     //field instance

    /**
     * input type
     */
    public $type = "";

    /**
     * input option for exporting to frontend
     */
    public $option = [];

    /**
     * construct
     */
    public function __construct($field)
    {
        $this->field = $field;

        //init
        $this->init();
    }

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
     * set $this->option[opt]
     * @param Array $opt    options need to set
     * @return $this
     */
    public function setOption($opt = [])
    {
        if (is_notempty_arr($opt) && is_associate($opt)) {
            $this->option = arr_extend($this->option, $opt);
        }
        return $this;
    }

    /**
     * set $this->[property]
     * @param Array $prop    property need to set
     * @return $this
     */
    public function setProperty($prop = [])
    {
        if (is_notempty_arr($opt) && is_associate($opt)) {
            foreach ($prop as $k => $v) {
                $this->$k = $v;
            }
        }
        return $this;
    }

    /**
     * export input option
     * @return Array option
     */
    public function exportOption()
    {
        $opt = get_object_vars($this);
        $props = ["table", "field", "types"];
        foreach ($props as $i => $prop) {
            if (isset($opt[$prop])) {
                unset($opt[$prop]);
            }
        }
        return $opt;
    }



    /**
     * call method based on field->inptype
     * @param String $methodType    method type
     * @param Mixed $args           extra method args
     * @return Mixed  or  $this
     */
    protected function callProcessMethod($methodType = "init", ...$args)
    {
        $inptype = $this->conf("inptype");
        $inptype = !is_notempty_str($inptype) ? "default" : strtolower($inptype);
        $ipt = str_replace(["-", "_", "/"], "|", $inptype);
        if (strpos($ipt,"|")!==false) {
            $ipt = implode("", array_map(function($i) {return ucfirst(strtolower($i));}, explode("|", $ipt)));
        } else {
            $ipt = ucfirst($ipt);
        }
        $m = strtolower($methodType).$ipt;
        if (!method_exists($this, $m)) $m = strtolower($methodType)."Unsupport";

        return $this->$m(...$args);
    }



    /**
     * init methods ( inputer->initSelect(), ... )
     * defined in sub class (different UI-framework)
     */

    /**
     * input option initialization
     * @return $this
     */
    protected function init()
    {
        $inptype = $this->conf("inptype");
        if (!is_notempty_str($inptype)) {
            $this->type = "NOSTR";
            $this->setOption([
                "inptype" => $inptype
            ]);
        } else {
            $this->callProcessMethod("init");
        }
        //extra options
        if (!$this->conf("editable")) $this->setOption(["disabled" => true]);
        $manualOpt = $this->conf("inpoption");
        if (!empty($manualOpt)) {
            $this->setOption($manualOpt);
        }

        return $this;
    }

    /**
     * default inptype init method
     * must override by sub class
     */
    public function initDefault()
    {
        //override
        //...

        return $this;
    }

    /**
     * unsupport inptype init method
     * must override by sub class
     */
    public function initUnsupport()
    {
        //override
        //...

        return $this;
    }



    /**
     * conversion methods
     */

    /**
     * convert field data before save to db
     * @param Mixed $data   field data
     * @return Mixed converted data
     */
    public function convBeforeSave($data)
    {
        $totype = $this->conf("type");
    }




}