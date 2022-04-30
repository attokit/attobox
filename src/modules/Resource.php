<?php

/**
 * Attobox Framework / Module
 * Resource Module
 * Resource provide, build, get ...
 */

namespace Atto\Box;

use Atto\Box\resource\Mime;
use Atto\Box\resource\Builder;
use Atto\Box\resource\Compiler;
use Atto\Box\request\Url;
use Atto\Box\request\Curl;
use Atto\Box\Response;

class Resource
{
    //引入trait
    //use staticCreate;

    //资源类型
    public static $resTypes = [
        "remote",       //远程资源
        "build",        //通过 build 生成的纯文本文件，如合并多个 js 文件
        "export",       //通过调用 route 动态生成内容的文件
        "required",     //通过 require 本地 php 文件动态生成内容
        "local",        //真实存在的本地文件
        "compile",      //通过编译本地文件，生成的文件
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

    //资源处理对象，根据 rawExt 指定
    //public $processor = null;

    //资源内容生成器
    //public $creator = null;

    //资源内容
    public $content = null;

    //资源输出器
    //public $exporter = null;

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
    /*public function __construct($path = "", $params = [])
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
    }*/
    public function __construct($opt = [])
    {
        if (empty($opt) || !is_array($opt) || !is_associate($opt)) return null;
        foreach ($opt as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = is_array($v) ? arr_extend($this->$k, $v) : $v;
            }
        }
        $this->rawMime = Mime::get($this->rawExt);
        $pinfo = pathinfo($this->rawPath);
        $this->rawBasename = $pinfo["basename"];
        $this->rawFilename = $pinfo["filename"];
    }

    /**
     * get resource content by $resType
     * if necessary, derived class should override this method
     * @return String $this->content
     */
    protected function getContent()
    {
        $rtp = $this->resType;
        $m = "get".ucfirst($rtp)."Content";
        if (method_exists($this, $m)) {
            $this->$m();
        }
        return $this->content;
    }

    /**
     * get[$resType]Content methods
     * if necessary, derived class should override these methods
     * @return void
     */
    protected function getRemoteContent() {
        $this->content = Curl::get($this->realPath);
    }
    protected function getBuildContent() {
        $builder = new Builder($this);
        $builder->build();
    }
    protected function getExportContent() {
        $this->content = Curl::get($this->realPath);
    }
    protected function getRequiredContent() {
        //@ob_start();
        require($this->realPath); 
        $this->content = ob_get_contents(); 
        ob_clean();
    }
    protected function getLocalContent() {
        $ext = $this->rawExt;
        if (Mime::isPlain($ext)) {
            $this->content = file_get_contents($this->realPath);
            //var_dump($this->content);
        }
    }
    protected function getCompileContent() {
        $compiler = new Compiler($this);
        $compiler->compile();
    }



    /**
     * 解析调用路径 rawPath（通常来自于url），返回生成的资源参数
     * 未找到资源返回 null
     * @return Array | null
     */
    protected static function parse($path = "")
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
                            $realPath = Compiler::getCompilePath($rawPath);
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

        if (is_null($rawExt)) return null;

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
     * 外部创建 Resource 入口方法
     * Resource::create($path, $params)
     * @param String $path      call path of resource (usually from url)
     * @param Array $param      resource process params
     * @return Resource instance or null
     */
    public static function create($path = "", $params = [])
    {
        if (is_indexed($path)) $path = implode(DS, $path);
        $p = self::parse($path);
        if (is_null($p)) return null;
        $params = is_notempty_arr($params) ? $params : [];
        if (!empty($_GET)) $params = arr_extend($params, $_GET);
        if (!isset($p["params"]) || !is_array($p["params"])) $p["params"] = [];
        $p["params"] = arr_extend($p["params"], $params);
        //find specific resource class
        $cls = self::resCls($p);
        if (is_null($cls)) return null;
        $res = new $cls($p);
        return $res;
    }

    /**
     * get resource class by parsed options
     * @param Array $option     option array returned by self::parse()
     * @return String full class name
     */
    protected static function resCls($option = [])
    {
        if (!is_notempty_arr($option)) return cls("Resource");
        $clss = [];
        $ext = $option["rawExt"];
        $clss[] = "resource/".ucfirst($ext);
        $processableType = Mime::getProcessableType($ext);
        if (!is_null($processableType)) {
            $clss[] = "resource/".ucfirst($processableType);
        } else {
            //$clss[] = "resource/Stream";
            $clss[] = "resource/Download";
        }
        $clss[] = "Resource";
        return cls(...$clss);
    }



    /**
     * export resource directly
     * if necessary, derived class should override this method
     * @return void exit
     */
    public function export($params = [])
    {
        //var_dump(Mime::isPlain($this->rawExt));exit;
        //get resource content
        $this->getContent();
        
        //sent header
        //$this->sentHeader();
        Mime::header($this->rawExt, $this->rawBasename);
        Response::headersSent();
        
        //echo
        echo $this->content;
        exit;

        /*
        if (is_null($this->creator) || is_null($this->exporter)) {
            Response::code(404);
        } else {
            $this->creator->create();
            $this->exporter->export($params);
        }
        exit;
        */
    }

    /**
     * sent header before export echo
     * @param String $ext       if export resource in different extension
     * @return Resource $this
     */
    protected function sentHeader($ext = null)
    {
        if (!is_notempty_str($ext) && $ext != $this->rawExt) {
            $basename = $this->rawFilename.".".$ext;
        } else {
            $ext = $this->rawExt;;
            $basename = $this->rawBasename;
        }
        Mime::header($ext, $basename);
        Response::headersSent();
        return $this;
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