<?php

/**
 * Attobox Framework / Request
 */

namespace Atto\Box;

use Atto\Box\request\Url;
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

    //uac
    public $uac = null;

    //app
    public $app = null;

    //request参数
    public $headers = [];
    public $method = "";
    public $https = false;
    public $isAjax = false;
    public $referer = "";
    public $lang = "zh-CN";
    public $pause = false;
    public $debug = false;
    public $gets = [];
    public $posts = [];
    public $inputs = [];

    //解析 request 后得到的 response.headers 初始值
    public $responseHeaders = [
        
    ];

    //构造
    public function __construct()
    {
        $this->url = Url::current();
        $this->headers = self::getHeaders();
        $this->method = $_SERVER["REQUEST_METHOD"];
        $this->time = $_SERVER["REQUEST_TIME"];
        $this->https = $this->url->protocol == "https";
        //$this->isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        $this->isAjax = $this->isAjaxRequest();
        $this->referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";
        //lang
        $this->lang = self::get("lang",EXPORT_LANG);
        //通过设置 WEB_PAUSE 暂停网站（src资源仍可以访问）
        $this->pause = WEB_PAUSE;
        //debug标记
        $this->debug = WEB_DEBUG;

        //处理跨域
        $this->handleCors();
    }

    /**
     * 处理 AJAX 跨域
     * 入口
     */
    protected function handleCors()
    {
        //如果 request.method==OPTIONS 预检请求，直接响应
        $this->responseOptionsRequest();
        //检查 origin
        $this->checkRequestOrigin();

        //if ()
    }

    /**
     * 处理 AJAX 跨域
     * 判断是否 ajax request
     * @return Bool
     */
    protected function isAjaxRequest()
    {
        $accept = $this->headers["Accept"] ?? null;
        $xRequestedWith = $this->headers["x-Requested-With"] ?? null;
        if (!empty($xRequestedWith) && strpos(strtolower($xRequestedWith), "xmlhttprequest")!==false) return true;
        if (!empty($accept) && strpos($accept, "application/json")!==false) return true;
        return false;
    }

    /**
     * 处理 AJAX 跨域
     * 检查 request.origin 与 WEB_DOMAIN_AJAXALLOWED 比较
     * @return Bool
     */
    protected function checkRequestOrigin()
    {
        $origin = $this->headers["Origin"] ?? "*";
        $domains = explode(",", WEB_DOMAIN_AJAXALLOWED);
        $allowed = false;
        for ($i=0;$i<count($domains);$i++) {
            $dmi = trim($domains[$i]);
            if ($origin==$dmi || strpos($origin, $dmi)!==false) {
                $allowed = true;
                break;
            }
        }
        if ($allowed) {
            $this->responseHeaders["Access-Control-Allow-Origin"] = $origin;
        } else {
            $this->responseHeaders["Access-Control-Allow-Origin"] = $this->url->domain;
            //$this->responseHeaders["Access-Control-Allow-Origin"] = "*";
        }
        return $allowed;
    }

    /**
     * 处理 AJAX 跨域
     * 响应预检 request.method == OPTIONS
     */
    protected function responseOptionsRequest()
    {
        if ($this->method=="OPTIONS") {
            $allowed = $this->checkRequestOrigin();
            if ($allowed) {
                $method = $this->headers["Access-Control-Request-Method"] ?? "*";
                $hds = $this->headers["Access-Control-Request-Headers"] ?? "GET,POST";
                $this->responseHeaders["Access-Control-Allow-Methods"] = $method;
                $this->responseHeaders["Access-Control-Allow-Headers"] = $hds;
            }
            //直接响应 OPTIONS 请求
            $hds = $this->responseHeaders;
            foreach ($hds as $k => $v) {
                header("$k: $v");
            }
            exit;
        }
    }

    /**
     * 
     */
    public function ttt()
    {
        $url = $this->url;
        $u = $url->full;
        if ($u=="https://wx.cgy.design/qyspkj/scaninput/E9999?format=json") {
            header("Content-Type: application/json; charset=utf-8",);
            header("Access-Control-Allow-Origin: *");
            $s = [
                "foo" => "bar"
            ];
            echo a2j($s);
            exit;
        }
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

    /**
     * getHeaders 获取请求头
     */
    public static function getHeaders()
    {
        $hds = [];
        //if (function_exists("getallheaders")) {     //Apache环境下
        if (function_exists("apache_request_headers")) {     //Apache环境下
            //$hds = getallheaders();
            $hds = apache_request_headers();
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

    //$_FILES
    public static function files($fieldname = [])
    {
        if (is_notempty_str($fieldname)) {
            if (!isset($_FILES[$fieldname])) return [];
            $fall = $_FILES[$fieldname];
            $fs = [];
            if (is_indexed($fall["name"])) {
                $ks = array_keys($fall);
                $ci = count($fall["name"]);
                for ($i=0;$i<$ci;$i++) {
                    $fs[$i] = [];
                    foreach ($ks as $ki => $k) {
                        $fs[$i][$k] = $fall[$k][$i];
                    }
                }
            } else {
                $fs[] = $fall;
            }
            return $fs;
        }
        //if (is_indexed($fieldname) && !empty($fieldname)) {
        if (is_notempty_arr($fieldname)) {
            $fds = $fieldname;
            $fs = [];
            for ($i=0; $i<count($fds); $i++) {
                $fsi = self::files($fds[$i]);
                if (!empty($fsi)) {
                    $fs = array_merge($fs, $fsi);
                }
            }
            return $fs;
        }
        $fs = [];
        foreach ($_FILES as $fdn => $fdo) {
            $fsi = self::files($fdn);
            if (!empty($fsi)) {
                $fs = array_merge($fs, $fsi);
            }
        }
        return $fs;
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