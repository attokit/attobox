<?php

namespace Atto\Box\app;

use Atto\Box\App;
use Atto\Box\Request;
use Atto\Box\request\Curl;
use Atto\Box\Response;
use Atto\Box\Resource;
use Atto\Box\resource\Mime;

/**
 * 库资源输出规则
 *  1   必须在 URI 提供版本号，或 @latest，或 @ 表示默认版本
 *          https://io.cgy.design/vue/2.7.9/common/dev[.min.js]?esm=yes
 *          https://io.cgy.design/vue/@/common/dev[.min.js]?esm=yes
 *          https://io.cgy.design/vue/@latest?dev=yes&esm=no
 *          https://io.cgy.design/vue/@/ui/element-ui/@latest/element-ui-dark.css
 *  2   只有 js/css 文件可以省略后缀名
 *  3   在特定路径下可以通过 buildFoobar 方法生成代码，仍必须在 URI 提供版本字符 @|@latest
 *          https://io.cgy.design/vue/@?esm=yes&dev=yes                build()
 *          https://io.cgy.design/vue/@/themes/base/createscss         buildThemes()
 *          https://io.cgy.design/vue/@/components/base                buildComponents()
 *          https://io.cgy.design/vue/@/components/base/cv-alert       buildComponents()
 *          https://io.cgy.design/vue/@/components/base/cv-alert.vue   不通过 build 方法，因为存在真实文件，vue 后缀不能省略，因此不提供后缀就要通过 build 方法
 *          https://io.cgy.design/vue/@/plugin/base                    buildPlugin()
 *          https://io.cgy.design/vue/@/plugin/base/global[.js]        不通过 build 方法，因为能够直接找到真实文件
 *          
 * 
 */
class LibApp extends App
{
    //默认 host
    public $host = "https://io.cgy.design";
    //默认输出
    public $defaultVersion = "";
    public $defaultFile = "";
    //库内部文件的默认版本、文件名，数组结构必须与实际路径层级一致
    public $defaults = [
        /*"2.7.9" => [
            "ui" => [
                "element-ui" => [
                    "version" => "2.15.14",
                    "file" => "",

                    "2.15.14" => [
                        "再下一级" => [
                            ...
                        ]
                    ]
                ]
            ]
        ]*/
    ];
    //允许省略的后缀名
    public $skipExts = [".min.js",".js",".min.css",".css"];
    //默认输出参数 子类覆盖
    public $queryParams = [
        "esm" => "yes",
        "dev" => "no",
        "plugin" => [],
        "use" => [],
        "theme" => "base"
    ];



    //默认方式输出库
    public function defaultRoute(...$args)
    {
        //处理 @|@latest 转换真实版本号
        $args = $this->fixVersion($args);
        //var_dump($args);exit;
        //查找是否指向真实文件
        if (($fp = $this->inAssets($args))!==false) {
            //URI 指定的文件真实存在，直接 Resource 输出
            return $this->resEko($fp);
        } else {
            //检查是否后缀名不正确，尝试调整文件后缀，只有 js/css 可以省略后缀名
            // 1  首先删除可能存在的文件名
            $args = $this->skipExtension($args);
            // 2  然后尝试不同的文件后缀名
            if (($fp = $this->findFileByExts($args))!==false) {
                //找到真实存在的文件，调用 Resource 输出，省略后缀名时，默认输出 min 
                return $this->resEko($fp, ["min"=>"yes"]);
            } else {
                //仍未找到真实文件，尝试调用 buildFoobar 方法，build 文件
                // 3  去除 $args 中的 version 元素
                $nargs = [];
                for ($i=0;$i<count($args);$i++) {
                    if (!$this->isVersionStr($args[$i])) {
                        $nargs[] = $args[$i];
                    }
                }
                // 4  从 $args 最长路径开始，合并成为 方法名，检查是否存在这个方法
                $m = "build".strtocamel(implode("-",$nargs), true);
                //var_dump($m);exit;
                if (!method_exists($this, $m)) {
                    $m = null;
                    for ($i=1;$i<count($nargs);$i++) {
                        $slice = $i*-1;
                        $sargs = array_slice($nargs, 0, $slice);
                        $m = "build".strtocamel(implode("-",$sargs), true);
                        if (!method_exists($this, $m)) {
                            $m = null;
                        } else {
                            break;
                        }
                    }
                }
                if (!is_null($m)) {
                    //找到了 build 方法，开始 build 文件
                    $exps = $this->getQueryParams();   //$_GET 参数
                    return $this->$m($args, $exps);
                } else {
                    //没有找到 build 方法，尝试查找默认文件
                    if (($fp = $this->findDefaultFile($args))!==false) {
                        //有定义 默认文件，直接输出
                        return $this->resEko($fp);
                    }
                }
                
            }
        }

        //检查是否存在 page 文件
        $pg = $this->path("page/".$args[0].EXT);
        if (file_exists($pg)) {
            Response::page($pg);
            exit;
        }

        //没有任何结果，返回 404
        Response::code(404);
        exit;
    }

    //进入测试页，page/test.php 如果不存在则生成一个网页，自动引入库文件 esm 版本
    public function tester()
    {
        $pg = $this->path("page/test".EXT);
        if (file_exists($pg)) Response::page($pg);
        $h = [];
        $h[] = "<!DOCTYPE html>";
        $h[] = "<html lang=\"zh-CN\">";
        $h[] = "<head>";
        $h[] = "<meta charset=\"utf-8\">";
        $h[] = "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0\">";
        $h[] = "<title>".$this->intr." - Tester</title>";
        $h[] = "<link rel=\"icon\" href=\"/icon/cgy-cgydesign-favicon.svg\">";
        $h[] = "</head>";
        $h[] = "<body>";
        $h[] = "请在 console 调试";
        $h[] = "<script type=\"module\" src=\"/".strtolower($this->name)."/@?esm=yes\"></script>";
        $h[] = "</body>";
        $h[] = "</html>";
        Response::html(implode("\r\n",$h));
    }

    //doc 文档
    public function doc()
    {
        $pg = $this->path("page/doc.php");
        //$loginpg = $this->path("page/login.php");
        if (file_exists($pg)) {
            Response::page($pg);
        }
        Response::code(404);
    }



    /**
     * tools
     */

    //处理 URI 中给出的版本号 或 @ 或 @latest
    //将 URI 中的 @ 替换成默认版本号，@latest 替换成 最新版本号
    protected function fixVersion($args=[], $fkey=false)
    {
        if (empty($args)) return $args;
        if ($fkey===false) {
            $fkey = $this->findVersionKey($args);
            if ($fkey===false) return $args;
        }
        $key = $fkey["key"];
        $idx = $fkey["idx"];
        $path = $idx<=0 ? [] : array_slice($args, 0, $idx);
        if ($key=="@") {
            $ver = empty($path) ? $this->defaultVersion : arr_item($this->defaults, implode("/", $path));
            if (is_notempty_arr($ver) && isset($ver["version"])) $ver = $ver["version"];
            if (empty($ver)) Response::code(404);
        } else if ($key=="@latest") {
            $vers = $this->getAllVersions($path);
            if (empty($vers)) Response::code(404);
            $ver = $vers[0];
        }
        array_splice($args, $idx, 1, $ver);
        $fkey = $this->findVersionKey($args);
        if ($fkey!==false) return $this->fixVersion($args, $fkey);
        return $args;
    }

    //检查 URI 中是否包含 @,@latest 字符，并返回 哪个字符排在最前面，未找到则返回 false
    protected function findVersionKey($args)
    {
        $keys = ["@","@latest"];
        $idx = -1;
        $key = "";
        $has = false;
        for ($i=0;$i<count($keys);$i++) {
            $ki = $keys[$i];
            $kidx = array_search($ki,$args);
            if ($kidx===false) continue;
            $has = true;
            if ($idx<0 || $idx>$kidx) {
                $idx = $kidx;
                $key = $ki;
            }
        }
        if (!$has) return false;
        return [
            "key" => $key,
            "idx" => $idx
        ];
    }

    //查找当前库中所有可用版本，并按 desc 排列
    protected function getAllVersions($path=[])
    {  
        array_unshift($path, "assets");
        $dir = $this->path(implode("/",$path));
        if (!is_dir($dir)) return [];
        $vers = [];
        $dh = opendir($dir);
        while (($fn = readdir($dh))!==false) {
            if ($fn=="." || $fn==".." || !is_dir($dir.DS.$fn)) continue;
            if (!is_numeric(str_replace(".","",$fn))) continue;
            $vers[] = $fn;
        }
        closedir($dh);
        if (empty($vers)) return [];
        return arr_sort_version($vers, "desc");
    }

    //判断字符串是否 版本号 格式，like：10.20.30
    protected function isVersionStr($str=null)
    {
        return is_notempty_str($str) && is_numeric(str_replace(".","",$str));
    }

    //判断在 URI 中是否存在 版本号
    protected function hasVersionStr($args=[])
    {
        $has = false;
        for ($i=0;$i<count($args);$i++) {
            if ($this->isVersionStr($args[$i])) {
                $has = true;
                break;
            }
        }
        return $has;
    }

    //生成 assets/... 文件路径，不论文件是否存在
    protected function getAssetsPath($args=[])
    {
        array_unshift($args, "assets");
        return $this->path(implode("/",$args));
    }

    //判断 路径数组 是否在 assets 文件夹中真实存在，不存在则返回 false，存在则返回文件路径
    protected function inAssets($args=[])
    {
        $fp = $this->getAssetsPath($args);
        return (is_file($fp) && file_exists($fp)) ? $fp : false;
    }

    //在 defaults 中查找预先定义好的 默认文件，没找到或找到了但是文件不真实存在返回 false，否则返回 文件路径
    protected function findDefaultFile($args=[])
    {
        //如果数组最后一个元素是版本号，删除之
        if ($this->isVersionStr(array_slice($args, -1)[0])) {
            $ver = array_pop($args);
        } else {
            $ver = $this->defaultVersion;
        }
        //如果空数组，读取 defaultFile
        if (empty($args)) {
            $file = $this->defaultFile;
        } else {
            $dft = arr_item($this->defaults, implode("/", $args));
            if (empty($dft) || !isset($dft["file"])) {
                $file = null;
            }
            $file = $dft["file"];
        }
        if (empty($file)) return false;
        array_push($args, $ver, $file);
        return $this->inAssets($args);
    }

    //查找不同文件后缀，是否存在真实文件，存在返回文件路径，否则返回 false
    protected function findFileByExts($args=[], $exts=[])
    {
        $exts = empty($exts) ? $this->skipExts : $exts;
        $fp = null;
        for ($i=0;$i<count($exts);$i++) {
            $ext = $exts[$i];
            $fn = array_slice($args,-1)[0].$ext;
            $nas = array_merge(array_slice($args,0,-1), [$fn]);
            if (($fi = $this->inAssets($nas))!==false) {
                $fp = $fi;
                break;
            }
        }
        return is_null($fp) ? false : $fp;
    }

    //从 $args 数组的文件名中删掉可能存在的后缀名
    protected function skipExtension($args=[], $exts=[])
    {
        $exts = empty($exts) ? $this->skipExts : $exts;
        //$args 参数最后一个应为文件名
        $fn = array_slice($args, -1)[0];
        for ($i=0;$i<count($exts);$i++) {
            $ext = $exts[$i];
            $fn = str_replace($ext, "", $fn);
        }
        return array_merge(array_slice($args,0,-1), [$fn]);
    }

    //获取输出参数
    protected function getQueryParams($defaultParams=[])
    {
        $dps = empty($defaultParams) ? $this->queryParams : $defaultParams;
        $exps = [];
        foreach ($dps as $ek => $ev) {
            if (is_array($ev)) {
                $gt = Request::get($ek, "");
                if ($gt!="") {
                    $param = array_merge($ev, explode(",", $gt));
                } else {
                    $param = $ev;
                }
            } else {
                $param = Request::get($ek, $ev);
            }
            $exps[$ek] = $param;
        }
        return $exps;
    }

    //手动输出 js/css/json/scss
    protected function eko($cnt, $ext="js")
    {
        Mime::header($ext);
        Response::headersSent();
        echo $cnt;
        exit;
    }

    //调用 Resource 输出文件
    protected function resEko($file, $params = [])
    {
        //带 min 后缀的默认压缩输出
        if (strpos($file, ".min.")!==false) {
            $params = arr_extend([
                "min" => "yes"
            ], $params);
        }
        $fi = pathinfo($file);
        //vue 文件默认输出为 JS
        if ($fi["extension"]=="vue") {
            $params = arr_extend([
                "export" => "js",
                "name" => $fi["filename"]
            ], $params);
        }
        //输出
        $res = Resource::create($file, $params);
        if (empty($res)) Response::code(404);
        $res->export();
        exit;
    }

    //针对默认非 esm 的 js 库，增加 import/export 代码段，并对 js 库原生代码进行自定义的 handle(code) 处理
    protected function esmEko($file, $handle = null)
    {
        $fi = pathinfo($file);
        $dir = $fi["dirname"];
        $fn = str_replace(".min","", $fi["filename"]);
        $inpf = $dir.DS.$fn.".import.js";
        $expf = $dir.DS.$fn.".export.js";
        $js = [];
        if (file_exists($inpf)) $js[] = file_get_contents($inpf);
        $fcnt = file_get_contents($file);
        if (is_callable($handle)) {
            //对库原始代码执行处理，通常是 替换 this 为 self
            $js[] = $handle($fcnt);
        } else {
            $js[] = $fcnt;
        }
        if (file_exists($expf)) $js[] = file_get_contents($expf);
        return $this->eko(implode("\r\n", $js), "js");
    }

    //读取 lib.cgy.design 中的其他库的文件内容，用于合并代码
    protected function getLibCnt($lib, ...$args)
    {
        $u = $this->host."/".$lib;
        if (!empty($args)) {
            $u .= "/".implode("/", $args);
        }
        $cnt = Curl::get($u, "ssl");
        return $cnt;
    }

    //加载兄弟库
    protected function getSibling($lib, ...$args)
    {
        $u = $this->host."/".$lib;
        if (!empty($args)) {
            $u .= "/".implode("/", $args);
        }
        $cnt = Curl::get($u, "ssl");
        return $cnt;
    }

    /**
     * 找出路径下版本号最高的文件夹名
     */
    /*protected function getLatestVersionDir($dir)
    {
        if (!is_dir($dir)) return null;
        $ns = [];
        $dh = opendir($dir);
        while (($n = readdir($dh))!==false) {
            if ($n=="." || $n==".." || !is_dir($dir.DS.$n)) continue;
            if (!is_numeric(str_replace(".","",$n))) continue;
            $ns[] = $n;
        }
        closedir($dh);
        if (empty($ns)) return null;
        $ns = arr_sort_version($ns, "desc");
        return $ns[0];
    }*/

    /**
     * 去掉 js 中的 use strict
     */
    protected function stripUseStrict($js)
    {
        $js = str_replace("\"use strict\";", "", $js);
        return $js;
    }
    

}