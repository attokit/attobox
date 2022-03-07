<?php
/**
 * CPHP框架  微信公众平台接口处理类
 * 获取与微信平台通信的接口，获取相关token、openid、info等信息
 */

namespace CGY\CPhp\util\wx;

use CGY\CPhp\Request;
use CGY\CPhp\Response;
use CGY\CPhp\request\Url;
use CGY\CPhp\request\Curl;
use CGY\CPhp\util\Session;

class WxApi {
    //微信接口
    public static $apis = [

        "dft_host" 	=> "host",
    
        "host" 		=> "https://api.weixin.qq.com",
        "host_mp" 	=> "https://mp.weixin.qq.com",
        "host_sh" 	=> "https://sh.api.weixin.qq.com",
        "host_sz" 	=> "https://sz.api.weixin.qq.com",
        "host_hk" 	=> "https://hk.api.weixin.qq.com",
    
        "api_cgi" 	=> "/cgi-bin",
        "api_kf" 	=> "/customservice",
        "api_card"	=> "/card",
    
        //ACCESS_TOKEN
        "access_token" => "GET|%{API_CGI}%/token?grant_type=client_credential&appid=%{APP_ID}%&secret=%{APP_SECRET}%",
    
        //获取用户openid
        "openid_code" 		=> "GET|https://open.weixin.qq.com/connect/oauth2/authorize?appid=%{APP_ID}%&redirect_uri=%{REDIRECT_URI}%&response_type=code&scope=%{SCOPE}%&state=%{STATE}%#wechat_redirect",
        "openid_token" 		=> "GET|https://api.weixin.qq.com/sns/oauth2/access_token?appid=%{APP_ID}%&secret=%{APP_SECRET}%&code=%{OPENID_CODE}%&grant_type=authorization_code",
        "openid_refresh"	=> "GET|https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=%{APP_ID}%&grant_type=refresh_token&refresh_token=%{REFRESH_TOKEN}%",
        "openid_info"		=> "GET|https://api.weixin.qq.com/sns/userinfo?access_token=%{USER_ACCESS_TOKEN}%&openid=%{OPENID}%&lang=zh_CN",
    
        //微信服务器IP地址
        "callback_ip" => "GET|%{API_CGI}%/getcallbackip?access_token=%{ACCESS_TOKEN}%",
    
        //自定义菜单
        "menu_create" 	=> "POST|%{API_CGI}%/menu/create?access_token=%{ACCESS_TOKEN}%",
        "menu_get" 		=> "GET|%{API_CGI}%/menu/get?access_token=%{ACCESS_TOKEN}%",
        "menu_delete" 	=> "GET|%{API_CGI}%/menu/delete?access_token=%{ACCESS_TOKEN}%",
        "menu_current" 	=> "GET|%{API_CGI}%/get_current_selfmenu_info?access_token=%{ACCESS_TOKEN}%",		//获取当前使用的自定义菜单配置
    
        //客服账号
        "kf_add" 		=> "POST|%{API_KF}%/kfaccount/add?access_token=%{ACCESS_TOKEN}%",
        "kf_update" 	=> "POST|%{API_KF}%/kfaccount/update?access_token=%{ACCESS_TOKEN}%",
        "kf_delete" 	=> "GET|%{API_KF}%/kfaccount/delete?access_token=%{ACCESS_TOKEN}%",
        "kf_uploadface" => "POST_FORM|%{API_KF}%/kfaccount/uploadheadimg?access_token=%{ACCESS_TOKEN}%&kf_account=%{KF_ACCOUNT}%",
        "kf_get" 		=> "GET|%{API_CGI}%%{API_KF}%/getkflist?access_token=%{ACCESS_TOKEN}%",
        "kf_message" 	=> "POST|%{API_CGI}%/message/custom/send?access_token=%{ACCESS_TOKEN}%",
    
        //群发消息接口
        "msg_toopenid" 	=> "POST|%{API_CGI}%/message/mass/send?access_token=%{ACCESS_TOKEN}%",
        "msg_toall" 	=> "POST|%{API_CGI}%/message/mass/sendall?access_token=%{ACCESS_TOKEN}%",
        "msg_delete"	=> "POST|%{API_CGI}%/message/mass/delete?access_token=%{ACCESS_TOKEN}%",
        "msg_preview"	=> "POST|%{API_CGI}%/message/mass/preview?access_token=%{ACCESS_TOKEN}%",
        "msg_statu"		=> "POST|%{API_CGI}%/message/mass/get?access_token=%{ACCESS_TOKEN}%",
    
        //模板消息接口
        "msg_tpl" => "POST|%{API_CGI}%/message/template/send?access_token=%{ACCESS_TOKEN}%",
    
        //JS api_ticket
        "jsapi_ticket" => "GET|%{API_CGI}%/ticket/getticket?access_token=%{ACCESS_TOKEN}%&type=jsapi",
    
        //卡券 js api_ticket
        "card_api_ticket" => "GET|%{API_CGI}%/ticket/getticket?access_token=%{ACCESS_TOKEN}%&type=wx_card",
    
        //素材管理
        "media_temp_upload" 	=> "POST_FORM|%{API_CGI}%/media/upload?access_token=%{ACCESS_TOKEN}%&type=%{MEDIA_TYPE}%",	//新增临时素材
        "media_temp_get" 		=> "GET|%{API_CGI}%/media/get?access_token=%{ACCESS_TOKEN}%&media_id=%{MEDIA_ID}%",			//获取临时素材
        "media_add_news" 		=> "POST|%{API_CGI}%/material/add_news?access_token=%{ACCESS_TOKEN}%",						//新增永久图文素材
        "media_add_news_img" 	=> "POST_FORM|%{API_CGI}%/media/uploadimg?access_token=%{ACCESS_TOKEN}%",					//上传图文素材中的图片，单张，不占5000
        "media_add" 			=> "POST|%{API_CGI}%/material/add_material?access_token=%{ACCESS_TOKEN}%&type=%{MEDIA_TYPE}%",	//新增永久素材，其他类型
        "media_get"				=> "POST|%{API_CGI}%/material/get_material?access_token=%{ACCESS_TOKEN}%",			//获取永久素材
        "media_delete"			=> "POST|%{API_CGI}%/material/del_material?access_token=%{ACCESS_TOKEN}%",			//删除永久素材
        "media_update_news"		=> "POST|%{API_CGI}%/material/update_news?access_token=%{ACCESS_TOKEN}%",			//编辑图文永久素材
        "media_count"			=> "GET|%{API_CGI}%/material/get_materialcount?access_token=%{ACCESS_TOKEN}%",		//获取永久素材总数
        "media_list"			=> "POST|%{API_CGI}%/material/batchget_material?access_token=%{ACCESS_TOKEN}%",		//获取永久素材列表
    
        //用户管理
        "user_tag_create" 	=> "POST|%{API_CGI}%/tags/create?access_token=%{ACCESS_TOKEN}%",
        "user_tag_get" 		=> "POST|%{API_CGI}%/tags/get?access_token=%{ACCESS_TOKEN}%",
        "user_tag_update" 	=> "POST|%{API_CGI}%/tags/update?access_token=%{ACCESS_TOKEN}%",
        "user_tag_delete" 	=> "POST|%{API_CGI}%/tags/delete?access_token=%{ACCESS_TOKEN}%",
        "userRequest::gettaguser" 	=> "POST|%{API_CGI}%/user/tag/get?access_token=%{ACCESS_TOKEN}%",
        "user_set_tags"		=> "POST|%{API_CGI}%/tags/members/batchtagging?access_token=%{ACCESS_TOKEN}%",
        "user_unset_tags"	=> "POST|%{API_CGI}%/tags/members/batchuntagging?access_token=%{ACCESS_TOKEN}%",
        "userRequest::getusertags" => "POST|%{API_CGI}%/tags/getidlist?access_token=%{ACCESS_TOKEN}%",
        "user_markname" 	=> "POST|%{API_CGI}%/user/info/updateremark?access_token=%{ACCESS_TOKEN}%",
        "user_info" 		=> "GET|%{API_CGI}%/user/info?access_token=%{USER_ACCESS_TOKEN}%&openid=%{OPENID}%&lang=zh_CN",
        "user_infos" 		=> "POST|%{API_CGI}%/user/info/batchget?access_token=%{ACCESS_TOKEN}%",
        "user_list" 		=> "GET|%{API_CGI}%/user/get?access_token=%{ACCESS_TOKEN}%&next_openid=%{NEXT_OPENID}%",
    
        //二维码
        "qr_ticket" => "POST|%{API_CGI}%/qrcode/create?access_token=%{ACCESS_TOKEN}%",
        "qr_img" 	=> "GET|%{MP_API_CGI}%/showqrcode?ticket=%{QR_TICKET}%",
    
        //短url
        "short_url" => "POST|%{API_CGI}%/shorturl?access_token=%{ACCESS_TOKEN}%",
    
        //卡券创建
        //会员卡
        "card_member_create" => "POST|%{API_CARD}%/create?access_token=%{ACCESS_TOKEN}%",
        //卡券投放
        //卡券二维码投放
        "card_qr" => "POST|%{API_CARD}%/qrcode/create?access_token=%{ACCESS_TOKEN}%",
        //卡券货架投放
        "cardset_create" => "POST|%{API_CARD}%/landingpage/create?access_token=%{ACCESS_TOKEN}%",
        //卡券嵌入图文消息
        "cardnews_create" => "POST|%{API_CARD}%/mpnews/gethtml?access_token=%{ACCESS_TOKEN}%",
        //设置测试白名单
        "cardtest_set" => "POST|%{API_CARD}%/testwhitelist/set?access_token=%{ACCESS_TOKEN}%",
        //客服消息发卡券到指定openid
        "card_touser" => "POST|%{API_CGI}%/message/custom/send?access_token=%{ACCESS_TOKEN}%",
    
        //卡券核销
        //卡券状态查询
        "card_statu" => "POST|%{API_CARD}%/code/get?access_token=%{ACCESS_TOKEN}%",
        //核销接口
        "card_consume" => "POST|%{API_CARD}%/code/consume?access_token=%{ACCESS_TOKEN}%",		//唯一卡券核销接口
        //JS SDK 核销，线上核销
        "card_dec" => "POST|%{API_CARD}%/code/decrypt?access_token=%{ACCESS_TOKEN}%",
    
        //卡券管理
        //用户卡券详情
        "user_card_detial" => "POST|%{API_CARD}%/code/get?access_token=%{ACCESS_TOKEN}%",		//查询用户卡券状态，以code查询
        //卡券详情
        "card_detial" => "POST|%{API_CARD}%/get?access_token=%{ACCESS_TOKEN}%",
        //获取用户卡券列表
        "user_card_list" => "POST|%{API_CARD}%/user/getcardlist?access_token=%{ACCESS_TOKEN}%",
        //批量查询
        "card_batch" => "POST|%{API_CARD}%/batchget?access_token=%{ACCESS_TOKEN}%",
        //修改库存
        "card_modquantity" => "POST|%{API_CARD}%/modifystock?access_token=%{ACCESS_TOKEN}%",
    
        //会员卡
        //拉取会员卡信息
        "mbcard_get"  => "POST|%{API_CARD}%/membercard/userinfo/get?access_token=%{ACCESS_TOKEN}%",
        //更新会员信息
        "mbcard_update" => "POST|%{API_CARD}%/membercard/updateuser?access_token=%{ACCESS_TOKEN}%"
    
    ];

    //公众号账号
    public $account = "";

    //用户openid获取相关
    public static $openidsessionids = [
        "WX_APP",
        "WX_OPENID",
        "WX_OPENID_ACTK",
        "WX_OPENID_RFTK",
        "WX_OPENID_SCOPE",
        "WX_OPENID_EXPR",
        "WX_OPENID_INFO"
    ];

    public function __construct($wxaccount="qzcygl")
    {
        $this->account = $wxaccount;
    }

    //获取关联的公众号对象
    public function wx()
    {
        return Wechat::load($this->account);
    }
    public function conf($key)
    {
        return $this->wx()->conf($key);
    }

    //获取微信API接口
    public function api()
    {
        $args = func_get_args();
		$api = $args[0];
		$u = self::$apis[$api];
		$host = self::$apis[self::$apis["dft_host"]];
		$prev = array(
			"API_CGI" => $host.self::$apis["api_cgi"],
			"API_KF" => $host.self::$apis["api_kf"],
			"API_CARD" => $host.self::$apis["api_card"],
			"MP_API_CGI" => self::$apis["host_mp"].self::$apis["api_cgi"]
		);
		$ua = explode("|",$u);
		foreach ($prev as $k => $v) {
			$ua[1] = str_replace("%{".$k."}%", $v, $ua[1]);
		}

		if ($api == "access_token") {
			$ua[1] = str_replace("%{APP_ID}%", $this->conf("APP_ID"), $ua[1]);
			$ua[1] = str_replace("%{APP_SECRET}%", $this->conf("APP_SECRET"), $ua[1]);
			return [
				"type" => $ua[0],
				"api" => $ua[1]
            ];
		} else {
			if (
                isset($args[1]) && 
                isset($args[1]["_RF_ACTK_"]) && 
                is_bool($args[1]["_RF_ACTK_"]) && 
                $args[1]["_RF_ACTK_"] == true
            ) {
				$actk = $this->getToken("access_token", true);
			}else{
				$actk = $this->getToken("access_token");
			}
			$ua[1] = str_replace("%{APP_ID}%", $this->conf("APP_ID"), $ua[1]);
			$ua[1] = str_replace("%{APP_SECRET}%", $this->conf("APP_SECRET"), $ua[1]);
			$ua[1] = str_replace("%{ACCESS_TOKEN}%", $actk, $ua[1]);
			if (isset($args[1]) && !empty($args[1])) {
				foreach ($args[1] as $k => $v) {
					$ua[1] = str_replace("%{".$k."}%", $v, $ua[1]);
				}
			}
			return [
				"type" => $ua[0],
				"api" => $ua[1]
            ];
		}
    }



    /*
     *  token / ticket
     */

    //从cache获取access_token / jsapi_ticket / card_api_ticket
    private function getTokenFromCache($type = "access_token")
    {
		//先从本地缓存中获取
		$get_local_token = file_get_contents($this->conf("APP_CACHE/token/".$type));
		$token_array = json_decode($get_local_token, true);
        //判断本地的缓存是否存在
        if (
            !is_array($token_array) || 
            !isset($token_array['get_token_time']) || 
            isset($token_array["errcode"])
        ) {
            //去微信获取，然后保存
            $token_array = $this->getTokenFromApi($type);
        } else {
            //判断 当前时间 减去 本地获取微信token的时间 大于7000秒 ,就要重新获取
            $now_time = time();
            if ( $now_time - $token_array['get_token_time'] > $this->conf("APP_CACHE/token/expire") ) {
                $token_array = $this->getTokenFromApi($type);
            }
        }
        //var_dump($token_array);
        return $token_array;
	}

	//远程获取access_token / jsapi_ticket，有频率限制，需要缓存到本地
	private function getTokenFromApi($type = "access_token")
    {
		$api = $this->api($type);
		$file = $this->conf("APP_CACHE/token/".$type);
        //var_dump($api); exit;
        $TOKEN = Curl::wx($api["api"]);
        $TOKEN_json = json_decode($TOKEN, true);
        if (isset($TOKEN_json["errcode"]) && $TOKEN_json["errcode"]!=0) {
        	if ($type != "access_token") {
        		//强制刷新ACCESS_TOKEN
                //$this->AccessToken(true);
                $this->getToken("access_token", true);
        		return $this->getTokenFromApi($type);
        	}
        } else {
        	$TOKEN_json['get_token_time'] = time();
        	$TOKEN_json['get_token_timestr'] = date("Y-m-d H:i:s", $TOKEN_json['get_token_time']);
			file_put_contents($file, json_encode($TOKEN_json));		//保存到本地
			return $TOKEN_json;
        }
    }

    //获取token/ticket
    private function getToken($type = "access_token", $refresh = false, $fullinfo = false)
    {
        if($refresh==true){
            $token_array = $this->getTokenFromApi($type);
        }else{
            $token_array = $this->getTokenFromCache($type);
        }
        if($fullinfo==true){
            return $token_array;
        }else{
            $ta = explode("_",$type);
            if (in_array("access", $ta)) {
                return $token_array["access_token"];
            } else {
                $key = array_pop($ta);
                return $token_array[$key];
            }
        }
    }

    //外部调用
    public function getAccessToken($refresh = false, $fullinfo = false)
    {
        return $this->getToken("access_token", $refresh, $fullinfo);
    }
    public function getJsapiTicket($refresh = false, $fullinfo = false)
    {
        return $this->getToken("jsapi_ticket", $refresh, $fullinfo);
    }
    public function getCardapiTicket($refresh = false, $fullinfo = false)
    {
        return $this->getToken("card_api_ticket", $refresh, $fullinfo);
    }

    
    
    /*
     *  jssdk/card 验证签名获取
     */

	//生成JS-SDK权限验证签名
	public function getJssdkSign($url = null)
    {
		$jsapiTicket = $this->getToken("jsapi_ticket");
		if(empty($url)){
			$url = Url::current()->url();
		}
	    $timestamp = time();
	    $nonceStr = str_nonce(16, false);
	    //这里参数的顺序要按照 key 值 ASCII 码升序排序
	    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
	    $signature = sha1($string);

	    $signPackage = [
		    "appId"     => $this->conf("APP_ID"),
		    "nonceStr"  => $nonceStr,
		    "timestamp" => $timestamp,
		    "url"       => $url,
		    "signature" => $signature,
		    "rawString" => $string
        ];
	    return $signPackage; 
	}

    //jssdk api list
    public function getJssdkApiList(...$args)
    {
        $apis = [   // v 1.6.0
            "updateAppMessageShareData",
            "updateTimelineShareData",
            "onMenuShareTimeline",  //（即将废弃）
            "onMenuShareAppMessage",//（即将废弃）
            "onMenuShareQQ",//（即将废弃）
            "onMenuShareWeibo",
            "onMenuShareQZone",
            "startRecord",
            "stopRecord",
            "onVoiceRecordEnd",
            "playVoice",
            "pauseVoice",
            "stopVoice",
            "onVoicePlayEnd",
            "uploadVoice",
            "downloadVoice",
            "chooseImage",
            "previewImage",
            "uploadImage",
            "downloadImage",
            "translateVoice",
            "getNetworkType",
            "openLocation",
            "getLocation",
            "hideOptionMenu",
            "showOptionMenu",
            "hideMenuItems",
            "showMenuItems",
            "hideAllNonBaseMenuItem",
            "showAllNonBaseMenuItem",
            "closeWindow",
            "scanQRCode",
            "chooseWXPay",
            "openProductSpecificView",
            "addCard",
            "chooseCard",
            "openCard"
        ];

        if (count($args) < 1) return $apis;
        switch ($args[0]) {
            case "all" :
                return $apis;
                break;
            case "except" :
                array_shift($args);
                return array_diff($apis, $args);
                break;
            default:
                return array_diff($args, array_diff($args, $apis));
                break;
        }
    }

	//生成card_sign权限验证签名
	public function getCardapiSign($cardid)
    {
		$card_api_ticket = $this->getToken("card_api_ticket");
		$timestamp = time();
		$card_id = $cardid;
		$nonce_str = str_nonce(16, false);

		$sortarr = array();
		$sortarr[] = $card_api_ticket;
		$sortarr[] = $card_id;
		$sortarr[] = $nonce_str;
		//$sortarr[] = $timestamp;
		sort($sortarr);
		$string = $timestamp.implode("", $sortarr);
		//$string = "api_ticket=$card_api_ticket&card_id=$card_id&nonce_str=$nonce_str&timestamp=$timestamp";
		$signature = sha1($string);
		return [
			"card_api_ticket" => $card_api_ticket,
			"rawstring" => $string,
			"card_ext" => [
				"card_id" => $card_id,
				"nonce_str" => $nonce_str,
				"timestamp" => $timestamp,
				"signature" => $signature
            ]
        ];
    }
    


    /*
     *  openid相关
     */

    //检查session中保存的openid相关信息，用于确认用户是否登录此公众号
    private function checkOpenidSession()
    {
        $statu = "ok";
        //var_dump($_SESSION);
		for ($i=0; $i<count(self::$openidsessionids); $i++) {
            //var_dump(Session::get(self::$openidsessionids[$i], self::$openidsessionids[$i]." noexists"));
			if (!Session::has(self::$openidsessionids[$i])) {
                $statu = "unset";
                //var_dump(self::$openidsessionids[$i]);
				break;
			}
        }
        //var_dump($statu);die();
		if ($statu == "ok") {
			if (Session::get("WX_APP") != $this->account) {
                $this->openidClearSession();
                $statu = "unset";
            } else {
                $expr = (int)Session::get("WX_OPENID_EXPR");
                if (time() > ($expr-300)) {
                    $statu = "expr";
                }
            }
        }
        return $statu;
    }

    //清空openid session
	public function openidClearSession()
    {
		for ($i=0; $i<count(self::$openidsessionids); $i++) {
			Session::del(self::$openidsessionids[$i]);
		}
    }

    //开始检查openid，不存在则调用微信接口获取
    public function checkOpenid($getusrinfo = false)
    {
        $openidsession = $this->checkOpenidSession();	//检查session
        //var_dump($openidsession); exit;
        if ($openidsession != "ok") {
            $gotourl = $this->getSelfUri();
            //var_dump("checkOpenid:", $gotourl); exit;
            if ($openidsession=="unset") {	//session中无信息
                $this->gotoUrl("openid/getcode?gotourl=".urlencode($gotourl)."&getusrinfo=".($getusrinfo==true ? "yes" : "no"));
            }else if ($openidsession == "expr") {	//session中保存了openid，但是过期了
                if ($getusrinfo == false) {		//不要求获取用户详细信息，执行下一步
                    return true;
                } else {	//要求获取用户详细信息，则跳转到刷新用户token页面
                    //$this->gotourl("/api/openidrefresh?gotourl=".urlencode($gotourl)."&getusrinfo=yes");
                    $this->gotoUrl("openid/getcode?gotourl=".urlencode($gotourl)."&getusrinfo=".($getusrinfo==true ? "yes" : "no"));
                }
            }
        } else {
            return true;
        }
    }

    //获取用户openid流程，第一步，获取code，跳转到 http://host/wx/[公众号ID]/openid/get?code=[获取到的code]&gotourl=[获取到用户openid后跳转的页面url]
    public function openidGetcode()
    {
        $gotourl = Request::get("gotourl", "");
		$getusrinfo = Request::get("getusrinfo", "no");	//是否获取微信用户信息
		$getusrinfo = strtolower($getusrinfo);
		$redirect = Url::protocol()."://".Url::host()."/wx/".$this->conf("APP_NAME")."/openid/get?gotourl=".urlencode($gotourl)."&getusrinfo=".$getusrinfo;
		$option = [
			"REDIRECT_URI" => urlencode($redirect),
			"SCOPE" => Request::get("scope", ($getusrinfo == "yes" ? "snsapi_userinfo" : "snsapi_base")),
			"STATE" => Request::get("state", "CPWX".time())
        ];
        //var_dump($option);exit;
        $api = $this->api("openid_code", $option);
        //var_dump($api["api"]);exit;
		//header("Location:".$api["api"]); exit;
		Response::redirect($api["api"]);
    }

    //获取用户openid流程，第二步，获取openid token，并保存到session中，跳转到gotourl
    public function openidGet()
    {
        $gotourl = Request::get("gotourl", "");
		$getusrinfo = Request::get("getusrinfo", "no");	//是否获取微信用户信息
		$code = Request::get("code", null);
		if (is_null($code)) {
			trigger_error("wx/openid/emptycode", E_USER_ERROR);
		}else{
			$api = $this->api("openid_token", [
				"OPENID_CODE" => $code
            ]);
            //var_dump($api["api"]);die();
			$ch = curl_init();
			curl_setopt($ch,CURLOPT_URL,$api["api"]); 
			curl_setopt($ch,CURLOPT_HEADER,0); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 ); 
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
			$res = curl_exec($ch); 
			curl_close($ch); 
			//var_dump($res);die();
            $reso = json_decode($res,true);
            //var_dump($reso);die();
			if (isset($reso["errcode"])) {
				trigger_error("wx/openid/exchange::".$reso["errcode"].",".$reso["errmsg"], E_USER_ERROR);
			}else{
				//将openid信息写入session
                $this->openidClearSession();
                //"WX_APP","WX_OPENID","WX_OPENID_ACTK","WX_OPENID_RFTK","WX_OPENID_SCOPE","WX_OPENID_EXPR","WX_OPENID_INFO"
                //var_dump($this->conf("APP_NAME"));
                //var_dump($reso);
				Session::set([
					"WX_APP" => $this->conf("APP_NAME"),
					"WX_OPENID" => $reso["openid"],
					"WX_OPENID_ACTK" => $reso["access_token"],
					"WX_OPENID_RFTK" => $reso["refresh_token"],
					"WX_OPENID_SCOPE" => $reso["scope"],
                    "WX_OPENID_EXPR" => time()+(int)$reso["expires_in"],
                    "WX_OPENID_INFO" => "none"
                ]);
                //exit;
                //var_dump($this->checkOpenidSession());
                //var_dump($_SESSION);
                //die();
				if (strtolower($getusrinfo) == "yes") {	//获取微信用户信息，并写入session
					$this->openidGetusrinfo($reso["openid"], $reso["access_token"], $gotourl);
				} else {
                    if (!empty($gotourl)) {
                        $this->gotoUrl($gotourl, false);
                    }
                }
			}
		}
    }

    //根据用户openid、refresh_token刷新用户access_token
	public function openidRefresh()
    {
		$gotourl = Request::get("gotourl", "");
		$getusrinfo = Request::get("getusrinfo", "no");
		$rftk = Request::get("refresh_token", Session::get("WX_OPENID_RFTK", null));
		if (empty($rftk)) {
			trigger_error("wx/openid/refresh::refresh_token为空", E_USER_ERROR);
		}else{
			$api = $this->API("openid_refresh", [
				"REFRESH_TOKEN" => $rftk
            ]);
			$res = Curl::wx($api["api"]);
			$res = j2a($res);
			if (isset($res["errcode"])) {
				//var_dump($res);die();
				trigger_error("wx/openid/refresh::".$res["errmsg"]." [errcode=".$res["errcode"]."]", E_USER_ERROR);
			} else {
				//将刷新后的用户access_token写入session
				Session::set([
					"WX_OPENID_ACTK" => $reso["access_token"],
					"WX_OPENID_RFTK" => $reso["refresh_token"],
					"WX_OPENID_EXPR" => time()+(int)$reso["expires_in"]
                ]);
				if ($getusrinfo == "yes") {
					$this->openidGetusrinfo();
				}
				if (!empty($gotourl)) {
					$this->gotoUrl($gotourl);
				}
			}
		}
	}

    //根据用户openid和用户access_token，获取用户信息，返回json格式数据
	public function openidGetusrinfo($openid = "", $actk = "", $gotourl = "")
    {
		$openid = Request::get("openid", $openid);
        $actk = Request::get("access_token", $actk);
        $gotourl = Request::get("gotourl", $gotourl);
		if (empty($openid) && Session::has("WX_OPENID")) {
			if (time()<=((int)Session::get("WX_OPENID_EXPR")-300)) {
				$openid = Session::get("WX_OPENID");
				$actk = Session::get("WX_OPENID_ACTK");
			} else {	//user_access_token过期，调用刷新api
				$this->openidRefresh();
				$openid = Session::get("WX_OPENID", "");
				$actk = Session::get("WX_OPENID_ACTK", "");
			}
		}
		if (empty($openid) || empty($actk)) {
			trigger_error("wx/openid/usrinfo::参数不完整", E_USER_ERROR);
		}else{
			$api = $this->api("openid_info", [
				"USER_ACCESS_TOKEN" => $actk,
				"OPENID" => $openid
            ]);
			$res = Curl::wx($api["api"]);
			$reso = j2a($res);
			if (isset($reso["errcode"])) {
				trigger_error("wx/openid/usrinfo::".$reso["errmsg"]." [errcode=".$reso["errcode"]."]", E_USER_ERROR);
			} else {
				//_export_data_(array("json"=>$res),"json");
                Session::set("WX_OPENID_INFO",$res);
                //var_dump(urldecode($gotourl));
                //var_dump(Session::get("WX_OPENID_INFO"),null);
                //exit;
                if (!empty($gotourl)) {
                    $this->gotoUrl($gotourl, false);
                }
			}
		}
    }

    //读取openid session
	public function openidSession()
    {
		return array(
			"app" => Session::get("WX_APP",null),
			"openid" => Session::get("WX_OPENID",null),
			"access_token" => Session::get("WX_OPENID_ACTK",null),
			"refresh_token" => Session::get("WX_OPENID_RFTK",null),
			"scope" => Session::get("WX_OPENID_SCOPE",null),
			"expires" => (int)Session::get("WX_OPENID_EXPR",time()),
			"info" => arr(Session::get("WX_OPENID_INFO",null))
		);
    }

    //用户登出，删除 session
    public function openidSessionClear()
    {
        foreach (["app","openid","openid_actk","openid_rftk","openid_scope","openid_expr","openid_info"] as $key) {
            $k = "WX_".strtoupper($key);
            Session::del($k);
        }
    }

    //获取当前用户在某个公众号下的openid，用于跨公众号支付
	public function openidOther($otheraccount = null, $clear = "no"){
        $otheraccount = Request::get("account",$otheraccount);
        $clear = Request::get("clear",$clear);
        $code = Request::get("code",null);
        if (is_null($otheraccount)) return null;
        if ($clear == "clear") {
            Session::del("WX_OPENID_OTHER_".strtoupper($otheraccount));
            return null;
        }
        if (Session::has("WX_OPENID_OTHER_".strtoupper($otheraccount))) {
            return Session::get("WX_OPENID_OTHER_".strtoupper($otheraccount));
        }
        $acf = $this->wx()->path("mps")."/$otheraccount/config.php";
        if (!file_exists($acf)) return null;
        $ocfg = require($acf);
        $selfurl = Url::current();
        if (!is_null($code)) {
            $ccurl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$ocfg["APP_ID"]."&secret=".$ocfg["APP_SECRET"]."&code=".$code."&grant_type=authorization_code";
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL,$ccurl); 
            curl_setopt($ch,CURLOPT_HEADER,0); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 ); 
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
            $res = curl_exec($ch); 
            curl_close($ch); 
            $reso = j2a($res);
            if (isset($reso["openid"])) {
                Session::set("WX_OPENID_OTHER_".strtoupper($otheraccount), $reso["openid"]);
                $qs = explode("?",$selfurl);
                $qsa = u2a($qs[1]);
                unset($qsa["code"]);
                unset($qsa["state"]);
                $qsn = a2u($qsa);
                $u = $qs[0].(empty($qsn) ? "" : "?".$qsn);
                //header("Location: ".$u);
                Response::redirect($u);
                //Session::set("WX_OPENID_PAY",$reso["openid"]);
                ////header("Location: ".$selfurl);
                //Response::redirect($selfurl);
                //return $reso["openid"];
            }else{
                return null;
            }
        }else{
            $rurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$ocfg["APP_ID"]."&redirect_uri=".urlencode($selfurl)."&response_type=code&scope=snsapi_base&state=GOOPID#wechat_redirect";
			//header("Location: ".$rurl);
			Response::redirect($rurl);
        }

		/*$selfurl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		if(strpos($selfurl,"?")!==false){
			$selfurla = explode("?",$selfurl);
			$selfurl = $selfurla[0];
		}
		$otheraccount = is_null($otheraccount) ? $this->_config_["APP_NAME"] : $otheraccount;
		//$openid = Request::get("openid",null);
		
		//if(is_null($openid)){
			
			
			$ocfg = require($acf);
			//var_dump($ocfg["APP_ID"]);die();
			if(!is_null($code)){
				//https://api.weixin.qq.com/sns/oauth2/access_token?appid=%{APP_ID}%&secret=%{APP_SECRET}%&code=%{OPENID_CODE}%&grant_type=authorization_code
				$ccurl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$ocfg["APP_ID"]."&secret=".$ocfg["APP_SECRET"]."&code=".$code."&grant_type=authorization_code";
				$ch = curl_init();
				curl_setopt($ch,CURLOPT_URL,$ccurl); 
				curl_setopt($ch,CURLOPT_HEADER,0); 
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 ); 
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
				$res = curl_exec($ch); 
				curl_close($ch); 
				$reso = j2a($res);
				if(isset($reso["openid"])){
					Session::set("WX_OPENID_PAY",$reso["openid"]);
					//header("Location: ".$selfurl);
					Response::redirect($selfurl);
				}else{
					echo "";
					exit;
				}
			}else{
				//https://open.weixin.qq.com/connect/oauth2/authorize?appid=%{APP_ID}%&redirect_uri=%{REDIRECT_URI}%&response_type=code&scope=%{SCOPE}%&state=%{STATE}%#wechat_redirect
				$rurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$ocfg["APP_ID"]."&redirect_uri=".urlencode($selfurl)."&response_type=code&scope=snsapi_base&state=GOOPID#wechat_redirect";
				//header("Location: ".$rurl);
				Response::redirect($rurl);
			}
		//}else{
			//已经获取到openid，输出
		//	if($rtn==false){
		//		echo $openid;
		//		exit;
		//	}else{
		//		return $openid;
		//	}
		//}*/
    }

    //获取特殊账号openid
    public function openidSpecial($name = "dommy")
    {
        if (strlen($name)>=28) return $name;
        $u = $this->conf("USR_".strtoupper($name));
        return !is_null($u) ? $u : $this->conf("USR_DOMMY");
    }

    //检查某个openid的权限
    public function openid_uac($openid="", $operation=null)
    {


        return true;
    }

    //获取当前uri
    private function getSelfUri()
    {
        $uri = $_SERVER["REQUEST_URI"];
		$sign = "/wx/".$this->account."/";
		if (strpos($uri, $sign) === false) {
			return ltrim($uri, "/");
		}else{
			return str_replace($sign, "_WX_/", $uri);
		}
    }

    //跳转到指定的微信页面
	private function gotoUrl($url = "", $fix = true)
    {
        $url = urldecode($url);
		//将 ?_HASH_=aa|bb|cc 转为 #/aa/bb/cc
		if (strpos($url, "?_HASH_=") !== false) {
			$ga = explode("?_HASH_=", $url);
			$hash = $ga[1];
			if (strpos("&", $hash) !== false) {
				$gaa = explode("&", $hash);
				$hash = array_shift($gaa);
				$qs = count($gaa) <= 0 ? "" : "?".implode("&", $gaa);
				$ga[0] .= $qs;
			}
			$hash = str_replace("|", "/", $hash);
			$url = $ga[0]."#/".$hash;
		}
		if ($fix == true) {
			$gu = $this->wx()->url($url);
			////header("Location: ".$this->wx()->url($url));
			//Response::redirect($this->wx()->url($url));
		}else{
			if (strpos($url, "_WX_/") === false) {
				$gu = Url::protocol()."://".Url::host()."/".ltrim($url, "/");
			}else{
				$gu = $this->wx()->url(str_replace("_WX_/", "", $url));
			}
		}
        //var_dump($gu); exit;
        //var_dump(headers_sent()); exit;
		//return $gu;
        //ob_end_clean();
		//header("Location:".$gu);
		Response::redirect($gu);
        exit;
        //$rst = Curl::wx($gu);
        //var_dump($rst); exit;
	}



    /**
     * api接口
     * url调用api接口时，参数传递方法为：向api调用页面post一个含有全部参数的json数据
     * 数据格式：
     *      openid      string，目标用户的openid
     *      data        array/string，api所需的参数，根据各个api需求不同
     *      其他可选api参数...
     */
    //使用php://获取传入的参数
    private function getInput($input=null){
        $input = is_null($input) ? _input_("json") : (is_array($input) ? $input : array());
        if(!isset($input["openid"])){
            $input["openid"] = "dommy";
        }
        $input["openid"] = $this->openidSpecial($input["openid"]);
        if(!isset($input["data"])){
            $input["data"] = array();
        }
        return $input;
    }

    /**** 设置自定义菜单 ****/
    public function menu($method="get", $menujson="menu"){
        $api = $this->api("menu_".$method);
        if(empty($api["api"])){
            trigger_error("WCT998@自定义菜单,方法不被支持",E_USER_ERROR);
        }else{
            if(in_array($method,["get","delete"])){
                return Curl::wx($api["api"]);
            }else{
                $menujson = $this->wx()->path($menujson.".json");
                if(file_exists($menujson)){
                    $menujson = fileRequest::getcontents($menujson);
                    return Curl::wx($api["api"],$menujson);
                }else{
                    trigger_error("WCT998@自定义菜单,菜单json不存在",E_USER_ERROR);
                }
            }
        }
    }

    /**** 下发消息相关，调用客服消息接口 ****/
    //发送文字消息到指定openid用户，
	public function kfmsg_text($input=null){
        $input = $this->Request::getinput($input);
        $data = $input["data"];
        $api = $this->api("kf_message");
		$d = array(
			"touser" => $input["openid"],
			"msgtype" => "text",
			"text" => array(
				"content" => $data["content"]
			)
		);
		$d = _a2j_($d);
		$d = Curl::wx($api["api"],$d);
		$d = j2a($d);
		return $d;
    }
    //发送图文消息到指定openid用户，仅支持1条图文，图文参数应为post进来的json格式数据
	public function kfmsg_news($input=null){
        $input = $this->Request::getinput($input);
        $data = $input["data"];
		$api = $this->api("kf_message");
        $article = _array_extend_(array(
            "title" => "实例标题",
            "description" => "文章摘要",
            "url" => "https://qzcygl.com/wx/qzcygl/test",
            "picurl" => "https://qzcygl.com/res/app/WX/icon/qz_logo_wxpage.svg"
        ), $data);
		$d = array(
			"touser" => $input["openid"],
			"msgtype" => "news",
			"news" => array(
				"articles" => array(
                    $article
                )
			)
		);
		$d = _a2j_($d);
		$d = Curl::wx($api["api"],$d);
		$d = j2a($d);
		return $d;
    }
    
    /**** 下发模板消息相关，调用模板消息接口 ****/
    //下发模板消息，应在公众号config中预设相应模板，外部url调用时应向此函数post一个json数据（$data）参数
    public function tplmsg_send($input=null){
        $input = $this->Request::getinput($input);
        $data = $input["data"];
        $tpl = $this->conf("MB_SET/".$input["mbid"]);
        if(!is_null($tpl)){
            $req = array(
                "touser" => $input["openid"],
                "template_id" => $tpl["id"],
                "url" => $input["url"],
                "topcolor" => $tpl["topcolor"],
                "data" => array()
            );
            $td = $tpl["data"];
            foreach($req as $k => $v){
                if($k == "data"){
                    foreach($td as $tk => $tv){
                        if(isset($data[$tk])){
                            if($tv=="%"){
                                $req["data"][$tk] = array(
                                    "value" => $data[$tk]
                                );
                            }else{
                                $req["data"][$tk] = array(
                                    "value" => _str_tpl_($tv, arr($data[$tk]))
                                );
                            }
                        }else{
                            $req["data"][$tk] = array(
                                "value" => $tv=="%" ? "" : $tv
                            );
                        }
                        if($tpl["color"][$tk]!="default" && strpos($tpl["color"][$tk],"#")==0){
                            $req["data"][$tk]["color"] = $tpl["color"][$tk];
                        }
                    }
                }else{
                    if(isset($data[$k])){
                        $req[$k] = $data[$k];
                    }
                }
            }
            $api = $this->api("msg_tpl");
            //var_dump($req);die();
            $d = _a2j_($req);
		    $d = Curl::wx($api["api"],$d);
            $d = j2a($d);
            //检查服务器返回结果
            if(isset($d["errcode"]) && $d["errcode"]!=0){   //模板消息下发失败
                //写入数据库记录

            }else{
                return $d;
            }
        }else{
            return array(
                "errcode" => 1,
                "errmsg" => "模板不存在"
            );
        }
    }

    /**** 素材 ****/
    //获取永久素材列表
    private function media_list($type="image",$offset=0,$count=20){
        $api = $this->api("media_list");
		$d = array(
            "type" => $type,
            "offset" => $offset,
			"count" => $count   //1~20
		);
		$d = _a2j_($d);
		$d = Curl::wx($api["api"],$d);
		$d = j2a($d);
		return $d;
    }
    //根据media_id获取永久素材内容
    private function media_get($media_id=null){
        $api = $this->api("media_get");
		$d = array(
            "media_id" => $media_id
		);
		$d = _a2j_($d);
		$d = Curl::wx($api["api"],$d);
		$d = j2a($d);
		return $d;
    }
    //获取永久素材列表，缓存到 cache/medialist.json
    public function media_cache($type="image"){
        $cf = $this->wx()->path("cache/medialist.json");
        $ch = fileRequest::getcontents($cf);
        $ch = j2a($ch);
        if(!isset($ch[$type]) || !is_array($ch[$type])) return false;
        $ml = $this->media_list($type,count($ch[$type]),20);
        if(isset($ml["errcode"]) || !isset($ml["item_count"]) || !is_array($ml["item"])) return false;
        $ic = $ml["item_count"];
        $mi = $ml["item"];
        if($ic>0){
            if($type=="video"){
                $vdo = null;
                for($i=0;$i<$ic;$i++){
                    $vdo = $this->media_get($mi[$i]["media_id"]);
                    $ml["item"][$i]["down_url"] = $vdo["down_url"];
                    $ch["vid"][$mi[$i]["vid"]] = $vdo["down_url"];
                }
            }
            $ch[$type] = array_merge($ch[$type],$ml["item"]);
        }
        if($ic<20){
            $chjson = _a2j_($ch);
            file_put_contents($cf,$chjson);
            return $ch[$type];
        }else{
            return $this->media_cache($type);
        }
    }
    //根据media_id获取永久素材信息,首先查找缓存，找不到则调用api
    public function media_getinfo($media_id=null,$type="image"){
        if(is_null($media_id) || !is_string($media_id)) return null;
        $cf = $this->wx()->path("cache/medialist.json");
        $ch = fileRequest::getcontents($cf);
        $ch = j2a($ch);
        if(!isset($ch[$type]) || !is_array($ch[$type])) return null;
        $mch = $ch[$type];
        $idx = -1;
        for($i=0;$i<count($mch);$i++){
            if($mch[$i]["media_id"]==$media_id){
                $idx = $i;
                break;
            }
        }
        if($idx>=0) return $mch[$idx];
        $mch_c = $this->media_cache($type);
        if(count($mch_c)<=count($mch)) return null;
        for($i=count($mch);$i<count($mch_c);$i++){
            if($mch_c[$i]["media_id"]==$media_id){
                $idx = $i;
                break;
            }
        }
        return $idx<0 ? null : $mch_c[$idx];
    }
    //根据vid查找video地址
    public function media_get_video($vid=null){
        if(is_null($vid) || !is_string($vid)) return null;
        $cf = $this->wx()->path("cache/medialist.json");
        $ch = fileRequest::getcontents($cf);
        $ch = j2a($ch);
        //if(isset($ch["vid"][$vid])) return $ch["vid"][$vid];
        $vch = $ch["video"];
        $idx = -1;
        for($i=0;$i<count($vch);$i++){
            if($vch[$i]["vid"]==$vid){
                $idx = $i;
                break;
            }
        }
        if($idx>=0) return $vch[$idx];
        $vch_c = $this->media_cache("video");
        if(count($vch_c)<=count($vch)) return null;
        for($i=count($vch);$i<count($vch_c);$i++){
            if($vch_c[$i]["vid"]==$vid){
                $idx = $i;
                break;
            }
        }
        return $idx<0 ? null : $vch_c[$idx];
    }

}