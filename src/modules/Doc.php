<?php

/**
 * Attobox Framework / Module
 * Doc library Module
 * documentation management
 */

namespace Atto\Box;

class Doc
{
    //support doc format
    //protected static $exts = ["md","html","xml"];
    protected static $descfile = "description.json";

    //init params
    protected $dir = "";
    protected $ext = ".md";

    /**
     * runtime info
     * from doc/[self::$descfile]
     */
    //public $desc = [];
    //public $tree = [];
    public $page = "";

    /**
     * construct
     */
    public function __construct($dir)
    {
        if (self::exists($dir)) {
            $this->dir = $dir;
            $desc = j2a(file_get_contents($dir.DS.self::$descfile));
            if (!empty($desc)) {
                foreach ($desc as $k => $v) {
                    $this->$k = $v;
                }
            }
        }
    }

    /**
     * set init params
     */
    public function setExt($ext = null)
    {
        if (self::supported($ext) !== false) {
            $this->ext = ".".$ext;
        }
        return $this;
    }

    /**
     * get doc page by path
     * @param String $path      page path
     * @return String page content
     */
    public function getPage($path = "")
    {
        $path = $path=="" ? "index" : $path;
        $dir = $this->dir;
        $pdir = $dir.DS.str_replace("/", DS, $path);
        $pf = $pdir.$this->ext;
        $cnt = "";
        if (file_exists($pf)) {
            $cnt = file_get_contents($pf);
        } else if (is_dir($pdir)) {
            $pf = self::latest($pdir);
            if (!is_null($pf)) {
                $cnt = file_get_contents($pf.$this->ext);
            }
        }
        
        if ($cnt == "") return "";

        //parse content from $this->ext to html
        $parserCls = self::supported($this->ext);
        if ($parserCls !== false) {
            $parser = new $parserCls($cnt);
            return $parser->parse();
        }

        return "";
    }

    /**
     * create doc instance
     */
    public static function create($dirkey = "")
    {
        $dir = self::dir($dirkey);
        if (is_null($dir)) return null;
        $doc = new Doc($dir);

        return $doc;
    }



    /**
     * static tools
     */

    /**
     * get doc dir from path key like "foo/bar/jaz"
     * @param String $key       path key
     * @return String realpath  or  null
     */
    public static function dir($key = "")
    {
        return path_find($key, [
            "inDir" => "docs,documents,"
        ]);
    }

    /**
     * check if doc exists
     * @param String $dir       doc location dir
     * @return Boolean
     */
    public static function exists($dir = "")
    {
        return is_dir($dir) && file_exists($dir.DS.self::$descfile);
    }

    /**
     * get latest file in dir by filename
     * @param String $dir       dir real path
     * @return String latest file full path  or  null
     */
    public static function latest($dir = "")
    {
        if (!is_dir($dir)) return null;
        $fs = [];
        $dh = opendir($dir);
        while( ($f = readdir($dh)) !== false ) {
            $fp = $dir.DS.$f;
            if (is_dir($fp)) continue;
            $fs[] = implode(".", array_slice(explode(".", $f), 0, -1));
        }
        closedir($dh);
        if (empty($fs)) return null;
        rsort($fs);
        return $dir.DS.$fs[0];
    }

    /**
     * check if doc ext is supported
     * @param String $ext       doc ext (md, xml, ...)
     * @return Boolean  or  Parser class fullname
     */
    public static function supported($ext = "md")
    {
        $parser = cls("doc/parser/".ucfirst(strtolower(str_replace(".", "", $ext))));
        return !is_null($parser) ? $parser : false;
    }

    /**
     * get doc dir & page key from path array (usually from URI)
     * @param Array $path      URI str explode by /
     * @return Array [ doc real path, page key ]  or  null
     */
    public static function parse($path = [])
    {
        $path = is_notempty_str($path) ? explode("/", $path) : (is_indexed($path) ? $path : []);
        if (empty($path)) return null;
        $docarr = [];
        for ($i=count($path); $i>0; $i--) {
            $sub = array_slice($path, 0, $i);
            $dir = self::dir(implode("/", $sub));
            if (self::exists($dir)) {
                $docarr[] = $dir;
                $docarr[] = implode("/", array_slice($path, $i));
                break;
            }
        }
        return empty($docarr) ? null : $docarr;
    }

}