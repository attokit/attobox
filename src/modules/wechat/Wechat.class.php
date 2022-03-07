<?php
/**
 *  DommyPHP框架
 *  微信处理类    /DommyPHP/core/Wechat.class.php
 *  V20170928    ver. 2.0
*/
//@date_default_timezone_set("Asia/Shanghai");

//调用加密解密接口
require_once(dirname(__FILE__)."/wxBizMsgCrypt.php");
//调用WxApi类
require_once(dirname(__FILE__)."/WxApi.class.php");
//调用WxMsg类
require_once(dirname(__FILE__)."/WxMsg.class.php");

class Wechat {

	const SERV_URL = 'https://qzcygl.com';

	//已经加载的公众号对象列表，加载公众号需使用Wechat::load()方法
	public static $_MP_ = array();

	/*const API_URL_PREFIX 			= 'https://api.weixin.qq.com/cgi-bin';
	const AUTH_URL 					= '/token?grant_type=client_credential&';
	const MENU_CREATE_URL 			= '/menu/create?';
	const MENU_GET_URL 				= '/menu/get?';
	const MENU_DELETE_URL 			= '/menu/delete?';
	const GET_TICKET_URL 			= '/ticket/getticket?';
	const CALLBACKSERVER_GET_URL 	= '/getcallbackip?';
	const QRCODE_CREATE_URL			= '/qrcode/create?';
	const QRCODE_IMG_URL			= 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=';
	const SHORT_URL					= '/shorturl?';
	const USER_GET_URL				= '/user/get?';
	const USER_INFO_URL				= '/user/info?';
	const TEMPLATE_SEND_URL 		= '/message/template/send?';*/

	//apix key
	//www.apix.cn   u:126email   p:laohui529
	/*const APIX_MOVIE_U = "http://a.apix.cn/apixlife/movie/movie?";
	const APIX_MOVIE_K = "addb76d4e3cf49e8693cd665fb9ca4c7";
	const APIX_FILM_U = "http://a.apix.cn/apistore/movie/film?";
	const APIX_FILM_K = "1ff8c2cd7ebb45de7709ba8f83464560";*/

	//图灵机器人
	/*const TURING_API = "http://www.tuling123.com/openapi/api";
	const TURING_KEY = "ff5cc61068e80a365db913de8eb8303e";*/
	
	//设置公众号账号
	public $account = "";
	private $_path_ = array(
		"sys" => NULL,
		"mps" => NULL,
		"self" => NULL
	);

	//公众号配置参数
	private $_config_ = array();

	//全局token缓存文件
	/*private $_token_cache_ = array(
		"file" => array(
			"access_token" => NULL,
			"jsapi_ticket" => NULL,
			"card_api_ticket" => NULL
		),
		"expire" => 4000	//token过期时间
	);*/
	
	//请求的URL输入参数
	/*private $get = array();
	private $urlParam = array("signature","timestamp","nonce","msg_signature","encrypt_type");

	//请求的数据
	private $post = NULL;
	private $request = NULL;

	//回复数据模板
	private $responseTpl = array();

	//判断是否为接入验证
	private $isConnValidate = FALSE;

	//判断是否处于加密状态
	private $isEncrypt = FALSE;
	private $encryptor = NULL;*/

	//用户openid获取相关
	//public static $openidsessionids = array("WX_APP","WX_OPENID","WX_OPENID_ACTK","WX_OPENID_RFTK","WX_OPENID_SCOPE","WX_OPENID_EXPR","WX_OPENID_INFO");

	//微信公众平台接口API处理类
	public $wxapi = NULL;

	//公众号 message/event 处理对象
	public $wxmsg = NULL;

	//wxpay
	/*public $payinfo = array(
		"appname" => "",
		"logfile" => ""
	);*/



	/**** 初始化 ****/

	public function __construct($wxaccount="qzcygl"){
		//检查
		if(self::_check_mp($wxaccount)){
			$this->account = $wxaccount;
			//配置路径
			$this->_path_["sys"] = dirname(__FILE__)."/..";
			$this->_path_["mps"] = $this->_path_["sys"]."/account";
			$this->_path_["self"] = $this->_path_["mps"]."/".$wxaccount;
			//导入配置文件
			$this->_config_ = require_once($this->path("config.php"));
			//cache
			$this->_config_["APP_CACHE"] = array(
				//token cache
				"token" => array(
					"access_token" => $this->path("access_token.txt"),
					"jsapi_ticket" => $this->path("jsapi_ticket.txt"),
					"card_api_ticket" => $this->path("card_api_ticket.txt"),
					"expire" => 4000
				),
				//回复数据模板
				"reptpl" => require_once($this->path("sys")."/lib/responseTpl.php")
			);
			//special usr
			if(file_exists($this->path("self")."/special_usr.php")){
				$this->_config_["USR_SPECIAL"] = require($this->path("self")."/special_usr.php");
			}else{
				$this->_config_["USR_SPECIAL"] = array();
			}

		}else{
			return FALSE;
		}
	}



	/**** 通用 ****/

	//获取路径信息
	public function path($p=""){
		if(array_key_exists($p,$this->_path_)){
			return $this->_path_[$p];
		}else{
			return $this->_path_["self"].(empty($p) ? "" : "/".$p);
		}
	}

	//获取config信息
	public function conf($key=""){
		if(empty($key)){
			return $this->_config_;
		}else if(isset($this->_config_[$key])){
			return $this->_config_[$key];
		}else{
			if(strpos($key,"/")!==FALSE){
				return _array_xpath_($this->_config_,$key);
			}else{
				return NULL;
			}
		}
	}

	//url  
	public function url($u=""){
		$wxu = _host_()."/wx/".$this->_config_["APP_NAME"];
		if(empty($u)){return $wxu;}
		return $wxu."/"._str_del_($u,"/","left");
	}

	//跳转到指定的微信页面
	public function gotourl($gotourl="", $fix=TRUE){
		//将 ?_HASH_=aa|bb|cc 转为 #/aa/bb/cc
		if(strpos($gotourl,"?_HASH_=")!==FALSE){
			$ga = explode("?_HASH_=",$gotourl);
			$hash = $ga[1];
			if(strpos("&",$hash)!==FALSE){
				$gaa = explode("&",$hash);
				$hash = array_shift($gaa);
				$qs = count($gaa)<=0 ? "" : "?".implode("&",$gaa);
				$ga[0] .= $qs;
			}
			$hash = str_replace("|","/",$hash);
			$gotourl = $ga[0]."#/".$hash;
		}
		if($fix==TRUE){
			$gu = $this->url($gotourl);
			//_header_("Location: ".$this->url($gotourl));
		}else{
			if(strpos($gotourl,"_WX_/")===FALSE){
				$gu = _host_()."/"._str_del_($gotourl,"/","left");
				//_header_("Location: "._host_()."/"._str_del_($gotourl,"/","left"));
			}else{
				$gu = $this->url(str_replace("_WX_/","",$gotourl));
				//_header_("Location: ".$this->url($gotourl));
			}
		}
		//return $gu;
		_header_("Location: ".$gu);
	}

	//获取当前页面的url，http://host/wx/{APP_NAME}/XXXX
	public function getselfurl(){
		$uri = $_SERVER["REQUEST_URI"];
		if(strpos($uri,"/wx/".$this->_config_["APP_NAME"]."/")===FALSE){
			return _str_del_($uri,"/","left");
		}else{
			$uri = str_replace("/wx/".$this->_config_["APP_NAME"]."/","_WX_/",$uri);
			return $uri;
		}
	}

	//检查用户openid，不存在则跳转获取后，再回来
	public function openidready($getusrinfo=FALSE){
		return $this->wxapi->check_openid($getusrinfo);
	}

	//根据用户信息，内部调用方法
	public function getusrinfo(){
		$u = $this->url("openid/getcode?gotourl=".$this->url("openid/getusrinfo")."&getusrinfo=yes");
		_curl_wx_($u);
		return $this->wxapi->openid_session();
	}



	/**** 静态 ****/
	//实例化Wechat类
	public static function load($wxaccount=""){
        if(self::_check_mp($wxaccount)){
            if(isset(self::$_MP_[$wxaccount])){
                return self::$_MP_[$wxaccount];
            }else{
                self::$_MP_[$wxaccount] = new Wechat($wxaccount);
				self::$_MP_[$wxaccount]->wxapi = new WxApi($wxaccount);
				//加载 message/event 处理类
				$mf = dirname(__FILE__)."/../account/".$wxaccount."/WxMsg.class.php";
				if(file_exists($mf)){
					$mcls = "WxMsg_".$wxaccount;
					require_once($mf);
					self::$_MP_[$wxaccount]->wxmsg = new $mcls($wxaccount);
				}else{
					self::$_MP_[$wxaccount]->wxmsg = new WxMsg($wxaccount);
				}
                return self::$_MP_[$wxaccount];
            }
        }else{
            trigger_error("WCT001@",E_USER_ERROR);
        }
    }
    public static function _check_mp($wxaccount=""){
        return !empty($wxaccount) && file_exists(dirname(__FILE__)."/../account/".$wxaccount."/config.php");
	}

	//获取有效的wxaccount列表
	public static function wxaccounts(){
		$dir = dirname(__FILE__)."/../account";
		$acs = _list_dir_($dir);
		$accs = array();
		if(!empty($acs)){
			for($i=0;$i<count($acs);$i++){
				if(file_exists($dir."/".$acs[$i]."/config.php")){
					$accs[] = $acs[$i];
				}
			}
		}
		return $accs;
	}

	//写入数据库
	public static function db_input(){
		
	}
	
	



	/**** Wechat API ****/
	//调用wxapi
	public function _api(){
		$args = func_get_args();
		$argn = func_num_args();
		if($argn<1){
			return FALSE;
		}
		$api = array_shift($args);
		if(method_exists($this->wxapi,$api)){
			return call_user_func_array(array($this->wxapi,$api),$args);
		}
	}
	//用户注册登记
	public function api_usregister(){
		if($this->openidready(TRUE)){
			$usrinfo = $this->wxapi->openid_session();
			//检查openid是否已经存在
			$tb = _db_("wx_base")->t("usr");
			$p = $this->path("page/usregister.php");
			if($tb->openid_exists($usrinfo["openid"])){
				_export_data_(file_get_contents($this->path("page/dialog/ok_alreadyreg.html")),"html");
			}else{
				//用户未注册过
				require($this->path("page/usregister.php"));
			}
		}
		/*if(file_exists($p)){
			//require($p);
			if($this->openidready(TRUE)){
				$usrinfo = $this->wxapi->openid_session();
				//检查openid是否已经存在
				$tb = _db_("wx_base")->t("usr");
				if($tb->openid_exists($usrinfo["openid"])){
					_export_data_(file_get_contents($this->path("page/dialog/ok_alreadyreg.html")),"html");
				}
				//写入数据库

				//require($p);
				//return $usrinfo;
			}
		}else{
			$h = file_get_contents($this->path("page/wx500.html"));
			_export_data_($h,"html");
		}*/
	}

	//wechat api调用url规则，调用方式http://host/wx/[公众号ID]/api/[apiname]?p=v&p=v
	/*public function api_router(){
		$uri = $_SERVER["REQUEST_URI"];
		if(strpos($uri,"?")!==FALSE){
			$uri = explode("?",$uri);
			$uri = $uri[0];
		}
		if(strpos($uri,"/")!==FALSE){
			$uris = explode("/",$uri);
			array_shift($uris);
			if(empty($uris[count($uris)-1])){
				array_pop($uris);
			}
		}else{
			$uris = array();
			$uris[] = $uri;
		}
		if(count($uris)<4){
			trigger_error("999099@API调用URL参数不完整",E_USER_ERROR);
		}else{
			array_shift($uris);		//wx
			$wxaccount = array_shift($uris);	//appname
			array_shift($uris);		//api
			$apiname = array_shift($uris);	//apiname
			if($wxaccount!=$this->_config_["APP_NAME"]){
				trigger_error("999099@API调用参数错误，错误的APP_NAME",E_USER_ERROR);
			}else{
				if(!method_exists($this,"api_".$apiname)){
					trigger_error("999099@API调用参数错误，目标API[".$apiname."]不存在",E_USER_ERROR);
				}else{
					return call_user_func_array(array($this,"api_".$apiname),$uris);
				}
			}
		}
	}

	//内部调用api
	public function api_run(){
		$args = func_get_args();
		$apiname = array_shift($args);
		if(!method_exists($this,"api_".$apiname)){
			trigger_error("999099@API调用参数错误，目标API[".$apiname."]不存在",E_USER_ERROR);
		}else{
			return call_user_func_array(array($this,"api_".$apiname),$args);
		}
	}*/

	

	
	//api method 记录用户地理位置
	protected function api_reclocal(){

		Session::set("WX_LOCAL_LATTITUDE",_get_("lattitude",0));
		Session::set("WX_LOCAL_LONGITUDE",_get_("longitude",0));
		Session::set("WX_LOCAL_PRECISION",_get_("precision",0));
	}
	//api 获取当前公众号使用的支付公众号APP_NAME
	public function api_getpayappname(){
		_export_data_(array("appname"=>$this->_config_["WPAY_APP"]));
	}
	public function api_getusrinfo(){
		return $this->getusrinfo();
	}
	public function api_video(){
		//$vdo = $this->wxapi->media_get_video(_get_("vid"));
		//$vpurl $vdo["down_url"];
		//return $this->wxapi->media_list("video",5,20);
		//$vdl = $this->wxapi->media_list("video");
		//if(isset($vdl["errcode"])) return $vdl["errmsg"];
		//$vds = $vdl["item"]
    }


	/*public function getAPIUrl(){
		$args = func_get_args();
		$api = $args[0];
		$u = '';
		$pf = self::API_URL_PREFIX;

		switch($api){
			case "token" :
				$u = $pf.self::AUTH_URL."appid=".$this->_config_["APP_ID"]."&secret=".$this->_config_["APP_SECRET"];
				break;
			case "userinfo" :
				$u = $pf.self::USER_INFO_URL."access_token=".$this->AccessToken()."&lang=zh_CN&openid=".$args[1];
				break;
			case "qrcodeimg" :
				$u = $pf.self::QRCODE_IMG_URL.$args[1];
				break;
			case "menucreate" :
				$u = $pf.self::MENU_CREATE_URL."access_token=".$this->AccessToken();
				break;
			case "ticket" :
				$u = $pf.self::GET_TICKET_URL."type=jsapi&access_token=".$this->AccessToken();
				break;
			case "template" :
				$u = $pf.self::TEMPLATE_SEND_URL."access_token=".$this->AccessToken();
				break;
		}
		return $u;
	}*/



	/**** 接收请求数据，返回处理结果 ****/

	//接受
	/*public function Receive(){
		//获取URL参数
		//$this->getURLParam();
		for($i=0;$i<count($this->urlParam);$i++){
			$p = $this->urlParam[$i];
			if(empty($p)){
				$this->get[$p] = NULL;
			}
			$this->get[$p] = _get_($p,NULL);
		}
		//验证此次请求的签名信息
		if($this->validateSignature()==FALSE){
			trigger_error("WCT991@签名验证未通过",E_USER_ERROR);
		}
		$this->isConnValidate = isset($_GET["echostr"])==TRUE;
		//网址接入验证
		if($this->isConnValidate==TRUE){
			echo $_GET["echostr"];
			die();
		}
		//获取加密状态
		if($this->get["msg_signature"]!=NULL && $this->get["encrypt_type"]!=NULL && $this->get["encrypt_type"]=="aes"){
			$this->isEncrypt = TRUE;
			$this->encryptor = new WXBizMsgCrypt($this->_config_["APP_TOKEN"],$this->_config_["APP_AES"],$this->_config_["APP_ID"]);
		}

		//获取并解析请求XML
		if(!isset($GLOBALS["HTTP_RAW_POST_DATA"])){
			trigger_error("WCT991@未收到请求数据",E_USER_ERROR);
		}
		$this->post = $GLOBALS["HTTP_RAW_POST_DATA"];
		$this->parseRequest();

		$this->wxmsg->ready();
	}*/
	
	public function Receive(){
		$this->wxmsg->receive();
	}

	//解析请求XML
/*	private function parseRequest(){
		$post = $this->post;
		$get = $this->get;
		$xml = '';
		if($this->isEncrypt==TRUE){
			$errcode = $this->encryptor->decryptMsg($get["msg_signature"],$get["timestamp"],$get["nonce"],$post,$xml);
			if($errcode!=0){
				trigger_error("WCT992@".$errcode,E_USER_ERROR);
			}
		}else{
			$xml = $post;
		}
		$xml = (array) simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		//将数组键名转换为小写，提高健壮性，减少因大小写不同而出现的问题
		$this->request = array_change_key_case($xml, CASE_LOWER);
	}

	//生成回复消息
	private function createResponse($param=array()){
		$tu = $this->Req("fromusername");
		$fu = $this->Req("tousername");
		$msgtype = array_shift($param);
		$tpl = $this->responseTpl[$msgtype];
		$rep = '';
		switch($msgtype){
			case "text" :
			case "image" :
			case "voice" :
				$rep = sprintf($tpl, $tu, $fu, time(), $param[0] );
				break;
			case "video" :
				$rep = sprintf($tpl, $tu, $fu, time(), $param[0], $param[1], $param[2] );
				break;
			case "music" :
				$rep = sprintf($tpl, $tu, $fu, time(), $param[0], $param[1], $param[2], $param[3], $param[4] );
				break;
			case "news" :
				$tpli = $this->responseTpl["newsitem"];
				$newsitems = $param[0];
				$itemnum = count($newsitems);
				//var_dump($itemnum);
				$itemstr = "";
				for($i=0;$i<$itemnum;$i++){
					$newsitem = $newsitems[$i];
					$itemstr .= sprintf($tpli,$newsitem[0],$newsitem[1],$newsitem[2],$newsitem[3]);
				}
				$rep = sprintf($tpl, $tu, $fu, time(), $itemnum, $itemstr);
		}
		return $rep;
	}

	//发送回复消息
	private function sendResponse($data){
		$get = $this->get;
		$msg = '';
		if($this->isEncrypt==TRUE){
			$errcode = $this->encryptor->encryptMsg($data, $get["timeStamp"], $get["nonce"], $msg);
			if($errcode!=0){
				trigger_error("WCT993@".$errcode,E_USER_ERROR);
			}
		}else{
			$msg = $data;
		}
		header("Content-type: text/xml; charset=utf-8");
		echo $msg;
		exit;
	}

	//读取请求数据内容
	public function Req($param=NULL){
		if($param==NULL){
			return $this->request;
		}else{
			if(isset($this->request[$param])){
				return $this->request[$param];
			}else{
				return NULL;
			}
		}
	}*/

	//回复请求
	/*
	*   回复文本    Rep("text","content")
	*   回复图片    Rep("image","media_id")
	*   回复语音    Rep("voice","media_id")
	*   回复视频    Rep("video","media_id","title","description")
	*   回复音乐    Rep("music","title","description","musicurl","hqmusicurl","thumbmediaid")
	*   回复图文    Rep("news",array(array("title","description","picurl","url"),array("title","description","picurl","url"),...))
	*/
/*	public function Rep(){
		$args = func_get_args();
		$rep = $this->createResponse($args);
		$this->sendResponse($rep);
	}*/

	//针对不同请求类型，调用响应方法
	/*public function Response(){
		$msgtype = $this->Req("msgtype");
		if($msgtype=="event"){
			$this->onEvent();
		}else{
			$this->onMessage();
		}
	}*/
	public function Response(){
		$this->wxmsg->response();
	}

	//模板消息下发
/*	public function RepTemplateMessage($tmpid,$openid,$url,$data,$color="#a80012"){
		$api = $this->API("msg_tpl");
		//self::export($u);
		//die();
		$u = $api["api"];
		$tmp = $this->conf("MB_SET");
		$tmp = $tmp[$tmpid];
		$tmpid = $tmp["id"];
		$tmpdataarr = explode("|",$tmp["data"]);
		$sdata = array();
		$sdata["touser"] = $openid;
		$sdata["template_id"] = $tmpid;
		$sdata["url"] = $url;
		$sdata["topcolor"] = $color;
		$tmpdata = array();
		for($i=0;$i<count($tmpdataarr);$i++){
			if(is_array($data[$tmpdataarr[$i]]) || isset($data[$tmpdataarr[$i]]["value"])){
				$tmpdata[$tmpdataarr[$i]] = $data[$tmpdataarr[$i]];
			}else{
				$tmpdata[$tmpdataarr[$i]] = array(
					"value" => $data[$tmpdataarr[$i]],
					"color" => $color
				);
			}
		}
		$sdata["data"] = $tmpdata;

		$sjson = json_encode($sdata,JSON_UNESCAPED_UNICODE);
		$rst = self::CURL($u,$sjson);
		//$rst = self::CURL($u,$sdata);
		return $rst;
	}

	//修改请求的数据
	public function editRequest($param, $value){
		if(!empty($param) && $this->Req($param)!=NULL){
			$this->request[$param] = $value;
		}
	}

	//获取请求来源的用户OpenID
	public function OpenID(){
		return $this->Req("fromusername");
	}
	*/



	/**** 静态/通用/私有 方法 ****/

	//验证此次请求的签名信息
/*	private function validateSignature(){
		$g = $this->get;
		if($g["signature"]==NULL || $g["timestamp"]==NULL || $g["nonce"]==NULL){
			return FALSE;
		}
		$signatureArr = array($this->_config_["APP_TOKEN"], $g["timestamp"], $g["nonce"]);
		sort($signatureArr, SORT_STRING);
		return sha1(implode($signatureArr)) == $g["signature"];
	}*/

	//curl传递数据
    public static function CURL($url, $data = null){
		$curl = curl_init();
		/*$header = array(
			"content-type: application/x-www-form-urlencoded;charset=UTF-8"
		);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);*/
		curl_setopt($curl, CURLOPT_URL, $url);
		//curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, TRUE);
		//curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, TRUE);
		//curl_setopt($curl, 2, TRUE);
		if (!empty($data)){
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($curl);
		curl_close($curl);
		return $output;
	}

	

	

	//读取/写入用户地理位置缓存，在用户上报地理位置时执行写操作，缓存位置  [Wechat->cachePath]/local.json
	public function getLocalCache($openid=""){
		if(!empty($openid)){
			$cf = $this->cachePath."/local.json";
			$lc = file_get_contents($cf);
			$lc = _j2a_($lc);
			if(isset($lc["local"]["U_".$openid])){
				return $lc["local"]["U_".$openid];
			}
		}
		return array();
	}
	public function setLocalCache($openid="",$lati,$long,$prec,$add){
		if(!empty($openid)){
			$cf = $this->cachePath."/local.json";
			$lc = file_get_contents($cf);
			$lc = _j2a_($lc);
			$lc["update"] = time();
			$lc["local"]["U_".$openid] = array(
				"latitude" => $lati,
				"longitude" => $long,
				"precision" => $prec,
				"address" => $add
			);
			$nlc = _a2j_($lc);
			if(file_put_contents($cf,$nlc,LOCK_EX)===FALSE){	//写入时锁定，如果写入失败，则2秒后再试
				sleep(2);
				$this->setLocalCache($openid,$lati,$long,$prec,$add);
			}
		}
	}
	/*
	//静态方法，检查用户消息，查找关键字
	public static function checkMessageKeyword($msg="",$keyword="小石头"){
		if(strpos($msg,$keyword)===FALSE){
			return FALSE;
		}else{
			return TRUE;
		}
	}
	public static function checkMessageKeywords($msg,$keywords=array()){
		$flag = FALSE;
		if(is_string($keywords)){
			if(strpos($keywords,",")===FALSE){
				$keywords = array($keywords);
			}else{
				$keywords = explode(",",$keywords);
			}
		}
		for($i=0;$i<count($keywords);$i++){
			if(self::checkMessageKeyword($msg,$keywords[$i])==TRUE){
				$flag = TRUE;
				break;
			}
		}
		return $flag;
	}

	//随机选取数组中的某一项
	public static function rndgetFromArray($arr){
		if(!empty($arr)){
			$n = count($arr)-1;
			$i = rand(0,$n);
			return $arr[$i];
		}
		return '';
	}*/



	/**** apix api ****/

	public static function apixGet($api,$key){
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $api,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 3,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_HTTPHEADER => array(
				"accept: application/json",
				"apix-key: ".$key,
				"content-type: application/json"
			),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		if($err){
			return FALSE;
		}else{
			return $response;
		}
	}
	public static function apixMovie($name,$page=1,$num=5,$tostring=TRUE){
		$api = self::APIX_MOVIE_U."name=".$name."&page=".$page."&num=".$num;
		$key = self::APIX_MOVIE_K;
		$rs = self::apixGet($api,$key);
		$rs = json_decode($rs,TRUE);
		if($tostring==TRUE){
			$rtn = '';
			$data = $rs["data"]["movie"];
			for($i=0;$i<count($data);$i++){
				$d = $data[$i];
				$rtn .= $d["name"]." [ ".$d["country"]." | ".$d["rating"]."分 | 上映于".$d["release_date"]." | ".$d["type"]." | 导演 ".$d["director"]." | 主演 ".implode(",",$d["actor"])." ] ：".$d["slot"]."；====No.".($i+1)."==end====";
			}
			return $rtn;
		}else{
			return $rs;
		}
	}
	public static function apixFilm($loca,$wd){
		$loca = explode(",",$loca);
		$api = self::APIX_FILM_U."wd=".$wd."&location=".$loca[1].",".$loca[0]."&pn=1&rn=5&coord_type=wgs84";
		$key = self::APIX_FILM_K;
		$rs = self::apixGet($api,$key);
		//$rs = json_decode($rs,TRUE);
		return $rs;
	}


	/* 响应处理 */

	//消息处理
	private function onMessage(){
		$msg = $this->Req("content");
		$openid = $this->Req("fromusername");
		//$this->Rep("text","xxxx");
		//exit;

		$mid = $this->Req("mediaid");
		if(!is_null($mid)){
			$this->Rep("text",$mid);
			exit;
		}

		//调用公众号账号method/on_message
		$m = $this->path("self")."/method/on_message.php";
		if(file_exists($m)){
			require($m);
		}else{
			$this->Rep("text","非常抱歉，公众号出了点技术问题。请过一会再试一下。*^_^*");
		}

	}

	//事件处理
	private function onEvent(){
		$evtName = $this->Req("event");
		$evtName = strtolower($evtName);
		$openid = $this->Req("fromusername");

		//调用公众号账号method/event/$evtName
		$m = $this->methodPath."/event/".$evtName.".php";
		if(file_exists($m)){
			require($m);
		}else{
			require($this->methodPath."/event/default.php");
		}

		
	}

	//用户点击菜单事件
	private function onClick(){
		$_key = $this->Req("eventkey");
		$openid = $this->Req("fromusername");

		switch($_key){

			//每日签到
			case "QS_M_SIGNUP" :
				$data = Wechat::CURL(_host_()."/Wechat/M/System/?m=user_modscore_signup&openid=".$openid);
				$data = Wechat::j2a($data);
				if(isset($data["errcode"]) && $data["errcode"]!="0"){
					$this->Rep("text","Sorry！系统好像出错了。请过一会再尝试。".$data["errmsg"]);
				}else{
					if($data["statu"]=="signed"){
						$this->Rep("text","您今天已经签过到了，明再来吧。记得每天签到哦，有积分送的。");
					}else{
						$this->Rep("text","签到成功！看看收到的积分通知吧。每天签到有积分相送哦！");
					}
				}
				break;

			//用户积分和余额查询
			case "QS_M_CHECKSB" :
				$urs = DB::query("SELECT * FROM `qr_wx_member` WHERE `openid` = '".$openid."' LIMIT 1");
				if(count($urs)<=0){
					$this->Rep("text","积分查询失败！\n\n你还没有领取乾石会员卡，回复\"1\"领取会员卡。");
				}else{
					$s = $urs[0]["score"];
					$l = $urs[0]["lvl"];
					$n = $urs[0]["cardnum"];
					$b = $urs[0]["balance"];
					$ls = array("铁牌会员","铜牌会员","银牌会员","金牌会员","白金会员","钻石会员","至尊会员");
					$this->RepTemplateMessage("JFYE",$openid,_host_()."/Wechat/M/#/my",array(
						"first" => "您的乾石会员账户积分以及充值余额信息查询成功：",
						"keyword1" => "￥ ".number_format($b,2),
						"keyword2" => $s." 积分（".$ls[(int)$l]."）",
						"keyword3" => $n,
						"remark" => "点击查看详情，进入用户主页。参与乾石活动，赢取更多积分，享更多会员折扣！"
					),"#000000");
				}
				break;

			//发表投诉与建议，指南
			case "GUIDE_ADVISE" :
				$this->rep("text","嗯，小石头知道了。现在我们的投诉系统正在紧张内测中，请耐心稍候。");
				break;

			//服务号操作指南，evt=跳转到 ?/about/index/tab/guide
			case "GUIDE_ALL" :
			case "GUIDE_SERVICE" :
			case "GUIDE_JOIN" :
				
				break;

			//默认操作
			default :
				$this->rep("text","小石头君还很小，猜不透您的心思啊，所以请您原谅咯，等小石头君长大一点，或许就能弄明白您要干什么了。*^_^*");
				break;
		}
	}

	//关键字检索，用于响应用户任意输入，根据结果返回文本信息，如果没有找到目标结果，返回FALSE
	public function doSearch($msg){
		$msg = trim($msg);
		$msg = strip_tags($msg);
		$msg = str_replace("=","",$msg);

		//开始检索
		$sql = "SELECT * FROM `qr_wx_article` WHERE ";
		$sql .= "`title` LIKE '%".$msg."%'";
		//$sql .= " OR `content` LIKE '%".urlencode($msg)."%'";
		$sql .= " ORDER BY `time_update` DESC LIMIT 0,10";
		$rs = DB::query($sql);
		if(count($rs)<=0){
			return FALSE;
		}else{
			$ss = "找到".count($rs)."条关于「".$msg."」的乾石信息，直接点击即可查看：\n\n";
			for($i=0;$i<count($rs);$i++){
				$tit = $rs[$i]["title"];
				//$tit = mb_substr($tit,0,12,'utf-8');
				$ss .= "<a href=\"http://www.qscygl.com/Wechat/M/#/view/article/id/".$rs[$i]["id"]."\">".$tit."</a>\n\n";
			}
			$ss .= "\n你还可以点击下方的乾石公众号菜单，进入相应的功能。或<a href=\"http://www.qscygl.com/Wechat/M/#/article\">点我直接进入最新消息</a>。";
			return $ss;
		}





	}




	/**
	 * 微信支付相关
	 * 
	 */
	//外部调用接口，input=订单数据，输出json对象，包含orderinfo,jsapiparameters
	public function pay($wxaccount=NULL, $trade_type="jsapi", $openid=NULL, $input=array(), $export_json=FALSE){
		if($this->pay_ready($wxaccount,$trade_type)===TRUE){
			if(method_exists($this,"pay_".$trade_type)){
				$wxpaydata = call_user_func_array(array($this,"pay_".$trade_type),array($openid,$input));
				//_export_data_(array("result"=>$jsApiParameters));
				if($export_json===TRUE){
					_header_("content-type:application/json; charset=utf-8");
					echo _a2j_($wxpaydata);
					die();
				}else{
					return $wxpaydata;
				}
			}else{
				trigger_error("999011@",E_USER_ERROR);
			}
		}else{
			trigger_error("999012@",E_USER_ERROR);
		}
	}

	//notify微信支付回调处理
	protected function api_paynotify($storeid=NULL){
		$wxaccount = $this->_config_["APP_NAME"];	//支付回调接口是在支付微信账号下，与调用支付接口的微信账号无关
		if($this->pay_ready($wxaccount)===TRUE){
			if(!class_exists("WxPayNotify")){
				require_once($this->sysPath."/pay/lib/WxPay.Notify.php");
			}
			//调用 CustomNotifyCallback 类，此类应在 WxStore.class.php 中定义
			if(!class_exists("CustomNotifyCallback")){
				require_once($this->sysPath."/pay/lib/WxPay.CustomNotifyCallback.php");
			}
			$notify = new CustomNotifyCallback();
			$notify->Handle(false);
		}
	}

	//支付接口准备，调用必需的类库，第一个参数为指定的将要使用的支付账号的公众号APP_NAME，不指定则参数为NULL。其余参数为要加载的 wx/pay/lib/WxPay.XXX.php 类文件文件
	public function pay_ready($wxaccount=NULL, $trade_type="jsapi"){
		if(is_null($wxaccount) || !is_string($wxaccount) || $wxaccount==""){
			$wxaccount = $this->_config_["APP_NAME"];
		}
		//引用 WxPay.Config.php，文件应包含在wxapp根目录下，或在指定appname的wxapp根目录下，文件不存在则返回FALSE
		$wpcf = $this->sysPath."/account/".$wxaccount."/pay/WxPay.Config.php";
		if(!file_exists($wpcf)){
			//不存在文件，则检查this->config["WPAY_APP"]是否指定了appname
			if(isset($this->_config_["WPAY_APP"])){
				$wpcf = $this->sysPath."/account/".$this->_config_["WPAY_APP"]."/pay/WxPay.Config.php";
				if(!file_exists($wpcf)){
					return FALSE;
				}else{
					require_once($wpcf);
				}
			}else{
				return FALSE;
			}
		}else{
			require_once($wpcf);
		}
		if(!class_exists('WxPayConfig')){return FALSE;}
		//引入WxPay接口必需文件
		require_once($this->sysPath."/pay/lib/WxPay.Exception.php");
		require_once($this->sysPath."/pay/lib/WxPay.Data.php");
		require_once($this->sysPath."/pay/lib/WxPay.Api.php");
		if(!class_exists('WxPayApi')){return FALSE;}
		//引入其他文件
		$reqfs = array();
		$rfs = array(
			"jsapi" => array("JsApiPay")
		);
		if(isset($rfs[$trade_type]) && is_array($rfs[$trade_type])){
			$reqfs = $rfs[$trade_type];
		}
		for($i=0;$i<count($reqfs);$i++){
			$reqf =$reqfs[$i];
			if(strpos($reqf,"/")!==FALSE){
				$reqfa = explode("/",$reqf);
				$reqfl = array_pop($reqfa);
				$reqf = $this->sysPath."/pay/lib/".implode("/",$reqfa)."/WxPay.".$reqfl.".php";
			}else{
				$reqf = $this->sysPath."/pay/lib/WxPay.".$reqf.".php";
			}
			if(file_exists($reqf)){
				require_once($reqf);
			}
		}
		return TRUE;
	}

	//create pay data
	private function pay_createdata($input=array()){
		//如果没有给出订单数据，则根据前台POST过来的订单json数据，生成微信支付必需的数据对象
		$input = empty($input) ? file_get_contents("php://input") : $input;
		if(empty($input)){
			return FALSE;
		}else{
			$inparr = is_array($input) ? $input : _j2a_($input);
			return array(
				"gname" => isset($inparr["name"]) ? $inparr["name"]."-订单" : $this->_config_["APP_NICK"]."-订单",
				"trade_no" => isset($inparr["id"]) ? $inparr["id"] : date("YmdHis").WxPayConfig::MCHID,
				"fee" => isset($inparr["fee"]["pay"]) && is_numeric($inparr["fee"]["pay"]) ? (int)($inparr["fee"]["pay"]) : 0,	//支付金额单位：分
				"attach" => isset($inparr["vertifycode"]) ? $inparr["vertifycode"] : $this->_config_["APP_NAME"]."_wxpay",
				"notify" => _url_("wx/".$input["usr"]["wxpayaccount"]."/api/paynotify/".$input["goods"]["storeid"]),
				"trade_type" => "jsapi",
				"original_input" => $inparr
			);
		}
	}

	//统一下单
	private function pay_unifiedorder($pdata=array()){
		if(!is_numeric($pdata["fee"]) || (int)$pdata["fee"]<=0){
			self::pay_log("create_wxpay_error,unifiedorder","创建微信支付接口失败，没有指定金额");
			//Response::export_wx500page("创建微信支付接口失败，没有指定金额！");
			return array(FALSE,"创建微信支付接口失败，没有指定金额");
		}
		$input = new WxPayUnifiedOrder();
		$input->SetBody($pdata["gname"]);
		$input->SetAttach($pdata["attach"]);
		$input->SetOut_trade_no($pdata["trade_no"]);
		$input->SetTotal_fee("".$pdata["fee"]);
		$input->SetTime_start(date("YmdHis"));
		$input->SetTime_expire(date("YmdHis", time() + 600));
		$input->SetGoods_tag("test");
		$input->SetNotify_url($pdata["notify"]);
		//$input->SetNotify_url(_url_("wx/".$this->_config_["APP_NAME"]."/pay/notify"));
		$input->SetTrade_type(strtoupper($pdata["trade_type"]));
		$input->SetOpenid($pdata["openid"]);
		$order = WxPayApi::unifiedOrder($input);
		if(isset($order["return_code"]) && $order["return_code"]=="FAIL"){
			trigger_error("999015@".$order["return_msg"],E_USER_ERROR);
		}else{
			return $order;
		}
	}

	//pay log
	private static function pay_log($keyword="info", $msg=""){
		$keyword = _to_array_($keyword);
		$logf = _path_("wx/pay/log/".WxPayConfig::MCHID.".json");
		$logt = time();
		if(!file_exists($logf)){
			$logfh = @fopen($logf,'a+');
			$log = '{"mchid":"'.WxPayConfig::MCHID.'","log":[]}';
			@fwrite($logfh,$log);
			@fclose($logfh);
		}else{
			$log = file_get_contents($logf);
		}
		$logarr = _j2a_($log);
		$logarr["log"][] = array(
			"keyword" => $keyword,
			"timestamp" => $logt,
			"time" => date("Y-m-d H:i:s",$logt),
			"msg" => $msg
		);
		$log = _a2j_($logarr);
		file_put_contents($logf,$log,LOCK_EX);
	}
	private static function pay_getlog($keyword=""){
		$logf = _path_("wx/pay/log/".WxPayConfig::MCHID.".json");
		if(!file_exists($logf)){
			return array();
		}else{
			$log = file_get_contents($logf);
			$logarr = _j2a_($log);
			$rst = array();
			for($i=0;$i<count($logarr["log"]);$i++){
				$tlog = $logarr["log"][$i];
				if(in_array($keyword,$tlog["keyword"]) || strpos($tlog["msg"],$keyword)!==FALSE){
					$rst[] = $tlog;
				}
			}
			return $rst;
		}
	}

	//jsapi微信支付接口，输出jsApiParameters，json格式数据，供前台WeixinJSBridge调用呼出支付接口
	public function pay_jsapi($openid=NULL, $input=array()){
		if(!class_exists('JsApiPay')){require_once($this->sysPath."/pay/lib/WxPay.JsApiPay.php");}
		$jsapipay = new JsApiPay();
		if(is_null($openid)){
			$openid = $jsapipay->GetOpenid();
		}
		$pdata = $this->pay_createdata($input);
		if($pdata!==FALSE){
			$pdata["trade_type"] = "JSAPI";
			$pdata["openid"] = $openid;
			$unifiedorder = $this->pay_unifiedorder($pdata);
			if(is_array($unifiedorder) && $unifiedorder[0]===FALSE){
				return $unifiedorder;
			}
			$jsApiParameters = $jsapipay->GetJsApiParameters($unifiedorder);
			$original_input = $pdata["original_input"];
			//return '{"orderinfo":'._a2j_($original_input).',"jsapiparameters":'.$jsApiParameters.'}';
			return array(
				"orderinfo" => $original_input,
				"jsapiparameters" => _j2a_($jsApiParameters)
			);
		}else{
			//log
			self::pay_log("create_wxpay_error,jsapi","创建微信支付接口失败，输入的参数不完整或不正确！");
			trigger_error("999013@",E_USER_ERROR);
		}
		 
	}




	/**
	 * 	微信店铺相关
	 * 
	 */
	//店铺准备，引用文件，创建店铺对象
	public function store_ready($storeid=NULL){
		if(!class_exists("WxStore")){
			require_once($this->sysPath."/store/lib/WxStore.class.php");
		}
		if(!is_null($storeid)){
			//return new WxStore($storeid);
			return WxStore::load_store($storeid);
		}else{
			return FALSE;
		}
	}








}



/**
 * 微信message处理类
 */




/**
 * 别名方法
 */
function _wechat_($wxaccount=""){return Wechat::load($wxaccount);}
function _wx_exists_($wxaccount=""){return Wechat::_check_mp($wxaccount);}
function _wx_accounts_(){return Wechat::wxaccounts();}

function _wx_add_response_(){return call_user_func_array(array("WxMsg","_add_response"),func_get_args());}
