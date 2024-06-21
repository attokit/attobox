<?php

/**
 * UAC 权限控制器预设参数
 */

return [

    //用户数据库
    "UAC_DB" => "", //"sqlite:usr",
    
    //登录页面路径
    "UAC_LOGIN" => "",  //"root/page",

    //UAC关联的微信公众账号，在 cgy.design 服务器中的账户名
    "UAC_WX" => "",     //"index",    //默认为 wx.cgy.design/index/***

    //临时中断所有用户权限，除 SUPER 权限外，用于开发阶段更新系统
    "UAC_PAUSED" => false,

    //可选的 uac 用户 role 
    "UAC_ROLE" => "",
    "UAC_ROLE_NAME" => "",
    
];