<?php

/**
 * Attobox Framework / Module Resource
 * Resource Extension
 * 
 * Plain
 */

namespace Atto\Box\resource;

use Atto\Box\Resource;
use Atto\Box\Response;

use MatthiasMullie\Minify;  //JS/CSS文件压缩

class Plain_bak extends Resource
{

    /**
     * @override export
     * export plain file
     * @return void exit
     */
    public function export()
    {
        //输出前 处理
        $this->fixContentBeforeExport();

        //输出
        $this->header()->sent();
        echo $this->resource->content;
        exit;
    }

    protected function fixContentBeforeExport()
    {
        $cnt = $this->resource->content;
        $ps = $this->resource->params;
        $ext = $this->resource->rawExt;

        //export 处理
        if (isset($ps["export"])) {
            //export es6 js module
            if ($ext == "js") {
                if (isset($ps["es6fix"]) && $ps["es6fix"] !== "no") {
                    //针对一些 module 的不规范输出方式与 es6 不匹配的，使用 module.exports 输出
                    $cnt = "let module = {exports:{}},exports = {};\r\n".$cnt;
                    $cnt .= ";\r\nlet ".$ps["export"]." = module.exports; export default ".$ps["export"].";";
                } else if (isset($ps["wxfix"]) && $ps["wxfix"] !== "no") {  //微信jssdk文件处理
                    $carr = explode("(this,", $cnt);
                    $cnt = implode("(cgy.global(),", $carr);
                } else if (isset($ps["fix"])) {
                    $exp = $ps["export"];
                    switch ($ps["fix"]) {
                        case "iconfont" :
                            $c = substr($cnt, 1);   //去除开头 ！
                            $c = substr($c, 0, strlen($c)-9);   //去除尾部 (window);
                            $cnt = "export default ".$c;
                            break;
                    }
                } else {
                    $cnt .= ";\r\nexport default ".$ps["export"].";";
                }
            } else if ($ext == "vue") { //处理 vue 文件
                //var_dump($cnt);
                if (strpos(strtolower($ps["export"]), "js") !== false) $this->resource->rawExt = "js";
                $cnt = $cnt->export($ps["export"]);
            }
        }

        //压缩 js/css 文件
        if (isset($ps["min"]) && $ps["min"] == "yes") {
            //压缩 js/css
            if (in_array($ext, ["js","css"])) {
                /*$minifier = $ext == "js" ? new Minify\JS() : new Minify\CSS();
                $minifier->add($cnt);
                $cnt = $minifier->minify();*/
                $cnt = $this->min($ext, $cnt);
            }
        }

        $this->resource->content = $cnt;
    }

    protected function min($ext="js", $cnt="")
    {
        $minifier = $ext == "js" ? new Minify\JS() : new Minify\CSS();
        $minifier->add($cnt);
        return $minifier->minify();
    }
}