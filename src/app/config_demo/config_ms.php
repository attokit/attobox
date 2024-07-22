<?php

/**
 * config 示例
 * 
 * app/*ms config
 * 此处定义的所有预设参数，可以通过常量 APP_[*MS]_[KEY] 访问到
 */

return [

    //是否启用 uac 权限控制，详细设置在 module/uac/config.php
    "UAC_CTRL"      => true,
    //覆盖 module/uac/config.php 设置项内容
    "UAC_DB"        => "app/*ms/usr",    //用户数据库 dsn，通常为 app/*ms/usr
    //"UAC_TABLE"     => "usr",             //用户表 表名，必须保持默认
    //"UAC_ROLE"      => "role",            //用户角色表 表名，必须保持默认
    "UAC_LOGIN"     => "app/*ms/page/login.php",   //用户登录页面，通常为 app/*ms/page/login.php
    "UAC_WX"        => "qyspkj",            //默认的关联微信账号，用于权限验证 
    "UAC_PAUSED"    => false,               //是否暂停除 Super 外的所有权限

    //可选的 uac 用户 role，与 role 表中的 用户角色对应

    //在此增加 role 后，还需要到 [box]/src/assets/vue/mixins/ms-usr.js 增加 usrIsXxxxRole 计算属性
    "UAC_ROLE" => "normal,db,query,admin,financial,stocker,purchase,qc,rnd,product,gsku,salesuper,salekf,produce",
    "UAC_ROLE_NAME" => "普通用户,数据库操作用户,查询者用户,行政用户,财务用户,仓库用户,采购用户,质检用户,研发用户,产品管理员,原材料品种管理员,销售主管,销售客服,生产流程主管",


    //指定 *ms 单页应用的入口文件名，通常为 app/[appname]/page/spa.php
    "SPA_INDEX"     => "app/*ms/page/spa.php",
    
];