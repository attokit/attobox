<?php

/**
 * Attobox Framework / Counter 计数器
 * 用于生成 自动编码
 */

namespace Atto\Box;

class Counter
{
    /**
     * 缓存
     * [box]/modules/counter/cache.json
     */
    public $cache = "box/modules/counter/cache";
    public $file = "";

    /**
     * context
     */
    public $context = [];

    /**
     * 构造
     * @param String $key 要使用的计数器名称 like：foo  -->  cache/foo.json 
     */
    public function __construct($key)
    {
        $file = $this->cache."/".$key.".json";
        $file = path_find($file);
        if (empty($file)) {     //如果不存在，则创建
            $dir = path_find($this->cache, ["inDir"=>"", "checkDir"=>true]);
            $fp = $dir.DS.$key.".json";
            $fh = @fopen($fp, "w");
            @fwrite($fh, '{}');
            fclose($fh);
            $file = $fp;
        }
        $this->file = $file;
        $this->context = j2a(file_get_contents($file));
    }

    /**
     * 保存
     * @return $this
     */
    public function save()
    {
        file_put_contents($this->file, a2j($this->context));
        return $this;
    }

    /**
     * 获取某个计数键的 值，不 +1，不存在则创建
     * @param String $key 键名
     * @return Integer
     */
    public function get($key)
    {
        if (!isset($this->context[$key])) {
            $this->context[$key] = 0;
            $this->save();
        }
        return $this->context[$key];
    }

    /**
     * 计数，context + 1
     * @param String $key 计数键名
     * @return Integer
     */
    public function count($key)
    {
        $d = $this->get($key);
        $d += 1;
        $this->context[$key] = $d;
        $this->save();
        return $d;
    }

    /**
     * 按日期计数，新一天重新从 0 开始计数
     * @param String $key 计数键名
     * @param String $datekey 日期键名
     * @return Integer
     */
    public function countInDate($key, $datekey)
    {
        $t = time();
        $date = $this->get($datekey);   //日期格式：2023-01-01
        $d = $this->get($key);
        if ($date==0 || $t>strtotime($date." 23:59:59")) {
            $this->context[$datekey] = date("Y-m-d", $t);
            $this->context[$key] = 0;
            $this->save();
            $d = 0;
        }
        $d += 1;
        $this->context[$key] = $d;
        $this->save();
        return $d;
    }

    /**
     * 按年计数，新一年重新从 0 开始计数
     * @param String $key 计数键名
     * @param String $yearkey 日期键名
     * @return Integer
     */
    public function countInYear($key, $yearkey)
    {
        $t = time();
        $year = $this->get($yearkey);   //日期格式：2023
        $d = $this->get($key);
        if ($year==0 || $t>strtotime($year."-12-31 23:59:59")) {
            $this->context[$yearkey] = date("Y", $t);
            $this->context[$key] = 0;
            $this->save();
            $d = 0;
        }
        $d += 1;
        $this->context[$key] = $d;
        $this->save();
        return $d;
    }

    /**
     * 设置额外的缓存信息
     * @param Array $data
     * @return $this
     */
    public function set($data=[])
    {
        $this->context = arr_extend($this->context, $data);
        return $this->save();
    }

    
    
    /**
     * static tools
     */

    /**
     * 外部直接调用，返回自增计数结果，并保存
     * @param String $keypath 文件名/键名
     * @return Integer
     */
    public static function auto($keypath)
    {
        $co = self::parse($keypath);
        if (is_null($co)) return 0;
        return $co["counter"]->count($co["key"]);
    }

    /**
     * 外部直接调用，返回已有的计数数值，不自增
     * @param String $keypath 文件名/键名
     * @return Integer
     */
    public static function current($keypath)
    {
        $co = self::parse($keypath);
        if (is_null($co)) return 0;
        return $co["counter"]->get($co["key"]);
    }

    /**
     * 从 keypath 获取 counter 实例，以及要操作的 key
     * @param String $keypath 文件名/键名
     * @return Array
     */
    public static function parse($keypath)
    {
        if (strpos($keypath, "/")!==false) {
            $karr = explode("/", $keypath);
            $fn = array_shift($karr);
            $key = array_shift($karr);
            $counter = new Counter($fn);
            return [
                "counter" => $counter,
                "key" => $key
            ];
        }
        return null;
    }

}