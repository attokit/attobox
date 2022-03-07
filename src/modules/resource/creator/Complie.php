<?php

/**
 * CPHP框架  Resource 模块
 * 资源内容生成器 creator
 * 本地资源，需要编译生成内容
 * 支持的编译格式：
 *      scss、sass  -> css
 *      ts          -> js
 */

namespace CPHP\resource\creator;

use CPHP\resource\Creator;
use CPHP\resource\Mime;

use ScssPhp\ScssPhp\Compiler as scssCompiler;
use ScssPhp\ScssPhp\OutputStyle as scssOutputStyle;

class Complie extends Creator
{
    public static $extension = [
        "css" => ["scss", "sass"],
        "js" => ["ts"]
    ];

    //根据资源的 Mime::processableType 读取内容，保存到content
    public function create()
    {
        $f = $this->resource->realPath;
        $cext = strtolower(pathinfo($f)["extension"]);
        $m = "compile".ucfirst($cext);
        if (method_exists($this, $m)) {
            $this->resource->content = $this->$m();
        } else {
            $this->resource->content = "";
        }
    }

    //判断输出的内容是否压缩
    private function isCompressed()
    {
        $p = $this->resource->params;
        return isset($p["min"]) && $p["min"] == "yes" ? true : false;
    }



    /*
     *  compiler
     */

    //scss
    private function compileScss()
    {
        $f = $this->resource->realPath;
        $compiler = new scssCompiler();
        $outputStyle = $this->isCompressed() ? scssOutputStyle::COMPRESSED : scssOutputStyle::EXPANDED;
        $compiler->setOutputStyle($outputStyle);
        $compiler->setImportPaths(dirname($f));
        $cnt = null;
        try {
            $cnt = $compiler->compileString(file_get_contents($f))->getCss();
        } catch (\Exception $e) {

        }
        return is_null($cnt) ? "" : $cnt;
    }



    /*
     *  静态工具
     */
    
    //根据给定的文件路径，获取编译文件的路径，不存在返回null
    public static function getCompilePath($path = "")
    {
        $pi = pathinfo($path);
        if (!isset($pi["extension"]) || empty($pi["extension"])) return null;
        $cext = self::getCompileExt($pi["extension"]);
        if (empty($cext)) return null;
        $ps = [];
        foreach ($cext as $i => $ext) {
            $ps[] = $pi["dirname"].DS.$pi["filename"].".$ext";
        }
        return path_exists($ps);
    }

    //根据文件ext获取编译文件ext，css -> scss,sass
    public static function getCompileExt($ext = "css")
    {
        return isset(self::$extension[strtolower($ext)]) ? self::$extension[strtolower($ext)] : [];
    }
}