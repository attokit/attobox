<?php

/**
 * Attobox Framework / Module Resource
 * route "src"
 * response to url "//host/src/foo/bar/..."
 */

namespace Atto\Box\route;

use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Box\Resource;
use Atto\Box\resource\Mime;
use Atto\Box\App;

class Src extends Base
{
    //route info
    public $intr = "Resource模块路由";       //路由说明，子类覆盖
    public $name = "Src";                   //路由名称，子类覆盖
    public $appname = "";                   //App路由所属App名称，子类覆盖
    public $key = "Atto/Box/route/Src";     //路由调用路径

    //此路由是否 不受 WEB_PAUSE 设置 影响
    public $unpause = true;


    /**
     * Resource 模块
     * 资源调用统一入口
     * 直接输出资源
     * @return void
     */
    public function defaultMethod()
    {
        //var_dump(cls("resource/creator/Complie"));
        $args = func_get_args();
        $resource = Resource::create($args);

        //未找到资源
        if (is_null($resource)) {
            return [
                "status" => 404
            ];
        }

        //输出资源
        $resource->export();
        //var_dump($resource->info());
        
        exit;
    }

    /**
     * 通过 use 参数，将文件夹下多个 es6 模块文件合并成单个 es6 模块文件，方便一次性导入多个 es6 模块
     * 模块文件必须 使用 export default {...} 语句
     * import mds from '//host/src/es6/foo/bar.js?use=md1,md2,md3'
     */
    public function es6(...$args)
    {
        $path = str_replace(".js", "", implode("/", $args));
        $dir = path_find($path, ["inDir"=>ASSET_DIRS]);
        //var_dump($dir);
        if (is_null($dir)) Response::code(404);
        $content = [];
        $use = Request::get("use", "all");
        if (is_notempty_str($use) && $use != "all") $use = arr($use);
        if (is_notempty_arr($use)) {
            $dn = str_replace(".js", "", array_slice($args, -1)[0]);
            if (!in_array($dn, $use)) {
                array_unshift($use, $dn);
            }
        }
        //var_dump($use);
        $uss = [];
        path_traverse($dir, function($p, $f) use (&$uss, $use) {
            if (!is_dir($p.DS.$f)) {
                //$fn = explode(".", $f)[0];
                $pi = pathinfo($f);
                $fn = $pi["filename"];
                if ($use == "all" || in_array($fn, $use)) {
                    $uss[] = $fn;
                }
            }
        });
        $fns = [];
        foreach ($uss as $i => $f) {
            $fn = str_replace(".","_",$f);
            //$fu = "/src/".$path."/".$f.".js";
            $fns[] = $fn;
            $content[] = "import $fn from '/src/$path/$f.js'";
        }
        $content[] = "export default {";
        $content[] = implode(", ", $fns);
        $content[] = "}";

        $cnt = implode("\r\n", $content);
        Mime::header("js");
        Response::echo($cnt);

        exit;
    }

    /**
     * load *.vue 文件
     */
    public function vueloader(...$args)
    {
        $predir = null;
        if (!empty($args)) {
            if (App::has($args[0])) {
                array_unshift($args, 'app');
            }
        } else {
            $args[] = "root";
        }
        $args[] = "vue/components";
        $predir = path_find(implode("/",$args), [
            "inDir" => ASSET_DIRS,
            "checkDir" => true
        ]);
        if (is_null($predir)) Response::code(404);
        //var_dump($predir);exit;
        $vues = Request::get("use","");
        $vues = is_notempty_str($vues) ? explode(",", $vues) : [];
        if (!empty($vues)) {
            $js = [];
            $js[] = "const components = {};";
            for ($i=0;$i<count($vues);$i++) {
                $vue = $vues[$i];
                $vf = $predir."/".$vue.".vue";
                if (!file_exists($vf)) continue;
                /*$res = Resource::create($vf, [
                    "export" => "compjs"
                ]);*/
                $vu = Resource::toUri($vf);
                $vp = "/src/".$vu."?export=compjs";
                $varr = explode("/", $vue);
                $vn = array_slice($varr, -1)[0];

                /*if (!is_null($predir)) {
                    $vf = $predir."/".$vue.".vue";
                } else {
                    if (strpos($vue,"vue/components")===false) {
                        $varr = explode("/", $vue);
                    }
                    $vf = path_find($vue.".vue", [
                        "inDir" => ASSET_DIRS,
                        "checkDir" => false
                    ]);
                }
                $vpre = (!is_null($predir)?$predir."/":"");
                $vf = !is_null($predir) ? $vpre.$vue : path_find($vue,)*/

                /*$varr = explode(".", $vue);
                $app = $varr[0];
                if (App::has($app)) {
                    array_splice($varr, 1, 0, "vue/components");
                } else {
                    array_unshift($varr, 'root/vue/components');
                }
                //var_dump(implode('/',$varr).".vue");
                $vf = path_find(implode('/',$varr).".vue", ["checkDir"=>false]);
                //var_dump($vf);
                if (is_null($vf)) continue;
                $vp = '/src/'.implode('/', $varr).".vue?export=compjs";
                $vn = array_slice($varr, -1)[0];
                if (strpos($vn,"-")===false) {
                    $vn = implode("-",$varr);
                    if (App::has($app)) {
                        $vn = str_replace("vue/components-","",$vn);
                    } else {
                        $vn = str_replace("root/vue/components-","",$vn);
                    }
                }*/
                $js[] = "import('".$vp."').then(comp=>{";
                $js[] = "   if (comp.default == undefined || typeof comp.default != 'object') return;";
                $js[] = "   comp = comp.default;";
                $js[] = "   let option = comp.option, style = comp.style;";
                $js[] = "   if (typeof style == 'string' && style!='') {";
                $js[] = "       let d = document.createElement('div');";
                $js[] = "       d.innerHTML = '<style>'+style+'</style>';";
                $js[] = "       document.querySelector('head').appendChild(d.childNodes[0]);";
                $js[] = "   }";
                $js[] = "   components['".$vn."'] = option;";
                $js[] = "});";
            }
            $js[] = "export default components;";
            $jsstr = implode("\r\n", $js);
        } else {
            $jsstr = "export default {}";
        }
        Mime::header("js");
        Response::echo($jsstr);
    }



    /**
     * upload resource
     */

    /*public function upload()
    {
        
    }*/
    
}