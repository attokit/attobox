<?php

/**
 * Attobox Framework / Module Wechat
 */

namespace Atto\Box;

use Atto\Box\wechat\WXBizMsgCrypt;
use Atto\Box\wechat\WxApi;
use Atto\Box\wechat\WxMsg;
use Atto\Box\wechat\WxScanner;

//use Atto\Box\QRcode;
//use Atto\Box\Request;
//use Atto\Box\Response;
//use Atto\Box\Model;
use Atto\Box\request\Url;

class Wechat
{
    //const SERV_URL = WEB_PROTOCOL . "://" . WEB_DOMAIN;
    //const WX_ROOT = WX_PATH;

	//已经加载的公众号对象列表，加载公众号需使用Wechat::load()方法
	public static $_MP_ = array();
	
	//设置公众号账号
	public $account = "";
	private $_path_ = array(
		"sys" => null,
		"mps" => null,
		"self" => null
	);

	//公众号配置参数
	private $_config_ = array();

	//微信公众平台接口API处理类
	public $wxapi = null;

	//公众号 message/event 处理对象
	public $wxmsg = null;

	//wxpay
	/*public $payinfo = array(
		"appname" => "",
		"logfile" => ""
	);*/



	/**** 初始化 ****/

	public function __construct($wxaccount = "qzcygl")
    {
		//检查
		if (self::checkAccount($wxaccount)) {
			$this->account = $wxaccount;
			//配置路径
			$this->_path_["sys"] = Path::make("cp/src/util/wx");
			$this->_path_["mps"] = Path::make("wx/account");
			$this->_path_["self"] = Path::make("wx/account/$wxaccount");
			//导入配置文件
			$this->_config_ = require_once($this->path("config.php"));
			//cache
			$this->_config_["APP_CACHE"] = array(
				//token cache
				"token" => array(
					"access_token" => $this->path("cache/access_token.txt"),
					"jsapi_ticket" => $this->path("cache/jsapi_ticket.txt"),
					"card_api_ticket" => $this->path("cache/card_api_ticket.txt"),
					"expire" => 4000
				),
				//扫码数据cache
				"scan" => $this->path("cache/scan.json"),
				//回复数据模板
				"reptpl" => require_once($this->_path_["sys"].DS."responseTpl.php")
			);
			//special usr
			if (file_exists($this->path("special_usr.php"))) {
				$this->_config_["USR_SPECIAL"] = require($this->path("special_usr.php"));
			} else {
				$this->_config_["USR_SPECIAL"] = array();
			}

		}else{
			return false;
		}
	}



	/**** 通用 ****/

	//获取路径信息
	public function path($p="")
    {
		if (array_key_exists($p,$this->_path_)) {
			return $this->_path_[$p];
		} else {
			return $this->_path_["self"].(empty($p) ? "" : DS.str_replace("/", DS, $p));
		}
	}

	//获取config信息
	public function conf($key = "")
    {
		if (empty($key)) {
			return $this->_config_;
		} else if (isset($this->_config_[$key])) {
			return $this->_config_[$key];
		} else {
			if (strpos($key,"/") !== false) {
				return arr_item($this->_config_, $key);
			}else{
				return null;
			}
		}
	}

	//url  
	public function url($u = "")
    {
        $wxu = Url::create(Url::protocol()."://".Url::host()."/wx/".$this->conf("APP_NAME"));
		if (!is_notempty_str($u)) return $wxu->url();
        return Url::build($u, $wxu)->url();
	}

	//检查用户openid，不存在则跳转获取后，再回来
	public function openidReady($getusrinfo = false)
	{
		return $this->wxapi->checkOpenid($getusrinfo);
	}

	//根据用户信息，内部调用方法
	public function getusrinfo()
	{
		$u = $this->url("openid/getcode?gotourl=".$this->url("openid/getusrinfo")."&getusrinfo=yes");
		Curl::wx($u);
		return $this->wxapi->openidSession();
	}

	//创建一个用户，根据用户授权的 openid 向数据模型 model/wx/Usr 中 insert 一条记录
	public function createUsr($modelKey = "")
	{
		if ($this->openidReady(true)) {
			$usr = Model::load("wx/usr");
			$data = [
				"wxaccount" => $this->conf("APP_NAME"),
				"openid" => Session::get("WX_OPENID",""),
				"model" => $modelKey
			];
			$usr->reset()->insert($data);
			return $usr->lastInsertId();
		}
		return false;
	}

	//输出 msg 页面
	public function msgPage($option = [])
	{
		Response::page("cp/src/page/wx/msg.php", [
			"pageParams" => arr_extend([
				"wxo" => $this
			], $option)
		]);
		exit;
	}



	/*
	 *	scan 相关
	 */
	/*	
	 *	扫码验证统一入口
	 *
	 *	在需要扫码验证的方法前，通过下述方式调用：
	 *		if ( Wechat::load($account)->scanVerify($option = []) === true ) { 
	 *			验证成功，用户信息在 j2a(Session::get("WX_OPENID_INFO"))
	 *			...
	 *		}
	 */
	public function scanVerify($option = [])
	{
		if (Session::has("WX_OPENID")) return true;
		//如果是ajax请求，不自动跳转到扫码界面
		if (Request::fromAjax()) return false;
		return $this->createScanQrcode($option);
	}

	//扫码动作，需要微信环境
	public function scanDo($action = "login", ...$args)
	{
		$vcode = array_shift($args);
		$cf = $this->conf("APP_CACHE/scan");
		if (!file_exists($cf)) return $this->scanMsgPage(["type"=>"notice", "errmsg"=>$cf."服务器500错误，请通知系统管理员。"]);
		$cache = file_get_contents($cf);
		$carr = j2a($cache);
		if ($this->openidReady(true) && is_notempty_str($action) && is_notempty_str($vcode)) {
			$usr = $this->wxapi->openidSession();
			
			//检查是否存在 usr 数据模型，存在则在模型数据库中查找 openid 记录
			if (!empty($args)) {
				$usrmodel = array_shift($args);
				$opid = $usr["openid"]."@".$this->account;
				$um = Model::load(str_replace("_","/",$usrmodel));
				if (empty($um)) {
					//usr 数据模型不能加载
					return $this->scanMsgPage(["type"=>"notice", "errmsg"=>"服务器错误，无法查找用户信息，请与管理员联系！"]);
				}
				$rs = $um->reset()->whereOpenid($opid)->limit(1)->select();
				if (empty($rs) || $rs->empty()) {
					//用户未注册到对应的 usr 数据库
					//跳转到注册页面
					$rgt = empty($args) ? "default" : array_shift($args);
					$url = $this->url("reg/".str_replace("/","_",$usrmodel)."/".$rgt);
					header("Location:".$url);
					exit;
				}
			}

			//save usr info
			$openid = $usr["openid"];
			if (!isset($carr["usr"][$openid])) {
				$carr["usr"][$openid] = $usr["info"];
			}
			//save scan data
			if (!isset($carr[$action])) $carr[$action] = [];
			if (!isset($carr[$action]["scan_".$vcode])) $carr[$action]["scan_".$vcode] = [];
			$carr[$action]["scan_".$vcode]["timestamp"] = time();
			$carr[$action]["scan_".$vcode]["openid"] = $openid;
			//save
			$cache = a2j($carr);
			file_put_contents($cf, $cache);
			return $this->scanMsgPage(["type"=>"success"]);
		}
		return $this->scanMsgPage(["type"=>"notice", "errmsg"=>"用户未授权，无法获取微信账号信息！"]);
	}

	//扫码动作反馈信息，需要微信环境
	protected function scanMsgPage($option = [])
	{
		$this->msgPage(arr_extend([
			"scene" => "scanverify"
		], $option));
		exit;
	}

	//查找 scan 数据，找到返回 true 并将用户信息写入 session
	public function scanCheck($action = "login", $vcode = "")
	{
		$cf = $this->conf("APP_CACHE/scan");
        if (!file_exists($cf)) return false;
        $cache = file_get_contents($cf);
        $carr = j2a($cache);
		$vcode = $vcode == "" ? Session::get("WX_SCAN_VCODE", "") : $vcode;
		if ($vcode == "") return false;
        if (!isset($carr[$action]["scan_".$vcode])) return false;
        $scan = $carr[$action]["scan_".$vcode];
        $scantime = $scan["timestamp"];
        if ($scantime + 5000 < time()) return false;
        $openid = $scan["openid"];
        Session::set("WX_APP", $this->account);
        Session::set("WX_OPENID", $openid);
        Session::set("WX_OPENID_INFO", a2j($carr["usr"][$openid]));
		Session::del("WX_SCAN_VCODE");
        return true;
	}

	/*
	 *	生成包含 vcode 的网址二维码
	 *		$action		扫码用途，cache[$action] = [timestamp => 123, openid => o_XXXX]
	 *		$newpage	是否生成页面，false 则只生成 二维码图像，默认生成扫码页面
	 */
	public function createScanQrcode($option = [])
	{
		$option = arr_extend([
			"action" => "login",
			"qrpage" => true,
			"brand" => "/src/icon/wx/scan_brand.svg"
		], $option);
		$action = $option["action"];
		$qrpage = $option["qrpage"];
		$vcode = str_nonce(8, false);
		Session::set("WX_SCAN_VCODE", $vcode);
		$url = $this->url("scan/$action/$vcode");
		if (isset($option["usrmodel"])) {
			$url .= "/".str_replace("/","_",$option["usrmodel"]);
			if (isset($option["regtype"])) $url .= "/".$option["regtype"];
			unset($option["usrmodel"]);
			unset($option["regtype"]);
		}
		$s = urlencode($url);
		if (!$qrpage) {
			return QRcodeCreator::create($s);
			exit;
		}
		Response::page("cp/src/page/wx/scanQrcode.php", [
			"pageParams" => arr_extend([
				"qrimg" => "/src/qrcode/".$s,
				"vcode" => $vcode,
				"chkurl" => $this->url("scan/$action/check/$vcode"),
				"wxo" => $this
			], $option)
		]);
		exit;
	}





	/*
     *  static
     */

	//实例化Wechat类
	public static function load($wxaccount=""){
        if(self::checkAccount($wxaccount)){
            if(isset(self::$_MP_[$wxaccount])){
                return self::$_MP_[$wxaccount];
            }else{
                self::$_MP_[$wxaccount] = new Wechat($wxaccount);
				self::$_MP_[$wxaccount]->wxapi = new WxApi($wxaccount);
				//加载 message/event 处理类
				$mcls = CP::class("wx/account/$wxaccount/WxMsgHandler");
				if(class_exists($mcls)){
					self::$_MP_[$wxaccount]->wxmsg = new $mcls($wxaccount);
				}else{
					self::$_MP_[$wxaccount]->wxmsg = new WxMsg($wxaccount);
				}
                return self::$_MP_[$wxaccount];
            }
        }
		return null;
    }

    //检查微信公众号账号
    public static function checkAccount($wxaccount = "")
    {
        if (!is_notempty_str($wxaccount)) return false;
        $conf = Path::find("wx/account/$wxaccount/config.php");
        return !empty($conf);
	}

	//通用 scanToLogin
	/*public static function scanToLogin($wxaccount = "")
	{
		if (Session::has("WX_SCANLOGIN_USR")) {
			$info = Session::get("WX_SCANLOGIN_USR", "");
			$iarr = explode("@", $info);
			$wxo = self::load($iarr[1], )
		}
		if (self::checkAccount($wxaccount)) {
			$sourceUrl = Url::current()->url();
		}
	}*/

	//

	//获取有效的wxaccount列表
	/*public static function wxaccounts(){
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
		
	}*/
	
	



	/**** Wechat API ****/
	//调用wxapi
	public function _api(){
		$args = func_get_args();
		$argn = func_num_args();
		if($argn<1){
			return false;
		}
		$api = array_shift($args);
		if(method_exists($this->wxapi,$api)){
			return call_user_func_array(array($this->wxapi,$api),$args);
		}
	}
	//用户注册登记
	public function api_usregister(){
		if($this->openidReady(TRUE)){
			$usrinfo = $this->wxapi->openidSession();
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
			if($this->openidReady(TRUE)){
				$usrinfo = $this->wxapi->openidSession();
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
		if(strpos($uri,"?")!==false){
			$uri = explode("?",$uri);
			$uri = $uri[0];
		}
		if(strpos($uri,"/")!==false){
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
	
	public function Receive(){
		$this->wxmsg->receive();
	}

	public function Response(){
		$this->wxmsg->response();
	}

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
			if(file_put_contents($cf,$nlc,LOCK_EX)===false){	//写入时锁定，如果写入失败，则2秒后再试
				sleep(2);
				$this->setLocalCache($openid,$lati,$long,$prec,$add);
			}
		}
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

	//关键字检索，用于响应用户任意输入，根据结果返回文本信息，如果没有找到目标结果，返回false
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
			return false;
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






}