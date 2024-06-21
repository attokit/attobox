<?php

/**
 * Attobox Framework / Error Class
 * 
 * Model error
 */

namespace Atto\Box\error;

use Atto\Box\Error;

class Model extends Error
{
    /**
     * error code prefix
     * should be overrided
     */
    protected $codePrefix = "004";

    /**
	 * errors config
	 * should be overrided
	 */
	protected $config = [
        
        "zh-CN" => [
            "unknown"       => ["未知错误", "数据模型操作发生未知错误"],

            "needparam"     => ["无法创建数据模型", "缺少数据模型创建参数"],


            

            "unsupport"     => ["不支持的数据库类型", "不支持操作 %{1}% 数据库类型"],
            "errordsn"      => ["DSN错误", "DSN连接字符串无效，请检查 [ DSN = %{1}% ]"],
            "noconfig"      => ["未找到预设参数", "未找到要读取或操作的数据库预设参数，请检查 [ config = %{1}% ]"],
            "notexists"     => ["数据库不存在", "未找到要连接的数据库，检查DSN连接字符串 [ DSN = %{1}% ]"],
            "errornewpath"  => ["无法创建数据库", "创建数据库失败，可能的原因包括：未指定新数据库的目录以及名称、无法创建目录、数据库已存在 等 [ PATH = %{1}% ]"],
            "errornew"      => ["无法创建数据库", "创建数据库文件失败 [ PATH = %{1}% ]"],

            "unknowntable"  => ["数据表不存在", "要操作的数据表未找到 [ tablename = %{1}% ]"],
            "errortbkey"    => ["无法加载数据表", "加载目标数据表出错，请检查加载路径 [ callpath = %{1}% ]"],
            "needtable"     => ["未指定数据表", "CURD操作需要指定目标数据表"],
            "errornewtb"    => ["无法创建数据表", "缺少必要的数据表创建参数 [ tablename = %{1}%/%{2}% ]"],

            "unknownfield"  => ["字段不存在", "要操作的字段未找到 [ fieldname = %{1}% ]"],

            "nocurd"        => ["CURD操作未能初始化","CURD操作必须先初始化，指定要操作的数据表 [ DB = %{1}% ]"],
            "test"          => ["Global Error Handle Test", "%{1}%, %{2}%"]
        ]
        
	];
}