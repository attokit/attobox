<?php

/**
 * Attobox Framework / Table Form frontend UI handler
 * this handler working with Element-UI
 */

namespace Atto\Box\db\form\inputer;

use Atto\Box\db\form\Inputer;
use Atto\Box\Db;
use Atto\Box\db\Table;

class ElementuiInputer extends Inputer
{
    /**
     * legal input type
     * must override by sub class (different UI-framework)
     */
    public static $types = [
        "input", "input-number",
        "select", "cascader",
        "switch",
        "date-picker",
        "slider",
        "upload"
    ];



    /**
     * init methods ( inputer->initSelect(), ... )
     * defined in sub class (different UI-framework)
     */

    /**
     * default init method
     * must override by sub class
     * inptype: default  -->  type: input | input-number | select
     * @return $this
     */
    public function initDefault()
    {
        $type = $this->conf("type");
        
        if ($type=="integer" || $type=="float") {
            $this->type = "input-number";
            if ($type=="integer") {
                $this->setOption([
                    "step" => 1
                ]);
            } else {
                $this->setOption([
                    "precision" => 2,
                    "step" => 0.01
                ]);
            }
        } else if ($multi == true) {
            $this->type = "select";
            $this->setProperty([
                "isinput" => true
            ]);
            $this->setOption([
                "multiple" => true,
                "allowCreate" => true,
                "filterable" => true,
                "defaultFirstOption" => true    //回车即选中
            ]);
        } else {
            $this->type = "input";
        }

        return $this;
    }

    /**
     * unsupport inptype init method
     * must override by sub class
     * inptype: anything unsupport  -->  type: inptype
     * @return $this
     */
    public function initUnsupport()
    {
        $this->type = $this->conf("inptype");

        return $this;
    }

    /**
     * inptype: select  -->  type: select
     * @return $this
     */
    public function initSelect()
    {
        $mul = $this->conf("multival") == true;
        $add = $this->conf("multiadd") == true;
        $flt = $this->conf("multiflt") == true;
        $this->type = "select";
        //$vals = $info["values"]["values"];
        //$flt = $flt || $add || !empty($vals) && count($vals)>10;
        $opt = [];
        if ($mul) $opt["multiple"] = true;
        if ($add) $opt["allowCreate"] = true;
        if ($flt || $add) $opt["filterable"] = true;
        $opt["clearable"] = true;

        return $this->setOption($opt);
    }

    /**
     * inptype: cascader  -->  type: cascader
     * @return $this
     */
    public function initCascader()
    {
        $this->type = "cascader";
        $opt = [
            "props" => [
                "value" => "value", 
                "emitPath" => false, 
                "checkStrictly" => true, 
                "multiple" => $this->conf("multival") == true
            ],
        ];

        return $this->setOption($opt);
    }

    /**
     * inptype: switch  -->  type: switch
     * @return $this
     */
    public function initSwitch()
    {
        $this->type = "switch";
        $opt = [
            "activeValue" => "1",
            "inactiveValue" => "0"
        ];

        return $this->setOption($opt);
    }

    /**
     * inptype: textarea  -->  type: input
     * @return $this
     */
    public function initTextarea()
    {
        $this->type = "input";
        $opt = [
            "type" => "textarea",
            "autosize" => [
                "minRows" => 3,
                "maxRows" => 10
            ]
        ];

        return $this->setOption($opt);
    }

    /**
     * inptype: date or datetime  -->  type: date-picker
     * @return $this
     */
    public function initDate()
    {
        $this->type = "date-picker";
        $inptype = $this->conf("inptype");
        $opt = [
            "type" => $inptype,
            "format" => "yyyy-MM-dd".($inptype=="date"?"":" HH:mm:ss"),
            "valueFormat" => "timestamp"
        ];

        return $this->setOption($opt);
    }
    public function initDatetime()
    {
        return $this->initDate();
    }

    /**
     * inptype: slider  -->  type: slider
     * @return $this
     */
    public function initSlider()
    {
        $this->type = "slider";
        $opt = [
            "inputSize" => "medium"
        ];

        return $this->setOption($opt);
    }

    /**
     * inptype: imgs  -->  type: upload
     * @return $this
     */
    public function initImgs()
    {
        $this->type = "upload";
        $opt = [
            "multiple" => $multi,
            "action" => "/upload",
            "listType" => "text",
            "autoUpload" => true,
            "showFileList" => false,
        ];

        return $this->setProperty(["isImgUpload"=>true])->setOption($opt);
    }



}