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
use Atto\Box\resource\Uploader;
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

        if ($args[0]=="atto" && count($args)>1) {
            //调用 Attobox 通用系统资源
            //资源路径 [BOX_PATH]/assets
            //array_shift($args); //atto
            //$srcpath = "box/assets/".implode("/", $args);
            $srcpath = "pre/assets/".implode("/", $args);
            $resource = Resource::create($srcpath);
        } else {
            //正常调用资源
            $resource = Resource::create($args);
        }


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
     * file explorer apis
     * 文件管理接口
     *      [host]/src/explorer[/app/appname]/....
     */
    public function explorer(...$args)
    {
        if (empty($args) || (count($args)==2 && $args[0]=="app")) return $this->explorerLoadFilesInDir(...$args);
        $query = Request::input("json");
        //获取 app name 如果存在的话
        if ($args[0]=="app") {
            array_shift($args);         //app
            $app = array_shift($args);  //appname
        } else {
            $app = null;
        }
        //开始处理接口功能
        $action = $args[0];
        switch ($action) {
            //重命名文件
            case "rename":
                $file = $query["file"] ?? null;
                $rename = $query["rename"] ?? null;
                if (empty($file) || empty($rename)) return ["success"=>false];
                $from = $file["path"];
                $to = str_replace($file["name"], $rename, $from);
                $success = rename($from, $to);
                if (!$success) return ["success"=>false];
                $finfo = $this->_getFileInfo($to);
                return [
                    "success" => $success,
                    "file" => $finfo
                ];
                break;
            //重命名文件夹
            case "renamefd":
                $folder = $query["folder"] ?? null;
                $rename = $query["rename"] ?? null;
                if (empty($folder) || empty($rename)) return ["success"=>false];
                if (isset($folder["isNew"]) && $folder["isNew"]==true) {
                    //新建文件夹
                    $to = $folder["path"]."/".$rename;
                    $success = mkdir($to, 0777, true);
                } else {
                    //重命名文件夹
                    $from = $folder["path"];
                    $to = str_replace($folder["name"], $rename, $from);
                    $success = rename($from, $to);
                }
                if (!$success) return ["success"=>false];
                //$finfo = $this->_getFileInfo($to);
                return [
                    "success" => $success,
                    "folder" => [
                        "name" => $rename,
                        "path" => $to,
                        "ctime" => filectime($to)
                    ]
                ];
                break;

            //删除单个文件或文件夹
            case "del":
                $folder = $query["folder"] ?? null;
                $file = $query["file"] ?? null;
                if (empty($folder) && empty($file)) return ["success"=>false];
                if (!empty($folder)) $fp = $folder["path"];
                if (!empty($file)) $fp = $file["path"];

                if (is_dir($fp)) {
                    $success = path_del($fp);
                    $name = $folder["name"];
                } else if (file_exists($fp)) {
                    $success = unlink($fp);
                    $name = $file["name"];
                }
                
                if (!$success) return ["success"=>false];
                return [
                    "success" => $success,
                    "name" => $name
                ];
                break;

            //批量删除文件
            case "delfiles":
                $files = $query["files"] ?? [];
                if (empty($files)) return ["success"=>false];
                $success = true;
                $msg = "";
                foreach ($files as $file) {
                    $fp = $file["path"];
                    if (file_exists($fp)) {
                        $success = $success && unlink($fp);
                        if (!$success) {
                            $msg = "删除文件 ".$file["basename"]." 出错";
                            break;
                        }
                    }
                }
                return [
                    "success" => $success,
                    "msg" => $msg
                ];
                break;
            //复制/剪贴文件
            case "pastefiles":
                $files = $query["files"] ?? [];
                $from = $query["from"] ?? null;
                $to = $query["to"] ?? null;
                $type = $query["type"] ?? "copy";
                if (empty($files) || empty($from) || empty($to)) return ["success"=>false];
                $success = true;
                $msg = "";
                foreach ($files as $file) {
                    $fp = $file["path"];
                    $nfp = str_replace($from, $to, $fp);
                    if ($type=="cut") {
                        //剪贴文件，使用 rename
                        $rst = rename($fp, $nfp);
                    } else if ($type=="copy") {
                        //复制文件，使用 copy
                        $rst = copy($fp, $nfp);
                    } else {
                        $rst = false;
                    }
                    $success = $success && $rst;
                    if (!$success) {
                        $msg = "粘贴文件 ".$file["basename"]." 出错";
                        break;
                    }
                }
                return [
                    "success" => $success,
                    "msg" => $msg
                ];
                break;
            //文件上传
            case "upload":
                $upath = $_POST["upath"];
                $upath = trim($upath, "/");
                if (empty($upath)) return ["success"=>false, "msg"=>"上传路径为空"];
                if (empty($app)) {
                    $upath = "assets/files/".$upath;
                } else {
                    $upath = "app/$app/assets/files/".$upath;
                }
                //如果路径不存在，尝试创建
                if (!is_dir($upath)) {
                    mkdir($upath, 0777, true);
                }
                $acc = $_POST["accept"];
                $acc = str_replace("/*","",$acc);
                $acc = str_replace(".","",$acc);
                $uploader = new Uploader($upath);
                $uploader->mime = $acc;
                $uploader->setUrlPrefix([
                    "download" => "src/".$upath,
                    "delete" => "src/explorer".(empty($app) ? "" : "/app/$app")."/del"
                ]);
                $files = $uploader->upload("files");
                return [
                    "success" => true,
                    "upath" => $upath,
                    "accept" => $acc,
                    "files" => $files
                ];
                break;
            //获取文件信息
            case "finfo":
                $files = $query["files"] ?? [];
                $root = $query["root"] ?? "assets/files";   //默认管理 assets/files 路径下文件
                $local = $query["local"] ?? false;

                if (empty($files) || ($local!==false && !is_string($local))) return ["success"=>false,"msg"=>"参数错误"];
                $finfo = [];
                if ($local===false) {
                    //读取远程文件信息
                    foreach ($files as $fp) {
                        if (!is_notempty_str($fp)) continue;
                        //文件路径必须是远程 URL 形式
                        if (strpos($fp, "://")===false) continue;
                        $fi =Resource::getRemoteFileInfo($fp);
                        unset($fi["headers"]);
                        $fi["url"] = $fp;
                        $finfo[] = $fi;
                    }
                } else {
                    //读取本地文件信息
                    $local = trim($local, "/");
                    //$local = empty($app) ? "assets/files/$local" : "app/$app/assets/files/$local";
                    $local = empty($app) ? "$root/$local" : "app/$app/$root/$local";
                    $localu = str_replace("assets/","", $local);
                    foreach ($files as $fp) {
                        if (!is_notempty_str($fp)) continue;
                        if (strpos($fp, $localu)!==false) {
                            $rfp = $local."/".explode($localu, $fp)[1];
                        } else if (strpos($fp, "://")===false) {
                            $rfp = $local."/".trim($fp, "/");
                        } else {
                            $rfp = null;
                        }
                        if (!empty($rfp)) {
                            //本地文件
                            $rfp = path_find($rfp);
                            if (empty($rfp)) continue;
                            $rfp = path_fix($rfp);
                            $fi = $this->_getFileInfo($rfp);
                        } else {
                            $fi =Resource::getRemoteFileInfo($fp);
                            unset($fi["headers"]);
                            $fi["url"] = $fp;
                        }
                        if (empty($fi)) continue;
                        $finfo[] = $fi;
                    }
                }
                return [
                    "success" => true,
                    "files" => $finfo
                ];
                break;
        }
    }

    //加载后端文件夹文件列表
    public function explorerLoadFilesInDir(...$args)
    {
        $query = Request::input("json");
        $dir = $query["dir"] ?? null;
        $sort = $query["sort"] ?? ["ctime", "desc"];    //排序方式，默认按创建时间降序排序，可选 [size,desc/asc][name,desc/asc]
        $root = $query["root"] ?? "assets/files";       //默认只允许访问 assets/files 文件夹
        $app = null;
        if (count($args)==2 && $args[0]=="app") $app = strtolower($args[1]);
        //if (!empty(Request::$current->app)) {
        if (!empty($app)) {
            //$root = "app/".strtolower(Request::$current->app)."/".$root;
            //$urlpre = "/src/app/".strtolower(Request::$current->app)."/files";
            $root = "app/$app/".$root;
            $urlpre = "/src/app/$app/$root";    //"/src/app/$app/files";
        } else {
            $root = "root/".$root;
            $urlpre = "/src/$root"; //"/src/files";
        }
        if (is_null($dir)) {
            $path = $root;
        } else {
            $path = $root."/".trim($dir, "/");
            $urlpre = $urlpre."/".trim($dir, "/");
        }
        //var_dump($path);
        $path = path_find($path, ["inDir"=>[], "checkDir"=>true]);
        if (empty($path)) Response::json([]);
        //var_dump($path);
        $rst = [
            //"dir" => explode("asstes".DS."files", $path)[1],
            "dir" => explode(str_replace("/", DS, $root), $path)[1],
            "folders" => [],
            "files" => []
        ];
        $dh = @opendir($path);
        while(($f = readdir($dh))!==false) {
            if ($f=="." || $f=="..") continue;
            if ($f=="usr") continue;    //不访问 usr 文件夹
            $fp = $path.DS.$f;
            if (is_dir($fp)) {
                $rst["folders"][] = [
                    "name" => $f,
                    "path" => $fp,
                    "ctime" => filectime($fp),
                ];
            } else {
                /*$finfo = $this->_getFileInfo($fp);
                $finfo = arr_extend($finfo, [
                    "thumb" => $finfo["isImage"] ? $urlpre."/".$f."?thumb=96,96" : null,
                    "url" => $urlpre."/".$f,
                ]);
                $rst["files"][] = $finfo;*/
                $rst["files"][] = $this->_getFileInfo($fp);
            }
        }
        if (empty($rst["files"])) return $rst;
        //排序
        $sby = $sort[0];
        $stp = strtolower($sort[1]);
        $i = $stp=="asc" ? 1 : -1;
        //文件夹只按照 ctime 排序
        if ($sby=='ctime') {
            usort($rst["folders"], function($ia, $ib) use ($sby, $i) {
                return $ia[$sby]>$ib[$sby] ? $i : ($ia[$sby]<$ib[$sby] ? $i*-1 : 0);
            });
        }
        //文件排序
        usort($rst["files"], function($ia, $ib) use ($sby, $i) {
            return $ia[$sby]>$ib[$sby] ? $i : ($ia[$sby]<$ib[$sby] ? $i*-1 : 0);
        });

        return $rst;
    }

    //解析某个文件路径，返回文件信息数组
    protected function _getFileInfo($fp)
    {
        //$app = Request::$current->app;
        //if (!empty($app)) {
        if (strpos($fp, "app/")!==false) {
            $url = "/src/app/".str_replace("assets/","", explode("app/", $fp)[1]);
        } else {
            $url = "/src"."/".explode("assets/", $fp)[1];
        }

        $fsize = filesize($fp);
        $pinfo = pathinfo($fp);
        $ext = strtolower($pinfo["extension"]);
        $isimg = in_array($ext, Mime::$processable["image"]);

        return [
            "name" => $pinfo["filename"],
            "basename" => $pinfo["basename"],
            "path" => $fp,
            "ext" => $ext,
            "mime" => Mime::$mimes[$ext],
            "ctime" => filectime($fp),
            "size" => $fsize,
            "sizestr" => num_file_size($fsize),
            "isImage" => $isimg,
            "thumb" => $isimg ? $url."?thumb=256,256" : null,
            "url" => $url,
            "isVideo" => in_array($ext, Mime::$processable["video"]),
            "isAudio" => in_array($ext, Mime::$processable["audio"]),
        ];
    }

    //dump file info
    public function dump(...$args)
    {
        if ($args[0]=="atto" && count($args)>1) {
            //调用 Attobox 通用系统资源
            //资源路径 [BOX_PATH]/assets
            array_shift($args); //atto
            $srcpath = "box/assets/".implode("/", $args);
            $resource = Resource::create($srcpath);
        } else {
            //正常调用资源
            $resource = Resource::create($args);
        }


        //未找到资源
        if (is_null($resource)) {
            var_dump(null);
        }

        var_dump($resource->info());
        var_dump($resource);

        exit;

        //输出资源
        //$resource->export();
        //var_dump($resource->info());
        
        //exit;
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



    public function tst()
    {
        //$f = "https://thirdwx.qlogo.cn/mmopen/vi_32/6SKDUlZlYCqBgHxtgxsQD0O8GmcxDYRlaO3UKmKsQkPn5JbmgtP5Jr50q6MbaJGMEglOTBXehzWFEw9nj5Bbiag/132";
        $f = "pre/qy.cgy.design/app/pms/assets/files/usr/E9999/btcalc.xlsx";
        $f = path_find($f);
        var_dump($f);
        $fo = \Atto\Box\resource\Excel::create($f);
        //var_dump(class_exists("\PHPExcel_IOFactory"));
        var_dump($fo);
    }



    /**
     * upload resource
     */

    /*public function upload()
    {
        
    }*/
    
}