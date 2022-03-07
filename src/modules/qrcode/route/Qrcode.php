<?php

/**
 * Attobox Framework / Module QRcode
 * route "qrcode"
 * response to url "//host/qrcode?..."
 */

namespace Atto\Box\route;

use Atto\Box\QRcode as QR;
use Atto\Box\request\Url;

class Qrcode extends Base
{
    //route info
    public $intr = "QRcode模块路由";         //路由说明，子类覆盖
    public $name = "Qrcode";                //路由名称，子类覆盖
    public $appname = "";                   //App路由所属App名称，子类覆盖
    public $key = "Atto/Box/route/Qrcode";  //路由调用路径

    //此路由是否 不受 WEB_PAUSE 设置 影响
    public $unpause = true;


    /**
     * QRcode 模块
     * 二维码生成接口
     * 直接输出PNG
     * @return void
     */
    public function defaultMethod()
    {
        $args = func_get_args();
        QR::create(...$args);
        
        exit;
    }

    /**
     * URL QRcode 生成
     * 直接输出PNG
     * @return void
     */
    public function url()
    {
        $args = func_get_args();
        if (empty($args)) {
            return [
                "status" => 404
            ];
        }
        if (in_array($args[0], ["http", "https"])) {
            $args[0] = $args[0].":/";
        } else {
            $args[0] = "/".$args[0];
        }
        $url = implode("/", $args);
        $uo = Url::mk($url);

        QR::create($uo->full);

        exit;
    }

}