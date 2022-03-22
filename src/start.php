<?php

/**
 * Attobox Framework / Entrance file
 * /vendor/attokit/attobox/src/start.php
 * 
 * Index page must require this page.
 */

/**
 * global setting
 */

@error_reporting(-1);	//0/-1 = 关闭/开启
ini_set("display_errors", "1");
@date_default_timezone_set("Asia/Shanghai");
@ob_start();
@session_start();


/**
 * private constant
 */
define("VERSION", "0.4.0");
define("EXT", ".php");
define("DS", DIRECTORY_SEPARATOR);
define("IS_CLI", PHP_SAPI == "cli" ? true : false);
define("IS_WIN", strpos(PHP_OS, "WIN") !== false);

//default namespace prefix
define("NS", "\\Atto\\Box\\");

//目录
define("PRE_PATH", __DIR__ . DS . ".." . DS . ".." . DS . ".." . DS . "..");
define("VENDOR_PATH", PRE_PATH . DS . "vendor");
define("ATTO_PATH", VENDOR_PATH . DS . "attokit");
define("BOX_PATH", VENDOR_PATH . DS . "attokit" . DS . "attobox" . DS . "src");
define("MODULE_PATH", BOX_PATH . DS . "modules");



/**
 * composer autoload
 */
require_once(VENDOR_PATH . DS . "autoload.php");
