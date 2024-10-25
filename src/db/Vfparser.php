<?php

/**
 * Attobox Framework / table config virtual field parser
 * 解析 config/dbn.json 中关于 虚拟字段值 的设置项
 *      virtual: {
 *          fields: [ _vfn, ... ],
 *          field: {
 *              _vfn: {
 *                  ...,
 *                  value: '***',   //解析此项，并计算取得 值
 *              }
 *          }
 *      }
 * 
 * 调用方式：
 *  1   $table->vfparser->useCtx([context array])->parse(cmd)
 *  2   $table->vfparser->useCtx([context array])->parse(virtualFieldName)
 * 
 * 支持的设置形式：
 *  1   直接替换真实字段值          _ctx_['field'].'foobar'._ctx_['field']
 *  2   直接计算表达式              _ctx_['field']>0?_ctx_['field'].'and'._ctx_['field']:date('Y-m-d H:i:s',_ctx_['field'])
 *  3   调用解析器内置方法          _vfp_Method(_ctx_['field'],'foobar',123,true,null,'{\'foo\':\'bar\'}',_vfp_Method2(_ctx_['field']))
 * 
 * 使用 eval 方法，因此表达式语法应符合 php 规则，关键字：
 *      _c_  -->  $this->context
 *      _p_  -->  $this->parse
 * 
 */

namespace Atto\Box\db;

use Atto\Box\Db;
use Atto\Box\db\Table;
use Atto\Box\Record;
use Atto\Box\RecordSet;

use Atto\Box\Uniter;

class Vfparser 
{
    //关联的 table 实例
    public $table = null;

    /**
     * 解析时调用的记录条目内容 context，
     * 一般由 record->export("show", false) 得到的 已处理好关联表字段值的 context 数组
     */
    public $context = [];

    //constructor
    public function __construct($tb)
    {
        $this->table = $tb;
    }

    /**
     * 使用某个 context 作为解析上下文
     */
    public function useCtx($ctx=[])
    {
        $this->context = $ctx;
        return $this;
    }

    /**
     * 解析入口
     * @param String $cmd 虚拟字段的字段名  or  虚拟字段的设置 value ，要解析的设置内容
     * @return Mixed 解析计算后得到的值
     */
    public function parse($cmd=null)
    {
        if (!is_notempty_str($cmd) || empty($this->context)) return "";
        $fds = $this->table->config["virtualFields"];
        $fd = $this->table->config["field"];
        //$tfds = $this->table->config["fields"];
        $ctx = $this->context;
        if (is_def($ctx, $cmd)) return $ctx[$cmd];
        if (in_array($cmd, $fds)) {
            $cmd = $fd[$cmd]["virtual"];
        }

        $cmds = $cmd;
        //var_dump($cmds);
        $cmds = str_replace("_c_","\$ctx", $cmds);
        $cmds = str_replace("_p_","\$this->parse", $cmds);
        if (strpos($cmds, 'return')===false) $cmds = "return ".$cmds;
        if (!str_end($cmds, ";")) $cmds .= ";";
        //var_dump($cmds);

        return eval($cmds);
    }



    /**
     * 预置的解析方式
     */

    //输出虚拟字段值
    public function parseVfd($vfd)
    {
        if (in_array($vfd, $this->table->config["virtualFields"])) return $this->parse($vfd);
        return "";
    }

    //输出包装规格
    public function parsePkg(/*$unit, $netwt, $maxunit, $minnum, $extra*/)
    {
        $ctx = $this->context;
        $uniter = Uniter::create($ctx);
        return $uniter->exportPkgString();


        $unit = $ctx["unit"];
        $netwt = $ctx["netwt"];
        $maxunit = $ctx["maxunit"];
        $minnum = $ctx["minnum"];
        $extra = $ctx["extra"];
        $extra = is_string($extra) ? j2a($extra) : (is_associate($extra) ? $extra : []);
        $pkgs = [];
        if ($unit=="克" && $netwt==1) {
            $pkgs[] = "散装";
        } else if ($unit!="克" && $netwt!=1) {
            $pkgs[] = $unit."装";
        }
        if (isset($extra["外箱标签包装规格"]) && $extra["外箱标签包装规格"]!="") {
            $pkgs[]= $extra["外箱标签包装规格"];
            return implode("，", $pkgs);
        } else if (isset($extra["标签包装规格"]) && $extra["标签包装规格"]!="") {
            $pkgs[] = $extra["标签包装规格"];
            return implode("，", $pkgs);
        } else {
            if ($netwt==1) {
                if ($unit!="克") {
                    //$pkgs[] = "1".$unit."/袋";
                    $pkgs[] = "单".$unit."装";
                }
            } else {
                $pkgs[] = $this->parseNetwt($netwt)."/".$unit;
            }
            if ($maxunit!="无") {
                if ($netwt==1 && $unit!="克") {
                    //$pkgs[] = $minnum."袋/".$maxunit;
                    $pkgs[] = $minnum.$unit."/".$maxunit;
                } else {
                    if ($unit=="克") {
                        $pkgs[] = $this->parseNetwt($minnum)."/".$maxunit;
                    } else {
                        $pkgs[] = $minnum.$unit."/".$maxunit;
                    }
                }
            }
            return implode("，", $pkgs);
        }
    }

    //输出 $num 将小包装数转换为外箱数+小包装数 字符串，需要表设置 "userelatedtable": true
    public function parseMaxunit($num="qty", $pre="skuid", $useGnum = false)
    {
        $ctx = $this->context;
        $uniter = Uniter::create($ctx, [
            "unitField" => $pre."_unit",
            "netwtField" => $pre."_netwt",
            "maxunitField" => $pre."_maxunit",
            "minnumField" => $pre."_minnum",
            "extraField" => $pre."_extra",
        ]);
        //如果当前记录包含了规格信息，则使用当前记录的规格
        $cupkg = $ctx["extra"]["currentPackage"] ?? null;
        $uniter->setCustomPackage($cupkg);
        $unum = $ctx[$num];
        $units = $uniter->calcUnumToUnits($unum);
        return $units["str"];


        $unum = $ctx[$num];
        //如果当前记录包含了规格信息，则使用当前记录的规格
        $cupkg = $ctx["extra"]["currentPackage"] ?? null;
        $nw = is_notempty_arr($cupkg) ? $cupkg["netwt"] : ($ctx[$pre."_netwt"] ?? 1);
        if ($useGnum) {
            //如果输入的 $num 是克重，则计算 小包装数
            $unum = floor($unum/$nw);
        }
        $unit = is_notempty_arr($cupkg) ? $cupkg["unit"] : $ctx[$pre."_unit"];
        $maxu = is_notempty_arr($cupkg) ? $cupkg["maxunit"] : $ctx[$pre."_maxunit"];
        $minn = is_notempty_arr($cupkg) ? ($cupkg["minnum"] ?? 1) : ($ctx[$pre."_minnum"] ?? 1);
        if (($maxu=="无" && $minn<=1) || $unum<$minn) {
            if ($unit=="克") {
                return $unum>=1000 ? round($unum/1000, 4)."Kg" : $unum."克";
            } else {
                return $unum.$unit;
            }
        } else {
            $mus = [];
            $mns = floor($unum/$minn);
            $lus = $unum%$minn;
            $mus[] = $mns.$maxu;
            if($lus>0) {
                if ($unit=="克") {
                    $mus[] = $lus>=1000 ? round($lus/1000, 4)."Kg" : $lus."克";
                } else {
                    $mus[] = $lus.$unit;
                }
            }
            return implode("+",$mus);
        }
        //(!isset(_c_['skuid_maxunit']) || _c_['skuid_maxunit']=='无')?_c_['qty']._c_['skuid_unit']:(_c_['qty']<_c_['skuid_minnum']?_c_['qty']._c_['skuid_unit']:(floor(_c_['qty']/_c_['skuid_minnum'])._c_['skuid_maxunit'].(_c_['qty']%_c_['skuid_minnum']<=0?'':'+'.(_c_['qty']%_c_['skuid_minnum'])._c_['skuid_unit'])))
    }

    //将小包装数转换为 克重/Kg
    public function parseUnitgnum($unum="num", $pre="skuid")
    {
        $ctx = $this->context;
        $uniter = Uniter::create($ctx, [
            "unitField" => $pre."_unit",
            "netwtField" => $pre."_netwt",
            "maxunitField" => $pre."_maxunit",
            "minnumField" => $pre."_minnum",
            "extraField" => $pre."_extra",
        ]);
        $unum = $ctx[$unum];
        $gnum = $uniter->calcUnumToGnum($unum);
        return $gnum["str"];


        $unum = $ctx[$unum];
        $nw = $ctx[$pre."_netwt"] ?? 1;
        $wt = $nw*$unum;
        if ($wt<1000) return $wt."克";
        return round($wt/1000,2)."Kg";
    }

    //获取成品的配方中指定的满批重量
    public function parseFullbatch($rppre="rpid")
    {
        $ctx = $this->context;
        $extra = $ctx[$rppre."_extra"] ?? null;
        if (is_notempty_str($extra) && is_json($extra)) {
            $extra = j2a($extra);
        } else {
            $extra = null;
        }
        if (is_notempty_arr($extra) && is_associate($extra)) {
            $fb = $extra["满批"] ?? null;
            if (is_null($fb)) $fb = $extra["满批重量"] ?? null;
        } else {
            $fb = null;
        }
        if (is_null($fb) || !is_numeric($fb)) {
            //未指定满批
            return 0;
        } else {
            return $fb*1000;
        }

    }

    //将小包装数转换为 Kg，并判断 == 几个满批
    public function parseFullbatchnum($unum="num", $pre="pid")
    {
        $ctx = $this->context;
        $unum = $ctx[$unum];
        $nw = $ctx[$pre."_netwt"] ?? 1;
        $wt = $nw*$unum;
        //$extra = $ctx["extra"];
        $extra = $ctx[$pre."_extra"];
        if (is_string($extra) && is_json($extra)) {
            $extra = j2a($extra);
        }
        if (!is_notempty_arr($extra) || !is_associate($extra)) {
            $extra = [];
        }
        $fb = $extra["满批"] ?? null;
        if (is_numeric($fb)) {
            $fbnum = $fb*1000;
            $dt = 10000;    //允许误差 5Kg，即 相差 10Kg 视为相等
            if (abs($wt-$fbnum)<=$dt) {
                $fbs = 1;
            } else {
                $fbs = round($wt/$fbnum, 2);
            }
            return $fbs." 满批 (".$fb."Kg/批)";
        } else {
            return "未指定满批";
        }
    }

    //输出 单价，散装货品显示 ￥20.00/Kg 有包装的货品显示 ￥10/袋，￥100/箱
    public function parsePriceunit($price="price", $pre="skuid")
    {
        $ctx = $this->context;
        $uniter = Uniter::create($ctx, [
            "unitField" => $pre."_unit",
            "netwtField" => $pre."_netwt",
            "maxunitField" => $pre."_maxunit",
            "minnumField" => $pre."_minnum",
            "extraField" => $pre."_extra",
        ]);
        $price = $ctx[$price];
        $up= $uniter->calcPriceToUnitPrice($price);
        return $up["str"];


        //return "";
        $dig = 10000;
        $ctx = $this->context;
        $pr = $ctx[$price];
        //如果当前记录包含了规格信息，则使用当前记录的规格
        $cupkg = $ctx["extra"]["currentPackage"] ?? null;
        $un = is_notempty_arr($cupkg) ? $cupkg["unit"] : $ctx[$pre."_unit"];
        $nw = is_notempty_arr($cupkg) ? $cupkg["netwt"] : $ctx[$pre."_netwt"];
        $maxu = is_notempty_arr($cupkg) ? $cupkg["maxunit"] : $ctx[$pre."_maxunit"];
        $minn = is_notempty_arr($cupkg) ? $cupkg["minnum"] : $ctx[$pre."_minnum"];
        $isbulk = $un=='克' && $nw==1;
        $nomax = $maxu=='无' && $minn==1;
        $pstr = [];
        if ($isbulk) {
            $pr_kg = round($pr*1000*$dig)/$dig;
            $pstr[] = "￥".$pr_kg."/Kg";
        } else {
            if ($un!="克") {
                $pr_unit = round($pr*$dig)/$dig;
                $pstr[] = "￥".$pr_unit."/".$un;
            }
        }
        if (!$nomax) {
            $pr_max = round($pr*$minn*$dig)/$dig;
            $pstr[] = "￥".$pr_max."/".$maxu;
        } else {
            if ($isbulk) {
                $pr_500 = round($pr*500*$dig)/$dig;
                array_unshift($pstr, "￥".$pr_500."/斤");
            }
        }
        return implode("，", $pstr);
    }

    //timestamp  -->  datestr
    public function parseDatestr($timestamp, $format="Y-m-d")
    {
        if (!is_numeric($timestamp)) return "";
        return date($format, $timestamp);
    }

    //显示保质期，按天计算的保质期转为 *年 *月 *天
    public function parseWarranty($wrt)
    {
        if (!is_numeric($wrt)) return "";
        //不显示 n年
        //$y = $wrt/360;
        //if (is_int($y)) return $y."年";
        $m = $wrt/30;
        if (is_int($m)) return $m."个月";
        return $wrt."天";
    }

    // 10000 -> 10Kg  100 -> 100g
    public function parseNetwt($wt, $dig=2)
    {
        if (is_string($wt) && !is_numeric($wt)) {
            $ctx = $this->context;
            $wt = $ctx[$wt];
            if (!is_numeric($wt)) {
                $wt = 0;
            } else {
                $wt = $wt*1;
            }
        } else {
            $wt = $wt*1;
        }
        if ($wt<1000) return $wt."克";
        $kg = round($wt/1000, $dig);
        return $kg."Kg";
    }

    //从名称字符串中解析出计量单位，'品名，袋装，10克/袋...' --> '袋'
    public function parseUnit($name)
    {
        if (strpos($name, "，散装，")!==false) return "克";
        if (strpos($name, "装，")!==false) {
            $narr = explode("装，", $name);
            $narr = explode("，", $narr[0]);
            return count($narr)>1 ? array_slice($narr, -1)[0] : $narr[0];
        }
        return "";
    }

    //获取 extra 信息
    public function parseExtra($key)
    {
        $ctx = $this->context;
        $extra = $ctx["extra"];
        $extra = is_string($extra) ? j2a($extra) : (is_associate($extra) ? $extra : []);
        if (isset($extra[$key]) && is_string($extra[$key])) return $extra[$key];
        return "";
    }

    //获取 json 格式内容
    public function parseJson($fdn, $key = null)
    {
        $ctx = $this->context;
        $json = $ctx["json"];
        $json = is_string($json) ? j2a($json) : (is_associate($json) ? $json : []);
        if (empty($key) || !is_notempty_str($key)) return $json;
        return $json[$key] ?? null;
    }

    //补零
    public function parsePadzero($n, $dig=2)
    {
        if (!is_numeric($n)) return $n;
        $dig = (!is_int($dig) || $dig<2) ? 2 : $dig;
        return str_pad($n, $dig, "0", STR_PAD_LEFT);
    }

    //查询关联表内容，参数为本条记录中的 关联表字段名
    /**
     * 查询关联表内容
     * @param String $rlid 本条记录中的关联表字段名，skuid / pid / ...
     * @param Array $rlfdn 要查询的关联表记录字段名，不指定则返回整个关联表数据
     */
    public function parseRelated($rlid = "skuid", ...$rlfdn)
    {
        $ctx = $this->context;
        $rldata = $ctx[$rlid."_related_rs"] ?? null;
        if (empty($rldata)) return null;
        if (empty($rlfdn)) {
            return $rldata;
        } else if (count($rlfdn)==1) {
            return $rldata[$rlfdn[0]] ?? null;
        } else {
            $rst = [];
            for ($i=0;$i<count($rlfdn);$i++) {
                $rst[] = $rldata[$rlfdn[$i]] ?? null;
            }
            return $rst;
        }
    }

    //  32袋  -->  3箱+2袋，将库存袋数转化为箱数
    /*public function parseUnitStock($pnum, $minnum, $unit, $maxunit)
    {
        var_dump($pnum);
        var_dump($minnum);
        var_dump($unit);
        var_dump($maxunit);
        if ($pnum<$minnum || $minnum<=0) return $pnum.$unit;
        $us = floor($pnum/$minnum);
        $lf = $pnum%$innum;
        $s = $us.$maxunit;
        if ($lf>0) {
            $s .= "+".$lf.$unit;
        }
        return $s;
    }*/





    /**
     * tools
     */
    //判断 cmd 是否是 带有 %{...}% 的表达式
    public function isExpr($cmd=null)
    {
        if (!is_notempty_str($cmd) || $cmd=="") return false;

    }

    //去除表达式头尾的 () ''
    public function trimExpr($cmd=null) 
    {
        if (!is_notempty_str($cmd)) return "";
        if (str_begin($cmd,"(") && str_end($cmd,")")) return trim(trim($cmd, "("), ")");
        if (str_begin($cmd,"'")) return trim($cmd, "'");
    }

}