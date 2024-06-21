<?php

/**
 * Attobox Framework / Module Resource
 * Resource Extension
 * 
 * CSS
 */

namespace Atto\Box\resource;

use Atto\Box\Resource;
use Atto\Box\Response;
use MatthiasMullie\Minify;  //JS/CSS文件压缩

use Sabberworm\CSS\Parser;

class Css extends Resource
{
    /**
     * Sabberworm\CSS\Parser
     */
    public $parser = null;
    public $css = null;


    /**
     * after resource created
     * if necessary, derived class should override this method
     * @return Resource $this
     */
    protected function afterCreated()
    {
        //$this->getContent();
        //$this->parser = new Parser($this->content);
        //$this->css = $this->parser->parse();

        return $this;
    }

    /**
     * @override export
     * export plain file
     * @return void exit
     */
    public function export($params = [])
    {
        $params = empty($params) ? $this->params : arr_extend($this->params, $params);

        //get resource content
        $this->getContent();

        //process
        $cnt = $this->content;
        $ps = $this->params;
        if (isset($ps["export"])) {
            
        }
        if (isset($ps["min"]) && $ps["min"] !== "no") { //压缩
            $minifier = new Minify\CSS();
            $minifier->add($cnt);
            $cnt = $minifier->minify();
        }
        $this->content = $cnt;

        //输出之前，如果需要，保存文件到本地
        $this->saveRemoteToLocal();

        //sent header
        //$this->sentHeader();
        Mime::header($this->rawExt, $this->rawBasename);
        Response::headersSent();

        //echo
        echo $this->content;
        exit;
    }
}