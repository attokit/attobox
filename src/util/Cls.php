<?php
/*
 *  Attobox Framework  工具函数
 *  cls_xxxx 类操作 工具函数
 */

/**
 * 取得 ReflectionClass
 * @param String $cls 类全称
 * @return ReflectionClass instance
 */
function cls_ref($cls)
{
    if (!class_exists($cls)) return null;
    return new \ReflectionClass($cls);
}

/**
 * method/property filter 简写
 * ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC 简写为 'static,is_public'
 * @param String $filter 简写后的 filter
 * @param String $type 区分 ReflectionMethod / ReflectionProperty / ReflectionClassConstant ... 默认 ReflectionMethod
 * @return Int 完整的 filter
 */
function cls_filter($filter=null, $type="method")
{
    if (is_null($filter) || $filter=="") return null;
    $fs = explode(",", $filter);
    $fs = array_map(function($i) {
        $j = strtolower(trim($i));
        if (substr($j, 0,3)!="is_") $j = "is_".$j;
        return strtoupper($j);
    }, $fs);
    $ff = array_shift($fs);
    $fp = "Reflection".ucfirst($type);
    $filter = constant($fp."::$ff");
    if (empty($fs)) return $filter;
    for ($i=0;$i<count($fs);$i++) {
        $fi = $fs[$i];
        $filter = $filter | constant($fp."::$fi");
    }
    return $filter;
}

/**
 * 获取 类 中的所有 method name
 * @param String $cls 类全称
 * @param String $filter 过滤方法，默认 null
 *      可选： 'static / public / protected / private / abstract / final'
 *      多个条件之间以 , 连接：'static,public'
 * @return Array [ method name, method name, ... ]
 */
function cls_ms($cls, $filter=null)
{
    $ref = cls_ref($cls);
    $filter = cls_filter($filter);
    $ms = $ref->getMethods($filter);
    $names = array_map(function($i) {
        return $i->name;
    }, $ms);
    return $names;
}

/**
 * 从某个类中筛选出符合指定条件的 methods
 * 返回 Reflection Method 对象数组
 * @return Array
 *  [
 *      methodName => ReflectionMethod 实例
 *      ...
 *  ]
 */
function cls_get_ms($cls, $condition, $filter='protected')
{
    $rcls = cls_ref($cls);
    if (empty($rcls)) return [];
    $filter = cls_filter($filter, "method");
    $methods = $rcls->getMethods($filter);
    $ms = [];
    for ($i=0;$i<count($methods);$i++) {
        $mi = $methods[$i];
        //执行判断函数，返回 true 则符合条件
        if ($condition($mi)===true) {
            //$name = strtolower($mi->name);
            $ms[$mi->name] = $mi;
        }
    }
    return $ms;
}

/**
 * 检查 类 中 是否包含方法
 * @param String $cls 类全称
 * @param String $method 要检查的方法名
 * @param String $filter 过滤方法，默认 null
 * @return Bool
 */
function cls_hasm($cls, $method, $filter=null)
{
    $ms = cls_ms($cls, $filter);
    return in_array($method, $ms);
}

/**
 * 获取 类 中的所有 property name
 * @param String $cls 类全称
 * @param String $filter 过滤方法，默认 null
 *      可选： 'static / public / protected / private / abstract / final'
 *      多个条件之间以 , 连接：'static,public'
 * @return Array [ property name, property name, ... ]
 */
function cls_ps($cls, $filter=null)
{
    $ref = cls_ref($cls);
    $filter = cls_filter($filter, "property");
    $ps = $ref->getProperties($filter);
    $names = array_map(function($i) {
        return $i->name;
    }, $ps);
    return $names;
}

/**
 * 检查 类 中 是否包含属性
 * @param String $cls 类全称
 * @param String $property 要检查的属性名
 * @param String $filter 过滤方法，默认 null
 * @return Bool
 */
function cls_hasp($cls, $property, $filter=null)
{
    $ps = cls_ps($cls, $filter);
    return in_array($property, $ps);
}
