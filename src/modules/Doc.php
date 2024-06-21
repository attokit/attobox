<?php

/**
 * Attobox Framework / Module
 * Doc library Module
 * documentation management
 */

namespace Atto\Box;

class Doc
{
    /**
     * document format use markdown
     */
    protected static $ext = ".md";

    /**
     * doc folder index file name
     */
    protected static $index = "index";

    /**
     * doc desc file
     */
    protected static $desc = "desc.json";

    /**
     * current doc instance config
     */
    public $dir = "";    //doc folder, realpath
    public $uri = "";    //doc dir export in URI

    /**
     * doc directory array
     * in desc.json
     */
    public $directory = [];

    //init params
    //protected $dir = "";
    //protected $ext = ".md";

    /**
     * runtime info
     * from doc/[self::$descfile]
     */
    //public $desc = [];
    //public $tree = [];
    //public $page = "";

    /**
     * construct
     * @param String $dir   use path_find()  or  realpath
     */
    public function __construct($dir)
    {
        if (self::exists($dir)) {
            $this->uri = $dir;
            $this->dir = path_find($dir, ["inDir"=>"docs,documents"]);

            //load desc.json
            $this->load();
        }


        /*$dir = path_find($dir, ["inDir"=>"docs,documents"]);
        if (is_notempty_str($dir)) {
            $this->dir = $dir;
        }
        if (self::exists($dir)) {
            $this->dir = $dir;
            $desc = j2a(file_get_contents($dir.DS.self::$descfile));
            if (!empty($desc)) {
                foreach ($desc as $k => $v) {
                    $this->$k = $v;
                }
            }
        }*/
    }

    /**
     * load doc desc.json
     * @return $this
     */
    public function load()
    {
        $desc = $this->dir.DS.self::$desc;
        if (file_exists($desc)) {
            $darr = j2a(file_get_contents($desc));
            if (is_notempty_arr($darr)) {
                foreach ($darr as $k => $v) {
                    $this->$k = $v;
                }
            }
        }
    }

    /**
     * get doc file (*.ext)
     * @param String $filepath      filepath
     * @return String real filepath  or  null
     */
    public function file($filepath)
    {
        if (!is_notempty_str($filepath)) return null;
        $fp = $this->dir.DS.str_replace("/", DS, $filepath);
        if (file_exists($fp.self::$ext)) return $fp.self::$ext;
        if (is_dir($fp)) {
            if (self::exists($fp)) return $fp.DS.self::$index.self::$ext;
            return self::latest($fp);
        }
        return null;
    }



    /**
     * create doc instance
     * @param String $dir       doc folder path, use path_find()
     * @param Array $conf       extra config array
     * @return Doc instance  or  null
     */
    public static function create($dir = "")
    {
        if (!self::exists($dir)) return null;
        return new Doc($dir);
    }



    /**
     * static tools
     */

    /**
     * check if dir containes docs
     * @param String $dir       doc location dir
     * @return Bool
     */
    public static function exists($dir = "")
    {
        $dir = path_find($dir, ["inDir"=>"docs,documents"]);
        if (!is_dir($dir)) return false;
        $index = $dir.DS.self::$index.self::$ext;
        $desc = $dir.DS.self::$desc;
        return file_exists($index) && file_exists($desc);
    }

    /**
     * sort *.ext file by filename, to get latest version of doc file
     * @param String $dir   dir containes files
     * @return String latest version file fullpath
     */
    public static function latest($dir = "")
    {
        if (!is_dir($dir)) return null;
        $fs = [];
        $dh = opendir($dir);
        while( ($f = readdir($dh)) !== false ) {
            if (strpos($f, self::$ext)===false || is_dir($dir.DS.$f)) continue;
            $fs[] = implode(".", array_slice(explode(".", $f), 0, -1));
        }
        closedir($dh);
        if (empty($fs)) return null;
        rsort($fs);
        return $dir.DS.$fs[0];
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
     * default doc cover method
     */
    public static function index()
    {
        return self::dir("root/index.md");
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
     * get latest file in dir by filename
     * @param String $dir       dir real path
     * @return String latest file full path  or  null
     */

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