<?php
/**
 *  通过微信权鉴的 usr 对象
 *  本地 usr 数据模型对象
 */

namespace CGY\CPhp\util\wx;

use CGY\CPhp\Model;
use CGY\CPhp\util\Session;

class WxUsr 
{
    public $model = null;   //保存本地数据的模型对象
    public $wxo = null;     //关联的微信对象
    
    public $uo = null;      //找到的本地 usr 记录 item 对象
    public $openid = "";
    public $info = [];      //微信用户的 info
    public $jssdk = [];     //jssdk

    public function __construct()
    {
        
    }

    /**
     *  权限检查，返回 bool
     *  方法在 $this->uo->authCheck() 数据模型中定义
     */
    public function authCheck(...$args)
    {
        $rst = $this->uo->authCheck(...$args);
        return is_bool($rst) ? $rst : false;
    }

    //获取info
    public function getUsrInfo()
    {
        if (empty($this->info)) {
            //返回 wxusrinfo
            $info = arr(Session::get("WX_OPENID_INFO",null));
            if (!empty($info)) $this->info = $info;
        }
        return $this;
    }
    //获取 jssdk
    public function getJssdk()
    {
        if (empty($this->jssdk)) {
            //准备 jssdk 参数
            $jssdk = $this->wxo->wxapi->getJssdkSign();
            $jssdk["jsApiList"] = $this->wxo->wxapi->getJssdkApiList("all");
            if (!empty($jssdk)) $this->jssdk = $jssdk;
        }
        return $this;
    }



    /**
     *  static
     */
    //入口
    public static function chk($model="")
    {
        $usr = self::load();
        if ($usr instanceof WxUsr) {
            return $usr;
        } else {
            $model = Model::load($model);
            if (empty($model)) return false;
            $wxaccount = Session::get("WX_APP",null);
            $openid = Session::get("WX_OPENID",null);
            //var_dump($wxaccount, $openid);
            if (empty($wxaccount) || empty($openid)) {
                return false;
            } else {
                $wxo = Wechat::load($wxaccount);
                $opid = $openid."@".$wxaccount;
                $uo = self::find($model, $opid);
                if (empty($uo)) {
                    //$wxo->wxapi->openidSessionClear();
                    return false;
                }
                $usr = new WxUsr();
                $usr->model = $model;
                $usr->uo = $uo;
                $usr->wxo = $wxo;
                $usr->openid = $openid;
                $usr->getUsrInfo()->getJssdk();
                Session::set([
                    "USR_LOGIN" => "yes",
                    "USR_INFO" => implode("@",[$openid,$wxaccount,$model->key])
                ]);
                return $usr;
            }
        }
    }

    //从 session 建立 wxusr 对象
    public static function load()
    {
        $usrl = Session::get("USR_LOGIN",null);
        $uinf = Session::get("USR_INFO",null);
        if ($usrl=="yes" && $uinf!="") {     //已登录
            $uinf = explode("@",$uinf);
            $model = Model::load($uinf[2]);
            $uo = self::find($model, $uinf[0]."@".$uinf[1]);
            if (empty($uo)) return null;
            $usr = new WxUsr();
            $usr->model = $model;
            $usr->uo = $uo;
            $usr->wxo = Wechat::load($uinf[1]);
            $usr->openid = $uinf[0];
            $usr->getUsrInfo()->getJssdk();
            return $usr;
        }
        return null;
    }

    //根据 openid@wxaccount 从 model 查找记录
    public static function find($model, $opid)
    {
        if ($model instanceof Model) {
            $rs = $model->reset()->whereOpenid($opid)->limit(1)->select();
            if (empty($rs) || $rs->empty()) return null;
            return $rs->single();
        }
        return null;
    }

    
}