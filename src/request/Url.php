<?php

/**
 * Attobox Framework / URL processor
 */

namespace Atto\Box\request;

use Atto\Box\traits\staticCurrent;

class Url
{
    //引入trait
    use staticCurrent;
    
    /**
     * current
     */
    public static $current = null;

    /**
     * URL参数
     */
    public $full = "";
    public $protocol = "http";
    public $host = "";
    public $domain = "";
    public $uri = "";
    public $path = [];
    public $query = [];

    /**
     * 构造
     */
    public function __construct($url = "")
    {
        $this->protocol = self::protocol($url);
        $this->host = self::host($url);
        $this->domain = self::domain($url);
        $this->uri = self::uristr($url);
        $uri = self::uri($url);
        $this->path = $uri["path"];
        $this->query = $uri["query"];
        $this->full = $this->domain.$this->uri;
    }

    /**
     * 合并生成新url实例
     */
    public function merge($url = "")
    {
        return self::mk($url, $this);
    }
    


    /**
     * static
     */

    public static function protocol($url = "")
    {
        if (!self::legal($url)) {
            $svr = $_SERVER;
            $protocol = $svr["SERVER_PROTOCOL"];
            if ($protocol == "HTTP/1.1") {
                if (!isset($svr["HTTPS"]) || $svr["HTTPS"] == "off" || empty($svr["HTTPS"])) {
                    return "http";
                }
            }
            return "https";
        } else {
            if (strpos(strtolower($url), "http://") !== false) return "http";
            if (strpos(strtolower($url), "https://") !== false) return "https";
            if (strpos($url, "://") !== false) {
                $pt = explode("://", $url);
                return $pt[0];
            }
            //return "http";
            return self::protocol();
        }
    }

    public static function host($url = "")
    {
        if (!self::legal($url)) {
            return $_SERVER["HTTP_HOST"];
        } else {
            $ua = explode("://", $url);
            if (count($ua) <= 1) return $_SERVER["HTTP_HOST"];
            $ua = explode("/", $ua[1]);
            return $ua[0];
        }
    }

    public static function domain($url = "")
    {
        return self::protocol($url)."://".self::host($url);
    }

    public static function uristr($url = "")
    {
        if (!self::legal($url)) return urldecode($_SERVER["REQUEST_URI"]);  //$_SERVER["REQUEST_URI"]
        /*add*/$url = urldecode($url);
        return str_replace(self::domain($url), "", $url);
    }

    /**
     * 解析uri
     */
    public static function uri($url = "")
    {
        //$uristr = self::legal($url) || empty($url) ? self::uristr($url) : $url;
        $uristr = self::legal($url) || empty($url) ? self::uristr($url) : urldecode($url);
        $ua = strpos($uristr, "?") !== false ? explode("?", $uristr) : [ $uristr ];
        $ups = ltrim($ua[0], "/");
        $upath = !empty($ups) ? explode("/", $ups) : [];
        $qs = count($ua) < 2 ? "" : $ua[1];
        $q = empty($qs) ? [] : u2a($qs);
        return [
            "uri"       => $uristr,
            "path"      => $upath,
            "query"     => $q,
            "pathstr"   => $ua[0],
            "querystr"  => $qs
        ];
    }

    /**
     * 获取当前url实例
     * 
     * public static function current()
     * 通过 traits 引入
     */

    public static function legal($url = "")
    {
        if (is_string($url) && !empty($url)) {
            if (strpos($url, "://") !== false) return true;
        }
        return false;
    }

    /**
     * 根据输入构造新url
     * http://xxxx      返回自身
     * /xxxx/xxxx?qs    返回 protocol() + :// + host() + /xxxx/xxxx
     * ../../xxxx/xxxx?qs     返回 current() + /../../xxxx/xxxx
     * query通过array方式合并
     * 返回新的 Url实例
     */
    public static function mk($url = "", $cu = null)
    {
        if (self::legal($url)) return new Url($url);
        $cu = empty($cu) || !($cu instanceof Url) ? self::current() : $cu;
        if (empty($url)) return $cu;
        $uri = self::uri($url);
        $uri["query"] = arr_extend($cu->query, $uri["query"]);
        $qs = empty($uri["query"]) ? "" : "?".a2u($uri["query"]);
        if (str_begin($url, "/")) {
            $nu = $cu->domain.$uri["pathstr"].$qs;
        } else {
            $uri["path"] = array_merge($cu->path, $uri["path"]);
            $nu = $cu->domain."/".path_up(implode("/", $uri["path"]), "/").$qs;
        }
        return new Url($nu);
    }

}