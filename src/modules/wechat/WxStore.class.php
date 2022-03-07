<?php
/**
 * 微信店铺类
 */

class WxStore {

    //store列表
    public static $_LIST_ = array();
    //tag
    private static $tags = array(
        "group" => "团购,group,white,wx-blue",
        "new" => "新店开业,shopfill,black,yellow",
        "hot" => "人气店铺,hotfill,white,wx-red",
        "champion" => "销量冠军,crownfill,white,wx-green",
        "selection" => "主厨推荐,selectionfill,white,wx-red",
        "upstage" => "高点击率,upstagefill,white,wx-blue",
        "choiceness" => "强力推荐,choicenessfill,white,wx-green",
        "recharge" => "性价比之选,rechargefill,white,cyan"
    );
    //store预设
    private $config = NULL;
    //cache
    private $_cache = array();
    


    public function __construct($storeid="f17"){
        $scf = self::path("stores/".$storeid.".store.json");
        if(file_exists($scf)){
            $this->config = DommyPHP::j2a(file_get_contents($scf));
            $this->config = self::fix_config($this->config);
            $this->config["goods"]["list"] = self::fix_goods($this->config);
            $this->config["desc"] = self::fix_desc($this->config);
            $this->config["descstr"] = implode("<br>",$this->config["desc"]);
            $this->config["statu_fixed"] = $this->check_statu();
        }else{
            return FALSE;
        }
    }

    //config
    public function conf($key=NULL){
        if(is_null($key)){return $this->config;}
        return DommyPHP::array_xpath($this->config,$key);
    }

    //cache
    public function cache($key, $val=NULL){
        if(is_null($val)){
            return !isset($this->_cache[$key]) ? NULL : $this->_cache[$key];
        }else{
            $this->_cache[$key] = $val;
        }
    }

    //hasaccess
    public function accessable($wxaccount=NULL){
        if(is_null($wxaccount)){return FALSE;}
        $ac = $this->config["access"];
        return $ac=="_ALL_" || (is_array($ac) && in_array($wxaccount,$ac));
    }

    //输出店铺页面
    public function export_store($extra=array()){
        $val = DommyPHP::array_overlay_v2($extra,$this->config);
        $val["WX_STORECONFIG"] = DommyPHP::a2j($this->config);
        $h .= self::tpl("store",$val);
        return $h;
    }

    //检查购物车中数据(价格/数量)是否满足店铺下单要求
    public function check_number(){
        $rqn = $this->config["required"]["number"];
        if($rqn["enabled"]==FALSE){return array(TRUE,"无起订数量要求");}
        $tp = $rqn["type"];
        $calc = $this->cart("calc");
        $flag = FALSE;
        switch($tp){
            case "cost" :
                $flag = $calc["totalcost"]>=$rqn["number"];
                break;
            case "number" :
                $flag = $calc["totalnum"]>=$rqn["number"];
                break;

        }
        return array($flag,$rqn["info"]);
    }

    //检查给定的地址是否在店铺配送范围内
    public function check_area(){
        $rqa = $this->config["required"]["area"];
        if($rqa["enabled"]==FALSE){return TRUE;}
        $pv = Request::post("province","");
        $ct = Request::post("city","");
        $ds = Request::post("district","");
        if($pv=="" || $ct=="" || $ds==""){return FALSE;}
        if(array_key_exists($ct,$rqa["area"])){
            return in_array($ds,$rqa["area"][$ct]);
        }else{
            return FALSE;
        }
    }

    //检查送货时间是否满足店铺要求
    public function check_delivertime($dt=NULL){
        $rqd = $this->config["required"]["deliver"];
        $rqp = $this->config["required"]["preorder"];
        if($rqd["enabled"]==FALSE && $rqp["enabled"]==FALSE){return array(TRUE,"不限定送货时间以及预定时间");}
        $dt = is_null($dt) ? Request::post("delivertime",0) : $dt;
        $dt = !is_numeric($dt) ? 0 : (int)$dt;
        if($dt<=0){
            return array(FALSE,"未设置送货时间");
        }else{
            //舍弃时间戳的秒数
            $flag = TRUE;
            if($rqd["enabled"]==TRUE){
                $dtw = date("w",$dt);
                switch($rqd["repeat"]){
                    case "no" : $tst = date("Y-m-d H:i",$dt).":00";break;
                    //case "hour" : 
                    case "day" : $tst = date("H:i",$dt).":00";break;
                    case "week" : $tst = $dtw."W".date("H:i",$dt).":00";break;
                    case "month" : $tst = date("d H:i",$dt).":00";break;
                    //case "season" : $tst = date("d H:i",$dt).":00";break;
                    case "year" : $tst = date("m-d H:i",$dt).":00";break;
                }
                if(!in_array($tst,$rqd["timeset"])){
                    $flag = FALSE;
                    return array($flag,$rqd["info"]);
                }
            }
            if($rqp["enabled"]==TRUE){
                $t = time();
                if($dt-$t<$rqp["timedistance"]+30*60){
                    $flag = FALSE;
                    return array($flag,$rqp["info"]);
                }
            }
            return array($flag,strtotime(date("Y-m-d H:i",$dt).":00"));
        }
    }

    //检查是否打烊
    public function check_statu(){
        $st = $this->config["statu"];
        if($st=="_CLOSE_"){
            return array(FALSE,"暂停营业","非常抱歉，此店铺已暂停营业");
        }else if($st=="_ALLWAYS_"){
            return array(TRUE,"","");
        }else{
            $ib = $this->config["inbusiness"];
            $t = time();
            $td = date("Y-m-d",$t);
            $ts = strtotime($td." ".$ib[0].":00");
            $te = strtotime($td." ".$ib[1].":00");
            if($t>$te || $t<$ts){
                return array(FALSE,"已打烊","非常抱歉，店铺已经打烊了<br>营业时间".$ib[0]." ~ ".$ib[1]);
            }else{
                return array(TRUE,"","");
            }
        }
    }

    //存取当前用户的购物车信息
    public function cart($action="get"){
        $openid = $this->cache("openid");
        $cl = self::data_path("cart");
        $cljson = file_get_contents($cl);
        $clarr = DommyPHP::j2a($cljson);
        $ucart = array();
        switch($action){
            case "get" :
                if(!array_key_exists($openid,$clarr)){return array();}
                if(!array_key_exists($this->config["id"],$clarr[$openid])){return array();}
                $ucart = $clarr[$openid][$this->config["id"]];
                unset($ucart["update"]);
                foreach($ucart as $k => $v){
                    if($v["number"]<=0){
                        unset($ucart[$k]);
                    }
                }
                return $ucart;
                break;
            case "set" :
                $input = file_get_contents("php://input");
                if(!empty($input)){
                    if(!array_key_exists($openid,$clarr)){
                        $clarr[$openid] = array();
                    }
                    $inparr = DommyPHP::j2a($input);
                    foreach($inparr as $k => $v){
                        if(!isset($v["number"]) || $v["number"]<=0){
                            unset($inparr[$k]);
                        }
                    }
                    if(empty($inparr)){
                        unset($clarr[$openid][$this->config["id"]]);
                        if(empty($clarr[$openid])){
                            unset($clarr[$openid]);
                        }
                    }else{
                        $inparr["update"] = time();
                        $clarr[$openid][$this->config["id"]] = $inparr;
                    }
                    $cljson = DommyPHP::a2j($clarr);
                    file_put_contents($cl, $cljson, LOCK_EX);
                    unset($inparr["update"]);
                    return $inparr;
                }else{
                    return $clarr;
                }
                break;
            case "clear" :
                if(array_key_exists($openid,$clarr) && array_key_exists($this->config["id"],$clarr[$openid])){
                    unset($clarr[$openid][$this->config["id"]]);
                    if(empty($clarr[$openid])){
                        unset($clarr[$openid]);
                    }
                    $cljson = DommyPHP::a2j($clarr);
                    file_put_contents($cl, $cljson, LOCK_EX);
                }
                return array("result"=>TRUE);
                break;
            case "calc" :   //计算购物车数据
                $ucart = $this->cart("get");
                $gl = $this->config["goods"]["list"];
                $totalcost = 0;
                $totaloriginalcost = 0;
                $totalnum = 0;
                if(!empty($ucart)){
                    foreach($ucart as $k => $v){
                        $num = $v["number"];
                        $totalcost += $num*$gl[$k]["price"];
                        $totaloriginalcost += $num*$gl[$k]["price_old"];
                        $totalnum += $num;
                    }
                }
                return array(
                    "totalcost" => $totalcost,
                    "totaloriginalcost" => $totaloriginalcost,
                    "totalnum" => $totalnum
                );
                break;
            case "check" :  //检查当前购物车中数据是否符合下单要求
                
                break;
        }
    }

    //存取当前用户的订单信息
    public function order($action="check"){
        $openid = $this->cache("openid");
        $payopenid = $this->cache("payopenid");
        $od = self::data_path("order");
        $odjson = file_get_contents($od);
        $odarr = DommyPHP::j2a($odjson);
        $odst = $odarr["common"]["structure"];
        $ods = $odarr["orders"];
        $args = func_get_args();
        switch($action){
            case "check" :  //检查未完成的订单
            case "get" :    //获取全部订单
            case "getbyid" :    //获取某个订单
                $myods = array();
                $odn = 0;
                $tod = NULL;
                if($action=="getbyid"){
                    if(count($args)>1){
                        $oid = $args[1];
                    }else{
                        $oid = Request::post("orderid",NULL);
                        if(is_null($oid)){
                            $oid = Request::get("orderid",NULL);
                        }
                    }
                }
                foreach($ods as $k => $v){
                    if($v["goods"]["storeid"]==$this->config["id"] && $v["usr"]["payopenid"]==$payopenid){
                        $ost = self::check_order($v["id"],"statu");
                        $v["_data_"] = array(
                            "statu" => $ost
                        );
                        $myods[] = $v;
                        if($ost[0]==FALSE){
                            $odn++;
                        }
                        if(!is_null($oid) && $v["id"]==$oid){
                            $tod = $v;
                        }
                    }
                }
                if($action=="getbyid"){
                    return $tod;
                }else if($action=="check"){
                    return $odn;
                }else{
                    return $myods;
                }
                break;
            case "settle" :     //结算
                $ucart = $this->cart();     //获取购物车信息
                $gl = $this->config["goods"]["list"];
                $st = $this->config["settle"];
                $totaloriginalcost = 0;
                $totalcost = 0;
                $totalnum = 0;
                $fee = 0;
                foreach($ucart as $k => $v){
                    $g = $gl[$k];
                    $totaloriginalcost += $g["price_old"]*$v["number"];
                    $totalcost += $g["price"]*$v["number"];
                    $totalnum += $v["number"];
                }
                $dfee = $st["deliverfee"];
                $calc = $st["calc"];
                $calc = str_replace("%{TOTALCOST}%",$totalcost,$calc);
                $calc = str_replace("%{TOTALNUM}%",$totalnum,$calc);
                $calc = str_replace("%{FEE}%","\$fee",$calc);
                $calc = str_replace("%{DELIVERFEE}%",$dfee,$calc);
                eval($calc);
                //var_dump($fee);die();
                $feelast = $fee+($st["packagefee"]*$totalnum);
                $feelast = (int)$feelast;
                $payfee = $st["paytype"]<=0 ? $feelast : (int)$st["payfee"];
                $leftfee = $feelast-$payfee;
                //$odd = $fee-$feelast;
                $totaldisc = floor(($feelast/$totaloriginalcost)*100)/10;
                $disccost = $totaloriginalcost-$feelast;
                $deliverfee_reduce = $st["deliverfee_reduce"];
                $deliverfee_reduce = str_replace("%{TOTALCOST}%",$totalcost,$deliverfee_reduce);
                $deliverfee_reduce = str_replace("%{TOTALNUM}%",$totalnum,$deliverfee_reduce);
                eval("\$deliverfee_reduce = ".$deliverfee_reduce.";");
                $rst = array(
                    "fee" => $feelast,  //最终应付款
                    "totaloriginalcost" => $totaloriginalcost,      //原价
                    "totaldiscount" => $totaldisc,  //最终折扣
                    "disccost" => $disccost,        //折扣金额
                    "deliverfee_reduce" => $deliverfee_reduce==TRUE ? $st["deliverfee_reduce_info"] : "",    //配送费减免
                    "deliverfee" => $deliverfee_reduce==TRUE ? 0 : $dfee,   //配送费
                    "payfee" => $payfee,    //微信支付金额
                    "leftfee" => $leftfee   //尾款
                    //"odd" => $odd                   //抹零
                );
                //var_dump($rst);die();
                return $rst;
                //return 'fee='.$fee."; totalcost=".$totalcost;

                break;
            case "mk" :     //下单
                $input = file_get_contents("php://input");
                if(!empty($input)){
                    $inparr = DommyPHP::j2a($input);
                    $gl = $this->config["goods"]["list"];
                    $gi = "";
                    foreach($inparr["goods"]["items"] as $k => $v){
                        $gi = $gl[$k];break;
                    }
                    $gn = $inparr["goods"]["number"];
                    $inparr["id"] = self::get_orderidx($this->config["orderid"]);
                    $inparr["pid"] = "QZPAY_".$inparr["id"];
                    $inparr["name"] = $gi["name"].($gn>1 ? "等".$gn."件商品" : "");
                    $inparr["time"]["order"] = time();
                    //写入
                    $odarr["orders"]["OD_".$inparr["id"]] = $inparr;
                    $odjson = DommyPHP::a2j($odarr);
                    file_put_contents($od,$odjson,LOCK_EX);
                    //$this->cart("clear");
                    return array(TRUE,$inparr["id"]);
                }else{
                    return array(FALSE,"下单参数为空或不完整！");
                }
                break;
            case "pay" :
                $oid = Request::post("orderid",NULL);
                if(is_null($oid) && count($args)>=2){
                    $oid = $args[1];
                }
                if(is_null($oid)){
                    return array(FALSE,"没有有效的订单号或支付公众号");
                }else{
                    $tod = NULL;
                    for($i=0;$i<count($ods);$i++){
                        if($ods[$i]["id"]==$oid){
                            $tod = $ods[$i];
                            break;
                        }
                    }
                    $statu = self::check_order($tod["id"],"statu");
                    if($statu[0]==TRUE){    //订单已完成
                        return $statu;
                    }else{
                        if(self::check_order($tod["id"],"payed")==TRUE){
                            return array(FALSE,"订单已经支付过");
                        }else{
                            //**** 测试账号 ****
                            //payopenid : o_EHa0Y39JRlArHfX9IZWyPXjZSs
                            //微信号  陈云天
                            $tester = array("o_EHa0Y39JRlArHfX9IZWyPXjZSs");
                            if(in_array($tod["usr"]["payopenid"],$tester)){
                                //支付金额调整为 0.1元
                                $tod["fee"]["pay"] = 10;
                            }
                            //**** 测试账号 结束 ****/
                            //var_dump($tod);die();
                            //获取支付接口参数，供前端调用以呼出支付界面
                            $wx = new Wechat($tod["usr"]["wxaccount"]);
                            $rtn = $wx->pay($tod["usr"]["wxpayaccount"],"jsapi",$tod["usr"]["payopenid"],$tod, FALSE);
                            //var_dump($rtn);die();
                            return $rtn;
                        }
                    }
                }
                break;
        }
    }

    //存取当前用户的地址数据
    public function address($action="get"){
        $payopenid = $this->cache("payopenid");
        $ad = self::data_path("address");
        $adjson = file_get_contents($ad);
        $adarr = DommyPHP::j2a($adjson);
        $ads = isset($adarr[$payopenid]) && is_array($adarr[$payopenid]) ? $adarr[$payopenid] : array();
        switch($action){
            case "get" :
            case "getselected" :
                $selad = NULL;
                if(!empty($ads)){
                    for($i=0;$i<count($ads);$i++){
                        if($ads[$i]["selected"]==TRUE){
                            $selad = $ads[$i];
                            break;
                        }
                    }
                }
                return $action=="get" ? $ads : $selad;
                break;
            case "sel" :    //选中某个地址
                $selidx = Request::post("selidx",-1);
                if(!is_numeric($selidx) || $selidx<0){
                    return FALSE;
                }else{
                    if(empty($ads)){return FALSE;}
                    for($i=0;$i<count($ads);$i++){
                        $ads[$i]["selected"] = FALSE;
                        if($i==$selidx){
                            $ads[$i]["selected"] = TRUE;
                        }
                    }
                    $adarr[$payopenid] = $ads;
                    $adjson = DommyPHP::a2j($adarr);
                    file_put_contents($ad,$adjson,LOCK_EX);
                    return TRUE;
                }
                break;
            case "del" :
                $delidx = Request::post("delidx",-1);
                if(!is_numeric($delidx) || $delidx<0){
                    return FALSE;
                }else{
                    if(empty($ads) || !isset($ads[$delidx])){return FALSE;}
                    array_splice($adarr[$payopenid], $delidx, 1);
                    $adjson = DommyPHP::a2j($adarr);
                    file_put_contents($ad,$adjson,LOCK_EX);
                    return TRUE;
                }
                break;
            case "set" :
                $fields = array("selected","title","province","city","district","street","address","contact","tel","tag");
                for($i=0;$i<count($ads);$i++){
                    $ads[$i]["selected"] = FALSE;
                }
                $newadd = array();
                for($i=0;$i<count($fields);$i++){
                    $newadd[$fields[$i]] = Request::post($fields[$i],"");
                }
                $newadd["selected"] = TRUE;
                $ads[] = $newadd;
                $adarr[$payopenid] = $ads;
                $adjson = DommyPHP::a2j($adarr);
                file_put_contents($ad, $adjson, LOCK_EX);
                break;
        }
    }

    





    /**** static ****/

    //获取某个店铺对象，如果不存在则加载之
    public static function load_store($storeid="f17"){
        if(isset(WxStore::$_LIST_[$storeid]) && !is_null(WxStore::$_LIST_[$storeid])){
            return WxStore::$_LIST_[$storeid];
        }else{
            $scf = self::path("stores/".$storeid.".store.json");
            if(!file_exists($scf)){
                return FALSE;
            }else{
                WxStore::$_LIST_[$storeid] = new WxStore($storeid);
                return WxStore::$_LIST_[$storeid];
            }
        }
    }

    //path
    public static function path($p=""){
        $sp = DommyPHP::path("wx/store");
        return empty($p) ? $sp : $sp."/".$p;
    }
    public static function data_path($dt="cart"){
        return self::path("data/".$dt.".json");
    }

    //处理config
    public static function fix_config($stconf=array()){
        $dftconf = DommyPHP::j2a(file_get_contents(self::path("stores/default.store.json")));
        $stconf = DommyPHP::array_overlay_v2($dftconf,$stconf);
        $res = DommyPHP::root("Public/wx/store/");
        $chkfs = array("face","titimg");
        for($i=0;$i<count($chkfs);$i++){
            if(!file_exists($res."/".$stconf[$chkfs[$i]])){
                $stconf[$chkfs[$i]] = $dftconf[$chkfs[$i]];
            }
        }
        return $stconf;
    }
    //处理菜品预设
    public static function fix_goods($stconf=array()){
        $gcf = $stconf["goods"];
        $gcfc = $gcf["common"]["category"];
        $dft = $gcf["default"];
        $gs = $gcf["list"];
        $gcs = array();
        foreach($gs as $k => $v){
            $gs[$k] = DommyPHP::array_overlay_v2($dft,$v);
            if($gs[$k]["discount"]==1){
                $gs[$k]["discount"] = $gcf["common"]["discount"];
            }
            $gs[$k]["price_old"] = $gs[$k]["price"];
            $gs[$k]["price"] = $gs[$k]["discount"]*$gs[$k]["price_old"];
            $gs[$k]["price"] = (int)$gs[$k]["price"]+0.99;
            $str = array();
            if(!empty($gs[$k]["tag"])){
                $gs[$k]["tagh"] = self::parse_tag($gs[$k]["tag"]);
                $str[] = $gs[$k]["tagh"];
            }
            if($gs[$k]["extra"]!=""){$str[] = '<span style="color:black;">'.$gs[$k]["extra"].'</span>';}
            if($gs[$k]["intr"]!=""){$str[] = $gs[$k]["intr"];}
            $gs[$k]["descstr"] = implode("<br>",$str);
        }
        return $gs; 
    }
    //description
    public static function fix_desc($stconf=array()){
        $desc = array();
        $tagh = self::parse_tag($stconf["tag"]);
        if(!is_null($tagh)){$desc[] = $tagh;}
        $desc[] = '<span style="color:var(--color-black);font-size:.7rem;">'.$stconf["intr"].'</span>';
        if(count($stconf["notice"]["show"])>0){
            for($j=0;$j<count($stconf["notice"]["show"]);$j++){
                $desc[] = '<i class="iconfont icon-notificationfill -dp-fc-yellow"></i>&nbsp;'.$stconf["notice"]["list"][$stconf["notice"]["show"][$j]];
            }
        }
        $req = $stconf["required"];
        foreach($req as $k => $v){
            if(isset($v["enabled"]) && $v["enabled"]==TRUE){
                $desc[] = '<i class="iconfont icon-warnfill -dp-fc-wx-red"></i>&nbsp;'.$v["info"];
            }
        }
        $desc[] = '<i class="iconfont icon-rechargefill -dp-fc-wx-green"></i>&nbsp;'.$stconf["settle"]["info"];
        return $desc;
    }

    //tpl
    public static function tpl($tplf, $val=array()){
        $tplf = self::path("tpl/".$tplf.".html");
        if(file_exists($tplf)){
            $tpls = file_get_contents($tplf);
            return DommyPHP::strTplReplace($tpls, $val);
        }else{
            return "";
        }
    }

    //遍历店铺列表，返回指定的公众号有访问权限的店铺列表
    public static function get_list($wxaccount=NULL){
        $sp = self::path("stores");
        $dftst = DommyPHP::j2a(file_get_contents($sp."/default.store.json"));
        $sl = array();
        $sph = opendir($sp);
        while(($sc = readdir($sph))!==FALSE){
            if($sc=="." || $sc==".." || $sc=="default.store.json" || is_dir($sp."/".$sc)){continue;}
            if(strpos($sc,".store.json")!==FALSE){
                if(is_null($wxaccount)){
                    $sl[] = self::load_store(str_replace(".store.json","",$sc));
                }else{
                    $stconf = DommyPHP::j2a(file_get_contents($sp."/".$sc));
                    $stconf = DommyPHP::array_overlay_v2($dftst,$stconf);
                    if($stconf["access"]=="_ALL_" || (is_array($stconf) && in_array($wxaccount,$stconf["access"]))){
                        $sl[] = self::load_store($stconf["id"]);
                    }else{
                        continue;
                    }
                }
            }
        }
        closedir($sph);
        return $sl;
    }

    //输出全部可用店铺列表
    public static function export_list($wxaccount=NULL, $extra=array()){
        if(is_null($wxaccount)){
            trigger_error("999008@没有指定公众号",E_USER_ERROR);
        }else{
            $sl = self::get_list($wxaccount);
            if(empty($sl)){trigger_error("999008@当前公众号没有店铺",E_USER_ERROR);}
            $h = self::tpl("head",'{"title":"选择店铺"}');
            //输出
            $h_gallery = '';    //页面头部活动幻灯片
            $h_list = '';       //店铺列表
            for($i=0;$i<count($sl);$i++){
                $so = $sl[$i];
                $sc = $so->conf();
                if($sc["sellaction"]!="_NONE_" && is_array($sc["sellaction"])){
                    for($j=0;$j<count($sc["sellaction"]);$j++){
                        if(file_exists(DommyPHP::root("Public/wx/store/".$sc["sellaction"][$j]))){
                            $h_gallery .= '<img src="/res/public/wx/store/'.$sc["sellaction"][$j].'">';
                        }
                    }
                }
                $desc = array();
                $tagh = self::parse_tag($sc["tag"]);
                if(!is_null($tagh)){$desc[] = $tagh;}
                $desc[] = '<span style="color:var(--color-black);">'.$sc["intr"].'</span>';
                if(count($sc["notice"]["show"])>0){
                    for($j=0;$j<count($sc["notice"]["show"]);$j++){
                        $desc[] = '<i class="iconfont icon-notificationfill -dp-fc-yellow"></i>&nbsp;'.$sc["notice"]["list"][$sc["notice"]["show"][$j]];
                    }
                }
                $h_list .= self::tpl("storeitem",DommyPHP::array_overlay_v2($sc,array(
                    "wxaccount" => $wxaccount,
                    "desch" => implode("<br>",$desc),
                    "starh" => str_repeat('<i class="iconfont icon-favorfill -dp-fc-yellow"></i>',$sc["star"]).str_repeat('<i class="iconfont icon-favor -dp-fc-yellow"></i>',(5-(int)$sc["star"]))
                )));
            }
            $h .= self::tpl("storelist",array(
                "galleryimgs" => $h_gallery,
                "storeitems" => $h_list
            ));

            $h .= '</body></html>';
            return $h;
        }
    }

    //tag处理
    public static function parse_tag($tag=array()){
        if(empty($tag)){return NULL;}
        $h = array();
        for($i=0;$i<count($tag);$i++){
            if(isset(self::$tags[$tag[$i]])){
                $t = self::$tags[$tag[$i]];
                $t = DommyPHP::to_array($t);
                $h[] = '<span class="-wxapp-tagsign -dp-bc-'.$t[3].' -dp-fc-'.$t[2].'"><i class="iconfont icon-'.$t[1].'"></i>'.$t[0].'</span>';
            }
        }
        if(count($h)<=0){return NULL;}
        return implode("",$h);
    }

    //输出订单格式
    public static function export_order_structure($data=array()){
        $odf = self::data_path('order');
        $odsjson = file_get_contents($odf);
        $odsarr = DommyPHP::j2a($odsjson);
        $odst = $odsarr["common"]["structure"];
        if(is_array($data) && !empty($data)){
            return DommyPHP::array_overlay_v2($odst,$data);
        }else{
            return $odst;
        }
    }

    //生成订单编号
    public static function get_orderidx($extra="QZOD"){
        $odf = self::data_path('orderidx');
        $odjson = file_get_contents($odf);
        $odarr = DommyPHP::j2a($odjson);
        $odidx = $odarr["idx"];
        $ts = (int)$odidx["timestamp"];
        $t = time();
        $tds = strtotime(date("Y-m-d",$t)." 23:59:59");
        if($ts<$t){
            $odarr["idx"]["timestamp"] = $tds;
            $odarr["idx"]["idx"] = 1;
            $oidx = 1;
        }else{
            $oidx = (int)$odarr["idx"]["idx"];
            $oidx += 1;
            $odarr["idx"]["idx"] = $oidx;
        }
        //var_dump($odarr["common"]["idx"]);die();
        $odjson = DommyPHP::a2j($odarr);
        file_put_contents($odf,$odjson,LOCK_EX);
        return date("YmdHis",$t).$extra.strtoupper(DommyPHP::left_pad(dechex($oidx),3));
    }

    //检查给定的订单数据
    public static function check_order($oid=NULL, $type="complete"){
        if(!empty($oid)){
            $odf = self::data_path('order');
            $odjson = file_get_contents($odf);
            $odarr = DommyPHP::j2a($odjson);
            $ods = $odarr["orders"];
            if(!isset($ods['OD_'.$oid])){return FALSE;}
            $od = $ods['OD_'.$oid];
            switch($type){
                case "statu" :
                case "complete" :
                    $st = $od["statu"];
                    $ststr = "";
                    $stbool = FALSE;
                    if($st["cancel"]>=0){
                        $stbool = TRUE;
                        $ststr = "订单已取消";
                    }else if($st["return"]>=0){
                        if($st["return"]>=2){
                            $stbool = TRUE;
                            $ststr = "退款已完成";
                        }else{
                            $ststr = $st["return"]==1 ? "退款已确认，完成需要1~3个工作日" : "退款申请已提交，等待确认";
                        }
                    }else{
                        switch($st["order"]){
                            case 0 : $ststr="订单已提交，等待付款"; break;
                            case 1 : $ststr="订单已付款，等待确认"; break;
                            case 2 : $ststr="订单已确认，正在备货"; break;
                            case 3 : $ststr="订单已发货，正在配送"; break;
                            case 4 : $ststr="订单已完成"; $stbool = TRUE; break;
                        }
                    }
                    if($type=="statu"){
                        return array($stbool, $ststr);
                    }else{
                        return $stbool;
                    }
                    break;
                case "payed" :
                    $st = $od["statu"];
                    return $st["pay"]>=0;
                    break;
            }
        }
        return FALSE;
    }

}
