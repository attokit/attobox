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
            "custom"        => ["数据库操作发生错误", "可能的原因：%{1}%"],
            "unsupport"     => ["不支持的数据库类型", "不支持操作 %{1}% 数据库类型"],
            "create"        => ["创建数据库失败", "无法创建数据库，可能的原因：%{1}%"],
            "recreate" => [
                "copyerr"   => ["重建数据库失败", "无法备份当前数据库"],
                "rctberr"   => ["重建数据库失败", "重建数据表 %{1}% 失败，操作已撤销"],
            ],

            "dsn"   => [
                "empty"     => ["DSN错误", "DSN连接字符串为空"],
                "illegal"   => ["DSN错误", "DSN连接字符串无效，请检查 [ DSN = %{1}% ]"],
            ],

            "pdo" => [
                "connect"   => ["PDO对象无法创建", "无法创建PDO对象，检查 [ DSN = %{1}% ] 或者确认mysql账号密码，或确认数据库文件路径"],
                "fetchall"  => ["PDO::query错误", "无法对query结果执行fetchAll操作"],
            ],

            "curd" => [
                "needdb"    => ["CURD操作未能初始化", "必须指定关联的数据库实例"],
                "needtb"    => ["CURD操作未能初始化","必须指定要操作的数据表 [ DB = %{1}% ]"],
                "nocurd"    => ["CURD操作未能初始化","检查是否已指定DB，以及要操作的Table [ DB = %{1}%; TB = %{2}% ]"],
                "incurd"    => ["CURD操作未能初始化","之前的CURD操作还未结束，不能再次初始化CURD操作 [ DB = %{1}% ]"],

                "create"    => ["新建记录出错","无法新建记录，可能的原因 %{1}% [ table = %{2}% ]"],
                "retrieve"  => [
                    "api"       => ["查询记录出错","通过接口查询记录出错，可能的原因 %{1}% [ table = %{2}%, api = %{3}% ]"],
                ],
                "generator" => ["调用自动生成方法出错","无法使用自动生成方法，可能的原因 %{1}% [ table = %{2}% ]"],
                "delete"    => ["删除记录出错","无法删除记录，可能的原因 %{1}% [ table = %{2}%; id = %{3}% ]"],
            ],

            "record" => [
                "needdbtbn" => ["无法创建活动记录", "必须指定 DbName/TbName"],
                "nocls"     => ["无法创建活动记录", "未找到 Record 类 [ DB/TB = %{1}% ]"],
            ],

            "sqlite" => [
                "notexists" => ["数据库不存在", "未找到要连接的数据库，检查DSN连接字符串 [ DSN = %{1}% ]"],
            ],

            "backup" => [
                "mkdir"     => ["备份数据库出错", "无法创建备份文件夹 [ DIR = %{1}% ]"],
                "copy"      => ["备份数据库出错", "无法将数据库文件复制到备份文件夹 [ DB = %{1}% ]"],
                "del"       => ["删除数据库备份记录出错", "无法删除备份文件 [ DB = %{1}% ]"],
                "restore"   => ["恢复数据库备份出错", "无法复制备份的数据库文件 [ DB = %{1}% ]"],
            ],
            
            "unknown"       => ["未知错误", "数据库操作发生未知错误"],
            "errordsn"      => ["DSN错误", "DSN连接字符串无效，请检查 [ DSN = %{1}% ]"],
            "nopdo"         => ["PDO对象无法创建", "无法创建PDO对象，检查 [ DSN = %{1}% ] 或者确认mysql账号密码，或确认数据库文件路径"],

            "mysqlnoauth"   => ["无法连接MYSQL数据库","缺少账户参数"],

            "noconfig"      => ["未找到预设参数", "未找到要读取或操作的数据库预设参数，请检查 [ config = %{1}% ]"],
            "notexists"     => ["数据库不存在", "未找到要连接的数据库，检查DSN连接字符串 [ DSN = %{1}% ]"],
            "errornewpath"  => ["无法创建数据库", "创建数据库失败，可能的原因包括：未指定新数据库的目录以及名称、无法创建目录、数据库已存在 等 [ PATH = %{1}% ]"],
            "errornew"      => ["无法创建数据库", "创建数据库文件失败 [ PATH = %{1}% ]"],

            "unknowntable"  => ["数据表不存在", "要操作的数据表未找到 [ tablename = %{1}% ]"],
            "errortbkey"    => ["无法加载数据表", "加载目标数据表出错，请检查加载路径 [ callpath = %{1}% ]"],
            "needtable"     => ["未指定数据表", "CURD操作需要指定目标数据表"],
            "errornewtb"    => ["无法创建数据表", "缺少必要的数据表创建参数 [ tablename = %{1}%/%{2}% ]"],
            "errornewtbexists"    => ["无法创建数据表", "缺少必要的数据表创建参数 [ tablename = %{1}%/%{2}% ]"],

            "newtb" => [
                "notbn"     => ["无法创建数据表", "数据表名不能为空"],
                "nosql"     => ["无法创建数据表", "数据表 Creation Array 不能为空"],
                "hastb"     => ["无法创建数据表", "数据表名已存在 [ tablename = %{1}% ]"],
                "execsql"   => ["无法创建数据表", "执行创建SQL出错 [ sql = %{1}% ]"],
            ],

            "unknownfield"  => ["字段不存在", "要操作的字段未找到 [ fieldname = %{1}% ]"],

            "nocurd"        => ["CURD操作未能初始化","CURD操作必须先初始化，指定要操作的数据表 [ DB = %{1}% ]"],
            "test"          => ["Global Error Handle Test", "%{1}%, %{2}%"]
        ]
        
	];
}