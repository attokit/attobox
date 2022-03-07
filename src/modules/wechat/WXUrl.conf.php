<?php
/**
*	微信数据调用接口
*
**/

return array(

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
	"user_get_taguser" 	=> "POST|%{API_CGI}%/user/tag/get?access_token=%{ACCESS_TOKEN}%",
	"user_set_tags"		=> "POST|%{API_CGI}%/tags/members/batchtagging?access_token=%{ACCESS_TOKEN}%",
	"user_unset_tags"	=> "POST|%{API_CGI}%/tags/members/batchuntagging?access_token=%{ACCESS_TOKEN}%",
	"user_get_usertags" => "POST|%{API_CGI}%/tags/getidlist?access_token=%{ACCESS_TOKEN}%",
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









);




?>