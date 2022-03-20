<?php

/**
 * Attobox Framework / Error Class
 * 
 * Base error
 */

namespace Atto\Box\error;

use Atto\Box\Error;

class Base extends Error
{
    /**
     * error code prefix
     * should be overrided
     */
    protected $codePrefix = "001";

    /**
	 * errors config
	 * should be overrided
	 */
	protected $config = [
        
        "zh-CN" => [
            "unknown"   => ["未知错误",         "发生未知错误"],
            "php"       => ["PHP 系统错误",     "%{1}%"],
            "test"      => ["Global Error Handle Test", "%{1}%, %{2}%"]
        ]
        
	];
}