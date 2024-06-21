<?php

/**
 * Attobox Framework / *ms 管理系统子页面操作类
 * 操作 *ms/foo-bar.vue 文件
 */

namespace Atto\Box;

use Atto\Box\Resource;

class Ms
{
    //关联的 Resource 对象实例
    public $src = null;

    //xms 页面默认 profile 参数
    //此参数保存在 各 页面 vue 文件的 <profile>{json...}</profile> 代码段中
    public $dftMsProfile = [
        "sort"      => 999999,
        "import"    => [
            "url" => "",
            "name" => "",
            "camel" => ""
        ],
        "mstype"    => "",
        "name"      => "",
        "title"     => "",
        "label"     => "",
        "icon"      => ""
    ];

    /**
     * construct
     */
    public function __construct()
    {

    }

    /**
     * 获取管理器页面的 profile
     * @return Array
     */
    public function profile()
    {
        $dft = $this->dftMsProfile;
        $set = $this->src->profile;
        $profile = arr_extend($dft, $set);
        $profile["import"]["url"] = Resource::toSrcUrl($this->src->realPath)."?export=js&name=".$profile["import"]["name"];
        return $profile;
    }

    /**
     * 输出当前 ms 页面的 meta 参数
     */





    /**
     * static tools
     */

    /**
     * 创建 *ms 管理器页面实例
     * @param String $msfile 页面 xpath like: pms-admin-index
     * @return Ms 实例
     */
    public static function load($msfile)
    {
        if (is_file($msfile)) {
            $file = $msfile;
        } else {
            $farr = explode("-",$msfile);
            $msn = array_shift($farr);
            $fn = implode("-", $farr);
            $fn = str_replace(".vue", "", $fn);
            $pre = Uac::prefix("","path");
            $fpre = "src/ms/$msn";
            if ($pre!="") {
                $fpre = $pre."/"."assets/ms";
            }
            $file = path_find("$fpre/$fn.vue");
        }
        if (empty($file)) return null;
        $ms = new Ms();
        $ms->src = Resource::create($file);
        $ms->src->returnContent();
        return $ms;
    }
}