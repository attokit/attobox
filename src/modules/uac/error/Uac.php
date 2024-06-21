<?php

/**
 * Attobox Framework / Errors
 * error config for Atto\Box\Uac
 */

namespace Atto\Box\error;

use Atto\Box\Error;

class Uac extends Error
{
    /**
     * error code prefix
     * should be overrided
     */
    protected $codePrefix = "002";

    /**
	 * errors config
	 * should be overrided
	 */
	protected $config = [
        
        "zh-CN" => [
            "unknown"   => ["未知错误",         "发生未知错误"],
            "notlogin"  => ["用户还未登录", "当前用户还没有登录，无法进行权限检查或其它操作"],
            "nousr"     => ["未找到用户", "系统中未找到当前用户，无法进行权限检查或其它操作"],
            "denied"    => ["无操作权限", "当前用户没有此项操作权限 [ OPR = %{1}% ]"],
            "jwterror"  => ["JWT 验证失败", "错误信息：%{1}%"],
            "uacpause"  => ["用户权限被暂停", "系统正在更新，暂时无法执行任何操作，请稍后再试"],
        ]
        
	];
}