<?php

/**
 * excel 文件处理
 */

namespace Atto\Box\resource;

use Atto\Box\Resource;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Excel extends Resource
{
    
    /**
     * 覆盖 getContent 方法
     * 调用 PHPExcel 读取文件，生成 PHPExcel 实例，保存在 res->content 中
     * 返回 PHPExcel 实例
     */
    protected function getContent(...$args)
    {
        $this->content = self::readFile($this->realPath, true);
        return $this->content;
    }

    /**
     * after resource created
     * if necessary, derived class should override this method
     * @return Resource $this
     */
    protected function afterCreated()
    {
        //在 resource 实例创建后
        if (empty($this->content)) {
            //如果 content 内容为空，则读取 excel
            $this->getContent();
        } else {
            //如果 content 内容不为空，则根据内容 创建 excel
            //通过内容创建 excel 输入的内容应为 csv 格式字符串
            $ctn = $this->content;
            //创建一个新的 spreadsheet
            $this->content = new Spreadsheet();

        }

        return $this;
    }

    /**
     * 关闭 excel 文件
     * !!! 操作完成后，一定关闭文件
     */
    public function closeFile()
    {
        if ($this->content instanceof Spreadsheet) {
            $this->content->disconnectWorksheets();
            $this->content = null;
        }
        return $this;
    }




    /**
     * static
     */

    /**
     * 覆盖 Resource::create 方法
     * 
     * Resource::create($path, $params)
     * @param String $path      call path of resource (usually from url)
     * @param Array $param      resource process params
     * @return Resource instance or null
     */
    public static function create($path = "", $params = [])
    {
        if (isset($params["content"])) {    //通过直接输入内容，创建纯文本文件
            $content = $params["content"];
            unset($params["content"]);
            $params = is_notempty_arr($params) ? $params : [];
            if (!empty($_GET)) $params = arr_extend($_GET, $params);
            if (strpos($path, ".")===false) $path .= ".txt";
            $p = [
                "rawPath"   => $path,
                "rawExt"    => Mime::ext($path),
                "resType"   => 'create',
                "realPath"  => '',
                "params"    => $params,
                "content"   => $content
            ];
        } else {
            if (is_indexed($path)) $path = implode(DS, $path);
            $p = self::parse($path);
            if (is_null($p)) return null;
            $params = is_notempty_arr($params) ? $params : [];
            if (!empty($_GET)) $params = arr_extend($_GET, $params);
            if (!isset($p["params"]) || !is_array($p["params"])) $p["params"] = [];
            $p["params"] = arr_extend($p["params"], $params);
        }
        $res = new Excel($p);
        return $res;
    }

    /**
     * 读取 excel 文件
     */
    public static function readFile($fn, $readonly = false)
    {
        $pi = pathinfo($fn);
        $ext = $pi["extension"];
        $cls = "\\PhpOffice\\PhpSpreadsheet\\Reader\\".ucfirst(strtolower($ext));
        if (class_exists($cls)) {
            $reader = new $cls();
        } else {
            $reader = IOFactory::createReaderForFile($fn);
        }
        if ($readonly) {
            $reader->setReadDataOnly(true);
        }
        return $reader->load($fn);
    }

    /**
     * 写入 excel 文件
     */
    public static function writeFile($fn, $resource = null)
    {
        if (empty($resource) || !$resource instanceof Excel) return null;
        if (empty($resource->content) || !$resource->content instanceof Spreadsheet) return null;
        $spreadSheet = $resource->content;
        $ext = $resource->rawExt;
        $cls = "\\PhpOffice\\PhpSpreadsheet\\Writer\\".ucfirst(strtolower($ext));
        if (class_exists($cls)) {
            $writer = new $cls($spreadSheet);
        } else {
            $writer = IOFactory::createWriter($spreadSheet, ucfirst(strtolower($ext)));
        }
        return $writer->save($fn);
    }


}