<?php

$host = isset($host) ? $host : \Atto\Box\Request::$current->url->domain;
$logo = isset($logo) ? $logo : "cgy-light";
$icon = isset($icon) ? $icon : "vant-close-circle-fill";
if (isset($error)) {
    //var_dump($error);
    $title = $error["title"];
    $msg = [
        "msg" => $error["msg"],
        "file" => str_replace("/data/wwwroot/","",$error["file"]),
        "line" => $error["line"]
    ];
} else {
    $title = isset($title) ? $title : "发生错误";
    $msg = isset($msg) ? $msg : "操作发生错误，请检查";
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=$title?></title>
<!--<link rel="stylesheet" href="//cdn.bootcdn.net/ajax/libs/element-ui/2.15.13/theme-chalk/index.min.css"/>
<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/element-ui.css"/>
<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/base.css"/>
<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/err-page.css"/>
<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/el-patch.css"/>-->

<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/theme-dark.css" id="attovue_dark_mode_css"/>
<link rel="icon" href="//cgy.design/icon/cgy-qypms-favicon.svg"/>
<!--<link rel="stylesheet" href="//lib.cgy.design/vue/@/themes/base/dark.css" id="cv_base_dark_css"/>
<link rel="icon" href="//lib.cgy.design/icon/cgy-cgydesign-favicon.svg"/>-->
<style>
[v-cloak] {display: none;}
body {
    width: 100vw; height: 100vh;
    display: flex; align-items: center; justify-content: center;
    border: none;
    background-color: var(--color-bg-light);
}
</style>
</head>
<body style="padding-bottom:20vh; background-color: var(--color-bg);">

<div id="err_page" class="atto-err-container" v-cloak>
    <img class="err-icon" :src="'//cgy.design/icon/'+icon+'?fill='+(cssvar.color.danger.substring(1))"/>
    <div class="err-title">{{title}}</div>
    <div v-if="typeof msg == 'string'" class="err-msg">
        {{msg}}
    </div>
    <div v-else class="err-msglist">
        <div 
            v-for="ki in msgkeys"
            :key="'err-msg-'+ki"
            class="err-msg-item"
        >
            <span class="err-msg-label">{{msglabel[ki]}}</span>
            <span class="err-msg-cnt" :title="msg[ki]">{{msg[ki]}}</span>
        </div>
    </div>
    <div class="err-ctrl">
        <atto-button
            icon="vant-home"
            size="medium"
            title="返回首页"
            custom-class="btn"
            @btn-click="gotoIndex"
        ></atto-button>
        <atto-button
            icon="vant-sync"
            size="medium"
            title="刷新页面"
            custom-class="btn"
            @btn-click="reloadPage"
        ></atto-button>
        <atto-button
            icon="vant-question-circle"
            size="medium"
            title="查看帮助"
            custom-class="btn"
        ></atto-button>
    </div>
    <div class="err-copy">
        <span>© 2021~<?=date("Y", time())?> All Rights Reserved.</span>
        <!--<img class="err-logo" :src="'https://cgy.design/icon/'+logo">-->
        <atto-logo src="cgy-design-expand" custom-class="err-logo"></atto-logo>
    </div>
</div>

<!--<script src="https://cdn.bootcdn.net/ajax/libs/axios/1.3.6/axios.min.js"></script>
<script src="https://cdn.bootcdn.net/ajax/libs/vue/2.7.9/vue.common.dev.min.js"></script>
<script src="//cdn.bootcdn.net/ajax/libs/element-ui/2.15.13/index.min.js"></script>-->

<script src="//lib.cgy.design/axios/@/axios.min.js"></script>
<script src="//lib.cgy.design/vue/@/vue.min.js"></script>
<script src="//lib.cgy.design/vue/@/ui/element-ui/2.15.14/element-ui.min.js"></script>

<!--<script type="module" src="//lib.cgy.design/vue/env/@/browser?dev=yes"></script>-->
<script type="module">
    import attovue from '//cgy.design/src/atto/vue/plugin/attovue.js';
    //import mixinBase from '//cgy.design/src/atto/vue/mixins/base.js';
    //import mixinBase from '//lib.cgy.design/vue/@/mixins/base/base';

    Vue.use(attovue, {
        host: '<?=$host?>',
        dbRoute: 'db',
    });

    window.app = new Vue({
        el: '#err_page',
        //mixins: [mixinBase],
        components: {
            
        },
        data: {
            host: '<?=$host?>',
            logo: '<?=$logo?>',
            icon: '<?=$icon?>',
            title: '<?=$title?>',
            msg: <?=is_array($msg) ? "JSON.parse('".a2j($msg)."')" : "'".$msg."'"?>,
            msgkeys: [
                'msg',
                'file',
                'line'
            ],
            msglabel: {
                'msg': '信息',
                'file': '文件',
                'line': '行号'
            },
        },
        computed: {},
        created() {
            console.log(this.cssvar);
        },
        methods: {
            gotoIndex() {
                let href = window.location.href,
                    arr = href.split('://')[1].split('/').slice(0,2),
                    u = href.split('://')[0]+'://'+arr.join('/');
                window.location.href = u;   //this.host;
            },
            reloadPage() {
                window.location.reload();
            },
        }
    });

</script>

</body>
</html>