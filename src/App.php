<?php
/*
 *  Attobox Framework / App base class
 * 
 */

namespace Atto\Box;

use Atto\Box\traits\staticCreate;

class App 
{
    //引入trait
    use staticCreate;

    //app info
    public $intr = "";  //app说明，子类覆盖
    public $name = "";  //app名称，子类覆盖
    public $key = "";   //app调用路径

    //构造
    public function __construct()
    {
        $this->init();
    }

    //init，子类覆盖
    protected function init()
    {
        //初始化动作，在构造后执行，子类覆盖

        return $this;   //要返回自身
    }

    //path
    public function path($path, $params = [])
    {
        $path = str_replace(["/", "\\"], DS, $path);
        return APP_PATH.DS.strtolower($this->name).DS.$path;
    }

    //默认路由方法
    public function defaultRoute()
    {
        var_dump("\\Atto\Box\\App::defaultRoute()");
        var_dump(func_get_args());
    }

    //根据 key 获取 app 下级类全称
    public function cls($key = "")
    {
        if (!is_notempty_str($key)) return null;
        $key = trim($key, "/");
        return cls("App/".$thid->name."/".$key);
    }








    /*
     *  static
     */

    //判断是否存在此app
    public static function has($appname = "")
    {
        if (!is_notempty_str($appname)) return false;
        $ap = APP_PATH.DS.strtolower($appname);
        if (!is_dir($ap)) return false;
        $acls = cls("App/".ucfirst($appname));
        return !is_null($acls);
    }


    //根据 key 生成 app 实例
    public static function load($key = "", ...$params)
    {
        if (!is_notempty_str($key)) return null;
        $cls = self::_class($key);
        if (!is_null($cls)) return $cls::create(...$params);
        return null;
    }

    //根据 appname 解析 app 类全称
    public static function _class($appname = "")
    {
        if (!is_notempty_str($appname)) return null;
        if (is_lower($appname)) $appname = ucfirst($appname);
        $cls = CP::class($appname);
        //var_dump($cls);
        return is_subclass_of($cls, self::class) ? $cls : null;
    }

    

}