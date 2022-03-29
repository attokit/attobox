<?php

/**
 * Attobox Framework / Error
 */

namespace Atto\Box;

use Atto\Box\Response;

class Error
{
    //error info
	public $level = 256;
	public $type = "Error";
	public $file = "";
	public $line = 0;
	public $title = "";
	public $msg = "";
	public $code = "";
	public $data = [];

    public $errkey = "";    //errConfigArrKeyPath

    /**
     * error code prefix
     * should be overrided
     */
    protected $codePrefix = "000";

    /**
	 * errors config
	 * should be overrided
	 */
	protected $config = [
        /*
        
        "zh-CN" => [
            "php" => ["title ...", "msg %{1}%, msg %{2}%"]
        ]
        
        */
	];

    /**
     * construct
     */
    public function __construct()
    {

    }

    /**
     * set error infos
     */
    protected function setLevel($level = 256)
    {
        $lvls = [
			1 		=> "Error",
			2 		=> "Warning",
			4 		=> "Parse",
			8 		=> "Notice",
			256 	=> "Error",
			512 	=> "Warning",
			1024 	=> "Notice"
		];
		$this->level = $level;
		if (isset($lvls[$level])) {
			$this->type = $lvls[$level];
		} else {
			$this->type = "Error";
		}
		return $this;
    }
    protected function setFile($file, $line = 0)
	{
		$rf = path_relative($file);
		$this->file = is_null($rf) ? $file : $rf;
		$this->line = $line;
		return $this;
	}
    protected function setMsg($errConfigArrKeyPath = "", $msgReplacement = [])
    {
        $title = "Undefined";
        $msg = "Undefined";
        //$lang = EXPORT_LANG;
        //var_dump($this->config[EXPORT_LANG]);
        if (isset($this->config[EXPORT_LANG])) {
            $conf = arr_item($this->config[EXPORT_LANG], $errConfigArrKeyPath);
            if (is_notempty_str($errConfigArrKeyPath) && !is_null($conf)) {
                $title = $conf[0];
                $msg = self::replaceErrMsg($conf[1], $msgReplacement);
            }
        }
        $this->errkey = $errConfigArrKeyPath;
        $this->title = $title;
        $this->msg = $msg;
        return $this;
    }
    protected function setCode()
    {

        return $this;
    }
    protected function setData()
	{
		$self = $this;
		$props = arr("level,type,file,line,title,msg,code");
		$data = [];
		foreach ($props as $i => $v) {
			if (property_exists($this, $v)) {
				$data[$v] = is_object($self->$v) ? arr($self->$v) : $self->$v;
			}
		}
		$this->data = $data;
		return $this;
	}

    /**
     * check if error must throw
     */
    public function mustThrow()
	{
		return in_array($this->level, [1,2,4,256,512]) || $this->level > 1024;
	}



    /**
	 * create error object
	 * @param Integer $level    error level
	 * @param String $file		php file path
	 * @param Integer $line		error at line in php file
	 * @param Array $cls        error class fullname
     * @param Array $key        errConfigArrKeyPath
	 * @param Array $msg		msg array for replacing error msgs
	 * @return Error instance  or  null
	 */
	public static function create($level, $file, $line, $cls, $key, $msg = [])
    {
        if (!class_exists($cls)) return null;
        $err = new $cls();
		return $err->setLevel($level)->setFile($file, $line)->setMsg($key, $msg)->setData();
	}



    /**
	 * global error handler
	 * @param Integer $errno		error level
	 * @param String $errstr		error msg could be customize
	 * @param String $errfile		php file path
	 * @param Integer errline		error at line in php file
	 * @return Error instance
	 */
	public static function handler(
		$errno,		//错误级别
		$errstr,	//错误信息
		$errfile,	//发生错误的文件
		$errline	//发生错误的行号
	) {
		if ($errno > 1024 || $errno < 256) {	//php system error
			//var_dump(func_get_args());
			$cls = self::cls("base/php");
			$msg = [ $errstr ];
		} else {	//customize error
			if (is_notempty_str($errstr)) {
				$arr = explode("::", $errstr);
				$cls = self::cls($arr[0]);
				$msg = count($arr)>1 ? arr($arr[1]) : [];
			} else {
				$cls = self::cls("base/unknown");
				$msg = [];
			}
		}
        if (is_null($cls)) {
            $cls = [ cls("error/base"), "unknown" ];
        }
        //var_dump($msg);
		//create error instance
		$err = self::create($errno, $errfile, $errline, $cls[0], $cls[1], $msg);
        if (!is_null($err) && $err instanceof Error) {
            if ($err->mustThrow()) {
                Response::current()->throwError($err);
            } else {
                Response::current()->setError($err);
            }
        }
	}

	//注册 set_error_handler
	public static function setHandler($callable = null)
	{
		if (is_callable($callable)) {
			set_error_handler($callable);
		} else {
			set_error_handler([static::class, "handler"]);
		}
	}



    /**
     * static tools
     */

    /**
     * replace %{n}% in err msg
     * @param String $msg       err msg
     * @param Array $params     replace strs
     * @return String replaced err msg
     */
    public static function replaceErrMsg($msg = "", $params = [])
    {
        if (!is_notempty_str($msg)) return "";
		if (is_notempty_arr($params)) {
			foreach ($params as $i => $v){
				$msg = str_replace("%{".($i+1)."}%", $v, $msg);
			}
		}
		return $msg;
    }

    /**
	 * get error class && error key (arr path)
	 * @param String $key				like 'foo/bar/jaz'
	 * @return Array [ class fullname, error key (arr path) ]  or  null
	 */
	public static function cls($key = null)
	{
		if (is_notempty_str($key)) {
			$key = str_replace("\\", "/", $key);
			$key = str_replace(".", "/", $key);
			$arr = explode("/", $key);
			if ($arr[0]=="error") array_shift($arr);
			$idx = 0;
			for ($i=count($arr); $i>=1; $i--) {
				$subarr = array_slice($arr, 0, $i);
				$subarr[count($subarr)-1] = ucfirst(strtolower(array_slice($subarr, -1)[0]));
				$cls = cls("error/".implode("/",$subarr));
				if (!is_null($cls)) {
					$idx = $i;
					break;
				}
			}
			if ($idx<=0) {
				return [ cls("error/Base"), implode("/", $arr) ];
			} else {
				$arrs = [
					array_slice($arr, 0, $idx),
					array_slice($arr, $idx)
				];
				return [ cls("error/".implode("/", $arrs[0])), implode("/", $arrs[1])];
			}
		}
		return null;
	}
}