<?php

/**
 * Attobox Framework / Module Resource
 * mime toolkit
 */

namespace Atto\Box\resource;

use Atto\Box\Response;

class Mime
{
    //可选mime类型
    public static $default = "application/octet-stream";
    public static $mimes = [
		// text
		"txt" => "text/plain",
		"asp" => "text/plain",
		"aspx" => "text/plain",
		"jsp" => "text/plain",
		"vue" => "text/plain",
        "htm" => "text/html",
        "html" => "text/html",
		"tpl" => "text/html",
        "php" => "text/html",
        "css" => "text/css",
        "scss" => "text/css",
        "sass" => "text/css",
        "csv" => "text/csv",
        "js" => "text/javascript",
        "json" => "application/json",
        "xml" => "application/xml",
        "swf" => "application/x-shockwave-flash",

        // images
        "png" => "image/png",
        "jpe" => "image/jpeg",
        "jpeg" => "image/jpeg",
        "jpg" => "image/jpeg",
        "gif" => "image/gif",
        "bmp" => "image/bmp",
        "webp" => "image/webp",
        "ico" => "image/vnd.microsoft.icon",
        "tiff" => "image/tiff",
        "tif" => "image/tiff",
        "svg" => "image/svg+xml",
        "svgz" => "image/svg+xml",
        "dwg" => "image/vnd.dwg",

        // archives
        "zip" => "application/zip",
        "rar" => "application/x-rar-compressed",
        "7z" => "application/x-7z-compressed",
        "exe" => "application/x-msdownload",
        "msi" => "application/x-msdownload",
        "cab" => "application/vnd.ms-cab-compressed",

        // audio
        "aac" => "audio/x-aac",
        "flac" => "audio/x-flac",
        "mid" => "audio/midi",
        "mp3" => "audio/mpeg",
        "m4a" => "audio/mp4",
        "ogg" => "audio/ogg",
        "wav" => "audio/x-wav",
        "wma" => "audio/x-ms-wma",

        // video
        "3gp" => "video/3gpp",
        "avi" => "video/x-msvideo",
        "flv" => "video/x-flv",
        "mkv" => "video/x-matroska",
        "mov" => "video/quicktime",
        "mp4" => "video/mp4",
        "m4v" => "video/x-m4v",
        "qt" => "video/quicktime",
        "wmv" => "video/x-ms-wmv",
        "webm" => "video/webm",

        // adobe
        "pdf" => "application/pdf",
        "psd" => "image/vnd.adobe.photoshop",
        "ai" => "application/postscript",
        "eps" => "application/postscript",
        "ps" => "application/postscript",

        // ms office
        "doc" => "application/msword",
        "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "rtf" => "application/rtf",
        "xls" => "application/vnd.ms-excel",
        "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "ppt" => "application/vnd.ms-powerpoint",
        "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation",

        // open office
        "odt" => "application/vnd.oasis.opendocument.text",
        "ods" => "application/vnd.oasis.opendocument.spreadsheet",
    ];

    //预定义的可处理类型
    public static $processable = [
        "plain" => [
            "txt",
            "asp",
            "aspx",
            "jsp",
            "vue",
            "htm",
            "html",
            "tpl",
            "php",
            "css","scss","sass",
            "js","json",

            "svg"
        ],

        "image" => [
            "jpg","jpe","jpeg",
            "png",
            "gif",
            "bmp",
            "webp"
        ],

        "audio" => [
            //"aac",
            //"flac",
            "mp3",
            "m4a",
            "ogg"
        ],

        "video" => [
            "mp4",
            "m4v",
            "mov",
            //"mkv",
            //"avi",
            //"wmv",
            //"webm"
        ],

        "office" => [
            "doc","docx",
            "xls","xlsx",
            "ppt","pptx",
            "csv",
            "pdf"
        ]
    ];



    /*
     *  tool
     */

    /**
     * 根据文件路径，获取 extension
     * @return String | null
     */
    public static function ext($path = "")
    {
        if (!is_notempty_str($path)) return null;
        $info = pathinfo($path);
        if (!isset($info["extension"])) return null;
        return strtolower($info["extension"]);
    }

    /**
     * 判断是否支持指定的 ext
     * @return Boolean
     */
    public static function support($ext = "")
    {
        if (!is_notempty_str($ext)) return false;
        return isset(self::$mimes[strtolower($ext)]);
    }

    /**
     * 获取全部 supported 类型
     * @return Array indexed
     */
    public static function supportedExts()
    {
        return array_keys(self::$mimes);
    }
    
    /**
     * 根据文件 ext，返回 mime
     * @return String
     */
    public static function get($ext = "")
    {
        if (!is_notempty_str($ext) || !self::support($ext)) return self::$default;
        return self::$mimes[$ext];
    }

    /**
     * 根据文件 ext，获取 processable 类型（plain,image,video,audio,office, default）
     * @return String | null
     */
    public static function getProcessableType($ext = "")
    {
        if (!is_notempty_str($ext) || !self::support($ext)) return null;
        $psb = self::$processable;
        foreach ($psb as $k => $m) {
            if (in_array($ext, $m)) return $k;
        }
        return null;
    }

    /**
     * 获取全部 processable 类型
     * @return Array indexed
     */
    public static function processableExts($ptype = "")
    {
        if (!is_notempty_str($ptype)) $ptps = array_keys(self::$processable);
        $ptps = arr($ptype);
        $exts = [];
        foreach ($ptps as $i => $v) {
            $exts = array_merge($exts, self::$processable[$v]);
        }
        return $exts;
    }

    /**
     * 检查ext是否支持直接输出
     * @return Boolean
     */
    public static function canExport($ext = "")
    {
        $ext = strtolower($ext);
        if (!self::support($ext)) return false;
        $mime = self::get($ext);
        if (in_array($ext, ["js","json","xml","swf"])) return true;
        if (in_array($ext, ["csv","psd"])) return false;
        if ($mime == self::$default) return false;
        $pexts = self::processableExts("plain,image,audio,video");
        if (in_array($ext, $pexts)) return true;
        return false;
    }

    /**
     * 设置 mime header 到 Response::$current
     * @return void
     */
    public static function header($ext = "", $fn = "")
    {
        $mime = self::get($ext);
        Response::headers(
            "Content-Type", 
            $mime . (self::isPlain($ext) ? "; charset=utf-8" : "")
        );
        if (!self::canExport($ext)) {
            Response::headers(
                "Content-Disposition", 
                "attachment; filename=\"".$fn."\""
            );
        }
    }



    /**
     * __callStatic魔术方法
     */
    public static function __callStatic($key, $args)
    {
        /**
         * 检查是否某个processable类型，参数为 ext
         * Mime::isPlain("js") == true
         * @return Boolean
         */
        if (substr($key, 0, 2) == "is") {
            $pm = strtolower(str_replace("is", "", $key));
            if (empty($args)) return false;
            return isset(self::$processable[$pm]) && isset(self::$processable[$pm][strtolower($args[0])]);
        }


        return null;
    }








    


}