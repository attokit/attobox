<?php
/**
 * 数据库类型 驱动 基类
 */

namespace Atto\Orm;

use Atto\Box\db\Dbo;

class Driver 
{
    /**
     * !! 必须实现 !!
     */
    
    /**
     * 数据库连接方法
     * @param Array $opt medoo 连接参数
     * @return Dbo 数据库实例
     */
    public static function connect($opt=[])
    {
        //... 子类实现

        return new Dbo();
    }

    /**
     * 创建数据库
     * @param Array $opt 数据库创建参数
     * @return Bool
     */
    public static function create($opt=[])
    {
        //... 子类实现

        return true;
    }
}