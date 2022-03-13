<?php

/**
 * Attobox Framework / Module Resource
 * Resource Compiler
 * 
 * Local resource complie, for plain file like: ts, scss ...
 * 
 * support formats：
 *      scss, sass   -> css
 *      ts           -> js
 */

namespace Atto\Box\resource;

use Atto\Box\resource\Mime;

use ScssPhp\ScssPhp\Compiler as scssCompiler;
use ScssPhp\ScssPhp\OutputStyle as scssOutputStyle;

class Compiler
{
    //reference of the resource instance
    protected $resource = null;

    //supported format
    protected static $extension = [
        "css" => ["scss", "sass"],
        //"js" => ["ts"]
    ];

    public function __construct($resource)
    {
        $this->resource = &$resource;
    }



    /**
     * compile method
     * enterence
     * @return String $resource->content
     */
    public function compile()
    {
        $f = $this->resource->realPath;
        $cext = strtolower(pathinfo($f)["extension"]);
        $m = "compile".ucfirst($cext);
        if (method_exists($this, $m)) {
            $this->resource->content = $this->$m();
        }
    }

    /**
     * compile methods
     * temporary support format: scss
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
        return /*is_null($cnt) ? "" : */$cnt;
    }



    //判断输出的内容是否压缩
    /**
     * check if content need to be compressed
     */
    private function isCompressed()
    {
        $p = $this->resource->params;
        return isset($p["min"]) && $p["min"] !== "no" ? true : false;
    }




    /**
     * tools
     */
    
    /**
     * get source file path from $path
     * @param String $path      dir been checking
     * @return String source file fullpath or null
     */
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
    /**
     * get source file extension from target file extension
     * css -> scss,sass
     * @param String $ext       target file extension
     * @return Array source file extensions or []
     */
    public static function getCompileExt($ext = "css")
    {
        return isset(self::$extension[strtolower($ext)]) ? self::$extension[strtolower($ext)] : [];
    }
}