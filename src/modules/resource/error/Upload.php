<?php

/**
 * Attobox Framework / Errors
 * error config for Atto\Box\resource\Uploader
 */

namespace Atto\Box\error;

use Atto\Box\Error;

class Upload extends Error
{
    /**
     * error code prefix
     * should be overrided
     */
    protected $codePrefix = "021";

    /**
	 * errors config
	 * should be overrided
	 */
	protected $config = [
        
        "zh-CN" => [
            "unknown"   => ["未知错误",         "发生未知错误"],
            "errsavepath"   => ["上传目录错误", "设定的上传目录不存在，或误操作权限"],
            "syserr"        => ["FILES 列表错误", "%{1}%"],
            "nolegalfile"   => ["未找到要上传的文件", "可能要上传的文件不是允许的文件类型"],
            "movefileerr"   => ["保存文件出错", "可能文件存在错误，或者上传目录没有写入权限"],
            "notupfile"     => ["未找到要保存的文件", "写入文件时出错，找不到要保存的文件"],
        ]
        
	];
}