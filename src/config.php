<?php

/**
 * Attobox Framework / default config data
 */

return [

    //当前网站 key, 应该是网站根目录 dirname
    "WEB_KEY"   => "",

    //是否显示debug信息
    "WEB_DEBUG" => false,	

    //暂停网站
    "WEB_PAUSE" => false,	
    //日志开关
    "WEB_LOG" => false,

    //网站参数
    "WEB_PROTOCOL"  => "https",
    "WEB_DOMAIN"    => "cgy.design",
    "WEB_IP"        => "106.14.28.128",
    "WEB_DOMAIN_AJAXALLOWED" => "cgy.design",

    //是否启用 uac 权限控制，详细设置在 module/uac/config.php
    "UAC_CTRL"      => false,

    //当使用阿里云解析时，安装ssl证书，首次需要开启此验证，通过后即可关闭
    "ALI_SSLCHECK"  => false,   

    //dirs
    "LIB_DIRS"      => "modules,library,model,operater,plugin,route,asset",
    //"MODEL_DIRS"    => "model,library/model,library/db,library/db/model",
    "RECORD_DIRS"    => "record,library/record,library/db,library/db/record",
    "DB_DIRS"       => "db,library/db",
    "ASSET_DIRS"    => "assets,assets/library,asset,asset/library,src,src/library,public,page",
    //"DB_PATH"     => "library/db",    //可手动指定 db 安装路径，默认 library/db
    "UPLOAD_DIR"    => "uploads",   //默认上传路径，在 assets 路径下

    //Response config
    "EXPORT_FORMATS"    => "html,page,json,xml,str,dump",
    "EXPORT_FORMAT"     => "html",
    "EXPORT_LANG"       => "zh-CN",     //输出语言
    "RESPONSE_PSR7"     => false,		//是否以Psr-7标准返回响应

    //DB config
    "DB_BASE"       => "",              //db path for current website, cover DB_PATH
    "DB_TYPE"       => "sqlite",        //default DB type, [mysql, sqlite, ...] 
    "DB_ROUTE"      => "dbm",           //默认的 db 管理路由，各站点可根据需求 extend 此路由，并在此指定新路由 name（类名称）
    "DB_FORMUI"     => "Elementui",     //default frontend From UI-framework

];
