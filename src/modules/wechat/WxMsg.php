<?php
/**
 * CPHP框架  微信公众平台用户消息处理类
 * 处理用户发送到公众号的信息，可由各公众号继承扩展
 */

namespace CGY\CPhp\util\wx;

class WxMsg {

    //静态响应方法，通过WxMsg::_add_response方法单独添加，可在类定义外添加消息响应方法
    public static $static_response = array();

	//关联的公众号账号
	public $account = "";
    //用户输入的消息，由微信接口传入的xml解析得到
    protected $raw_data = NULL;   //xml原数据
    protected $msg_data = array();     //解析得到的数据
    protected $text_data = array();     //针对text消息进行解析的结果
    
    //微信服务器向本公众号响应服务器发送消息时，网址中自动附加的参数
    protected $url_params = array("signature","timestamp","nonce","msg_signature","encrypt_type");
    //微信服务器向本公众号响应服务器发送消息时，网址中自动附加参数的值，针对本次发送
    protected $url_get = array();
    protected $is_encrypt = FALSE;
    protected $encryptor = NULL;    //解密类实例


	public function __construct($wxaccount="qzcygl"){
        $this->account = $wxaccount;
    }

    //获取关联的公众号对象
    public function wx(){
        return Wechat::load($this->account);
    }
    public function conf($key){
        return $this->wx()->conf($key);
    }

    //获取解析后的消息数据
    public function msg($key=""){
        if(empty($key)){
			return $this->msg_data;
		}else if(isset($this->msg_data[$key])){
			return $this->msg_data[$key];
		}else{
			if(strpos($key,"/")!==FALSE){
				return _array_xpath_($this->msg_data,$key);
			}else{
				return NULL;
			}
		}
    }
    //touser
    public function touser(){return $this->msg_data["fromusername"];}
    //uac
    public function uac($operation=NULL){
        $uac = FALSE;
        if(!is_null($operation)){
            $uac = $this->wx()->wxapi->openid_uac($this->touser(), $operation);
        }
        if($uac == FALSE){
            $this->r("text","没有权限!");
        }
        return $uac;
    }
    


    /**** 接收用户发送到此公众号的消息，并解析 ****/
    public function receive(){
        //获取URL参数
        //$gURLParam();
        $g = array();
		for($i=0;$i<count($this->url_params);$i++){
			$p = $this->url_params[$i];
			if(empty($p)){
				$g[$p] = NULL;
			}
			$g[$p] = _get_($p,NULL);
		}
		//验证此次请求的签名信息
		if($this->validate_signature($g)==FALSE){
			trigger_error("WCT991@签名验证未通过",E_USER_ERROR);
		}
		//$this->isConnValidate = isset($_GET["echostr"])==TRUE;
		//网址接入验证
		if(isset($_GET["echostr"])==TRUE){
			echo $_GET["echostr"];
			die();
        }
        //获取请求的XML数据
		if(!isset($GLOBALS["HTTP_RAW_POST_DATA"])){
			trigger_error("WCT991@未收到请求数据",E_USER_ERROR);
		}
        $this->raw_data = $GLOBALS["HTTP_RAW_POST_DATA"];
		//获取加密状态，并解密
		if($g["msg_signature"]!=NULL && $g["encrypt_type"]!=NULL && $g["encrypt_type"]=="aes"){
			$this->is_encrypt = TRUE;
            $this->encryptor = new WXBizMsgCrypt($this->conf("APP_TOKEN"), $this->conf("APP_AES"), $this->conf("APP_ID"));
            $xml = '';
            $errcode = $this->encryptor->decryptMsg($g["msg_signature"], $g["timestamp"], $g["nonce"], $this->raw_data, $xml);
            if($errcode!=0){
				trigger_error("WCT992@".$errcode,E_USER_ERROR);
            }
            $this->raw_data = $xml;
        }
        //解析xml
        $xml = (array) simplexml_load_string($this->raw_data, 'SimpleXMLElement', LIBXML_NOCDATA);
        $this->msg_data = array_change_key_case($xml, CASE_LOWER);
        $this->url_get = $g;
		
		/*$get = $this->get;
		$xml = '';
		if($this->is_encrypt==TRUE){
			$errcode = $this->encryptor->decryptMsg($get["msg_signature"],$get["timestamp"],$get["nonce"],$this->raw_data,$xml);
			if($errcode!=0){
				trigger_error("WCT992@".$errcode,E_USER_ERROR);
			}
		}else{
			$xml = $this->raw_data;
		}
		$xml = (array) simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		//将数组键名转换为小写，提高健壮性，减少因大小写不同而出现的问题
		$this->request = array_change_key_case($xml, CASE_LOWER);
		$this->parseRequest();

		$this->wxmsg->ready();*/
    }
    //验证此次请求的签名信息
	private function validate_signature($g=array()){
		if($g["signature"]==NULL || $g["timestamp"]==NULL || $g["nonce"]==NULL){
			return FALSE;
		}
		$signatureArr = array($this->conf("APP_TOKEN"), $g["timestamp"], $g["nonce"]);
		sort($signatureArr, SORT_STRING);
		return sha1(implode($signatureArr)) == $g["signature"];
    }
    


    /**** 回复用户消息 ****/
    public function response(){
        $msgtype = strtolower($this->msg("msgtype"));
        if(method_exists($this,"on_".$msgtype)){
            return call_user_func_array(array($this,"on_".$msgtype), array());
        }else{
            return $this->r("text","no response method");
        }
    }
    //创建并发送回复消息
    /*
	*   回复文本    r("text","content")
	*   回复图片    r("image","media_id")
	*   回复语音    r("voice","media_id")
	*   回复视频    r("video","media_id","title","description")
	*   回复音乐    r("music","title","description","musicurl","hqmusicurl","thumbmediaid")
	*   回复图文    r("news",array(array("title","description","picurl","url"),array("title","description","picurl","url"),...))
	*/
    public function r(){
        $args = func_get_args();
        $rep = NULL;
        if(empty($args) || count($args)<=1){
            return $this->r("text","empty response");
        }else{
            $reptype = array_shift($args);
            if(method_exists($this, "create_".$reptype)){
                $rep = call_user_func_array(array($this, "create_".$reptype), $args);
            }else{
                $rep = $this->create($reptype,$args);
            }
        }
        if(is_null($rep)){
            $rep = sprintf($this->conf("APP_CACHE/reptpl")["text"], $this->touser(), $this->msg("tousername"), time(), "error response");
        }
        //发送回复
        $g = $this->url_get;
        $msg = '';
        if($this->is_encrypt==TRUE){
            $errcode = $this->encryptor->encryptMsg($rep, $g["timeStamp"], $g["nonce"], $msg);
            if($errcode!=0){
                trigger_error("WCT993@".$errcode,E_USER_ERROR);
            }
        }else{
            $msg = $rep;
        }
        header("Content-type: text/xml; charset=utf-8");
        echo $msg;
        exit;
    }
    //生成回复消息
    protected function create($msgtype="text", $param=array()){
        $tu = $this->touser();
		$fu = $this->msg("tousername");
		$tpl = $this->conf("APP_CACHE/reptpl")[$msgtype];
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
				$tpli = $this->conf("APP_CACHE/reptpl")["newsitem"];
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
    


    /**** 处理用户消息 ****/
    protected function on_text(){
        $msg = trim($this->msg("content"));
        //解析
        $rst = array();
        if(is_numeric($msg)){
			$rst["type"] = "num";		//数字命令
            $rst["content"] = (int)$msg;
		}else{
			if(strpos($msg,"#")==0){		//#命令行，语法：#cmdname@param1,param2...
				$rst["type"] = "cmd";
                $cmda = explode("#",$msg);
                array_shift($cmda);
                $cmda = implode("#",$cmda);
                if(strpos($cmda,"@")===FALSE){
                    $rst["content"] = strtolower(trim($cmda));
                    $rst["param"] = array();
                }else{
                    $cmda = explode("@",$cmda);
                    $rst["content"] = strtolower(trim(array_shift($cmda)));
                    $cmda = implode("@",$cmda);
                    //$rst["param"] = _to_array_($cmda);
                    $rst["param"] = explode(",",$cmda);
                }
            //}else if( other conditions ){
                //...
            }else{  //普通字符消息
                $rst["type"] = "text";
                $rst["content"] = $msg;
            }
        }
        //调用响应方法
        $touser = $this->touser();
        switch($rst["type"]){
            case "text" :   //响应普通文本消息
                /** 调用图灵机器人 **/
                $tl = _plugin_("tuling");
                $rst = $tl::tuling($rst["content"],"",$touser);
                return $this->r("text",$rst);
                break;

            default :
                if($this->uac("response/text/".$rst["type"]."/".$rst["content"])==TRUE){
                    $m = $rst["type"]."_".($rst["content"]);
                    $p = isset($rst["param"]) ? $rst["param"] : array();
                    if(method_exists($this, $m)){
                        return call_user_func_array(array($this, $m), $p);
                    }else{
                        //当在WxMsg类实例中没找到对应的响应方法时，调用WxMsg::_response静态方法
                        //检查是否有通过WxMsg::_add_response方法单独添加的处理方法
                        return self::_response($this->account,$m,$p);
                        //return $this->r("text","command line error:\r\nunknown command");
                    }
                }
                break;
        }
    }
    protected function on_event(){
        $evt = strtolower($this->msg("event"));
        $evtm = "event_".$evt;
        if($this->uac("response/event/".$evt)==TRUE){
            if(method_exists($this,$evtm)){
                return call_user_func_array(array($this, $evtm), array());
            }else{
                return $this->r("text","event response error:\r\nunknown event");
            }
        }
    }
    protected function on_image(){ }
    protected function on_voice(){ }
    protected function on_video(){ }
    protected function on_shortvideo(){ }
    protected function on_location(){ }
    protected function on_link(){ }









    /**** 处理text消息的方法，可由子类覆盖 ****/
    //针对数字命令
    protected function num_0(){
        $this->r("text","foobar");
    }
    //针对cmd命令行
    protected function cmd_var($key=""){
        $v = $this->conf($key);
        if(is_null($v)){
            $v = $this->msg($key);
        }
        return $this->r("text",_to_string_($v));
    }
    //openid方法
    protected function cmd_openid(){
        $args = func_get_args();
        $argn = func_num_args();
        if($argn<=0){   //获取当前用户openid
            $this->r("text",$this->touser());
        }else{
            $m = strtolower($args[0]);
            switch($m){
                case "register" :   //保存用户到数据库，用于已关注用户的登记
                    $openid = isset($args[1]) ? $args[1] : $this->touser();
                    $tb = _db_("wx_base")->t("usr");
                    if($tb->openid_exists($openid)==FALSE){
                        $this->r("news",array(
                            "点击注册成为公众号用户",
                            "进入页面注册成为本公众号的用户，需要读取您的微信账号个人数据，包括：名称、头像等。此信息仅用于本公众号服务项目，禁止向任何第三方透露。",
                            "",
                            $this->wx()->url("api/usregister")
                        ));
                    }
                    break;
            }
        }
        /*$openid = "";
        if(is_null($usr)){
            $openid = $this->touser();
        }else{
            $openid = $this->wx()->wxapi->openid_special($usr);
        }
        $this->r("text",$openid);*/
    }
    protected function cmd_goto($url=""){
        $u = _url_("wx/".$this->conf("APP_NAME")."/"._str_del_($url,"/","left"));
        $this->r("news",array(
            array(
                "cmd goto url",
                "cmd goto url = ".$url,
                "",
                $u
            )
        ));
    }
    protected function cmd_openid_exists($openid="self"){
        if(empty($openid) || strtolower($openid)=="self"){
            $openid = $this->touser();
        }
        if(_db_("wx_base")->t("usr")->openid_exists($openid)){
            $this->r("text","此用户数据库中已经存在！");
        }else{
            $this->r("text","此用户数据库中不存在！");
        }
    }
    //qzserver
    protected function cmd_qzserver($uris=""){
        $u = DommyPHP::url("qzserver");
        if($uris!="") $u .= "/".$uris;
        $this->r("news",array(
            "外网访问QZ-SERVER",
            "通过公众号跳转访问QZ-SERVER。因运营商端口限制以及无固定IP，不能直接外网访问。".$u,
            "",
            $u
        ));
    }



    /**** 处理event消息的方法，可由子类覆盖 ****/
    //subscribe/unsubscribe
    protected function event_subscribe(){
        $rsc = _db_("wxusr")->total("usr","`wxaccount` = '".$this->account."' AND `openid` = '".$this->touser()."'");
        if($rsc<=0){
            //获取用户的信息
            _curl_wx_($this->wx()->url("openid/getcode?gotourl=".$this->wx()->url("openid/getusrinfo")."&getusrinfo=yes"));
            _db_("wxusr")->insert("usr",array(
                "wxaccount" => $this->account,
                "openid" => $this->touser()
            ));
        }
        $rs = _db_("wxusr")->select("usr","`wxaccount` = '".$this->account."' AND `openid` = '".$this->touser()."'");
        $this->r("text",$rs[0]["id"]);
    }
    protected function event_unsubscribe(){

    }
    //扫码
    protected function event_scan(){
        $this->r("text",$this->msg("eventkey"));
    }
    //使用自定义菜单中扫码工具scancode_waitmsg，扫码后显示正在接收消息，等待服务器回复消息
    protected function event_scancode_waitmsg(){
        $_key = $this->msg("eventkey");
        $scan_info = $this->msg("scancodeinfo");
        $scan_type = $scan_info->ScanType;
        $scan_rst = $scan_info->ScanResult;

        $this->r("text",$scan_rst);
    }
    //使用自定义菜单中扫码工具scancode_push，扫码后直接跳转到结果（可以是url），同时推送事件到服务器，不接收直接回复，可调用WxApi与客户互动
    //此处定义统一扫码处理接口   https://host/wx/{wxaccount}/scan/{scanresult} (此url转成二维码)
    protected function event_scancode_push(){
        $_key = $this->msg("eventkey");
        $scan_info = $this->msg("scancodeinfo");
        $scan_type = $scan_info->ScanType;
        $scan_rst = $scan_info->ScanResult;
        //$this->r("text",$scan_rst."12345");
        _session_set_(array(
            "openid" => $this->touser()
        ));
    }
    //location 用户上报地理位置，需要用户同意
    protected function event_location(){

    }
    //自定义菜单点击，click事件，子类禁止覆盖
    protected function event_click(){
        $ekey = strtolower($this->msg("eventkey"));
        if($this->uac("response/event/click/".$ekey)==TRUE){
            $cm = "click_".$ekey;
            if(method_exists($this, $cm)){
                return call_user_func_array(array($this, $cm), array());
            }else{
                return $this->r("text","click response error:\r\nunknown click key");
            }
        }
    }



    /**** 处理click事件的方法，可由子类覆盖 ****/



    /**** 对scancode_push扫码结果的处理，可由子类覆盖 ****/
    //外部调用方法，不可子类覆盖
    public function scan_result(){
        $args = func_get_args();
        if(empty($args)){
            return FALSE;
        }else{
            $tp = strtolower(array_shift($args));
            if(method_exists($this, "scan_".$tp)){
                return call_user_func_array(array($this, "scan_".$tp), $args);
            }
            return FALSE;
        }
    }
    //需要识别用户openid信息
    public function usrscan_result(){
        $args = func_get_args();
        if(count($args)<2) return FALSE;
        $openid = array_shift($args);
        $m = "usrscan_".strtolower(array_shift($args));
        if(method_exists($this, $m)){
            array_unshift($args, $openid);
            return call_user_func_array(array($this, $m), $args);
        }else{
            $f = _root_("Apps/".implode("/",$args).".php");
            if(file_exists($f)){
                require($f);
                exit;
            }else{
                return FALSE;
            }
        }
    }
    //根据扫码结果，调用不同的方法

    protected function scan_goodsinfo($gid=""){
        return $gid;
    }
    protected function scan_test($p=""){
        $opid = _session_get_("openid");
        return $opid."；".$p;
    }
    protected function usrscan_test($openid="", $p=""){
        return $openid."；".$p;
    }





    /**** 静态响应方法，通过WxMsg::_add_response方法单独添加的消息响应方法，与wxaccount相关 ****/
    //添加相应方法
    public static function _add_response($wxaccount,$m="",$funcarr=array()){
        if(!isset(self::$static_response[$wxaccount])){
            self::$static_response[$wxaccount] = array();
        }
        if(!empty($m) && !empty($funcarr)){
            self::$static_response[$wxaccount][$m] = $funcarr;
        }
    }
    //调用响应方法
    public static function _response($wxaccount,$m="",$param=array()){
        if(isset(self::$static_response[$wxaccount])){
            if(isset(self::$static_response[$wxaccount][$m])){
                $mfunc = self::$static_response[$wxaccount][$m];
                if(is_array($mfunc)){
                    $param = !is_array($param) || !array_key_exists(0,$param) ? array() : $param;
                    $wxo = _wechat_($wxaccount);
                    array_unshift($param,$wxo);
                    $msgarr = call_user_func_array($mfunc,$param);
                    //_wechat_($wxaccount)->wxmsg->r("text",$msgarr);
                    if(is_array($msgarr) && !empty($msgarr)){
                        $wxo = _wechat_($wxaccount);
                        return call_user_func_array(array($wxo->wxmsg,"r"),$msgarr);
                    }
                }
            }
        }
        if(_wx_exists_($wxaccount)){
            _wechat_($wxaccount)->wxmsg->r("text","command line error:\r\nunknown command!!");
        }
    }




}

