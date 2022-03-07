<?php
/**
 * CPHP框架  Resource 模块
 * 资源内容生成器 Creator
 * 本地资源动态构建 Build，用于纯文本文件  js，css，vue 等
 */

namespace CPHP\resource\creator;

use CPHP\Resource;
use CPHP\resource\Creator;
use CPHP\resource\creator\Compile;
use CPHP\request\Url;
use CPHP\request\Curl;

use ScssPhp\ScssPhp\Compiler as scssCompiler;
use ScssPhp\ScssPhp\OutputStyle as scssOutputStyle;

use MatthiasMullie\Minify;  //JS/CSS文件压缩

class Build extends Creator
{
    //builder params 文件构造参数，来自$_GET，Arr实例
    public $params = [
        "version" => "latest",
        "use" => null,
        "min" => "no",
        "export" => "no"
    ];

    //修改content，默认针对 plain 纯文本
    public function appendContent()
    {
        $args = func_get_args();
        $rn = $this->rn();
        $cnt = empty($args) ? "" : $rn . implode($rn, $args);
        $this->resource->content .= $cnt;
        return $this;
    }

    public function prependContent()
    {
        $args = func_get_args();
        $rn = $this->rn();
        $cnt = empty($args) ? "" : implode($rn, $args) . $rn;
        $this->resource->content = $cnt . $this->resource->content;
        return $this;
    }

    public function resetContent()
    {
        $args = func_get_args();
        $rn = $this->rn();
        $cnt = empty($args) ? "" : implode($rn, $args);
        $this->resource->content = $cnt;
        return $this;
    }



    /*
     *  构建方法
     */
    //入口
    public function create()
    {
        //prepsre params
        $this->getParams();
        //start
        $this->resetContent(
            $this->comment("Build ".$this->resource->rawBasename." Start")
        );

        //build
        foreach ($this->params as $k => $v) {
        //$this->params->each(function($v, $k) use (&$self) {
            $m = "build" . ucfirst($k);
            if (method_exists($this, $m)) {
                if (!empty($v)) $this->$m($v);
            } else {
                if (is_dir($this->resource->realPath.DS.$k)) {
                    if (!empty($v)) $this->buildSubDir($k);
                }
            }
        //});
        }

        //end
        $this->appendContent(
            $this->comment("Build End")
        );

        //export
        /*if ($this->params["export"] != "no" && $this->resource->rawExt == "js") {
            $this->jsEs6Export();
        }

        //minimize
        if ($this->params["min"] == "yes") {
            $this->minimize();
        }*/

        //$this->file->setContents($this->contents);
        //return $this;
    }

    //buildVersion
    protected function buildVersion($ver = null)
    {
        $res = $this->resource;
        $ext = $res->rawExt;
        $name = $res->rawFilename;
        $ver = is_null($ver) ? $this->params["version"] : $ver;
        if ($ver == "latest") $ver = $this->latest();
        //var_dump($ver);exit;
        if (is_null($ver)) $ver = $name;
        $fn = $res->realPath . DS . $ver;
        $cnt = $this->getContent($fn, $name, $ext);
        if (!is_null($cnt)) {
            $this->appendContent(
                $this->comment("$ver.$ext"),
                $cnt,
                $this->comment("$ver.$ext End")
            );
        } else {
            $this->appendContent($this->comment("$ver.$ext Not Found"));
        }
        $this->appendContent($this->separator());
        //var_dump($res->content); exit;
        return $this;
    }

    //buildUse
    protected function buildUse($uarr = null)
    {
        $res = $this->resource;
        $uarr = empty($uarr) ? $this->params["use"] : $uarr;
        if (!is_array($uarr)) $uarr = arr($uarr);
        //if (empty($uarr)) return $this;
        //var_dump($uarr); exit;
        $dir = $res->realPath;
        $ext = $res->rawExt;
        //$self = &$this;
        $this->appendContent($this->comment("Attached Content"));
        foreach ($uarr as $k => $v) {
            $cnt = $this->getContent($dir.DS.$v, $v, $ext);
            if (!is_null($cnt)) {
                $this->appendContent(
                    $this->comment("$v.$ext"),
                    $cnt,
                    $this->comment("$v.$ext End")
                );
            }
        }
        $this->appendContent(
            $this->comment("Attached Content End"),
            $this->separator()
        );
        //var_dump($res->content); exit;
        return $this;
    }

    //buildSubDir by params[subdir]
    protected function buildSubDir($subdir = null)
    {
        $res = $this->resource;
        if (is_notempty_str($subdir)) {
            $dir = $res->realPath.DS.$subdir;
            $uarr = arr($this->params[$subdir]);
            if (!in_array($subdir, $uarr)) array_unshift($uarr, $subdir);
            if (!is_dir($dir)) return $this;
            $ext = $res->rawExt;
            $this->appendContent($this->comment("Attached Content"));
            foreach ($uarr as $k => $v) {
                $cnt = $this->getContent($dir.DS.$v, $v, $ext);
                if (!is_null($cnt)) {
                    $this->appendContent(
                        $this->comment("$v.$ext"),
                        $cnt,
                        $this->comment("$v.$ext End")
                    );
                }
            }
            $this->appendContent(
                $this->comment("Attached Content End"),
                $this->separator()
            );
        }
        //var_dump($res->content); exit;
        return $this;
    }



    /*
     *  工具
     */

    //params 准备
    protected function getParams()
    {
        $resp = $this->resource->params;
        $this->params = arr_extend($this->params, $resp);
    }

    //创建comment注释
    public function comment($comment = "")
    {
        //if ($this->params["min"] == "yes") return "";
        $rn = $this->rn();
        switch ($this->resource->rawExt) {
            case "vue" :
            case "html" :
                return "$rn<!-- $comment -->$rn";
                break;

            default :
                return "$rn/** $comment **/$rn";
                break;
        }
    }

    //生成间隔行
    public function separator()
    {
        $rn = $this->rn();
        return $rn.$rn.$rn;
    }

    //生成换行符
    public function rn()
    {
        return /*$this->params["min"] == "yes" ? "" : */"\r\n";
    }

    //从dir中查找版本号（版本号作为名称）最高的file或subdir，返回版本号字符串
    public function latest($dir = null)
    {
        $dir = empty($dir) ? $this->resource->realPath : $dir;
        $vers = [];
        path_traverse($dir, function($p, $f) use (&$vers) {
            $fp = $p . DS . $f;
            if (is_dir($fp)) {
                $ver = str_replace(".", "", $f);
                if (is_numeric($ver)) $vers[] = $f;
            } elseif (is_file($fp)) {
                if (strpos($f, ".min.") !== false) {
                    $f = str_replace(".min", "", $f);
                }
                $fr = explode(".", $f);
                if (!is_numeric($fr[count($fr)-1])) array_pop($fr);
                if (is_numeric(implode("",$fr))) $vers[] = implode(".", $fr);
            }
        });
        if (empty($vers)) return null;
        rsort($vers);
        return $vers[0];
    }

    //查找可能存在的文件
    protected function find($path, $name, $ext, $checkMin = true)
    {
        $ps = [];
        if ($checkMin) $ps = array_merge($ps, [
            "$path.min.$ext",
            $path.DS.$name.".min.$ext",
            $path.DS.$ext.DS.$name.".min.$ext"
        ]);
        $ps = array_merge($ps, [
            "$path.$ext",
            $path.DS.$name.".$ext",
            $path.DS.$ext.DS.$name.".$ext"
        ]);
        //var_dump($ps);
        $f = path_exists($ps,["inDir"=>[]]);
        return $f;
    }

    //获取某文件的内容，参数为 path or pathinf($path)
    protected function getContent($path, $name, $ext)
    {
        //step 1    检查是否存在真实的本地文件
        $f = $this->find($path, $name, $ext, true);
        if (!is_null($f)) return file_get_contents($f);

        //step 2    检查是否存在php文件，动态生成内容
        $f = $this->find($path, $name, $ext.EXT);
        if (!is_null($f)) {
            $f = str_replace(EXT, "", $f);
            //$u = Url::domain()."/src/".Resource::toUri($f);
            $u = Url::mk("/src/".Resource::toUri($f))->full;
            //var_dump($u);
            $cnt = Curl::get($u);
            if (!is_null($cnt)) return $cnt;
        }
        
        //step 3    检查是否需要编译生成文件内容
        $cext = Compile::getCompileExt($ext);
        if (!empty($cext)) {
            $f = null;
            foreach ($cext as $i => $ei) {
                $f = $this->find($path, $name, $ei);
                if (!is_null($f)) break;
            }
            if (!is_null($f)) {
                foreach ($cext as $i => $ei) {
                    $f = str_replace(".$ei", ".$ext", $f);
                }
                //$u = Url::domain()."/src/".Resource::toUri($f);
                $u = Url::mk("/src/".Resource::toUri($f))->full;
                //var_dump($u);
                $cnt = Curl::get($u);
                if (!is_null($cnt)) return $cnt;
            }
        }
        return null;
    }
    
    //当输出 JS 文件时，export=yes 则在当前路径下寻找 export.js，找到则引入
    protected function jsEs6Export()
    {
        if ($this->params["export"] != "no" && $this->resource->rawExt == "js") {
            $f = $this->resource->realPath.DS."export.js";
            if (file_exists($f)) {
                $this->appendContent(
                    $this->comment("ES6 export"),
                    file_get_contents($f)
                );
            } else {
                $exportKey = $this->params["export"];
                $this->appendContent(
                    $this->comment("ES6 export"),
                    "export default $exportKey;"
                );
            }
        }
        return $this;
    }

    //压缩文件，支持 css,js
    protected function minimize()
    {
        $min = $this->params["min"] == "yes";
        if (!$min) return ;
        $ext = $this->resource->rawExt;

        $m = "min".ucfirst($ext);
        if (method_exists($this, $m)) {
            return $this->$m();
        }

        return ;
    }

    //压缩css
    protected function minCss()
    {
        $compiler = new scssCompiler();
        $compiler->setOutputStyle(scssOutputStyle::COMPRESSED);
        $cnt = null;
        try {
            $cnt = $compiler->compileString($this->resource->content)->getCss();
        } catch (\Exception $e) {

        }
        
        if (!is_null($cnt)) {
            $this->resetContent($cnt);
        }
    }

    //压缩js
    protected function minJs()
    {
        $minifier = new Minify\JS();
        $minifier->add($this->resource->content);
        $minjs = $minifier->minify();
        $this->resetContent($minjs);
    }
    
    
}
