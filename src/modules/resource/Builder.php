<?php

/**
 * Attobox Framework / Module Resource
 * Resource Builder
 * 
 * Local resource dynamic build，for plain file like: js，css，vue ...
 */

namespace Atto\Box\resource;

use Atto\Box\Resource;
use Atto\Box\resource\Compiler;
use Atto\Box\request\Url;
use Atto\Box\request\Curl;

use ScssPhp\ScssPhp\Compiler as scssCompiler;
use ScssPhp\ScssPhp\OutputStyle as scssOutputStyle;

use MatthiasMullie\Minify;  //JS/CSS文件压缩

class Builder
{
    //reference of the resource instance
    protected $resource = null;

    //builder params (runtime params)
    protected $params = [
        "version" => "latest",
        "use" => null,
        "min" => "no",
        "export" => "no"
    ];

    public function __construct($resource)
    {
        $this->resource = &$resource;
        $this->params = arr_extend($this->params, $this->resource->params);
    }



    /**
     * build method
     * entrence
     * @return String $resource->content
     */
    public function build()
    {
        //start
        $this->resetContent(
            $this->comment("Build ".$this->resource->rawBasename." Start")
        );

        //build
        foreach ($this->params as $k => $v) {
            $m = "build" . ucfirst($k);
            if (method_exists($this, $m)) {
                if (!empty($v)) $this->$m($v);
            } else {
                if (is_dir($this->resource->realPath.DS.$k)) {
                    if (!empty($v)) $this->buildSubDir($k);
                }
            }
        }

        //end
        $this->appendContent(
            $this->comment("Build End")
        );

        return $this->resource->content;
    }

    /**
     * build methods
     */
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


    /**
     * content modify methods
     */
    protected function appendContent()
    {
        $args = func_get_args();
        $rn = $this->rn();
        $cnt = empty($args) ? "" : $rn . implode($rn, $args);
        $this->resource->content .= $cnt;
        return $this;
    }

    protected function prependContent()
    {
        $args = func_get_args();
        $rn = $this->rn();
        $cnt = empty($args) ? "" : implode($rn, $args) . $rn;
        $this->resource->content = $cnt . $this->resource->content;
        return $this;
    }

    protected function resetContent()
    {
        $args = func_get_args();
        $rn = $this->rn();
        $cnt = empty($args) ? "" : implode($rn, $args);
        $this->resource->content = $cnt;
        return $this;
    }



    /**
     * tools
     */
    
    /**
     * create comment
     * @param String $comment       comment content
     * @return String formatted comment
     */
    public function comment($comment = "")
    {
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

    public function separator()
    {
        $rn = $this->rn();
        return $rn.$rn.$rn;
    }

    public function rn()
    {
        return "\r\n";
    }

    /**
     * get latest version file or subdir from $dir
     * @param String $dir       the dir need to be checken
     * @return String version   file or subdir name
     */
    public function latest($dir = null)
    {
        $dir = empty($dir) ? $this->resource->realPath : $dir;
        $vers = [];
        path_traverse($dir, function($p, $f) use (&$vers) {
            $fp = $p . DS . $f;
            if (is_dir($fp)) {
                $ver = str_replace(".", "", $f);
                if (is_numeric($ver)) $vers[] = $f;
            } else if (is_file($fp)) {
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

    /**
     * find the file that could been exists
     * @param String $path      the dir been looking over
     * @param String $name      filename must contains $name
     * @param String $ext       file extension
     * @param Boolean $checkMin     if filename contains ".min"
     * @return String file fullpath or null
     */
    protected function find($path, $name, $ext, $checkMin = true)
    {
        $ps = [];
        if ($checkMin) {
            $ps = [
                "$path.min.$ext",
                $path.DS.$name.".min.$ext",
                $path.DS.$ext.DS.$name.".min.$ext"
            ];
        }
        $ps = array_merge($ps, [
            "$path.$ext",
            $path.DS.$name.".$ext",
            $path.DS.$ext.DS.$name.".$ext"
        ]);
        //var_dump($ps);
        $f = path_exists($ps,["inDir"=>[]]);
        return $f;
    }

    /**
     * find the file & get contents
     * @param String $path      the dir been looking over
     * @param String $name      filename must contains $name
     * @param String $ext       file extension
     * @return String file content or null
     */
    protected function getContent($path, $name, $ext)
    {
        //step 1    if local file exists
        $f = $this->find($path, $name, $ext, true);
        if (!is_null($f)) return file_get_contents($f);

        //step 2    if local PHP file exists, dynamic create file content
        $f = $this->find($path, $name, $ext.EXT);
        if (!is_null($f)) {
            $f = str_replace(EXT, "", $f);
            $u = Url::mk("/src/".Resource::toUri($f))->full;
            //var_dump($u);
            $cnt = Curl::get($u);
            if (!is_null($cnt)) return $cnt;
        }
        
        //step 3    if need to complie file content
        $cext = Compiler::getCompileExt($ext);
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
                $u = Url::mk("/src/".Resource::toUri($f))->full;
                //var_dump($u);
                $cnt = Curl::get($u);
                if (!is_null($cnt)) return $cnt;
            }
        }
        return null;
    }

}