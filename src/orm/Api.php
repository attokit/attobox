<?php
/**
 * Atto-ORM Api 处理/执行 工具类
 * 当调用某个 api 时，需要构造 api 实例
 * 然后调用 $api->run() 方法，取得返回值
 */

namespace Atto\Orm;

use Atto\Orm\Orm;
use Atto\Orm\DbApp;
use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Atto\Orm\model\Configer;
use Atto\Box\Request;
use Atto\Box\Response;

class Api 
{
    /**
     * dependency
     */
    public $app = null;
    public $db = null;
    public $model = "";
    public $conf = null;    //$model::$configer
    //调用 Model 实例 api 时，应传入 目标 Model/ModelSet 实例 
    public $ins = null;
    public $isSet = false;  //是否 ModelSet 实例

    /**
     * api 参数
     * 取自 $model::$configer->api[key]
     */
    public $key = "";
    public $name = "";
    public $role = [];
    public $desc = "";
    public $isModel = false;

    /**
     * 前端 post 到此 api 的数据
     */
    public $post = [];

    /**
     * 构造
     * @param Dbo $db 数据库实例
     * @param String $model 数据表类
     * @param String $api api 方法名，不包含末尾的 "Api"
     * @return void
     */
    public function __construct($db, $model, $api)
    {
        $this->db = $db;
        $this->app = $db->app;
        $this->model = $model;
        $this->conf = $this->model::$configer;
        $ao = $this->model::getApiConf($api);
        foreach ($ao as $k => $v) {
            $this->$k = $v;
        }
        $this->key = $ao["authKey"];

        //处理 post 数据
        $this->post = static::parsePost();

    }

    /**
     * 执行 api 方法，返回结果到前端
     * @param Array $args URI 参数
     * @return Mixed
     */
    public function run(...$args)
    {
        $apim = $this->name."Api";
        if ($this->isModel==true) {
            //调用 Model类 api，直接执行
            $args[] = $this->post;  //post 数据作为最后一个参数，传递给 api 方法
            $rst = $this->model::$apim(...$args);
            return $rst;
        }

        Response::code(500);
    }



    /**
     * static tools
     */

    /**
     * 在调用 api 的 DbApp 实例内部，创建 api 实例
     * @param String $api 方法名，不含末尾的 "Api"
     * @param DbApp $app 传入 调用此 api 的 app 实例
     * @return Api 实例  or  null
     */
    public static function init($api, $app)
    {
        if (!$app instanceof DbApp) return null;
        $db = $app->db;
        //app 未指定操作目标 数据库实例
        if (!$db instanceof Dbo) return null;
        $model = $app->model;
        //app 未指定操作目标 数据表类
        if (empty($model) || $model==null) return null;
        $model = $app->model->cls;
        //指定的 数据表类 不包含 api
        if ($app->model->hasApi($api)===false) return null;

        /**
         * UAC 权限控制
         */
        //...

        return new Api($db, $model, $api);
    }

    /**
     * 解析前端 post 到 api 的数据
     * @return Array 解析得到的 post data
     */
    public static function parsePost()
    {
        $post = Request::input("json");
        $pp = [
            "export" => [],     //查询结果输出参数，不指定默认输出所有字段
            "data" => [],       //要 insert/update 的记录数据
            "query" => [],      //查询参数，where/sk/filter/page
            "extra" => []       //其他
        ];

        if (empty($post)) return $pp;
        if (isset($post["data"])) {
            $pp["data"] = $post["data"];
            unset($post["data"]);
        }
        if (isset($post["export"])) {
            $pp["export"] = $post["export"];
            unset($post["export"]);
        }
        if (!isset($post["query"])) {
            $pp["query"] = $post;
        } else {
            $pp["query"] = $post["query"];
            unset($post["query"]);
            if (!empty($post)) {
                $pp["extra"] = $post;
            }
        }

        return $pp;
    }

}