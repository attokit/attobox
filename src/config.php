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

    //网站参数
    "WEB_PROTOCOL"  => "https",
    "WEB_DOMAIN"    => "cgy.design",
    "WEB_IP"        => "106.14.28.128",
    "WEB_DOMAIN_AJAXALLOWED" => "cgy.design,cgy.cool,820529.com",

    //当使用阿里云解析时，安装ssl证书，首次需要开启此验证，通过后即可关闭
    "ALI_SSLCHECK"  => false,   

    //dirs
    "LIB_DIRS"      => "modules,library,model,operater,plugin,route,asset",
    "DB_DIRS"       => "db,library/db",
    "ASSET_DIRS"    => "asset,asset/library,src,src/library,public,page",


    //不受WEB_PAUSE影响的route
    //"UNPAUSE_ROUTE" => "src",	

    
    "EXPORT_FORMATS"    => "html,page,json,xml,str,dump",
    "EXPORT_FORMAT"     => "html",
    "EXPORT_LANG"       => "zh-CN",     //输出语言
    "RESPONSE_PSR7"     => false,		//是否以Psr-7标准返回响应

];
