<?php

/**
 * CPHP框架  Resource 模块
 * 资源内容生成器
 * 本地静态资源，直接读取内容
 */

namespace CPHP\resource\creator;

use CPHP\resource\Creator;
use CPHP\resource\Mime;

use CPHP\resource\Vue;

use MatthiasMullie\Minify;  //JS/CSS文件压缩

class Local extends Creator
{
    //根据资源的 Mime::processableType 读取内容，保存到content
    public function create()
    {
        $ext = $this->resource->rawExt;
        $m = "get".ucfirst($ext)."Content";
        if (!method_exists($this, $m)) {
            $ptp = Mime::getProcessableType($this->resource->rawExt);
            $m = is_null($ptp) ? "" : ucfirst($ptp);
            $m = "get".$m."Content";
            if (!method_exists($this, $m)) {
                $m = "getContent";
            }
        }
        $this->resource->content = $this->$m();
    }


    /*
     *  按 rawExt 读取本地文件内容
     */
    //vue 文件
    protected function getVueContent()
    {
        $cnt = file_get_contents($this->resource->realPath);
        $ps = $this->resource->params;

        //如果需要特殊输出，则解析 vue 文件
        if (isset($ps["export"])) {
            $cnt = new Vue($this->resource->realPath);
            //var_dump($cnt);
        }

        return $cnt;
    }


    /*
     *  按 Mime::getProcessableType 读取本地文件内容
     */
    //默认读取方法
    protected function getContent()
    {
        return "";
    }
    
    //以纯文本形式读取
    protected function getPlainContent()
    {
        $cnt = file_get_contents($this->resource->realPath);
        return $cnt;
    }

    //针对视频资源，不读取
    protected function getVideoContent()
    {
        return "";
    }

    
}