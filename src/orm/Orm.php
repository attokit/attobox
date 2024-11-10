<?php
/**
 * Attokit/Attoorm common methods
 */

namespace Atto\Orm;

use Atto\Orm\DbApp;
use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Atto\Box\Request;

class Orm 
{
    const NS = "\\Atto\\Orm\\";

    /**
     * 缓存已实例化的 DbApp 
     */
    public static $APP = [];

    /**
     * 记录 Orm 是否已经初始化
     */
    public static $inited = false;
    //Orm 服务初始化时，必须要加载的 DbApp 列表
    public static $initRequiredApps = [
        "Uac"
    ];

    /**
     * 事件处理器
     */
    protected static $event = [
        /*
        "event-name" => [
            event listener,
            可以是：DbApp实例  or  Dbo实例  or  Model数据模型(表)类  ,
            ...
        ],
        */
    ];

    /**
     * Atto-orm 服务初始化
     * 当实例化任意 DbApp 时，将执行此操作
     * @param String $callby 此初始化动作是在哪个 DbApp 中被调用
     * @param Array $opt Orm 服务初始化参数
     * @return Orm
     */
    public static function Init($callby, $opt=[])
    {
        if (self::$inited==false) {
            $reqs = self::$initRequiredApps;
        } else {
            $reqs = [];
        }

        //按顺序，实例化 Orm 服务必须的 DbApp
        $appreqs = $opt["requiredApps"] ?? [];
        $reqs = array_merge($reqs, $appreqs);
        if (!empty($reqs)) {
            $reqs = array_merge(array_flip(array_flip($reqs)));
            if (in_array($callby, $reqs)) array_splice($reqs, array_search($callby, $reqs), 1);
            if (!empty($reqs)) {
                for ($i=0;$i<count($reqs);$i++) {
                    $apn = ucfirst($reqs[$i]);
                    Orm::$apn();
                }
            }
        }

        self::$inited = true;
        return self::class;
    }

    /**
     * __callStatic
     * @param String $key
     * @param Mixed $args
     */
    public static function __callStatic($key, $args)
    {
        /**
         * Orm::App()
         * 返回 DbApp 实例
         */
        if (Orm::hasApp($key)) {
            //已实例化的，直接返回
            if (Orm::appInsed($key)) return Orm::$APP[$key];
            //实例化 DbApp
            $cls = cls("app/$key");
            if (!class_exists($cls)) return null;
            $app = new $cls();
            //缓存
            Orm::cacheApp($app);
            //返回 DbApp 实例
            return $app;
        }

        return null;
    }

    /**
     * 获取 app 路径下所有 可用的 DbApp name
     * @return Array [ DbAppName, ... ]
     */
    public static function apps()
    {
        $appcls = Orm::cls("DbApp");
        $dir = APP_PATH;
        $dh = @opendir($dir);
        $apps = [];
        while(($app = readdir($dh))!==false) {
            if ($app=="." || $app=="..") continue;
            $dp = $dir.DS.$app;
            if (!is_dir($dp)) continue;
            if (!file_exists($dp.DS.ucfirst($app).EXT)) continue;
            $cls = cls("app/".ucfirst($app));
            if (!class_exists($cls)) continue;
            if (!is_subclass_of($cls, $appcls)) continue;
            $apps[] = ucfirst($app);
        }
        return $apps;
    }

    /**
     * 判断是否存在 给出的 DbApp
     * @param String $app DbApp name like: Uac
     * @return Bool
     */
    public static function hasApp($app)
    {
        $apps = Orm::apps();
        return in_array(ucfirst($app), $apps);
    }

    /**
     * 判断给出的 DbApp 是否已经实例化
     * @param String $app DbApp name like: Uac
     * @return Bool
     */
    public static function appInsed($app)
    {
        return isset(Orm::$APP[ucfirst($app)]);
    }

    /**
     * 缓存 DbApp 实例
     * @param DbApp $app 实例
     * @return Orm self
     */
    public static function cacheApp($app)
    {
        if (!$app instanceof DbApp) return self::class;
        $appname = $app->name;
        Orm::$APP[$appname] = $app;
        return self::class;
    }

    /**
     * 创建 DbApp 路径以及文件
     * @param String $app name
     * @param Array $opt 更多创建参数
     * @return Orm self
     */
    public static function createAppFile($app, $opt=[])
    {
        //即使存在 app 路径，也需要检查是否存在下级必要路径，因此不检查 app 主路径是否存在
        //if (self::hasApp($app)) return self::class;
        $dbs = $opt["dbOptions"] ?? [];
        $dbns = array_keys($dbs);
        $app = strtolower($app);
        //创建主文件夹
        $approot = APP_PATH.DS.$app;
        if (!is_dir($approot)) @mkdir(APP_PATH.DS.$app, 0777);
        //创建必要目录
        $ds = [
            "assets","db","library","model","page",
            "db".DS."sqlite",
            "db".DS."config"
        ];
        for ($i=0;$i<count($dbns);$i++) {
            $ds[] = "model".DS.strtolower($dbns[$i]);
            $ds[] = "db".DS."config".DS.strtolower($dbns[$i]);
        }
        foreach ($ds as $i => $di) {
            $diri = $approot.DS.$di;
            if (!is_dir($diri)) @mkdir($diri, 0777);
        }
        //创建 app 主文件
        $mf = $approot.DS.ucfirst($app).EXT;
        if (!file_exists($mf)) {
            $tmpd = [
                "app" => [
                    "name" => ucfirst($app),
                    "intr" => ""
                ],
                "dbOptions" => arr_extend([
                    "main" => [
                        "type" => "sqlite",
                        "database" => "main.db"
                    ]
                ], $dbs),
            ];
            var_export($tmpd["dbOptions"]);
            //从缓冲区读取 sql
            $dbos = ob_get_contents();
            //清空缓冲区
            ob_clean();
            $dbos = str_replace("array (","[", $dbos);
            $dbos = str_replace(")","]", $dbos);
            $dbos = str_replace("'","\"", $dbos);
            $tmpd["dbos"] = $dbos;
            $tmp = file_get_contents(path_find("root/library/temp/dbapp.tmp"));
            $tmp = str_tpl($tmp, $tmpd);
            $fh = @fopen($mf, "w");
            @fwrite($fh, $tmp);
            @fclose($fh);
        }
        return self::class;
    }

    /**
     * 创建 DbApp 路径下 Model 路径以及文件
     * @param String $app name like: Uac
     * @param String $dbn db name like: main
     * @param Array $conf model 参数 数据表参数 json 内容
     * @return Orm self
     */
    public static function createModelFile($app, $dbn, $conf=[])
    {
        if (empty($conf)) return self::class;
        $mdn = $conf["name"] ?? null;
        if (empty($mdn)) return self::class;
        $mdp = APP_PATH.DS.strtolower($app).DS."model".DS.strtolower($dbn);
        if (!is_dir($mdp)) @mkdir($mdp, 0777);
        $mdf = $mdp.DS.ucfirst($mdn).EXT;
        if (file_exists($mdf)) return self::class;
        $tmp = file_get_contents(path_find("root/library/temp/model.tmp"));
        $tmp = str_tpl($tmp, [
            "appName" => ucfirst($app),
            "dbName" => strtolower($dbn),
            "modelName" => ucfirst($mdn),
            "table" => [
                "name" => $conf["table"],
                "title" => $conf["title"],
                "desc" => $conf["desc"],
            ]
        ]);
        $fh = @fopen($mdf, "w");
        @fwrite($fh, $tmp);
        @fclose($fh);
        return self::class;
    }


    /**
     * event 事件订阅与处理
     */

    /**
     * event 方法
     * 获取所有 $event 事件 的 订阅者
     * @param String $event 事件名称
     * @return Array
     */
    protected static function eventListeners($event)
    {
        $evts = self::$event;
        if (!isset($evts[$event]) || !is_notempty_arr($evts[$event])) return [];
        $evt = $evts[$event];
        $ock = $event."-once";
        $once = (!isset($evts[$ock]) || !is_notempty_arr($evts[$ock])) ? [] : $evts[$ock];
        $ls = array_merge($evt, $once);
        if (empty($ls)) return [];
        //return array_merge(array_flip(array_flip($ls)));  //value可能是 object 无法 flip
        return $ls;
    }

    /**
     * 判断是否合法的 listener 
     * @param Mixed $listener 订阅者
     * @return Bool
     */
    protected static function eventListenerIsLegal($listener)
    {
        if (
            $listener instanceof DbApp ||                   //DbApp实例
            $listener instanceof Dbo ||                     //Dbo实例
            $listener instanceof Model ||                   //数据记录实例
            is_subclass_of($listener, self::cls("Model"))   //数据表(模型)类
        ) {
            return true;
        }
        return false;
    }

    /**
     * event 方法
     * 订阅事件
     * @param Mixed $listener 订阅者，可以是：DbApp实例  or  Dbo实例  or  Model数据模型(表)类
     * @param String $event 事件名称
     * @param Bool $once 是否一次性事件
     * @return Bool
     */
    public static function eventListen($listener, $event, $once=false)
    {
        if (self::eventListenerIsLegal($listener)) {
            $ls = self::eventListeners($event);
            if (!in_array($listener, $ls)) {
                if (!$once) {
                    if (!isset(self::$event[$event])) self::$event[$event] = [];
                    self::$event[$event][] = $listener;
                } else {
                    $ek = $event."-once";
                    if (!isset(self::$event[$ek])) self::$event[$ek] = [];
                    self::$event[$ek][] = $listener;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * event 方法
     * 取消订阅事件
     * @param Mixed $listener 订阅者，可以是：DbApp实例  or  Dbo实例  or  Model数据模型(表)类
     * @param String $event 事件名称
     * @return Bool
     */
    public static function eventStopListen($listener, $event)
    {
        $evts = self::$event;
        $ek = $event."-once";
        if (!isset($evts[$event]) && !isset($evts[$ek])) return false;
        if (in_array($listener, $evts[$event])) {
            array_splice(self::$event[$event], array_search($listener, $evts[$event]), 1);
            return true;
        } else if (in_array($listener, $evts[$ek])) {
            array_splice(self::$event[$ek], array_search($listener, $evts[$ek]), 1);
            return true;
        }
        return false;
    }

    /**
     * event 方法
     * 触发事件
     * @param String $event 事件名称
     * @param Mixed $triggerBy 触发者
     * @param Array $args 传递给 handler 的参数
     * @return Bool
     */
    public static function eventTrigger($event, $triggerBy, ...$args)
    {
        $ls = self::eventListeners($event);
        if (empty($ls)) return false;
        /**
         * 每个 listener 对象内部应实现 handler 方法 ormEventHandlerEventName()
         * 此方法第一个参数应为 触发事件的 对象
         * 此方法应 return void
         */
        for ($i=0;$i<count($ls);$i++) {
            $lsi = $ls[$i];
            if (empty($lsi)) continue;
            if (self::eventListenerIsLegal($lsi)==false) continue;
            //handler 方法名： event-name  -->  ormEventHandlerEventName
            $m = "ormEventHandler".strtocamel($event, true);
            $ismodel = is_subclass_of($lsi, self::cls("Model"));
            if ($ismodel) {
                //订阅者是 数据表(模型) 类
                if (cls_hasm($lsi, $m, "static,public")) {
                    $lsi::$m($triggerBy, ...$args);
                }
            } else {
                //订阅这是 其他实例
                if (method_exists($lsi, $m)) {
                    $lsi->$m($triggerBy, ...$args);
                }
            }
        }
        /**
         * 删除一次性 订阅者
         */
        $ek = $event."-once";
        if (isset(self::$event[$ek])) unset(self::$event[$ek]);
        return true;
    }

    /**
     * 检查 类 或 实例对象内部，自动将 ormEventHandlerFooBar() 创建事件订阅
     * @param Mixed $listener 要检查的 类 或 实例对象
     * @return Bool
     */
    public static function eventRegist($listener)
    {
        if (!self::eventListenerIsLegal($listener)) return false;
        $ismodel = is_subclass_of($listener, self::cls("Model"));
        if ($ismodel) {
            //检查 数据表(模型) 类 内部 ormEventHandler*** 方法
            $ms = cls_get_ms($listener, function($mi) {
                if (substr($mi->name, 0, 15)=="ormEventHandler") {
                    return true;
                }
                return false;
            }, "static,public");
        } else {
            //检查 其他实例 内部 ormEventHandler*** 方法
            $ms = cls_get_ms(get_class($listener), function($mi) {
                if (substr($mi->name, 0, 15)=="ormEventHandler") {
                    return true;
                }
                return false;
            }, "public");
        }
        if (empty($ms)) return false;
        foreach ($ms as $mn => $mi) {
            $evt = substr($mi->name, 15);
            $evt = trim(strtosnake($evt, "-"), "-");
            self::eventListen($listener, $evt);
        }
        return true;
    }


    
    /**
     * 获取 attoorm 类
     * @param String $clspath like: foo/Bar  --> \Atto\Orm\foo\Bar
     * @return Class 类全称
     */
    public static function cls($clspath)
    {
        return self::NS.str_replace("/", "\\", trim($clspath, "/"));
    }
}