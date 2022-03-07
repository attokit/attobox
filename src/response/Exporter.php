<?php
/*
 *  Attobox Framework / Response Exporter
 * 
 */

namespace Atto\Box\response;

use Atto\Box\Response;
//use Atto\Box\Error;

class Exporter
{
    //关联的Response实例
    public $response = null;
    //默认输出数据的结构
    protected static $default = [
        "error" => false,
        "errors" => [],
        "data" => null
    ];

    //当前format的页头
    public $contentType = "";

    //要输出的数据
    public $data = null;

    //要输出的数据，字符串，用于echo
    public $content = "";


    /**
     * 构造
     */
    public function __construct($response = null) 
    {
        $this->response = $response;
        if ($this->contentType != "") $this->response->setHeaders("Content-Type", $this->contentType);
        if (is_null($this->data)) $this->data = array_merge(self::$default, []);
        $this->data["data"] = $this->response->data;
        $this->prepareError();
    }

    /**
     * 解析 data["data"]，写入 content，用于最终输出
     * 子类覆盖
     * @return Exporter
     */
    public function prepare()
    {
        //子类实现...
        //$this->content = ...;

        return $this;
    }

    /**
     * 处理要输出的error
     * @return Exporter
     */
    private function prepareError()
    {
        $errs = $this->response->errors;
        $this->data["errors"] = [];
        foreach ($errs as $i => $err) {
            if ($err instanceof Error) {
                $this->data["errors"][] = $err->data;
            }
        }
        return $this;
    }


    /**
     * 最终输出
     * 执行完毕后 exit
     * 子类可覆盖
     * @return String content
     */
    public function export()
    {
        //$this->prepare();
        $this->response->sentHeaders();
        echo $this->content;
        return $this->content;
        exit;
    }
    

}