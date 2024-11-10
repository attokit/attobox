<?php
/**
 *  Attoorm Framework / traits  可复用的类特征
 *  model\pkgCalcer 为 数据表记录实例 增加 规格计算 功能
 * 
 *  
 * 
 */

namespace Atto\Orm\traits\model;

trait Packager 
{
    //定义各规格字段名
    public $pkgFields = [
        "unit" => "unit",
        "netwt" => "netwt",
        //"midpkg" => "midpkg",
        "maxunit" => "maxunit",
        "minnum" => "minnum",
    ];

    //当前记录实例中的规格参数值
    public $pkgParams = [
        "unit" => "克",
        "netwt" => 1,
        //"midpkg" => [],
        "maxunit" => "无",
        "minnum" => 1,
    ];

    //包含规格参数的 数据记录实例，可以是主表记录实例，或 join 关联表记录实例
    public $pkgIns = null;

    /**
     * 添加 initIns*** 初始化方法
     * 初始化 当前数据记录实例 中的规格参数
     * @return Model $this
     */
    protected function initInsPackager()
    {
        //从当前数据记录实例数据中，查找 规格参数
        $pfds = $this->pkgFields;
        $fds = static::$configer->fields;
        if (empty(array_diff(array_values($pfds), $fds))) {
            //主表记录数据中 包含规格数据
            foreach ($pfds as $k => $fdn) {
                $this->pkgParams[$k] = $this->$fdn;
            }
            $this->pkgIns = $this;
        }
        //主表记录数据中不包含规格数据，则在 join 关联表中查找
        if (empty($this->joined)) return $this;
        foreach ($this->joined as $tbn => $jtb) {
            $jfds = $jtb->conf->fields;
            if (empty(array_diff(array_values($pfds), $jfds))) {
                foreach ($pfds as $k => $fdn) {
                    $this->pkgParams[$k] = $jtb->$fdn;
                }
                $this->pkgIns = $jtb;
                break;
            }
        }
        return $this;
    }

    /**
     * 定义计算字段
     */

    /**
     * Getter
     * @name pkg
     * @title 成品规格
     * @desc 用于规格计算的成品规格参数
     * @type varchar
     * @jstype object
     * @phptype JSON
     */
    protected function pkgGetter()
    {
        return $this->pkgParams;
    }



    /**
     * 计算方法
     */

    /**
     * 判断
     * 是否 不按重量计数
     * @return Bool
     */
    public function pkgNonWt()
    {
        $pkg = (object)$this->pkgGetter();
        $uns = "个，片，只，条，块";
        return in_array($pkg->unit, explode("，", $uns));
    }

    /**
     * 判断
     * 是否 无小包装
     * @return Bool
     */
    public function pkgNoMinu()
    {
        $pkg = (object)$this->pkgGetter();
        if ($this->pkgNonWt()) {
            return $pkg->netwt == 1;
        } else {
            return $pkg->unit=="克" && $pkg->netwt==1;
        }
    }

    /**
     * 判断
     * 是否 无大包装
     * @return Bool
     */
    public function pkgNoMaxu()
    {
        $pkg = (object)$this->pkgGetter();
        return $pkg->maxunit=="无" && $pkg->minnum==1;
    }

    /**
     * 判断
     * 是否 散装
     * @return Bool
     */
    public function pkgIsBulk()
    {
        return $this->pkgNoMinu() && $this->pkgNoMaxu();
    }

    /**
     * 将当前记录数据中的 最小计量单位数量 解析为 规格详情数据：
     *  [
     *      "min"       => 总计 * 个最小计量单位，散装或不按重量计数的 == g
     *      "max"       => 总计 * 个最大计量单位，无大包装 == 0，散装或不按重量计数的 == kg
     *      "min_max"   => [ 小包装数, 大包装数 ]
     *      "g"         => 克重，不按重量计数的表示 *个
     *      "kg"        => Kg，不按重量计数的 == g
     *      "odd"       => 零头重量 克重，不按重量计数的 == 0
     *  ]
     * @param Numeric $units 最小计量单位数量，不按重量计数的 *个
     * @return Array
     */
    public function pkgParse($units)
    {
        $rst = [
            "min" => $units,
            "max" => 0,
            "min_max" => [],
            "g" => 0,
            "kg" => 0,
            "odd" => 0
        ];
        if (!is_numeric($units)) return $rst;
        $pkg = (object)$this->pkgGetter();
        $nonWt = $this->pkgNonWt();
        $noMinu = $this->pkgNoMinu();
        $noMaxu = $this->pkgNoMaxu();
        if ($nonWt==false) {
            //按重量计数
            $gnum = $units*$pkg->netwt;
            $kgnum = round(($gnum*100)/1000)/100;
            $max = $noMaxu ? 0 : ceil($units/$pkg->minnum);
            $min = $noMaxu ? ($gnum) : $units%$pkg->minnum;
            $min_max = [$min, $max];
            $rst = arr_extend($rst, [
                "max" => $max,
                "min_max" => $min_max,
                "g" => $gnum,
                "kg" => $kgnum
            ]);
        } else {
            //不按重量计数
            $gnum = $units;
            $kgnum = $units;
            $max = $noMaxu ? 0 : ceil($units/$pkg->minnum);
            $min = $noMaxu ? $gnum : $units%$pkg->minnum;
            $min_max = [$min, $max];
            $rst = arr_extend($rst, [
                "max" => $max,
                "min_max" => $min_max,
                "g" => $gnum,
                "kg" => $kgnum
            ]);
        }
        return $rst;
    }

    /**
     * 小包装数 --> 大包装数+小包装数 | 大包装数+*Kg | 大包装数+*个 | *unit
     * @param Numeric $units 最小计量单位数，不按重量计数的 *个
     * @return String
     */
    public function pkgToUnitStr($units)
    {
        if (!is_numeric($units)) return $units;
        $pps = (object)$this->pkgParse($units);
        $str = [];
        $min_max = $pps->min_max;
        $min = $min_max[0];
        $max = $min_max[1];
        $pkg = (object)$this->pkgGetter();
        $nonWt = $this->pkgNonWt();
        $noMinu = $this->pkgNoMinu();
        if ($nonWt==false) {
            //按重量计数
            if ($max>0) $str[] = $max.$pkg->maxunit;
            if ($min>0) {
                if ($noMinu) {
                    $str[] = $this->pkgToKgStr($min);
                } else {
                    $str[] = $min.$pkg->unit;
                }
            }
        } else {
            //不按重量计数
            if ($max>0) $str[] = $max.$pkg->maxunit;
            if ($min>0) $str[] = $min.$pkg->unit;
        }
        return implode("+",$str);
    }

    /**
     * 克重 --> *Kg | *克
     * @param Numeric $gnum 克重
     * @return String
     */
    public function pkgToKgStr($gnum)
    {
        if ($gnum<1000) return $gnum."克";
        return (rount(($gnum*100)/1000)/100)."Kg";
    }

}