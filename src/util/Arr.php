<?php
/*
 *  CPHP框架  工具函数
 *  arr_xxxx array工具函数
 */
//复制一个array
function arr_copy($arr = [])
{
    return array_merge($arr, []);
}

//last，返回arr中最后一个value，针对一维数组，多维数组返回null
function arr_last($arr = [])
{
    //return $arr[array_key_last($arr)];    //php>=7.3
    return array_slice($arr, -1)[0];
}

//按 a/b/c... 形式搜索多维数组，返回找到的值，未找到则返回null
function arr_item($arr = [], $key = "")
{
    if (is_int($key)) {
        if (isset($arr[$key])) return $arr[$key];
        return null;
    } else if (!is_notempty_str($key)) {
        //return $arr;
        return $key=="" ? $arr : null;
    }
    if (!is_array($arr) || empty($arr)) return null;
    $ctx = $arr;
    $karr = explode("/", $key);
    $rst = null;
    for ($i=0; $i<count($karr); $i++) {
        $ki = $karr[$i];
        if (!isset($ctx[$ki])) {
            break;
        }
        if ($i >= count($karr)-1) {
            $rst = $ctx[$ki];
            break;
        } else {
            if (is_array($ctx[$ki])) {
                $ctx = $ctx[$ki];
            } else {
                break;
            }
        }
    }
    return $rst;
}

//len，查找arg（可有多个）在arr中出现的次数，不指定arg则返回数组长度
function arr_len($arr = [], ...$args)
{
    if (empty($args)) return count($arr);
    $count = [];
    foreach ($args as $i => $v) {
        $count[$i] = count(array_keys($arr, $v));
    }
    return count($args) <= 1 ? $count[0] : $count;
}

//key，查找arg（可有多个）在arr中的key，多个arg的默认返回第一个，
//最后一参数为 false 时，返回所有key
//未找到返回 false
function arr_key($arr = [], ...$args)
{
    if (empty($args)) return false;
    if (count($args)<=1 && is_bool(arr_last($args))) return false;
    $getFirst = is_bool(arr_last($args)) ? array_pop($args) : true;
    $idxs = [];
    foreach ($args as $i => $v) {
        $idxs[$i] = $getFirst ? array_search($v, $arr) : array_keys($arr, $v);
    }
    return count($args) <= 1 ? $idxs[0] : $idxs;
}

//返回当前arr的维度
function arr_dimension($arr = [])
{
    $di = [];
    foreach ($arr as $k => $v) {
        if (!is_array($v)) {
            $di[$k] = 1;
        } else {
            $di[$k] = 1 + arr_dimension($v);
        }
    }
    return max($di);
}

//判断两个arr是否相等，如果是多维数组，则递归判断
function arr_equal($arr_a = [], $arr_b = [])
{
    //异或运算 ^ （true ^ false == true），确保两个数组维度相同
    $di_a = is_onedimension($arr_a);
    $di_b = is_onedimension($arr_b);
    if ($di_a ^ $di_b) return false;
    //长度必须相同
    if (count($arr_b) != count($arr_a)) return false;
    
    if ($di_a) {   //一维数组比较
        return empty(array_diff_assoc($arr_a, $arr_b));   //值 和 顺序都必须一样
    } else {    //多维数组比较，递归
        foreach ($arr_a as $k => $v) {
            if (isset($arr_b[$k])) {
                if (is_array($v) ^ is_array($arr_b[$k])) {
                    return false;
                } else {
                    if (arr_equal($v, $arr_b[$k]) === true) {
                        continue;
                    } else {
                        return false;
                    }
                }
            } else {
                return false;
            }
        }
        return true;
    }
}

//从数组中按 closure 条件筛选，返回第一个或全部符合条件的 (值) ，适用于一维数字键数组
/*function choose($closure, $all = false)
{
    $arr = array_filter($this->context, $closure);
    $arr = array_merge($arr, []);
    if (empty($arr)) return $all ? [] : null;
    return $all ? $arr : $arr[0];
}

//从数组中按 closure 条件筛选，返回第一个或全部符合条件的 (键) ，适用于一维数字键数组
function chooseKey($closure, $all = false)
{
    $self = $this->self();
    $val = $this->choose($closure, $all);
    if (is_null($val)) return null;
    if ($all) {
        $val = Arr::var($val)->each(function($v, $k) use ($self) {
            $this->set($k, $self->idx($v));
        });
        return $val->val();
    } else {
        return $this->idx($val);
    }
}*/

//多维数组递归合并，新值替换旧值，like jQuery extend
function arr_extend($old = [], $new = []) 
{
    if (func_num_args()>2) {
        $args = func_get_args();
        $old = $args[0];
        for ($i=1; $i<count($args); $i++) {
            $old = arr_extend($old, $args[$i]);
        }
        return $old;
    } else {
        if (!is_notempty_arr($new)) return $old;
        if (!is_notempty_arr($old)) return $new;
        foreach ($old as $k => $v) {
            if (!array_key_exists($k, $new)) continue;
            if ($new[$k] === "__delete__") {
                unset($old[$k]);
                continue;
            }
            if (is_array($v) && is_array($new[$k])) {
                if (is_indexed($v) && is_indexed($new[$k])) {		//新旧值均为数字下标数组
                    //合并数组，并去重
                    //！！！！当数组的值为{}时，去重报错！！！！
                    //！！！！添加 SORT_REGULAR 参数，去重时不对数组值进行类型转换
                    $old[$k] = array_unique(array_merge($v, $new[$k]), SORT_REGULAR);
                }else{
                    //递归extend
                    $old[$k] = arr_extend($v, $new[$k]);
                }
            }else{
                $old[$k] = $new[$k];
            }
        }
        foreach ($new as $k => $v) {
            if (array_key_exists($k, $old) || $v === "__delete__") continue;
            $old[$k] = $v;
        }
        return $old;
    }
}

//多维数组转为一维数组
function arr_indexed($arr=[], $key="children")
{
    $narr = [];
    for ($i=0;$i<count($arr);$i++) {
        $ai = $arr[$i];
        $ki = [];
        if (isset($ai[$key])) {
            $ki = $ai[$key];
            unset($ai[$key]);
        }
        $narr[] = $ai;
        if (!empty($ki)) {
            $idxki = arr_indexed($ki, $key);
            if (!empty($idxki)) {
                $narr = array_merge($narr, $idxki);
            }
        }
    }
    return $narr;
}

//uni 去重
/*function arr_uni($arr = [])
{
    if (is_indexed($arr)) {
        $arr = array_merge(array_flip(array_flip($arr));
    }
    return $arr;
}*/

