<?php
/*
 *  Attobox Framework / traits  可复用的类特征
 *  staticCreate
 * 
 *  1   通过 self::create(...$args) 静态方法生成类实例，并返回
 * 
 */

namespace Atto\Box\traits;

trait staticCreate
{
    //默认实例
    //public static $current = null;

    //生成默认实例
    public static function create()
    {
        $args = func_get_args();
        $cls = static::class;

        //php >= 5.6
        return new $cls(...$args);

        //reflection
        //$reflect  = new ReflectionClass($cls);
        //return $reflect->newInstanceArgs($args);
    }
}