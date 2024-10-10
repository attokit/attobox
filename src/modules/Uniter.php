<?php

/**
 * 处理 计量单位 与 重量之间的转换
 */

namespace Atto\Box;

use Atto\Box\Record;
use Atto\Box\RecordSet;

class Uniter
{
    /**
     * 物品的 record->export() 得到的 array
     */
    public $record = [];

    /**
     * 指定各计算参数在 record 中的 字段名
     */
    protected $unitField = "unit";
    protected $netwtField = "netwt";
    protected $maxunitField = "maxunit";
    protected $minnumField = "minnum";
    protected $extraField = "extra";
    //保存在 record->extra 中的 计量计算参数 字段名
    protected $ngPkgField = "标签包装规格";
    protected $ngMaxPkgField = "外箱标签包装规格";
    protected $ngMinWtField = "每*克重";

    //从 record 中解析得到的 计量计算参数
    protected $unit = "克";
    protected $netwt = 1;
    protected $maxunit = "无";
    protected $minnum = 1;
    protected $extra = [];
    //如果不以重量计数，还需要这些参数
    protected $ngpkg = "";      //如：9个/袋
    protected $ngmaxpkg = "";   //如：9个/袋，10袋/箱

    /**
     * 解析得到的更多计算参数
     */
    //是否散装，无任何包装
    protected $isBulk = true;
    //是否散装带外包装
    protected $isBulkWithMaxu = false;
    //是否无内包装，有或没有外包装都可以
    protected $noMinu = true;
    //是否无外包装，有或没有内包装都可以
    protected $noMaxu = true;
    //是否内外包装都有
    protected $allUnits = false;

    //是否不以重量计数，如：1只/袋，9个/袋
    protected $notGnum = false;

    //属于不以重量计数的 计量单位 
    //protected $notGnumUnits = ["个", "片", "只"/*, "块", "条"*/];
    
    /**
     * 如果不以重量计数，需要解析得到更多参数
     */
    //最小计量单位，如：个，只，片
    protected $ngMinUnit = "无";
    protected $ngMinWt = 0;     //最小计量单位的克重，如：80
    //中间包装单位，如：1只/盒 --> 盒
    protected $ngMidUnit = "无";
    protected $ngMinNum = 1;
    //外包装单位
    protected $ngMaxUnit = "无";
    protected $ngMidNum = 1;

    /**
     * construct
     * @param Mixed $record 物品 record 对象 或 record->export() 得到的 array
     * @param Array $opt 指定字段名，如果 record 中保存计量参数的字段名与默认不一致，在此处指定
     */
    public function __construct($record, $opt=[])
    {
        if (!empty($opt)) {
            foreach ($opt as $k => $v) {
                $this->$k = $v;
            }
        }
        if ($record instanceof Record) {
            $this->record = array_merge([], $record->context);
        } else if (is_array($record) && !empty($record)) {
            $this->record = $record;
        }
        
        //解析 record
        $this->parseRecord();
    }

    /**
     * 解析传入的 record 得到计算参数
     */
    protected function parseRecord()
    {
        $rs = $this->record;
        if (empty($rs)) return false;
        //常规 规格参数
        $this->unit = $rs[$this->unitField];
        $this->netwt = $rs[$this->netwtField]*1;
        $this->maxunit = $rs[$this->maxunitField];
        $this->minnum = $rs[$this->minnumField]*1;

        //解析 extra
        $this->parseExtra();

        //首先检查，是否不以重量计量，如：9个/袋
        $this->notGnum = $this->chkNotGnum();
        if ($this->notGnum) {
            //处理特殊内包装
            if ($this->ngpkg!="") {
                //在 extra 指定了 特殊内包装规格
                $nparr = explode("/", explode("，", $this->ngpkg)[0]);
                $this->ngMinUnit = trim(preg_replace("/[0-9\.]/","", $nparr[0]));
                $this->ngMinNum = trim(str_replace($this->ngMinUnit, "", $nparr[0]))*1;
                if (count($nparr)>1) $this->ngMidUnit = trim($nparr[1]);
            } else {
                //未指定特殊内包装规格
                $this->ngMinUnit = $this->unit;
                $this->ngMinNum = $this->netwt;
                //$this->ngMidUnit = "无";
            }
            //处理特殊外包装
            if ($this->ngmaxpkg!="") {
                //在 extra 指定了 特殊外包装规格
                $nparr = explode("，", $this->ngmaxpkg);
                $nparr = array_slice($nparr, 1);
                if (count($nparr)>0) {
                    $midnum = 1;
                    $maxunit = "";
                    for ($i=0;$i<count($nparr);$i++) {
                        $npi = $nparr[$i];
                        $npiarr = explode("/", $npi);
                        $numi = preg_replace("/[^0-9\.]/","", $npiarr[0]);
                        if (!is_numeric($numi)) continue;
                        $midnum = $midnum * $numi;
                        if (count($npiarr)>1) $maxunit = $npiarr[1];
                    }
                }
                $this->ngMidNum = $midnum;
                if ($maxunit!="") $this->ngMaxUnit = $maxunit;
            } else {
                //未指定特殊外包装规格
                $this->ngMidNum = $this->minnum;
                $this->ngMaxUnit = $this->maxunit;
            }
            //处理 最小单位 克重
            $ngnwfdn = str_replace("*", $this->ngMinUnit, $this->ngMinWtField);
            if (isset($this->extra[$ngnwfdn]) && is_numeric($this->extra[$ngnwfdn])) {
                //指定了最小单位 克重
                $this->ngMinWt = $this->extra[$ngnwfdn]*1;
                $this->netwt = $this->ngMinNum * $this->ngMinWt;
            } else {
                //未指定 最小单位 克重
                $this->ngMinWt = 0;
                $this->netwt = $this->ngMinNum;
            }
            $this->unit = $this->ngMidUnit=="无" ? $this->ngMinUnit : $this->ngMidUnit;
            $this->maxunit = $this->ngMaxUnit;
            $this->minnum = $this->ngMidNum;
        }

        //排错
        if ($this->netwt<=0) $this->netwt = 1;
        if ($this->minnum<=0) $this->minnum = 1;
        
        //通过解析得到的参数
        $this->chkAll();
        return true;
    }
    //处理 extra 数据
    protected function parseExtra()
    {
        $rs = $this->record;
        $exFdn = $this->extraField;
        $pkgFdn = $this->ngPkgField;
        $mpkgFdn = $this->ngMaxPkgField;
        
        $this->extra = $rs[$exFdn];
        if (is_json($this->extra)) $this->extra = j2a($this->extra);
        if (!is_associate($this->extra)) $this->extra = [];

        $extra = $this->extra;
        if (empty($extra)) return false;
        if (isset($extra[$pkgFdn]) && $extra[$pkgFdn]!="") $this->ngpkg = self::fixPkgString($extra[$pkgFdn]);
        if (isset($extra[$mpkgFdn]) && $extra[$mpkgFdn]!="") $this->ngmaxpkg = self::fixPkgString($extra[$mpkgFdn]);

        return true;
    }

    /**
     * 使用传入的 package 规格数据，替换现有的规格数据
     */
    public function setCustomPackage($pkg=[/* unit=>,netwt=>,maxunit=>,minnum=> */])
    {
        if (empty($pkg)) return false;
        foreach ($pkg as $k => $v) {
            if (is_numeric($v)) $v = $v*1;
            $this->$k = $v;
        }
        //批量判断
        $this->chkAll();
        return true;
    }

    /**
     * 判断函数
     */
    //批量判断，并写入实例
    public function chkAll()
    {
        //是否散装，无任何包装
        $this->isBulk = $this->chkIsBulk();
        //是否散装带外包装
        $this->isBulkWithMaxu = $this->chkIsBulkWithMaxu();
        //是否无内包装，有无外包装都可以
        $this->noMinu = $this->chkNoMinu();
        //是否无外包装，有无内包装都可以
        $this->noMaxu = $this->chkNoMaxu();
        //是否内外包装都有
        $this->allUnits = $this->chkAllUnits();
    }
    //是否不以重量计数，如：1只/袋，9个/袋
    public function chkNotGnum()
    {
        if (
            $this->ngpkg!="" && 
            strpos($this->ngpkg,"克")===false
        ) {
            return true;
        }
        //if (in_array($this->unit, $this->notGnumUnits)) return true;
        if ($this->unit!="克" && $this->netwt==1) return true;
        return false;
    }
    //是否散装/单个装，无外包装
    public function chkIsBulk()
    {
        return $this->chkNoMinu()==true && $this->chkNoMaxu()==true;
        //if ($this->notGnum) return $this->netwt==1 && $this->maxunit=="无";
        //return $this->unit=="克" && $this->netwt==1 && $this->maxunit=="无";
    }
    //是否散装/单个装，带外包装
    public function chkIsBulkWithMaxu()
    {
        return $this->chkNoMinu()==true && $this->chkNoMaxu()==false;
        //if ($this->notGnum) return $this->netwt==1 && $this->maxunit!="无";
        //return $this->unit=="克" && $this->netwt==1 && $this->maxunit!="无";
    }
    //是否无内包装/单个装，有或没有外包装都可以
    public function chkNoMinu()
    {
        if ($this->notGnum) return $this->netwt==1 || $this->ngMinNum==1;
        return $this->unit=="克" && $this->netwt==1;
    }
    //是否无外包装，有或没有内包装或单个装都可以
    public function chkNoMaxu()
    {
        return $this->maxunit=="无";
    }
    //是否内外包装都有
    public function chkAllUnits()
    {
        return $this->chkNoMinu()==false && $this->chkNoMaxu()==false;
        //if ($this->notGnum) return $this->netwt!=1 && $this->maxunit!="无";
        //return $this->unit!="克" && $this->netwt!=1 && $this->maxunit!="无";
    }



    /**
     * tools
     */
    //输出 uniter 规格信息
    public function export()
    {
        return [
            "unit" => $this->unit,
            "netwt" => $this->netwt,
            "maxunit" => $this->maxunit,
            "minnum" => $this->minnum,
            "extra" => $this->extra,

            "notGnum" => $this->notGnum,
            "isBulk" => $this->isBulk,
            "isBulkWithMaxu" => $this->isBulkWithMaxu,
            "noMinu" => $this->noMinu,
            "noMaxu" => $this->noMaxu,
            "allUnits" => $this->allUnits,

            "ngpkg" => $this->ngpkg,
            "ngmaxpkg" => $this->ngmaxpkg,

            "ngMinUnit" => $this->ngMinUnit,
            "ngMinWt" => $this->ngMinWt,
            "ngMinNum" => $this->ngMinNum,
            "ngMidUnit" => $this->ngMidUnit,
            "ngMidNum" => $this->ngMidNum,
            "ngMaxUnit" => $this->ngMaxUnit,

            "package" => $this->exportPkgString()
        ];
    }

    /**
     * 输出字符串
     */
    //输出 散装/袋装/个装/单个装
    public function exportPrePkgString()
    {
        if (!$this->notGnum) {
            //普通规格
            if ($this->noMinu) return "散装";
            return $this->unit."装";
        } else {
            //非重量计量规格
            $str = [];
            if ($this->noMinu) $str[] = "单";
            $str[] = $this->ngMinUnit;
            $str[] = "装";
            return implode("",$str);
        }
    }
    //输出 小包装 1Kg/袋
    public function exportMinUnitPkgString()
    {
        if ($this->noMinu) return "";
        if (!$this->notGnum) {
            return self::gToKgString($this->netwt)."/".$this->unit;
        } else {
            if ($this->ngpkg!="") return $this->ngpkg;
            if ($this->ngMidUnit=="无") return "";
            return $this->ngMinNum.$this->ngMinUnit."/".$this->ngMidUnit;
        }
    }
    //输出 外包装 10袋/箱
    public function exportMaxUnitPkgString()
    {
        if ($this->noMaxu) return "";
        if (!$this->notGnum) {
            $str = [];
            if ($this->noMinu) {
                $str[] = self::gToKgString($this->netwt*$this->minnum);
            } else {
                $str[] = $this->minnum.$this->unit;
            }
            $str[] = $this->maxunit;
            return implode("/", $str);
        } else {
            if ($this->ngmaxpkg!="") {
                $pkgarr = explode("，", $this->ngmaxpkg);
                if (count($pkgarr)>1) return $pkgarr[1];
            }
            return $this->minnum.$this->unit."/".$this->maxunit;
        }
    }
    //输出 package 字符串
    public function exportPkgString()
    {
        $str = [];
        $str[] = $this->exportPrePkgString();
        if (!$this->notGnum) {
            //正常按重量计量，但是在 extra 额外指定了 规格
            if ($this->ngmaxpkg!="") {
                $str[] = $this->ngmaxpkg;
            } else if ($this->ngpkg!="") {
                $str[] = $this->ngpkg;
            }
            if (count($str)>1) {
                return implode("，", $str);
            }
        }
        $minstr = $this->exportMinUnitPkgString();
        if ($minstr!="") $str[] = $minstr;
        $maxstr = $this->exportMaxUnitPkgString();
        if ($maxstr!="") $str[] = $maxstr;
        return implode("，", $str);
    }
    //小包装数 转为 大包装数+小包装数 字符串
    public function unumToMinMax($unum)
    {
        $rst = $this->calcUnumToUnits($unum);
        return $rst["str"];
    }

    /**
     * 计算
     */
    /**
     * 单位数量 计算得到 克重，如果 单个装 返回 *个 数字
     * @param Number $unum 最小计量单位数量，散装=克重，单个装=*个，袋装 或 *片/袋=*袋
     * @return Array 计算结果
     * [
     *      "g"     => 数字，克重，不按重量计数的返回 *(个)
     *      "kg"    => 数字，Kg数，不按重量计数的 与 g 相同
     *      "str"   => 字符串，*克，*Kg，*个，*Kg(*个)
     * ]
     */
    public function calcUnumToGnum($unum)
    {
        $rst = [
            "g" => 0,
            "kg" => 0,
            "str" => ""
        ];
        $unit = $this->unit;
        $nw = $this->netwt;
        $maxu = $this->maxunit;
        $minn = $this->minnum;
        $gnum = $unum*$nw;
        if (!$this->notGnum) {
            //正常的按重量计数
            $rst["g"] = $gnum;
            $rst["kg"] = round(($gnum*100)/1000)/100;   //两位小数
            $rst["str"] = self::gToKgString($gnum);
        } else {
            //不以重量计量
            $minwt = $this->ngMinWt;
            $unit = $this->ngMinUnit;
            if ($this->noMinu) {
                //单个装
                if ($minwt<=0) {
                    //未指定单个重量
                    $rst["g"] = $unum;
                    $rst["kg"] = $unum;
                    $rst["str"] = $unum.$unit;
                } else {
                    //指定了单个重量
                    $rst["g"] = $gnum;
                    $rst["kg"] = round(($gnum*100)/1000)/100;   //两位小数
                    $rst["str"] = self::gToKgString($gnum)."(".($unum*$this->ngMinNum).$unit.")";
                }
            } else {
                //多个装
                if ($minwt<=0) {
                    //未指定单个重量
                    $mnum = $unum*$this->ngMinNum;
                    $rst["g"] = $mnum;
                    $rst["kg"] = $mnum;
                    $rst["str"] = $mnum.$unit;
                } else {
                    //指定了单个重量
                    $rst["g"] = $gnum;
                    $rst["kg"] = round(($gnum*100)/1000)/100;   //两位小数
                    $rst["str"] = self::gToKgString($gnum)."(".$mnum.$unit.")";
                }
            }
        }
        return $rst;
    }
    /**
     * 单位数量 计算得到 不同单位数量
     * @param Number $unum 最小计量单位数量，散装=克重，单个装=*个，袋装 或 *片/袋=*袋
     * @return Array 计算结果
     * [
     *      "min"           => 无法凑整的最小计量单位数量，散装=克重，单个装=*个，袋装 或 *片/袋=*袋
     *      "max"           => 凑整后的外包装数量，无外包装=0
     *      "totalMin"      => 最小计量单位总数，=输入的 $unum
     *      "odd"           => 此处无意义，=0
     *      "str"           => 字符串，*箱+*袋 || *箱+*个 || *箱+*Kg
     *      "strTotalMin"   => 字符串，最小计量单位总数量，*克 || *Kg || *袋 || *个
     *      "strKg"         => 字符串，克重，*克 || *Kg
     *      "strFull"       => 字符串，完整单位数量，*箱+*袋(共*袋) || *箱+*Kg(共*Kg) || *箱+*个(共*个)
     *      "strFullKg"     => 字符串，完整的单位数量，显示克重，*箱+*袋(共*袋，*Kg) || *箱+*Kg(共*Kg) || *箱+*个(共*个，*Kg)
     * ]
     */
    public function calcUnumToUnits($unum)
    {
        $rst = [
            "min" => 0,
            "max" => 0,
            "totalMin" => $unum,
            "odd" => 0,
            "str" => "",
            "strTotalMin" => "",
            "strKg" => "",
            "strFull" => "",
            "strFullKg" => ""
        ];
        $unit = $this->unit;
        $nw = $this->netwt;
        $maxu = $this->maxunit;
        $minn = $this->minnum;
        $gnum = $unum*$nw;
        if (!$this->notGnum) {
            //正常的按重量计数
            if ($this->noMaxu) {
                $rst["min"] = $unum;
            } else {
                $rst["max"] = floor($unum/$minn);
                $rst["min"] = $unum%$minn;
            }
            $str = [];
            if ($rst["max"]>0) $str[] = $rst["max"].$maxu;
            if ($this->noMinu) {
                if ($rst["min"]>0) $str[] = self::gToKgString($rst["min"]);
                $rst["strTotalMin"] = self::gToKgString($rst["totalMin"]);
                $rst["strKg"] = $rst["strTotalMin"];
            } else {
                if ($rst["min"]>0) $str[] = $rst["min"].$unit;
                $rst["strTotalMin"] = $rst["totalMin"].$unit;
                $rst["strKg"] = self::gToKgString($gnum);
            }
            if (!empty($str)) {
                $rst["str"] = implode("+", $str);
            } else {
                $rst["str"] = "0".$unit;
            }
            if ($this->isBulk) {
                $rst["strFull"] = $rst["str"];
                $rst["strFullKg"] = $rst["str"];
            } else if ($this->isBulkWithMaxu || ($this->noMaxu && !$this->noMinu)) {
                $fkg = $rst["str"]."(共".$rst["strKg"].")";
                $rst["strFull"] = $fkg;
                $rst["strFullKg"] = $fkg;
            }/* else if ($this->noMaxu) {
                $rst["strFull"] = $rst["str"]."(共".$rst["strKg"].")";
                $rst["strFullKg"] = $rst["str"]."(共".$rst["strKg"].")";
            }*/ else {
                $rst["strFull"] = $rst["str"]."(共".$rst["strTotalMin"].")";
                $rst["strFullKg"] = $rst["str"]."(共".$rst["strTotalMin"]."，".$rst["strKg"].")";
            }
        } else {
            //不以重量计量
            if ($this->noMinu) {
                //单个装
                $unit = $this->ngMinUnit;
            } else {
                //多个装

            }
                if ($this->noMaxu) {
                    //无外包装
                    $rst["min"] = $unum;
                } else {
                    //有外包装
                    $rst["min"] = $unum%$minn;
                    $rst["max"] = floor($unum/$minn);
                }
                $str = [];
                if ($rst["max"]>0) $str[] = $rst["max"].$maxu;
                if ($rst["min"]>0) $str[] = $rst["min"].$unit;
                if (!empty($str)) {
                    $rst["str"] = implode("+", $str);
                } else {
                    $rst["str"] = "0".$unit;
                }
                $rst["strTotalMin"] = $rst["totalMin"].$unit;
                $rst["strFull"] = $rst["str"]."(共".$rst["strTotalMin"].")";
                $rst["strFullKg"] = $rst["str"]."(共".$rst["strTotalMin"];
                //if ($this->ngMinWt>0) {
                if (!$this->noMinu) {
                    //多个装，strKg 保存个数
                    $rst["strKg"] = ($unum*$this->ngMinNum).$this->ngMinUnit;   //self::gToKgString($gnum);
                    $rst["strFullKg"] .= "，".$rst["strKg"];
                }
                $rst["strFullKg"] .= ")";
                
            //} else {
            /*    //多个装
                $minn = $this->ngMinNum * $this->ngMidNum
                if ($this->noMaxu) {
                    //无外包装
                    $rst["min"] = $unum;
                } else {
                    //有外包装
                    
                    $rst["min"] = $unum%$minn;
                    $rst["max"] = floor($unum/$minn);
                }
            }*/
        }
        return $rst;
    }
    /**
     * 单价 计算得到 不同单位的单价
     * @param Number $price 单价，最小计量单位的价格，散装=每克价格，单个装=每个价格，袋装 或 *片/袋=每袋价格
     * @return Array 计算结果
     * [
     *      "g"     => 数字，换算为每克价格，不按重量计数则为 每个价格
     *      "kg"    => 数字，换算为每Kg价格，不按重量计数的 与 g 相同
     *      "jin"   => 数字，换算为每斤价格，不按重量计数的 与 g 相同
     *      "min"   => 数字，最小计量单位价格 == price
     *      "max"   => 数字，外包装单位价格，散装/不按重量计数的 与 kg 相同
     *      "str"   => 字符串，￥* /袋，￥* /箱  散装：￥* /斤，￥* /Kg  不按重量计数：￥* /个，￥* /袋，￥* /箱
     * ]
     */
    public function calcPriceToUnitPrice($price)
    {
        $rst = [
            "g" => 0,
            "kg" => 0,
            "jin" => 0,
            "min" => $price<0.01 ? round($price*10000)/10000 : round($price*100)/100,
            "max" => 0,
            "str" => ""
        ];
        $unit = $this->unit;
        $nw = $this->netwt;
        $maxu = $this->maxunit;
        $minn = $this->minnum;
        $k = "￥";
        $s = "/";
        if (!$this->notGnum) {
            //正常的按重量计数
            $g = round(($price/$nw)*10000)/10000;
            $rst["g"] = $g>=0.01 ? round($g*100)/100 : $g;
            $rst["kg"] = round(($g*1000)*100)/100;
            $rst["jin"] = round(($g*500)*100)/100;
            if ($this->noMaxu) {
                $rst["max"] = $rst["kg"];
            } else {
                $rst["max"] = round($price*$minn*100)/100;
            }
            $str = [];
            if (!$this->noMinu) {
                $str[] = $k.$rst["min"].$s.$unit;
            } else {
                if ($this->isBulk) $str[] = $k.$rst["jin"].$s."斤";
                if ($this->isBulkWithMaxu) $str[] = $k.$rst["kg"].$s."Kg";
            }
            if (!$this->noMaxu) {
                $str[] = $k.$rst["max"].$s.$maxu;
            } else {
                if ($this->isBulk) $str[] = $k.$rst["kg"].$s."Kg";
            }
            $rst["str"] = implode("，", $str);
        } else {
            //不按重量计数
            $minwt = $this->ngMinWt;
            $unit = $this->ngMinUnit;
            if ($this->noMinu) {
                //单个装
                if ($minwt<=0) {
                    //未指定单个重量
                    $g = round($price*100)/100;
                    $rst["g"] = $g;
                    $rst["kg"] = $g;
                    $rst["jin"] = $g;
                    $str = [];
                    $str[] = $k.$g.$s.$unit;
                    if ($this->noMaxu) {
                        $rst["max"] = $rst["kg"];
                    } else {
                        $rst["max"] = round($g*$minn*100)/100;
                        $str[] = $k.$rst["max"].$s.$maxu;
                    }
                    $rst["str"] = implode("，", $str);
                } else {
                    //指定了单个重量
                    $g = round(($price/$nw)*10000)/10000;
                    $rst["g"] = $g>=0.01 ? round($g*100)/100 : $g;
                    $rst["kg"] = round(($g*1000)*100)/100;
                    $rst["jin"] = round(($g*500)*100)/100;
                    $str = [];
                    $str[] = $k.$rst["min"].$s.$unit;
                    if ($this->noMaxu) {
                        $rst["max"] = $rst["kg"];
                    } else {
                        $rst["max"] = round($price*$minn*100)/100;
                        $str[] = $k.$rst["max"].$s.$maxu;
                    }
                    $rst["str"] = implode("，", $str);
                }

            } else {
                //多个装
                if ($minwt<=0) {
                    //未指定单个重量
                    $g = round(($price/$this->ngMinNum)*100)/100;
                    $rst["g"] = $g;
                    $rst["kg"] = $g;
                    $rst["jin"] = $g;
                    $str = [];
                    $str[] = $k.$g.$s.$unit;
                    if ($this->noMaxu) {
                        $rst["max"] = $rst["kg"];
                    } else {
                        $rst["max"] = round($price*$minn*100)/100;
                        $str[] = $k.$rst["max"].$s.$maxu;
                    }
                    $rst["str"] = implode("，", $str);
                } else {
                    //指定了单个重量
                    $g = round(($price/$nw)*10000)/10000;
                    $ni = round(($price/$this->ngMinNum)*100)/100;
                    $rst["g"] = $ni;
                    $rst["kg"] = round(($g*1000)*100)/100;
                    $rst["jin"] = round(($g*500)*100)/100;
                    $str = [];
                    $str[] = $k.$ni.$s.$unit;
                    if ($this->noMaxu) {
                        $rst["max"] = $rst["kg"];
                    } else {
                        $rst["max"] = round($price*$minn*100)/100;
                        $str[] = $k.$rst["max"].$s.$maxu;
                    }
                    $rst["str"] = implode("，", $str);
                }
            }
        }

        return $rst;
    }
    

    /**
     * 其他工具
     */



    /**
     * static tools
     */

    //静态创建
    public static function create($record, $opt=[])
    {
        $uniter = new Uniter($record, $opt);

        return $uniter;
    }

    /**
     * calc 计算
     */
    // g --> Kg 输出字符串
    public static function gToKgString($gnum, $withGap=false)
    {
        $str = [];
        if ($gnum<1000) {
            $str[] = $gnum;
            $str[] = "克";
        } else {
            $str[] = round(($gnum*100)/1000)/100;
            $str[] = "Kg";
        }
        return implode($withGap ? " " : "", $str);
    }

    /**
     * 其他
     */
    //处理 extra 中的规格字符串，只能出现中文逗号，g-->克，kg-->Kg，去除空格
    public static function fixPkgString($pkg)
    {
        if (!is_notempty_str($pkg)) return $pkg;
        $pkg = trim($pkg);
        $pkg = str_replace(",", "，", $pkg);
        //$pkg = str_replace(";", "，", $pkg);
        //$pkg = str_replace("；", "，", $pkg);
        $pkg = str_replace("g", "克", $pkg);
        //$pkg = str_replace("kg", "Kg", $pkg);
        //$pkg = preg_replace("/[^kK]g/", "克", $pkg);
        $pkg = preg_replace("/\s+/","",$pkg);
        return $pkg;
    }

    
}