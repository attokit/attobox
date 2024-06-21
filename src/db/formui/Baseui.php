<?php

/**
 * attobox framework db form ui
 * 前端表单 ui 库参数生成
 * 
 * ui 库 = base
 */

namespace Atto\Box\db\formui;

use Atto\Box\db\Formui;

class Baseui extends Formui
{
    /**
     * 生成 config->table->field->[fieldname]->inputer 参数
     */
    public function createInputer($fdn, $conf=[])
    {
        $cfger = $this->dbConfiger;
        $rst = [];
        $ipt = [];
        $sif = $cfger->getConf("$fdn/showInForm");
        $ftp = $cfger->getConf("$fdn/type");
        $isNumber = $cfger->getConf("$fdn/isNumber");
        $isJson = $cfger->getConf("$fdn/isJson");
        $isSelector = $cfger->getConf("$fdn/isSelector");
        $isSwitch = $cfger->getConf("$fdn/isSwitch");
        $isTime = $cfger->getConf("$fdn/isTime");
        /*if (!$sif) {
            $ipt["type"] = "el-input";
            $ipt["params"] = [
                "readonly" => true,
                "disabled" => true
            ];
            $rst["formType"] = "string";
        } else {*/
            if ($isTime) {
                $ti = $cfger->getConf("$fdn/time");
                $ipt["type"] = "cv-input-date";
                $ipt["params"] = [
                    "inpType" => str_replace("range","-range",$ti["type"]),
                    //"value-format" => "timestamp",
                    //"format" => strpos($ti["type"],"time")!==false ? "yyyy-MM-dd hh:mm:ss" : "yyyy-MM-dd"
                ];
                unset($ti["type"]);
                if (isset($ti["isRange"])) {
                    $isRange = $ti["isRange"]==true;
                    unset($ti["isRange"]);
                } else {
                    $isRange = false;
                }
                if (isset($ti["default"])) unset($ti["default"]);
                $ipt["params"] = arr_extend($ipt["params"], $ti);
                if ($isRange) {
                    $rst["formType"] = "array";
                } else {
                    $rst["formType"] = "integer";
                }
            } else if ($isSelector) {
                $sel = $cfger->getConf("$fdn/selector");
                $ipt["type"] = "cv-input";
                $ipt["params"] = [];
                if ((isset($sel["cascader"]) && $sel["cascader"]==true)) {
                    $ipt["params"] = [
                        "inpType" => "cascader",
                        "filterable" => true
                    ];
                } else {
                    $ipt["params"] = [
                        "inpType" => "select",
                        "filterable" => true
                    ];
                }
                if (isset($sel["useApi"]) && $sel["useApi"]===true) {
                    $ipt["params"]["optionsApi"] = $sel["source"]["api"];
                } else if (isset($sel["useTable"]) && $sel["useTable"]===true) {
                    $ipt["params"]["optionsApi"] = "retrieve-table";
                    $ipt["params"]["optionsApiParams"] = $sel["source"];
                } else {
                    $ipt["options"] = isset($sel["values"]) ? $sel["values"] : [];
                }
                $ks = explode(",","filterable,multiple,clearable,allow-create");
                for ($i=0;$i<count($ks);$i++) {
                    if (isset($sel[$ks[$i]])) {
                        $ipt["params"][$ks[$i]] = $sel[$ks[$i]];
                    }
                }
                if (isset($ipt["params"]["multiple"]) && $ipt["params"]["multiple"]==true) {
                    $rst["formType"] = "array";
                    if (!$isJson) {
                        $rst["isJson"] = true;
                        $rst["json"] = [
                            "type" => "indexed",
                            "default" => j2a("[]")
                        ];
                    }
                } else {
                    $rst["formType"] = $ftp=="varchar" ? "string" : $ftp;
                }
            } else if ($isSwitch) {
                $ipt["type"] = "cv-switch";
                $ipt["params"] = [
                    "active-value" => 1,
                    "inactive-value" => 0
                ];
                $rst["formType"] = "integer";
            } else if ($isNumber || in_array($ftp, ["integer","float"])) {
                $ipt["type"] = "cv-input";
                $ipt["params"] = [
                    "inpType" => "number"
                ];
                if ($ftp=="float") {
                    ////$ipt["params"]["precision"] = 2;
                    //$ipt["params"]["precision"] = $cfger->precision;
                    $prec = $cfger->precision;
                    $ipt["params"]["step"] = pow(10, $prec*-1);
                } else if ($ftp=="integer") {
                    $ipt["params"]["step"] = 1;
                }
                if ($isNumber) {
                    $num = $cfger->getConf("$fdn/number");
                    if (isset($num["default"])) {
                        $rst["default"] = $num["default"];
                        unset($num["default"]);
                    }
                    $ipt["params"] = arr_extend($ipt["params"], $num);
                }
                $rst["formType"] = $ftp;
            } else if ($isJson) {
                $ipt["type"] = "cv-input-jsoner";
                $ipt["params"] = [];
                $rst["formType"] = "object";
            } else {
                $ipt["type"] = "cv-input";
                $ipt["params"] = [
                    "clearable" => true
                ];
                $rst["formType"] = $ftp=="varchar" ? "string" : $ftp;
            }
        //}
        $rst["inputer"] = $ipt;
        if (!$sif) {
            //$rst["inputer"]["params"]["disabled"] = true;
            $rst["inputer"]["params"]["readonly"] = true;
        }

        return $rst;
    }
}