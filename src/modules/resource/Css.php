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

class Css extends Resource
{
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

        //sent header
        $this->sentHeader();

        //echo
        echo $this->content;
        exit;
    }
}