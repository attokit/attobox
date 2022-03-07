<?php

/**
 * Attobox Framework / Request
 */

namespace Atto\Box;

use Atto\Box\Request\Url;
use Atto\Box\traits\staticCurrent;

class Request
{
    //引入trait
    use staticCurrent;

    /**
     * current
     */
    public static $current = null;

    //url
    public $url = null;

    //request参数
    public $headers = [];
    public $method = "";
    public $https = false;
    public $isAjax = false;
    public $lang = "zh-CN";
    public $pause = false;
    public $debug = false;
    public $gets = [];
    public $posts = [];
    public $inputs = [];

    //构造
    public function __construct()
    {
        $this->url = Url::current();
        $this->headers = self::getHeaders();
        $this->method = $_SERVER["REQUEST_METHOD"];
        $this->time = $_SERVER["REQUEST_TIME"];
        $this->https = $this->url->protocol == "https";
        $this->isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        //lang
        $this->lang = self::get("lang",EXPORT_LANG);
        //通过设置 WEB_PAUSE 暂停网站（src资源仍可以访问）
        $this->pause = WEB_PAUSE;
        //debug标记
        $this->debug = WEB_DEBUG;
    }


    /**
     * static
     */

    /**
     * 获取当前url实例
     * 
     * public static function current()
     * 通过 traits 引入
     */

    public static function getHeaders()
    {
        $hds = [];
        if (function_exists("getallheaders")) {     //Apache环境下
            $hds = getallheaders();
        } else {
            foreach ($_SERVER as $k => $v) {
                if (substr($k, 0, 5) == "HTTP_") {
                    $hds[str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($k, 5)))))] = $v;
                }
            }
        }
        return $hds;
    }

    //$_GET
    public static function get($key = [], $val = null)
    {
        if (is_array($key)) {
            if (empty($key)) return $_GET;
            $p = array();
            foreach ($key as $k => $v) {
                $p[$k] = self::get($k, $v);
            }
            return $p;
        }else{
            return isset($_GET[$key]) ? $_GET[$key] : $val;
        }
    }

    //$_POST
    public static function post($key = [], $val = null)
    {
        if (is_array($key)) {
            if (empty($key)) return $_POST;
            $p = array();
            foreach ($key as $k => $v) {
                $p[$k] = self::post($k,$v);
            }
            return $p;
        }else{
            return isset($_POST[$key]) ? $_POST[$key] : $val;
        }
    }

    //php://input，输入全部转为json，返回array
    public static function input($in = "json")
    {
        $input = file_get_contents("php://input");
        if (empty($input)) {
            $input = session_get("_php_input_", null);
            if (is_null($input)) return null;
            session_del("_php_input_");
        }
        $output = null;
        switch($in){
            case "json" :
                $output = j2a($input);
                break;
            case "xml" :
                $output = x2a($input);
                break;
            case "url" :
                $output = u2a($input);
                break;
            default :
                $output = arr($input);
                break;
        }
        return $output;
    }
    

}