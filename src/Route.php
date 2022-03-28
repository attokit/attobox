<?php

/**
 * Attobox Framework / Route base class
 */

namespace Atto\Box;

use Atto\Box\Response;

class Route
{
    //route info
    public $intr = "";      //路由说明，子类覆盖
    public $name = "";      //路由名称，子类覆盖
    public $appname = "";   //App路由所属App名称，子类覆盖
    public $key = "";       //路由调用路径

    //此路由是否不受 WEB_PAUSE 设置影响
    public $unpause = false;

    //接受的参数
    public $option = [];  //外部调用通过post传入的参数，Arr类型实例

    //由 router 创建的本次会话的 response 响应对象，即 Response::$current
    public $response = null;

    //此路由全部方法都需要权限验证
    public $allNeedAuthority = false;
    //此路由的权限验证器
    public $authVerifier = null;

    public function __construct($option = [])
    {
        $this->key = str_replace("/", "\\", $this->key);
        $this->setOption($option);
        $this->response = Response::$current;

        //生成权限验证器
        if (is_notempty_str($this->authVerifier)) {
            $ver = Auth::class($this->authVerifier);
            //var_dump($ver);
            if (!is_null($ver)) {
                $this->authVerifier = $ver::create($this);
            } else {
                $this->authVerifier = Auth::create($this);
            }
        }/* else {
            $this->authVerifier = Auth::create($this);
        }*/
    }

    public function setOption()
    {
        
    }



    /**
     * tools
     */

    /**
     * call other exists route method
     * @param String $uri       like foo/bar/jaz/...
     * @return Mixed return data
     */
    public static function exec($uri)
    {
        $uarr = arr($uri);
        $route = Router::seek($uarr);
        return Router::exec($route);
    }

    
}