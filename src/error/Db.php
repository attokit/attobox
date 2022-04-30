<?php

/**
 * Attobox Framework / Error Class
 * 
 * DB error
 */

namespace Atto\Box\error;

use Atto\Box\Error;

class Db extends Error
{
    /**
     * error code prefix
     * should be overrided
     */
    protected $codePrefix = "003";

    /**
	 * errors config
	 * should be overrided
	 */
	protected $config = [
        
        "zh-CN" => [
            "unknown"       => ["未知错误", "数据库操作发生未知错误"],
            "unsupport"     => ["不支持的数据库类型", "不支持操作 %{1}% 数据库类型"],
            "errordsn"      => ["DSN错误", "DSN连接字符串无效，请检查 [ DSN = %{1}% ]"],
            "errortbkey"    => ["无法加载数据表", "加载目标数据表出错，请检查加载路径 [ callpath = %{1}% ]"],
            "notexists"     => ["数据库不存在", "未找到要连接的数据库，检查DSN连接字符串 [ DSN = %{1}% ]"],
            "unknowntable"  => ["数据表不存在", "要操作的数据表未找到 [ tablename = %{1}% ]"],
            "nocurd"        => ["CURD操作未能初始化","CURD操作必须先初始化，指定要操作的数据表 [ DB = %{1}% ]"],
            "needtable"     => ["未指定数据表", "CURD操作需要指定目标数据表"],
            "test"          => ["Global Error Handle Test", "%{1}%, %{2}%"]
        ]
        
	];
}