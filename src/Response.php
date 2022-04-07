<?php

/**
 * Attobox Framework / Response
 */

namespace Atto\Box;

use Atto\Box\Request;
use Atto\Box\Router;
use Atto\Box\Error;
use Atto\Box\traits\staticCurrent;

//use GuzzleHttp\Psr7;

class Response
{
    //引入trait
    use staticCurrent;

    /**
     * current
     */
    public static $current = null;

    /**
     * current request & router
     */
    public $req = null;
    public $rou = null;

    /**
     * response params
     */
    private static $defaultParams = [
        "status" => 0,
        "headers" => [],
        "protocol" => "",
        "data" => null,
        "format" => "",
        "errors" => []
    ];
    public $status = 200;
    public $headers = [
        "Content-Type" => "text/html; charset=utf-8",
        "User-Agent" => "Attobox/Response",
        "X-Framework" => "attokit/attobox",
        "Access-Control-Allow-Origin" => "*"
    ];
    public $protocol = "1.1";
    //other
    public $data = null;    //准备输出的内容
    public $format = EXPORT_FORMAT;
    public $errors = [];    //本次会话发生的错误收集，Error实例
    //public $throwError = null;  //需要立即抛出的错误对象

    //数据输出类
    public $exporter = null;

    //是否只输出 data，默认 false，输出 [error=>false, errors=>[], data=>[]]
    public $exportOnlyData = false;


    /**
     * 构造
     */
    public function __construct($params = [])
    {
        $this->req = Request::current();
        $this->rou = Router::current();
        $this->setFormat();
        $this->setParams($params);
    }

    /**
     * 调用 route，生成 response params
     * @return Response
     */
    public function create($params = [])
    {
        $rou = $this->rou;
        //WEB_PAUSE == true
        if (WEB_PAUSE && !$rou->unpause) {
            $page = path_exists([
                "pause.php",
                "box/pause.php"
            ]);
            $params = arr_extend($params, [
                "format" => "page",
                "data" => $page,
                "extra" => "额外的属性" 
            ]);
        } else {
            $data = $this->rou->run();
            $params = self::createParams($data, $params);
        }
        return $this->setParams($params);
    }

    /**
     * 输出
     */
    public function export($usePsr7 = false)
    {
        $exporter = $this->createExporter();
        $exporter->prepare();
        //var_dump($this->info());

        if (RESPONSE_PSR7 == true || $usePsr7 == true) {
            $status = $this->status;
            $headers = $this->headers;
            $body = $exporter->content;
            $protocol = $this->protocol;
            $response = new Psr7\Response($status, $headers, $body, $protocol);
            var_dump($response);
        } else {
            return $exporter->export();
        }
        exit;
    }

    /**
     * throw error
     */
    public function throwError($error = null)
    {
        if (is_object($error) && $error instanceof Error) {
            $this->setError($error);
            if ($error->mustThrow()) {
                $this->setData($error->data);
                //$this->setExporter("error");
                $exporter = $this->createExporter();
                $exporter->prepare();
                return $exporter->export();
            }
        }
        exit;
    }


    /**
     * set response params
     */

    /**
     * 设定 statu
     * @return Response
     */
    public function setStatus($code = 200)
    {
        $this->status = (int)$code;
        return $this->setExporter();
    }

    /**
     * 设定 headers
     * @return Response
     */
    public function setHeaders($key = [], $val = null)
    {
        if (is_array($key) && !empty($key)) {
            $this->headers = arr_extend($this->headers, $key);
        } else if (is_string($key) && isset($this->headers[$key])) {
            $this->headers[$key] = $val;
        }
        return $this;
    }

    /**
     * 设定输出内容 data
     * @return Response
     */
    public function setData($data = null, $reset = false)
    {
        if (is_null($this->data) || $reset) {
            $this->data = $data;
        } else {
            if (is_array($this->data) && is_array($data)) {
                $this->data = arr_extend($this->data, $data);
            } else {
                $this->data = $data;
            }
        }
        return $this;
    }

    /**
     * 设定 export format
     * @return Response
     */
    public function setFormat($format = null)
    {
        $this->format = self::getExportFormat($format);
        return $this->setExporter();
    }

    /**
     * 设定 errors
     * @return Response
     */
    public function setError($error = null)
    {
        if (is_object($error) && $error instanceof Error) {
            $this->errors[] = $error;
        }
        return $this;
    }
    
    /**
     * 设定 exporter 类
     * @return Response
     */
    public function setExporter($format = null)
    {
        if ($this->status != 200) {
            $exporter = self::getExporterClass("code");
        } else {
            if (is_null($format)) {
                $exporter = self::getExporterClass($this->format);
            } else {
                $exporter = self::getExporterClass($format);
            }
        }
        if (!is_null($exporter)) {
            //$this->exporter = new $exporter($this);
            $this->exporter = $exporter;
        } else {
            $this->setFormat();
        }
        return $this;
    }

    /**
     * 手动设定多个参数
     * @return Response
     */
    public function setParams($params = [])
    {
        //var_dump($params);
        if (isset($params["headers"]) && is_array($params["headers"]) && !empty($params["headers"])) {
            $this->setHeaders($params["headers"]);
        }
        if (isset($params["headers"])) unset($params["headers"]);
        foreach (["data","format","status","exportOnlyData"] as $k => $v) {
            if (isset($params[$v])) {
                $m = "set".ucfirst($v);
                if (method_exists($this, $m)) {
                    $this->$m($params[$v]);
                } else {
                    $this->$v = $params[$v];
                }
                unset($params[$v]);
            }
        }
        foreach ($params as $k => $v) {
            //if (property_exists($this, $k)) $this->$k = $v;
            if (!property_exists($this, $k)) $this->$k = $v;
            //$this->$k = $v;
        }
        
        return $this;
    }


    /**
     * 创建 exporter 对象
     * @return Exporter
     */
    public function createExporter()
    {
        if (empty($this->exporter)) $this->setStatus(500);
        $exporterClass = $this->exporter;
        return new $exporterClass($this);
    }

    /**
     * sent headers
     * @return Response
     */
    public function sentHeaders($key = [], $val = null)
    {
        if (headers_sent() === true) return $this;
        if (!empty($key)) {
            if (is_associate($key)) {
                foreach ($key as $k => $v) {
                    header("$k: $v");
                }
            } else if (is_string($key) && is_string($val)) {
                header("$key: $val");
            }
        } else {
            foreach ($this->headers as $k => $v) {
                header("$k: $v");
            }
        }
        return $this;
    }


    /**
     * response info
     * @return Array
     */
    public function info()
    {
        $rtn = [];
        $keys = array_keys(self::$defaultParams);
        for ($i=0;$i<count($keys);$i++) {
            $ki = $keys[$i];
            $rtn[$ki] = $this->$ki;
        }
        $rtn["route"] = $this->rou->info();
        return $rtn;
    }


    /**
     * 静态调用，按 format 输出
     * 输出后退出
     * @return void
     */
    private static function _export($format = "html", $data = null, $params = [])
    {
        $params = arr_extend($params, [
            "data" => $data,
            "format" => $format
        ]);
        return self::$current->setParams($params)->export();
        exit;
    }

    public static function json($data =  [], $params = [])
    {
        return self::_export("json", $data, $params);
    }

    public static function str($str = "", $params = [])
    {
        return self::_export("str", $str, $params);
    }
    
    public static function html($html = "", $params = [])
    {
        return self::_export("html", $html, $params);
    }

    public static function code($code = 404)
    {
        return self::$current->setStatus($code)->export();
    }
    
    public static function page($path = "", $params = [])
    {
        $path = path_find($path, ["inDir"=>"page"]);
        if (empty($path)) return self::code(404);
        return self::_export("page", $path, $params);
    }
    
    public static function pause()
    {
        $pages = func_get_args();
        $page = path_exists(array_merge($pages, [
            "pause.php",
            "cphp/pause.php"
        ]));
        if (empty($page)) return self::code(404);
        return self::_export("page", $page, $params);
    }

    //不通过exporter，直接输出内容，headers已经手动指定
    //用于输出文件资源
    public static function echo($content = "")
    {
        self::headersSent();
        echo $content;
        exit;
    }

    //header("Location:xxxx") 跳转
    public static function redirect($url="", $ob_clean=false)
    {
        if (headers_sent() !== true) {
            if ($ob_clean) ob_end_clean();
            header("Location:".$url);
        }
        exit;
    }




    /**
     * tools
     */

    /**
     * 静态调用 Response::$current->setHeaders
     * @return Response
     */
    public static function headers()
    {
        $args = func_get_args();
        return self::$current->setHeaders(...$args);
    }

    /**
     * 静态调用 Response::$current->sentHeaders
     * @return Response
     */
    public static function headersSent()
    {
        $args = func_get_args();
        return self::$current->sentHeaders(...$args);
    }

    /**
     * 获取要输出的 format 类型
     * format 类型在 EXPORT_FORMATS 中定义
     * @return String
     */
    public static function getExportFormat($format = null)
    {
        if (Request::$current->debug) return "dump";
        $fs = arr(strtolower(EXPORT_FORMATS));
        $format = empty($format) ? Request::get("format", EXPORT_FORMAT) : $format;
        $format = strtolower($format);
        if (is_notempty_str($format)) {
            if (in_array($format, $fs) && !is_null(self::getExporterClass($format))) {
                return $format;
            }
        }
        return strtolower(EXPORT_FORMAT);
    }

    /**
     * 获取 format 对应的 exporter 类，返回类全名
     * @return String | NULL
     */
    public static function getExporterClass($format = null)
    {
        if (is_notempty_str($format)) {
            return cls("response/exporter/".ucfirst($format));
        }
        return null;
    }

    /**
     * 将 route 运行结果 data 与 response params 合并，生成包含 data 的新 params
     * @return Array
     */
    public static function createParams($data = [], $params = [])
    {
        if (is_associate($data) && !empty($data)) {
            $ps = [];
            foreach (self::$defaultParams as $k => $v) {
                if (isset($data[$k])) {
                    $ps[$k] = $data[$k];
                    unset($data[$k]);
                }
            }
            if (!empty($ps)) $params = arr_extend($params, $ps);
            if (!empty($data)) {
                if (isset($params["data"]) && !empty($params["data"])) {
                    /*if (is_associate($params["data"]) && is_associate($data)) {
                        $params["data"] = arr_extend($params["data"], $data);
                    } else {
                        $params["data"] = $data;
                    }*/
                    $params = arr_extend($params, $data);
                } else {
                    $params["data"] = $data;
                }
            }
        } else {
            $params["data"] = $data;
        }
        return $params;
    }

    /**
     * 获取 defaultParams
     * @return Array
     */
    public static function getDefaultParams()
    {
        return self::$defaultParams;
    }
}