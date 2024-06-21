<?php

/**
 * Attobox Framework / Module Resource
 * Resource Extension
 * 
 * VUE file processor
 */

namespace Atto\Box\resource;

use Atto\Box\Resource;
use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Box\resource\Mime;
use MatthiasMullie\Minify;  //JS/CSS文件压缩

class Vue extends Resource
{
    //export extension
    protected $expExt = null;

    //vue content parse, blocks
    public $template = [];
    public $script = [];
    public $style = [];     //可以有多个style
    public $custom = [];    //自定义语言块

    //自定义的 vue 文件说明，在 <profile></profile> 中定义，json形式
    public $profile = [];

    /**
     * @override getContent
     * @return null
     */
    protected function getContent(...$args)
    {
        $rtp = $this->resType;
        $m = "get".ucfirst($rtp)."Content";
        if (method_exists($this, $m)) {
            $this->$m();
        }

        //parse vue content
        $this->parseTemplate();
        $this->parseScript();
        $this->parseStyle();
        $this->parseCustom();
        $this->parseProfile();

        return $this->content;
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
        //$ps = $this->params;
        $ps = $params;
        
        /** 在输出之前 注入 profile 数据 **/
        if (isset($ps["profile"])) {
            $this->profile = arr_extend($this->profile, $ps["profile"]);
            unset($ps["profile"]);
        }

        if (isset($ps["export"])) {
            $m = "export".ucfirst($ps["export"]);
            if (method_exists($this, $m)) {
                $cnt = $this->$m();
            }
        }
        $this->content = $cnt;
        if (is_null($this->expExt)) $this->expExt = $this->rawExt;

        //sent header
        //$this->sentHeader($this->expExt);
        Mime::header($this->expExt);
        Response::headersSent();


        //echo
        echo $this->content;
        exit;
    }



    /**
     * content parse methods
     */
    //解析 template
    protected function parseTemplate()
    {
        $cnt = $this->content;
        $count = 0;
        $temp = "";
        $regx = "/\<template\>[\s\S]*\<\/template\>/";
        str_each($cnt, $regx, function($str, $i) use (&$count, &$temp) {
            if ($count>0) return false;
            $temp = $str;
            $count++;
        });
        $temp = substr(substr($temp, strlen("<template>")), 0, strlen("</template>")*-1);
        $this->template = [
            "attr" => [],
            "content" => $temp
        ];
        return $this;
    }

    //解析 script
    protected function parseScript()
    {
        $cnt = $this->content;
        $count = 0;
        $temp = "";
        $regx = "/\<script\>[\s\S]*\<\/script\>/";
        str_each($cnt, $regx, function($str, $i) use (&$count, &$temp) {
            if ($count>0) return false;
            $temp = $str;
            $count++;
        });
        $temp = substr(substr($temp, strlen("<script>")), 0, strlen("</script>")*-1);
        $min = $this->min("js", $temp);
        $tarr = explode("export default", $min);
        $this->script = [
            "attr" => [],
            "content" => $temp,
            "min" => $min,
            "script" => $tarr
        ];
        return $this;
    }

    //解析 style
    protected function parseStyle()
    {
        $rst = $this->parseNode("style");
        $all = "";
        foreach ($rst as $i => $sty) {
            $all .= $sty["content"];
        }
        $this->style = [
            "list" => $rst,
            "all" => $all,
            "min" => $this->min("css", $all)
        ];
        return $this;

    }

    //解析自定义语言块
    protected function parseCustom()
    {
        $cnt = $this->content;
        //先排除固定的三种 node
        foreach (["template","script","style"] as $i => $node) {
            $cnt = preg_replace("/\<".$node."[^\>]*\>[\s\S]*\<\/".$node."\>/", "", $cnt);
        }

        $nodes = [];
        $regx = "/\<(\w|\-)+[^\>]*\>[\s\S]*\<\/(\w|\-)+\>/U";
        str_each($cnt, $regx, function($str, $i) use (&$nodes) {
            $str = explode("</", $str);
            $nodes[] = trim($str[1], ">");
        });
        foreach ($nodes as $i => $node) {
            $cnts = $this->parseNode($node);
            $this->custom[$node] = $cnts[0];
        }
    }

    //处理 profile 数据
    protected function parseProfile()
    {
        if (isset($this->custom["profile"]) && is_string($this->custom["profile"]["content"])) {
            $pfs = $this->custom["profile"]["content"];
            $this->profile = j2a($pfs);
        }
    }

    //解析 <node ...>...</node>
    protected function parseNode($node="")
    {
        //var_dump($node);
        $cnt = $this->content;
        $cnts = [];
        //$node = str_replace("-","\\-")
        $regx = "/\<".$node."[^\>]*\>[\s\S]*\<\/".$node."\>/U";
        str_each($cnt, $regx, function($str, $i) use (&$cnts, $node) {
            //var_dump($str);
            $regx = "/\<".$node."[^\>]*\>/";
            $attr = [];
            str_each($str, $regx, function($s, $k) use (&$attr) {
                $attr = $this->parseAttr($s);
            });
            $str = preg_replace($regx, "", $str);
            $str = preg_replace("/\<\/".$node."\>/", "", $str);
            $str = trim(trim($str, "\r\n"));
            //var_dump($str);
            $cnts[] = [
                "attr" => $attr,
                "content" => $str
            ];
        });
        return $cnts;
    }

    //解析行内参数 foo="bar" bar="foo"
    protected function parseAttr($str)
    {
        $attr = [];
        //var_dump($str);
        $regx = "/\s+.+((\=\"[^\"]+\")|\s|\>)/U";
        str_each($str, $regx, function($s, $i) use (&$attr) {
            $s = trim(trim(trim($s, "\r\n"), ">"));
            //var_dump($s);
            $sa = explode("=", str_replace("\"", "", trim($s)));
            $attr[$sa[0]] = count($sa)>1 ? $sa[1] : true;
        });
        return $attr;
    }



    /**
     * process methods
     */
    //输出信息
    public function info($node="")
    {
        $info = [];
        foreach (["template","script","style","custom"] as $i => $nd) {
            $info[$nd] = $this->$nd;
        }
        return $node=="" ? $info : $info[$node];
    }

    //component name，按顺序从 $_GET["name"] / profile["name"] / pathinfo(realPath)["filename"] 中获取
    public function name()
    {
        $name = Request::get("name", $this->profile["name"]);
        if (empty($name)) {
            $name = pathinfo($this->realPath)["filename"];
        }
        return $name;
    }

    //查找父级 operater，返回数组
    public function forefathers()
    {
        $rp = $this->realPath;
        $rparr = explode("/", str_replace(DS,"/",$rp));
        $fs = [];
        for ($i=count($rparr)-2;$i>=0;$i--) {
            $rpi = implode(DS, array_slice($rparr, 0, $i+1)).".vue";
            if (file_exists($rpi)) {
                //array_unshift($fs, new Vue($rpi));
                array_unshift($fs, Resource::create($rpi));
            } else {
                break;
            }
        }
        return $fs;
    }

    //查找父级 operater，返回 $vue->profile[$field] 组成的数组，用于生成名称链
    public function chain($field = "title")
    {
        if (!isset($this->profile[$field])) return [];
        $self = $this->profile[$field];
        $fs = $this->forefathers();
        if (empty($fs)) return [$self];
        $cs = [];
        for ($i=0;$i<count($fs);$i++) {
            $fpi = $fs[$i]->profile;
            if (!isset($fpi[$field])) return [$self];
            $cs[] = $fpi[$field];
        }
        $cs[] = $self;
        return $cs;
    }
    
    //去除换行符，tab
    protected function inline($str) 
    {
        $str = preg_replace("/\r\n/","",$str);
        $str = preg_replace("/\s{2,}/"," ", $str);
        return $str;
    }

    //压缩js/css
    protected function min($ext="js", $cnt)
    {
        $minifier = $ext == "js" ? new Minify\JS() : new Minify\CSS();
        $minifier->add($cnt);
        return $minifier->minify();
    }

    
    
    /**
     * export methods
     */
    //输出 vue 组件的定义结构，并 export default
    protected function exportJs()
    {
        $name = $this->name();
        $sarr = $this->script["script"];

        /** Vue 对象可能需要先 import **/
        //$js_z = "import Vue from '/src/atto/requires/vue.js';";
        //$js_z = "console.log(atto);";

        $js_a = $sarr[0];
        $js_b = $sarr[1];
        $js_b = substr($js_b, 0, -1).",template:`".$this->inline($this->template["content"])."`}";
        if (!empty($this->profile)) {
            $pf = self::min("js", a2j($this->profile));
            $js_brr = explode("data(){return{", $js_b);
            $js_brr[1] = "profile:".$pf.",".$js_brr[1];
            $js_b = implode("data(){return{", $js_brr);
        }
        $js_b = "let comp = Vue.component('".$name."', ".$js_b.");";
        $js_c = $this->style["min"];
        if (!empty($js_c)) {
            //$js_c = "document.querySelector('head').innerHTML+=`<style>".$js_c."</style>`;";
            $js_c = "let sty = document.createElement('div');sty.innerHTML='<style>".$js_c."</style>';sty=sty.childNodes[0];document.querySelector('head').appendChild(sty);";
        }
        $this->expExt = "js";
        return /*$js_z.*/$js_a.";".$js_b.$js_c."export default comp;";
    }

    //输出根组件的内容，export 到 cgy.option.vue.root
    protected function exportRootjs()
    {
        $sarr = $this->script["script"];
        $js_a = $sarr[0];
        $js_b = "let option = ".$sarr[1].";";
        $template = "let template = `".$this->inline($this->template["content"])."`;";
        $style = "let style = `".$this->style["min"]."`;";
        $export = "export default {option, template, style}";
        $this->expExt = "js";
        return $js_a.";".$js_b.$template.$style.$export;
    }

    //输出组件定义对象
    protected function exportCompjs()
    {
        $sarr = $this->script["script"];
        $js_a = $sarr[0];
        $js_b = $sarr[1];
        $js_b = substr($js_b, 0, -1).",template:`".$this->inline($this->template["content"])."`}";
        if (!empty($this->profile)) {
            $pf = self::min("js", a2j($this->profile));
            $js_brr = explode("data(){return{", $js_b);
            $js_brr[1] = "profile:".$pf.",".$js_brr[1];
            $js_b = implode("data(){return{", $js_brr);
        }
        $js_b = "let option = ".$js_b.";";
        $style = "let style = `".$this->style["min"]."`;";
        $export = "export default {option, style}";
        $this->expExt = "js";
        return $js_a.";".$js_b.$style.$export;
    }

    //debug
    protected function exportDump()
    {
        var_dump($this->info());
        return $this->content;
    }
    


    /**
     *  权限检查
     */
    //检查给定的 hash 是否属于某个 atom 操作，并检查权限
    public function authCheckAtom($hash, $usr = null)
    {
        $atom = $this->getAtomByHash($hash);
        if (is_null($atom)) return true;
        if (empty($usr) || empty($usr->uo)) return "用户未登录";
        $tit = "/".implode("/", $this->chain("title"))."/".$atom["title"];
        $ah = $atom["hash"];
        $au = isset($atom["auth"]) ? $atom["auth"] : null;
        if (is_notempty_str($au) || (is_indexed($au) && !empty($au))) {
            if (is_notempty_str($au)) $au = explode(",", $au);
            if (in_array("all",$au)) return true;
            if (in_array("none",$au)) return $tit;
            $ck = $usr->uo->authCheck($au);
        } else {
            $ck = $usr->uo->authCheck($ah);
        }
        return $ck==true ? true : $tit;
    }
    //根据 hash 查找 atom
    protected function getAtomByHash($hash)
    {
        if (!isset($this->profile["atom"])) return null;
        $atoms = $this->profile["atom"];
        $hash = str_replace("_","/", $hash);
        $harr = explode("/", $hash);
        $idx = -1;
        $atom = null;
        for ($i=0;$i<count($atoms);$i++) {
            $ati = $atoms[$i];
            $atharr = explode("/", $ati["hash"]);
            if (count($harr)!=count($atharr)) continue;
            $diff = array_diff_assoc($atharr, $harr);
            $diff = array_values(array_unique($diff));
            if (empty($diff) || (count($diff)==1 && $diff[0]=="*")) {
                $atom = $ati;
                break;
            }
        }
        return $atom;
    }



    /*
     *  static
     */
    //简易解析 profile
    public static function prof($vuefile)
    {
        $cnt = file_get_contents($vuefile);
        if (empty($cnt)) return null;
        $pf = explode("</profile>", explode("<profile>", $cnt)[1])[0];
        return j2a($pf);
    }
}