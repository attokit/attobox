<?php

/**
 * Attobox Framework / default spa container
 * 默认单页应用主界面
 */

use Atto\Box\Uac;
use Atto\Box\Request;
use Atto\Box\Response;

//必须登录
$uac = $app->uo();
if (!$uac->isLogin) {
    $uac->jumpToUsrLogin();
    exit;
}

//$args = [];   //URI

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>cVue SPA</title>
<link rel="icon" href="//lib.cgy.design/icon/cgy-cgydesign-favicon.svg">
</head>
<body>

<div id="spa_main" v-cloak>
    <cv-layout
        use="toolbar,menubar,tabbar,ctrlbar"
        page-logo="//lib.cgy.design/icon/cgy-design"
        page-title="SPA"
    >
        <template v-slot:navtool="{layout}">
            
        </template>
    </cv-layout>
</div>



<script>
/**
 * baseOptions 示例
 */
var baseOptions = {
    //指定此 spa 页面所在的 App Name，不为空的字符串
    defaultApiPrefix: '',
    
    usr: {uac: true}
}
</script>
<script type="module" src="//lib.cgy.design/vue/env/@/browser?dev=yes"></script>
<script type="module">

    //Vue.$debug.on();
    Vue.rootApp({
        el: '#spa_main',
        data: {
            
        },
        created() {
            console.log('root created', this);
        },
        methods: {
            
        }
    });

</script>
</body>
</html>