<?php

use Atto\Box\request\Url;
//var_dump($comp);
$containerId = str_replace("-","_",$comp["name"])."_container";

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title></title>
<link rel="stylesheet" href="//cdn.bootcdn.net/ajax/libs/element-ui/2.15.13/theme-chalk/index.min.css">
<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/base.css">
<style>
.ms-container {
    width: 100vw; height: 100vh; overflow: hidden;
}
</style>
</head>
<body>

<div 
    class="ms-container" 
    id="<?=$containerId?>"
>
    <atto-layout
        
    ></atto-layout>
</div>

<script src="https://cdn.bootcdn.net/ajax/libs/axios/1.3.6/axios.min.js"></script>
<script src="https://cdn.bootcdn.net/ajax/libs/vue/2.7.9/vue.common.dev.min.js"></script>
<script src="//cdn.bootcdn.net/ajax/libs/element-ui/2.15.13/index.min.js"></script>
<script type="module">
    import attovue from '//cgy.design/src/atto/vue/plugin/attovue.js';
    import attoLayout from '//cgy.design/src/atto/vue/components/atto-layout.vue?export=js&name=atto-layout';

    Vue.use(attovue, {
        host: '<?=Url::mk()->domain?>',
        dbRoute: '<?=DB_ROUTE?>',
    });

    let app = new Vue({
        el: '#<?=$containerId?>',
        components: {
            'atto-layout': attoLayout
        },
        data: {},
        computed: {},
        methods: {

        }
    });

    window.Vue = Vue;
    window.app = app;

</script>

</body>
</html>