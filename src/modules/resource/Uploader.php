<?php

/**
 * Attobox Framework / Module Resource
 * Resource Uploader
 * 
 */

namespace Atto\Box\resource;

use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Box\Resource;
use Atto\Box\resource\Mime;

class Uploader
{
    //支持上传的 mime 类型
    public static $mimes = ["image","audio","video","office"];

    //$_FILES[fieldname]["error"] 包含的错误代码
    public static $uperr = [
        0 => ["上传成功", UPLOAD_ERR_OK],
        1 => ["上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值", UPLOAD_ERR_INI_SIZE],
        2 => ["上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值", UPLOAD_ERR_FORM_SIZE],
        3 => ["文件只有部分被上传", UPLOAD_ERR_PARTIAL],
        4 => ["没有文件被上传", UPLOAD_ERR_NO_FILE],
        6 => ["找不到临时文件夹", UPLOAD_ERR_NO_TMP_DIR],
        7 => ["文件写入失败", UPLOAD_ERR_CANT_WRITE],
        8 => ["此文件类型无法上传", UPLOAD_ERR_EXTENSION]
    ];

    //当前上传参数
    //允许上传的类型
    public $mime = "image";
    //允许上传的size，100M
    public $maxsize = 100*1024*1024; 
    //保存路径，只能在 asset 路径下
    public $upath = UPLOAD_DIR;
    //是否改名
    public $rename = true;

    //获取的 $_FILES，处理后结果
    public $files = [];

    public function __construct($upath=null)
    {
        if (is_notempty_str($upath)) {
            $upath = path_find($upath, ["inDir"=>ASSET_DIRS,"checkDir"=>true]);
            if (!is_null($upath)) {
                $this->upath = $upath;
            } else {
                $this->upath = path_find($this->upath, ["inDir"=>ASSET_DIRS,"checkDir"=>true]);
                if (is_null($this->upath)) {
                    trigger_error("upload/errsavepath", E_USER_ERROR);
                    exit;
                }
            }
        }
    }

    //设置上传路径
    public function setUploadPath($path)
    {
        $path = path_find($path, ["inDir"=>ASSET_DIRS,"checkDir"=>true]);
        if (is_null($path)) return false;
        $this->upath = $path;
        return true;
    }

    //上传主方法
    public function upload($fieldname = null)
    {
        //解析 $_FILES
        $this->parse($fieldname);
        if (!empty($this->files)) {
            //写入
            $rst = [];
            for ($i=0; $i<count($this->files); $i++) {
                $fo = $this->files[$i];
                if (is_uploaded_file($fo["tmp_name"])) {
                    //create savename
                    if ($this->rename) {
                        $fo["savename"] = $this->newFileName($fo["name"], $i+1);
                    } else {
                        $fo["savename"] = $fo["name"];
                    }
                    if (move_uploaded_file($fo["tmp_name"], $this->upath.DS.$fo["savename"])) {
                        //上传成功
                        $rst[] = $fo;   //$fo["savename"];
                        continue;
                    } else {
                        trigger_error("upload/movefileerror", E_USER_ERROR);
                    }
                } else {
                    trigger_error("upload/notupfile", E_USER_ERROR);
                }
            }
            return $rst;
        } 
        return false;
    }

    //解析 $_FILES
    protected function parse($fieldname = null)
    {
        $fs = Request::files($fieldname); //$_FILES;
        $mimes = $this->acceptMimes();
        //var_dump($mimes);exit;
        $this->files = [];
        foreach ($fs as $fn => $fo) {
            if ($fo["error"]>0) {
                $err = self::$uperr[$fo["error"]];
                $errinfo = isset($err[0]) ? $err[0] : "未知错误";
                trigger_error("upload/syserr::".$errinfo, E_USER_ERROR);
                break;
            }
            if (in_array($fo["type"], $mimes) && $fo["size"] <= $this->maxsize) {
                $this->files[] = $fo;
            }
        }
        if (empty($this->files)) trigger_error("upload/nolegalfile", E_USER_ERROR);
        return $this;
    }

    //返回允许上传的 mime 数组
    protected function acceptMimes()
    {
        $mime = arr($this->mime);
        $ms = [];
        foreach ($mime as $i => $mi) {
            if (isset(Mime::$processable[$mi])) {
                array_push($ms, ...Mime::$processable[$mi]);
            } else if (isset(Mime::$mimes[$mi])) {
                array_push($ms, $mi);
            }
        }
        if (empty($ms)) return [];
        $mimes = [];
        foreach ($ms as $i => $mi) {
            if (isset(Mime::$mimes[$mi])) {
                $mimes[] = Mime::$mimes[$mi];
            }
        }
        return $mimes;
    }

    //rename
    protected function newFileName($filename = "", $idx = 0)
    {
        $pi = pathinfo($filename);
        $idxs = str_pad((string)$idx, 3, "0", STR_PAD_LEFT);
        return date("YmdHis",time())."_".$idxs.".".$pi["extension"];
    }




}