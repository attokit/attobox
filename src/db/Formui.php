<?php

/**
 * attobox framework db form ui
 * 前端表单 ui 库参数生成
 * 
 * 不同的 ui 库生成不同的参数
 */

namespace Atto\Box\db;

class Formui
{
    //关联的 db configer 实例
    public $dbConfiger = null;

    public function __construct($dbConfiger)
    {
        $this->dbConfiger = $dbConfiger;
    }
}