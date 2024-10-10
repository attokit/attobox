<?php

/**
 * Attobox Framework / Uac 权限管理器，操作类型与操作列表管理类
 * 
 * 各管理系统应继承并实现各自的 Parser 类：
 *      在 [webroot]/library/uac/ 路径下(或 [webroot]/app/*ms/library/uac/)建立与 各自管理系统 对应的 OperationsParser 类，如： PmsOperationsParser 类
 *      这个 Parser 类继承自 \Atto\Box\uac\OperationsParser 并应该：
 *          定义操作的类型（即权限的类型），如：menus 菜单类型操作(权限)，curd 数据操作(权限)，atom 按钮等原子操作(权限)，等
 *          定义获取这些类型操作列表的方法，应这样命名：getMenusOperations(), getCurdOperations(), getAtomOperations(), 等
 *          定义一个总入口方法，来获取所有操作，并输出为数组，方法命名为：getOperations()
 *          获取的操作列表数组结构应为：

            //操作名称 like：category-group-oprname-atomopr，
            //如：menus-admin-doc-publish 表示一个原子操作（发布通知，属于 admin 菜单组，doc 文档管理项下）
            {
                //操作类型数组
                otypes: [sys, menus, curd, atom],
                //操作列表，有序数组
                operations: [
                    {
                        name: 'sys-all',    //必选
                        label: 'SUPER',     //menus类型必选参数
                        title: 'SUPER',     //必选
                        //可选参数
                        icon: '',
                        desc: '',
                        role: '',   //此操作需要的特别的 role 角色
                        ...
                    },
                    ...
                ]
            }

 */

namespace Atto\Box\uac;

use Atto\Box\Request;
use Atto\Box\request\Url;
use Atto\Box\Response;
use Atto\Box\Route;
use Atto\Box\Uac;

class OperationsParser
{
    //定义操作类型
    public $otypes = [
        "sys",  //系统操作
        //"menu", //菜单项
        //"api",  //api 访问
        //"db",   //数据库操作
        //"atom"  //原子操作，隶属于菜单项下，是菜单页面内的 button 操作
    ];

    //管理系统 namespace
    public $ms = "";

    /**
     * sys-oprs 系统操作
     * !!! 如果不是必须的，子类不要覆盖 !!!
     * !!! 不同的 uac 系统可以有不同的系统级权限，但是 sys-login / sys-super / sys-role-*** 是必须要的
     */
    public $sysOprs = [
        [
            "name" => "sys-login",
            "title" => "登录系统权限"
        ],

        //SUPER权限
        [
            "name" => "sys-super",
            "title" => "超级管理员权限"
        ],

        //用以区分各用户 role
        //在 app/appname/library/config.php 中定义
        /*[
            "name" => "sys-role-normal",
            "title" => "普通用户角色权限"
        ],
        [
            "name" => "sys-role-db",
            "title" => "数据库操作用户角色权限"
        ],
        [
            "name" => "sys-role-query",
            "title" => "查询者用户角色权限"
        ],
        [
            "name" => "sys-role-financial",
            "title" => "财务用户角色权限"
        ],
        [
            "name" => "sys-role-stocker",
            "title" => "仓库用户角色权限"
        ],
        [
            "name" => "sys-role-purchase",
            "title" => "采购用户角色权限"
        ],
        [
            "name" => "sys-role-qc",
            "title" => "质检用户角色权限"
        ],
        [
            "name" => "sys-role-rnd",
            "title" => "研发用户角色权限"
        ],
        [
            "name" => "sys-role-product",
            "title" => "产品管理员角色权限"
        ],
        [
            "name" => "sys-role-salesuper",
            "title" => "销售主管角色权限"
        ],
        [
            "name" => "sys-role-salekf",
            "title" => "销售客服角色权限"
        ],*/

        //系统级数据库权限
        [
            "name" => "sys-directedit",
            "title" => "直接编辑记录权限"
        ],
        [
            "name" => "sys-db-manual",
            "title" => "直接编辑数据库权限"
        ],
    ];

    /**
     * 必须的入口方法
     */

    /**
     * 获取全部操作列表
     * @param String $otype 某个指定的操作类型，如 menu 或 menu-admin，不指定则获取所有
     * @return Array
     */
    public function getOperations($otype=null)
    {
        if (is_notempty_str($otype)) {
            if (strpos($otype, "-")!==false) {
                $oarr = explode("-", $otype);
                $otype = array_shift($oarr);
                $group = implode("-", $oarr);
            } else {
                $group = null;
            }
            $m = "get".ucfirst($otype)."Operations";
            if (!method_exists($this, $m)) return [];
            $oprs = $this->$m();
            if (empty($oprs)) return [];
            if (is_null($group)) {
                return [
                    "otypes" => [$otype],
                    "operations" => $oprs
                ];
            }
            $prefix = $otype."-".$group."-";
            $subs = array_filter($oprs, function($opr) use ($prefix) {
                $n = $opr["name"];
                return substr($n, 0, strlen($prefix)) == $prefix;
            });
            $subs = array_merge($subs, []);
            return [
                "otypes" => [$otype],
                "operations" => $subs
            ];
        } else {
            $tps = $this->otypes;
            $oprs = [
                "otypes" => [],
                "operations" => []
            ];
            for ($i=0;$i<count($tps);$i++) {
                $tpi = $tps[$i];
                $opris = $this->getOperations($tpi);
                if (!empty($opris)) {
                    $oprs["otypes"][] = $tpi;
                    array_push($oprs["operations"], ...$opris["operations"]);
                }
            }
            return $oprs;
        }
    }

    //按下拉列表形式获取所有 operations
    public function getOperationsValues()
    {
        return [];
    }

    /**
     * sys 系统操作
     * !!! 如果不是必须的，子类不要覆盖 !!!
     * !!! 不同的 uac 系统可以有不同的系统级权限，但是 sys-login / sys-super / sys-role-*** 是必须要的
     * @return Array
     */
    public function getSysOperations()
    {
        $oprs = array_merge([], $this->sysOprs);
        //读取预定义的 UAC_ROLE 数组
        $roles = Uac::getUacConfig("role");
        $rolesname = Uac::getUacConfig("role_name");
        //var_dump($roles);
        if (is_array($roles) && !empty($roles)) {
            for ($i=0;$i<count($roles);$i++) {
                $oprs[] = [
                    "name" => "sys-role-".$roles[$i],
                    "title" => $rolesname[$i]."角色权限"
                ];
            }
        }

        //查找微信账号中的权限操作
        $wxoprs = $this->getWxSysOperations();
        $oprs = array_merge($oprs, $wxoprs);
        //array_push($oprs, $wxoprs);

        return $oprs;
    }

    /**
     * 获取微信账号中的操作权限
     */
    protected function getWxSysOperations()
    {
        $wxaccount = Uac::getUacConfig("wx");
        $oprs = [];
        
        //$wxdir = PRE_PATH.DS."wx.cgy.design".DS."app".DS.$wxaccount.DS."cache".DS."scan".DS."scene";
        //var_dump(path_fix($wxdir));

        //在这些 [wx.cgy.design/app/wxaccount/]**** 路径中查找 json 文件
        $wxdirpre = PRE_PATH.DS."wx.cgy.design".DS."app".DS.$wxaccount;
        $dirs = [
            "cache".DS."scan".DS."scene",
            "cache".DS."sent"
        ];
        for ($i=0;$i<count($dirs);$i++) {
            $wxdir = $wxdirpre.DS.$dirs[$i];
            if (is_dir($wxdir)) {
                $dh = opendir($wxdir);
                while(($f = readdir($dh))!==false) {
                    if (strpos($f,".json")===false) continue;
                    $st = j2a(file_get_contents($wxdir.DS.$f));
                    if (is_def($st,"settings") && is_def($st["settings"], "operation") && is_def($st["settings"], "title")) {
                        $oprs[] = [
                            "name" => $st["settings"]["operation"],
                            "title" => $st["settings"]["title"]
                        ];
                    }
                }
            }
        }

        //在 [wx.cgy.design/app/wxaccount/]cache/auth.json 中保存有其他的预定义的 操作权限列表
        $wxauth = PRE_PATH.DS."wx.cgy.design".DS."app".DS.$wxaccount.DS."cache".DS."auth.json";
        if (file_exists($wxauth)) {
            $wau = j2a(file_get_contents($wxauth));
            $auth = $wau["authes"] ?? null;
            if (!empty($auth) && is_indexed($auth)) {
                $oprs = array_merge($oprs, $auth);
            }
        }
        return $oprs;
    }

    /**
     * 系统权限相关方法
     * !!! 子类不要覆盖 !!!
     */

    public function attachSysOperations()
    {

    }



    /**
     * 子类实现
     */

    /**
     * api 访问
     * @return Array
     */
    public function getApiOperations()
    {
        //查找通用 apis
        $apis = Route::getApis();
        //查找当前 app 中的 apis
        //$appn = strtolower(explode("\\App\\", explode("\\uac", get_class($this))[0])[1]);
        $appn = Request::$current->app;
        if (!empty($appn)) {
            $appcls = cls("App/".ucfirst($appn));
            if (class_exists($appcls)) {
                $appms = cls_methods_filter($appcls, function($mi) {
                    return substr($mi->name, 0,3)=="api" && strpos($mi->getDocComment(),"<%")!==false;
                }, null);
                $this->mergeMethods($appms, $apis, function($mi) use ($appn) {
                    return [
                        "name" => "app-$appn-".strtolower(substr($mi->name, 3)),
                        "title" => trim(explode("%>",explode("<%",$mi->getDocComment())[1])[0])
                    ];
                });
            }
            //查找当前 app 中所有 record 类的 api 方法，查找 app/[appname]/record/ 路径下的所有 类文件
            $rdir = path_find("app/$appn/record", ["inDir"=>"", "checkDir"=>true]);
            if (is_dir($rdir)) {
                $rh = opendir($rdir);
                $dbs = [];
                while(($dbn = readdir($rh))!==false) {
                    if ($dbn=="." || $dbn==".." || !is_dir($rdir.DS.$dbn)) continue;
                    $dbs[] = $dbn;
                }
                closedir($rh);
                for ($i=0;$i<count($dbs);$i++) {
                    $dbi = $dbs[$i];
                    $rdi = $rdir.DS.$dbi;
                    $rh = opendir($rdi);
                    while(($fn = readdir($rh))!==false) {
                        if ($fn=="." || $fn==".." || is_dir($rdi.DS.$fn)) continue;
                        if (strpos($fn, EXT)===false) continue;
                        $dbn = strtolower($dbi);
                        $tbn = strtolower(str_replace(EXT, "", $fn));
                        $clsn = "App/$appn/record/$dbn/".ucfirst($tbn);
                        $rcls = cls($clsn);
                        if (class_exists($rcls)) {
                            $fsi = cls_methods_filter($rcls, function($mi) {
                                return substr($mi->name, 0,3)=="api" && strpos($mi->getDocComment(),"<%")!==false;
                            }, null);
                            $this->mergeMethods($fsi, $apis, function($mi) use ($appn, $dbn, $tbn) {
                                return [
                                    "name" => "app-$appn-$dbn-$tbn-".strtolower(substr($mi->name, 3)),
                                    "title" => trim(explode("%>",explode("<%",$mi->getDocComment())[1])[0])
                                ];
                            });
                        }
                    }
                    closedir($rh);
                }
            }
        }

        if (empty($apis)) return [];
        $napis = array_map(function($api) {
            return [
                "name" => "api-".$api["name"],
                "title" => $api["title"]
            ];
        }, $apis);
        $napis = array_merge($napis, []);
        //var_export($napis);
        return $napis;
    }

    /**
     * db 数据库操作
     * @return Array
     */
    public function getDbOperations()
    {
        //$dir = path_find("library/db/config");
        $dbp = Uac::prefix("library/db","path");
        $cfp = "$dbp/config";
        $dir = path_find($cfp);
        if (empty($dir)) return [];
        $oprs = [];
        path_traverse($dir, function($path, $file) use (&$oprs) {
            if (!is_file($path.DS.$file) || substr($file, -5)!==".json") return "_continue_";
            $cf = $path.DS.$file;
            $conf = j2a(file_get_contents($cf));
            $tbs = array_keys($conf["table"]);
            for ($i=0;$i<count($tbs);$i++) {
                $tbi = $tbs[$i];
                $oprs[] = [
                    "name" => "db-".$conf["name"]."-".$tbi."-directedit",
                    "title" => "通过表格组件直接编辑 ".$conf["table"][$tbi]["title"]." ".$conf["xpath"]."/".$tbi
                ];
            }
        });
        return $oprs;
    }

    /**
     * 获取 nav 列表
     * 用户 nav vue 文件保存在 [app/appname | webroot]/assets/nav/ 文件夹下
     * !!! 不查找普通 nav !!! 用户普通 nav 在 route 或 app 类内部实现，带有 <%nav注释%> 的 public 方法
     */
    public function getNavOperations($dir=null, $uac=null)
    {
        //查找通用 navs
        //$navs = Route::getNavs();
        $navs = [];     //一维数组包含所有 nav oprs
        $navt = [];     //nav tree 树状结构的 navs
        //查找当前 app 中的 navs
        $app = Request::$current->app;
        /*if (!empty($app)) {
            //查找在 app 类内部定义的 nav 方法
            $appcls = cls("App/".ucfirst($app));
            if (class_exists($appcls)) {
                $appms = cls_methods_filter($appcls, function($mi) {
                    return strpos($mi->getDocComment(),"<%nav:")!==false;
                }, \ReflectionMethod::IS_PUBLIC);
                $this->mergeMethods($appms, $navs, function($mi) use ($app) {
                    return [
                        "name" => "app-$app-".strtolower($mi->name),
                        "title" => trim(explode("%>",explode("<%nav:",$mi->getDocComment())[1])[0])
                    ];
                });
            }
        }*/

        //查找 nav vue
        if (is_notempty_str($dir)) {
            $navd = path_find($dir, ["checkDir"=>true]);
        } else {
            $navd = empty($app) ? "root/assets/nav" : "app/$app/assets/nav";
            $navd = path_find($navd, ["checkDir"=>true]);
        }
        if (!empty($navd)) {
            //遍历 nav 路径
            function _traverse_nav_ ($dir, $uac) {
                $dh = opendir($dir);
                $navarr = [];
                $navtree = [];
                while (($f=readdir($dh))!==false) {
                    if ($f=="." || $f=="..") continue;
                    $fn = $dir.DS.$f;
                    $fnm = str_replace(".vue","",str_replace(DS, "-", explode("nav".DS,$fn)[1]));
                    $farr = explode("-", $fnm);
                    if (is_dir($fn)) {
                        $nsf = $fn.DS."index.json";
                        if (file_exists($nsf)) {
                            $ns = j2a(file_get_contents($nsf));
                            $ns["name"] = "nav-".$fnm;
                            //if (!empty($app)) $ns["name"] = "app-".strtolower($app)."-".$ns["name"];
                            $ns["id"] = $ns["sort"];
                            /*$ns["comp"] = [
                                "name" => $fnm,
                                "url" => "/nav"."/".implode("/", $farr),
                                "params" => isset($ns["compParams"]) ? $ns["compParams"] : []
                            ];
                            if (isset($ns["compParams"])) unset($ns["compParams"]);
                            $lock = $ns["lock"] ?? false;
                            $lock = is_bool($lock) ? $lock : false;
                            $ns["lock"] = $lock;
                            $ns["narr"] = $farr;*/

                            $trav = _traverse_nav_($fn, $uac);
                            
                            $navarr[] = $ns;
                            $navarr = array_merge($navarr, $trav["navarr"]);

                            $ns["children"] = $trav["navtree"];
                            $navtree[] = $ns;
                        }
                        //_traverse_nav_($fn, $navarr);
                    } else if (substr($f,-4)=='.vue') {
                        $fcnt = file_get_contents($fn);
                        $fcnt = str_replace("\r\n","",$fcnt);
                        $profile = j2a(explode("<profile>", explode("</profile>", $fcnt)[0])[1]);
                        $profile["name"] = "nav-".$fnm;
                        $profile["id"] = $profile["sort"];
                        $profile["comp"] = [
                            "name" => $fnm,
                            "url" => "/nav"."/".implode("/", $farr),
                            "props" => isset($profile["props"]) ? $profile["props"] : []
                        ];
                        if (isset($profile["props"])) unset($profile["props"]);
                        $lock = $profile["lock"] ?? false;
                        $lock = is_bool($lock) ? $lock : false;
                        $profile["lock"] = $lock;
                        $profile["narr"] = $farr;
                        
                        if (!empty($uac)) {
                            $unavs = $uac->getUsrAuthByType("nav");
                            if (!in_array($profile["name"], $unavs) && !$uac->isSuperUsr()) continue;
                        }
                        $navarr[] = $profile;
                        $navtree[] = $profile;
                    }
                }
                closedir($dh);
                return [
                    "navarr" => $navarr,
                    "navtree" => $navtree
                ];
            }
            //根据 sort 对 navs 进行排序
            function _sort_nav_(&$navs) {
                usort($navs, function($ia, $ib) {
                    return $ia["sort"]>$ib["sort"] ? 1 : ($ia["sort"]<$ib["sort"] ? -1 : 0);
                });
                for ($i=0;$i<count($navs);$i++) {
                    if (empty($navs[$i]["children"])) continue;
                    _sort_nav_($navs[$i]["children"]);
                }
            }
            //开始查找

            $trav = _traverse_nav_($navd, $uac);
            $navs = array_merge($navs, $trav["navarr"]);
            $navt = $trav["navtree"];
        }
        
        /*if (empty($navs)) return [];
        $nnavs = array_map(function($nav) {
            $nav["name"] = "nav-".$nav["name"];
            return $nav;
        }, $navs);
        $nnavs = array_merge($nnavs, []);
        //var_export($nnavs);

        //将普通 nav 方法 和 nav vue 组件分离
        $navvue = [];
        $navmethod = [];
        for ($i=0;$i<count($nnavs);$i++) {
            if (isset($nnavs[$i]["sort"])) {
                $navvue[] = $nnavs[$i];
            } else {
                $navmethod[] = $nnavs[$i];
            }
        }*/
        //对 nav vue 组件 按 sort 排序
        //usort($navs, function($ia, $ib) {
        //    return $ia["sort"]>$ib["sort"] ? 1 : ($ia["sort"]<$ib["sort"] ? -1 : 0);
        //});
        _sort_nav_($navs);
        _sort_nav_($navt);

        if (empty($dir) && empty($uac)) {
            return $navs;
        } else {
            return [
                "navs" => $navs,
                "navtree" => $navt
            ];
        }

        return array_merge($navvue, $navmethod);

        return [
            "vue" => $navvue,
            "method" => $navmethod
        ];
    }



    /**
     * tools
     */

    //将找到的 method 合并到目标数组
    protected function mergeMethods($methods, &$target, $getMethodInfo)
    {
        if (!empty($methods)) {
            $ms = [];
            foreach ($methods as $name => $mi) {
                $ms[] = $getMethodInfo($mi);;
            }
            array_push($target, ...$ms);
        }
    }


}