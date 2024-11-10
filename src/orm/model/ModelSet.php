<?php
/**
 * 数据模型(表) 记录集 类
 * curd 操作得到的 recordset 被包裹为 此类型
 */

namespace Atto\Orm\model;

use Atto\Orm\Dbo;
use Atto\Orm\Model;

use Atto\Box\traits\arrayIterator;

class ModelSet implements \ArrayAccess, \IteratorAggregate, \Countable
{
    //引入trait
    //可 for 循环此类，可用 $rs[idx] 访问 $context 数组，增加 each 方法
    use arrayIterator;


    //关联的数据库实例 Dbo
    public $db = null;

    //关联的 模型(数据表) 类全称
    public $model = "";

    /**
     * 数据模型(表) 实例 数组
     */
    public $context = [
        //model instance, ...
    ];

    /**
     * 构造
     * @param String $model 数据表(模型) 类全称
     * @param Array curd->select() 查询结果 array  or  [ model instance, ... ]
     * @return void
     */
    public function __construct($model, $rs=[])
    {
        if (!class_exists($model)) return null;
        $this->model = $model;
        $this->db = $model::$db;

        if (!is_array($rs) || empty($rs)) {
            $this->context = [];
        } else if (!is_indexed($rs)) {
            $this->context = [];
            $this->context[] = $model::create($rs);
        } else {
            if ($rs[0] instanceof Model) {
                $this->context = $rs;
            } else {
                $this->context = array_map(function($rsi) use ($model) {
                    return $model::create($rsi);
                }, $rs);
            }
        }
    }

    /**
     * __call
     * @param String $key 方法
     * @param Array $args 参数
     * @return Array [ 调用结果, ... ]
     */
    public function __call($key, $args)
    {
        if (!empty($this->context) && $this->context[0] instanceof Model) {
            $msi = $this->context[0];

            /**
             * $modelset->modelInstanceMethod()
             * $modelset->getter()
             * 调用 model 实例方法 / __call 方法，返回 结果数组
             */
            $tst = $msi->$key(...$args);
            if (method_exists($msi, $key) || !empty($tst)) {
                $rst = $this->map(function($i) use ($key, $args) {
                    return $i->$key(...$args);
                });
                if (empty($rst)) return [];
                if ($rst[0] instanceof Model) {
                    //如果每个 model 实例 执行结果返回 model 实例 本身
                    array_splice($this->context, 0);
                    $this->context = $rst;
                    //返回 ModelSet 实例本身
                    return $this;
                } else {
                    return $rst;
                }
            }

            /**
             * $modelset->slice(0,2)
             * 调用 array_*** 方法，对 modelset->context 执行数组操作
             * 返回处理后的 modelset 实例
             */
            $arr_funcs = [
                "shift","unshift",
                "pop","push",
                "splice","slice"
            ];
            if (in_array($key, $arr_funcs)) {
                $af = "array_".$key;
                if (!function_exists($af)) return $this;
                if (in_array($key, ["shift", "pop"])) {
                    //shift, pop 操作 context 返回移出的 model 实例
                    return $af($this->context);
                } else if (in_array($key, ["unshift","push"])) {
                    //unshift, push 向 context 增加 model 实例
                    if (!empty($args)) {
                        $ags = [];
                        for ($i=0;$i<count($args);$i++) {
                            if ($args[$i] instanceof Model) {
                                //增加到 ￥modelset->context 数组的 必须是 model 实例
                                $ags[] = $args[$i];
                            }
                        }
                        $af($this->context, ...$ags);
                    }
                    return $this;
                } else if ($key=="slice") {
                    //slice 从 context 中复制部分 model 实例，返回新 modelset 实例
                    $ctx = array_slice($this->context, ...$args);
                    return new ModelSet($this->model, $ctx);
                } else if ($key=="splice") {
                    //slice 从 context 中分割部分 model 实例，插入新 model 实例
                    $ctx = array_splice($this->context, ...$args);
                    //返回移除的 model 实例 组成的 modelset
                    return new ModelSet($this->model, $ctx);
                    //return $this;
                }
            }

        }

        return $this;
    }

    /**
     * __get
     * @param String $key
     * @return Mixed
     */
    public function __get($key) 
    {
        if (!empty($this->context) && $this->context[0] instanceof Model) {
            $msi = $this->context[0];

            /**
             * $modelset->fieldname
             * 返回 字段值 数组 [ "field value", ... ]
             * 返回 计算字段值 数组 [ "getterfield value", ... ]
             */
            if (
                isset($msi->context[$key]) || 
                in_array($key, $msi->conf->getterFields) ||
                (strpos($key, "_")!==false && count(explode("_",$key))>1)
            ) {
                return $this->map(function($i) use ($key) {
                    return $i->$key;
                });
            }
            
            /**
             * $modelset->ctx 
             * 返回 [ [ model->context ], [...], ... ]
             */
            if ($key=="ctx") {
                return $this->map(function($i) {
                    return $i->context;
                });
            }

            /**
             * $modelset->modelget
             * 调用 model 实例 __get()
             */
            $rst = $msi->$key;
            if (!is_null($rst)) return $rst;
        }
        
        return null;
    }

    /**
     * __set 设置全部 context record 对象的属性
     */
    /*public function __set($key, $value)
    {
        if ($this->table->hasField($key)) {
            return $this->map(function($record) use ($key, $value) {
                return $record->setField($key, $value);
            });
        }
    }*/

    /**
     * 是否空记录集
     */
    public function isEmpty()
    {
        return empty($this->context);
    }

    /**
     * 在 modelset 中筛选
     * @param Function $callback 筛选方法，返回 true or false
     * @return ModelSet 返回新 modelset 实例
     */
    public function filter($callback)
    {
        if (!is_callable($callback)) return $this;
        $rs = [];
        for ($i=0;$i<count($this->context);$i++) {
            $ctx = $this->context[$i];
            $rst = $callback($ctx);
            if ($rst===true) {
            //if ($callback($ctx)===true) {
                //$rs[] = $ctx->export("db", false, false);
                $rs[] = $ctx;
            }
        }
        return new ModelSet($this->model, $rs);
    }
}