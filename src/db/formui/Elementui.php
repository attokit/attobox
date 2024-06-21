<?php

/**
 * attobox framework db form ui
 * 前端表单 ui 库参数生成
 * 
 * ui 库 = element-ui
 */

namespace Atto\Box\db\formui;

use Atto\Box\db\Formui;

class Elementui extends Formui
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
        $isFile = $cfger->getConf("$fdn/isFile");
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
                $ipt["type"] = "el-date-picker";
                $ipt["params"] = [
                    "type" => $ti["type"],
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
            } else if ($isFile) {
                $ipt["type"] = "atto-file-inputer";
                $fil = $cfger->getConf("$fdn/file");
                $ipt["params"] = $fil;
                $rst["formType"] = $fil["multiple"]===false ? "string" : "array";
            } else if ($isSelector) {
                $sel = $cfger->getConf("$fdn/selector");
                $ipt["type"] = (isset($sel["cascader"]) && $sel["cascader"]==true) ? "el-cascader" : "el-select";
                $ipt["options"] = isset($sel["values"]) ? $sel["values"] : [];
                if ($ipt["type"]=="el-select") {
                    $ipt["params"] = [
                        "filterable" => true
                    ];
                    if (isset($sel["dynamic"]) && $sel["dynamic"]==true) {
                        //$ipt["params"] = arr_extend($ipt["params"], [
                        //    "remote" => true,
                        //    "remote-method" => $fdn
                        //]);
                    }
                    $ks = explode(",","filterable,multiple,clearable,multiple-limit,allow-create");
                    for ($i=0;$i<count($ks);$i++) {
                        if (isset($sel[$ks[$i]])) {
                            $ipt["params"][$ks[$i]] = $sel[$ks[$i]];
                        }
                    }
                } else if ($ipt["type"]=="el-cascader") {
                    $ipt["params"] = [
                        "props" => [
                            "expandTrigger" => "hover",   //子节点展开方式，默认 click 点击展开下级菜单
                            "checkStrictly" => true,    //所有节点都可选，并不是只能选择叶子节点
                            "emitPath" => false         //是否选中节点的整个路径，false 仅选中当前节点值
                        ],
                        //"filterable" => true,
                        "clearable" => true
                    ];
                    if (isset($sel["dynamic"]) && $sel["dynamic"]==true) {
                        //$ipt["params"]["props"] = arr_extend($ipt["params"]["props"], [
                        //    "lazy" => true,
                        //    "lazyLoad" => $fdn
                        //]);
                    }
                    $ks = explode(",","multiple,checkStrictly,emitPath,value,label,children,disabled,leaf");
                    for ($i=0;$i<count($ks);$i++) {
                        if (isset($sel[$ks[$i]])) {
                            $ipt["params"]["props"][$ks[$i]] = $sel[$ks[$i]];
                        }
                    }
                }
                if (
                    (isset($ipt["params"]["multiple"]) && $ipt["params"]["multiple"]==true) ||
                    (isset($ipt["params"]["props"]["multiple"]) && $ipt["params"]["props"]["multiple"]==true)
                ) {
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
                $ipt["type"] = "el-switch";
                $ipt["params"] = [
                    "active-value" => 1,
                    "inactive-value" => 0
                ];
                $rst["formType"] = "integer";
            } else if ($isNumber || in_array($ftp, ["integer","float"])) {
                $ipt["type"] = "el-input-number";
                $ipt["params"] = [];
                if ($ftp=="float") {
                    //$ipt["params"]["precision"] = 2;
                    $ipt["params"]["precision"] = $cfger->precision;
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
                $jsi = $cfger->getConf("$fdn/json");
                $ipt["type"] = "atto-jsoner";
                $ipt["params"] = $jsi["params"] ?? [];
                $rst["formType"] = "object";
            } else {
                $ipt["type"] = "el-input";
                $ipt["params"] = [
                    "clearable" => true
                ];
                $rst["formType"] = $ftp=="varchar" ? "string" : $ftp;
            }
        //}
        $rst["inputer"] = $ipt;
        if (!$sif) {
            $rst["inputer"]["params"]["disabled"] = true;
            $rst["inputer"]["params"]["readonly"] = true;
        }

        return $rst;
    }
}

/*return $this->eachField("inputer", function($fdn, $conf) {
    $rst = [];
    $ipt = [];
    $sif = $this->getConf("$fdn/showInForm");
    $ftp = $this->getConf("$fdn/type");
    $isNumber = $this->getConf("$fdn/isNumber");
    $isJson = $this->getConf("$fdn/isJson");
    $isSelector = $this->getConf("$fdn/isSelector");
    $isSwitch = $this->getConf("$fdn/isSwitch");
    $isTime = $this->getConf("$fdn/isTime");
    */
    /*if (!$sif) {
        $ipt["type"] = "el-input";
        $ipt["params"] = [
            "readonly" => true,
            "disabled" => true
        ];
        $rst["formType"] = "string";
    } else {*/
/*        if ($isTime) {
            $ti = $this->getConf("$fdn/time");
            $ipt["type"] = "el-date-picker";
            $ipt["params"] = [
                "type" => $ti["type"],
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
            $sel = $this->getConf("$fdn/selector");
            $ipt["type"] = (isset($sel["cascader"]) && $sel["cascader"]==true) ? "el-cascader" : "el-select";
            $ipt["options"] = isset($sel["values"]) ? $sel["values"] : [];
            if ($ipt["type"]=="el-select") {
                $ipt["params"] = [
                    "filterable" => true
                ];
                if (isset($sel["dynamic"]) && $sel["dynamic"]==true) {
                    //$ipt["params"] = arr_extend($ipt["params"], [
                    //    "remote" => true,
                    //    "remote-method" => $fdn
                    //]);
                }
                $ks = explode(",","filterable,multiple,clearable,multiple-limit,allow-create");
                for ($i=0;$i<count($ks);$i++) {
                    if (isset($sel[$ks[$i]])) {
                        $ipt["params"][$ks[$i]] = $sel[$ks[$i]];
                    }
                }
            } else if ($ipt["type"]=="el-cascader") {
                $ipt["params"] = [
                    "props" => [
                        "expandTrigger" => "hover",   //子节点展开方式，默认 click 点击展开下级菜单
                        "checkStrictly" => true,    //所有节点都可选，并不是只能选择叶子节点
                        "emitPath" => false         //是否选中节点的整个路径，false 仅选中当前节点值
                    ],
                    //"filterable" => true,
                    "clearable" => true
                ];
                if (isset($sel["dynamic"]) && $sel["dynamic"]==true) {
                    //$ipt["params"]["props"] = arr_extend($ipt["params"]["props"], [
                    //    "lazy" => true,
                    //    "lazyLoad" => $fdn
                    //]);
                }
                $ks = explode(",","multiple,checkStrictly,emitPath,value,label,children,disabled,leaf");
                for ($i=0;$i<count($ks);$i++) {
                    if (isset($sel[$ks[$i]])) {
                        $ipt["params"]["props"][$ks[$i]] = $sel[$ks[$i]];
                    }
                }
            }
            if (
                (isset($ipt["params"]["multiple"]) && $ipt["params"]["multiple"]==true) ||
                (isset($ipt["params"]["props"]["multiple"]) && $ipt["params"]["props"]["multiple"]==true)
            ) {
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
            $ipt["type"] = "el-switch";
            $ipt["params"] = [
                "active-value" => 1,
                "inactive-value" => 0
            ];
            $rst["formType"] = "integer";
        } else if ($isNumber || in_array($ftp, ["integer","float"])) {
            $ipt["type"] = "el-input-number";
            $ipt["params"] = [];
            if ($ftp=="float") {
                //$ipt["params"]["precision"] = 2;
                $ipt["params"]["precision"] = $this->precision;
            }
            if ($isNumber) {
                $num = $this->getConf("$fdn/number");
                if (isset($num["default"])) {
                    $rst["default"] = $num["default"];
                    unset($num["default"]);
                }
                $ipt["params"] = arr_extend($ipt["params"], $num);
            }
            $rst["formType"] = $ftp;
        } else if ($isJson) {
            $ipt["type"] = "atto-jsoner";
            $ipt["params"] = [];
            $rst["formType"] = "object";
        } else {
            $ipt["type"] = "el-input";
            $ipt["params"] = [
                "clearable" => true
            ];
            $rst["formType"] = $ftp=="varchar" ? "string" : $ftp;
        }
    //}
    $rst["inputer"] = $ipt;
    if (!$sif) {
        $rst["inputer"]["params"]["disabled"] = true;
        $rst["inputer"]["params"]["readonly"] = true;
    }

    return $rst;
});
*/