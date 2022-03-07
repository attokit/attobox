<?php
/*
 *  微信支付回调方法类，继承自  wx/pay/lib/WxPay.Notify.php
 * 
 */
class CustomNotifyCallback extends WxPayNotify {

    //回调处理，覆盖父类中的方法
    public function NotifyProcess($data, &$msg) {
        //保存微信支付接口返回的元数据
        $this->saveReturnData($data);
		$notfiyOutput = array();
		
		if(!array_key_exists("transaction_id", $data)){
			$msg = "输入参数不正确";
			return false;
		}
		//查询订单，判断订单真实性
		if(!$this->Queryorder($data["transaction_id"])){
			$msg = "订单查询失败";
			return false;
		}
		return true;
    }
    
    //储存微信支付接口返回的元数据
    public function saveReturnData($data){
        $rtnf = DommyPHP::path("wx/pay/log/wxpay_return_data.json");
        $rtnjson = file_get_contents($rtnf);
        $rtnarr = DommyPHP::j2a($rtnjson);
        $rtnarr["data"][] = array(
            "timestamp" => time(),
            "return" => DommyPHP::a2j($data)
        );
        $rtnjson = DommyPHP::a2j($rtnarr);
        file_put_contents($rtnf,$rtnjson,LOCK_EX);
    }


}