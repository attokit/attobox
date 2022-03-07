<?php

/**
 * Attobox Framework / Module
 * Resource Module
 * Resource provide, build, get ...
 */

namespace Atto\Box;

use Atto\Box\resource\Mime;
use Atto\Box\resource\creator\Complie;
use Atto\Box\request\Url;
use Atto\Box\traits\staticCreate;

class Resource
{
    //引入trait
    use staticCreate;

    //资源类型
    public static $resTypes = [
        "remote",       //远程资源
        "build",        //通过 build 生成的纯文本文件，如合并多个 js 文件
        "export",       //通过调用 route 动态生成内容的文件
        "required",     //通过 require 本地 php 文件动态生成内容
        "local",        //真实存在的本地文件
        "complie",      //通过编译本地文件，生成的文件
    ];

    /**
     * 资源参数
     */

    //rawPath，原始调用路径，host/src/xxxx/xxxx.ext 中 src 之后的内容
    public $rawPath = "";

    //rawExt，原始调用输出 extension，即要输出的资源 extension
    public $rawExt = "";
    public $rawMime = "";

    //要输出资源的文件名，来自 rawPath
    public $rawBasename = "";
    public $rawFilename = "";

    //realPath，通过原始调用路径解析获得实际资源路径
    //可以是：远程 url，本地 php 文件，本地文件夹，本地文件
    public $realPath = "";

    //资源类型
    public $resType = "";

    //资源内容生成器
    public $creator = null;

    //资源内容
    public $content = null;

    //资源输出器
    public $exporter = null;

    //资源路径信息，从 rawPath 与 realPath 解析得到
    public $extension = "";
    public $dirname = "";
    public $basename = "";
    public $filename = "";

    //params，资源调用get参数
    public $params = [];

    /**
     * 构造
     */
    public function __construct($path = "", $params = [])
    {
        if (is_indexed($path)) $path = implode(DS, $path);
        $p = self::parse($path);
        if (is_null($p)) return null;
        foreach ($p as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = is_array($v) ? arr_extend($this->$k, $v) : $v;
            }
        }
        $this->rawMime = Mime::get($this->rawExt);
        $pinfo = pathinfo($this->rawPath);
        $this->rawBasename = $pinfo["basename"];
        $this->rawFilename = $pinfo["filename"];
        //params
        $params = is_notempty_arr($params) ? $params : [];
        if (!empty($_GET)) $params = arr_extend($params, $_GET);
        $this->params = arr_extend($this->params, $params);
        //creator
        $this->parseCreator();
        //exporter
        $this->parseExporter();
    }


    /**
     * 解析调用路径 rawPath（通常来自于url），返回生成的资源参数
     * 未找到资源返回 null
     * @return Array | null
     */
    public static function parse($path = "")
    {
        if (!is_notempty_str($path)) return null;
        $params = [];
        $rawPath = str_replace("/", DS, $path);
        
        if (strpos($rawPath, '.min.')) $params["min"] = "yes";

        if (file_exists($rawPath) || is_dir($rawPath)) {  //给出的path参数为真实存在的文件或路径，直接返回
            $resType = is_dir($rawPath) ? "build" : "local";
            $realPath = $rawPath;
            $rawExt = self::getExtFromPath($rawPath);
        } else {
            $rawPath = trim(str_replace("/", DS, $path), DS);
            $rawExt = self::getExtFromPath($rawPath);
            $rawArr = explode(DS, $rawPath);
            if (in_array(strtolower($rawArr[0]), ["http", "https"])) {   //远程文件
                $resType = "remote";
                $realPath = strtolower($rawArr[0])."://".implode("/", array_slice($rawArr, 1));
            } else if (strtolower($rawArr[0]) == "export") {    //通过调用本地 route 动态生成内容的文件
                $resType = "export";
                $temp = implode("/", array_slice($rawArr, 1));
                $info = pathinfo($temp);
                $realPath = Url::mk($info["dirname"]."/".$info["filename"])->full;
            } else {
                $realPath = path_find($rawPath);
                if (!is_null($realPath)) {  //本地真实存在文件
                    $resType = "local";
                } else {
                    if (str_has($rawPath, ".min.")) {   //min标记转换成  params["min"] = "yes"
                        $rawPath = str_replace(".min.", ".", $rawPath);
                        $params["min"] = "yes";
                    }
                    $realPath = path_find($rawPath, ["checkDir" => true]);
                    if (!is_null($realPath)) {
                        if (is_dir($realPath)) {    //通过 build 生成的纯文本文件
                            $resType = "build";
                        } else {    //本地真实存在的文件
                            $resType = "local";
                        }
                    } else {
                        $realPath = path_find($rawPath.EXT);
                        if (!is_null($realPath)) {  //通过require本地php文件动态生成内容
                            $resType = "required";
                        } else {
                            $realPath = Compile::getCompilePath($rawPath);
                            if (!is_null($realPath)) {  //通过编译本地文件，生成纯文本内容
                                $resType = "compile";
                            } else {
                                return null;
                            }
                        }
                    }
                }
            }
        }

        $rst = [
            "rawPath"   => $rawPath,
            "rawExt"    => $rawExt,
            "resType"   => $resType,
            "realPath"  => $realPath,
            "params"    => $params
        ];
        return $rst;
    }

    /**
     * 根据 resType 生成 creator 对象实例
     * @return Creator
     */
    protected function parseCreator()
    {
        $rtp = $this->resType;
        $cls = cls("resource/creator/".ucfirst($rtp));   //"\\CPHP\\resource\\creator\\".ucfirst($rtp);
        if (empty($cls) || !class_exists($cls)) {
            $cls = cls("resource/Creator");   //"\\CPHP\\resource\\Creator";
        }
        $this->creator = new $cls($this);
    }

    /**
     * 根据 rawExt 生成 exporter 对象实例
     * @return Exporter
     */
    protected function parseExporter()
    {
        $ext = $this->rawExt;
        //首先检查是否存在当前 ext 专用的 exporter
        $cls = cls("resource/exporter/".ucfirst($ext));   //"\\CPHP\\resource\\exporter\\".ucfirst($ext);
        if (empty($cls)) {
            $processableType = Mime::getProcessableType($ext);
            if (is_null($processableType)) {
                //不支持直接处理的文件类型，采用 数据流 输出，浏览器将解析为下载文件
                $cls = cls("resource/exporter/Stream");   //"\\CPHP\\resource\\exporter\\Stream";
            } else {
                //支持直接处理的文件类型，调用对应的 exporter 输出
                $cls = cls("resource/exporter/".ucfirst($processableType));   //"\\CPHP\\resource\\exporter\\".ucfirst($processableType);
            }
            if (!class_exists($cls)) {
                //如果 目标exporter 不存在，则调用 默认exporter 输出 
                $cls = cls("resource/Exporter"); //"\\CPHP\\resource\\Exporter";
            }
        }
        
        $this->exporter = new $cls($this);
    }

    /**
     * 输出文件
     * 跳过 Response，直接输出资源
     * @return void
     */
    public function export($params = [])
    {
        $this->creator->create();
        $this->exporter->export($params);
        exit;
    }


    /**
     * 输出资源 info
     */
    public function info()
    {
        return [
            "rawPath"   => $this->rawPath,
            "rawExt"    => $this->rawExt,
            "resType"   => $this->resType,
            "realPath"  => $this->realPath,
            "params"    => $this->params,
            "rawBasename"    => $this->rawBasename,
            "rawFilename"    => $this->rawFilename

        ];
    }




    /**
     * tools
     */

    /**
     * 根据 rawPath 或 url 获取文件 ext
     * foo/bar.js?version=3.0  => js
     * @return String | null
     */
    public static function getExtFromPath($path = "")
    {
        if (!is_notempty_str($path)) return null;
        if (str_has($path, "?")) {
            $pstr = explode("?", $path)[0];
        } else {
            $pstr = $path;
        }
        $pathinfo = pathinfo($pstr);
        return isset($pathinfo["extension"]) ? strtolower($pathinfo["extension"]) : null;
    }

    /**
     * file_exists，支持远程文件
     * @return Boolean
     */
    public static function exists($file = "")
    {
        if (is_remote($file)) {
            $hds = get_headers($file);
            return in_array("HTTP/1.1 200 OK", $hds);
        } else {
            return file_exists($file);
        }
    }

    /**
     * 计算调用路径，  realPath 转换为 relative
     * @return String
     */
    public static function toUri($realPath = "")
    {
        $realPath = str_replace("/", DS, $realPath);
        $relative = path_relative($realPath);
        if (is_null($relative)) return "";
        return $relative;
    }

    /**
     * 计算调用路径，realPath 转换为 /src/foo/bar...
     */
    public static function toSrcUrl($realpath = "")
    {
        $relative = self::toUri($realpath);
        if ($relative=="") return "";
        $rarr = explode(DS, $relative);
        $spc = explode("/", str_replace(",","/",ASSET_DIRS));
        $spc = array_merge($spc,["app","cphp"]);
        $nrarr = array_diff($rarr, $spc);
        $src = implode("/", $nrarr);
        $src = "/src".($src=="" ? "" : "/".$src);
        return $src;
    }




}