<?php

$doc = $_Params["doc"];

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $doc->title; ?></title>
    <link rel="shortcut icon" href="//cgy.design/src/favicon.ico"/>
    <link rel="bookmark" href="//cgy.design/src/favicon.ico"/>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/normalize/8.0.1/normalize.min.css" />
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.1.0/css/all.min.css" />
    <link rel="stylesheet" href="/src/module/doc/css/github-markdown-light.css" />
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/element-ui/2.15.7/theme-chalk/index.min.css" />
    <!--<link rel="stylesheet" href="//lib.cgy.design/src/element-ui.css" />-->
    <link rel="stylesheet" href="/src/module/doc/css/container.css" />
</head>
<body class="nice-scroll_y_thin">

<div id="lib-app" v-cloak>

    <el-menu 
        class="lib-topbar" 
        mode="horizontal"
    >
        <span class="sep-sm"></span>
        <img class="logo-main" src="<?php echo $doc->logo; ?>">
        <span class="sep-sm"></span>
    </el-menu>
    
    <div class="lib-menubar">
        <div class="menubar-top"></div>
        
        <el-menu
            default-active="1-0"
            class="lib-assets-menu nice-scroll_y_thin"
            @select="selectdoc"
            @open=""
            @close=""
        >
            <template
                v-for="(direct, dindex) in directory"
            >
                <el-submenu
                    v-if="direct.sub"
                    :key="direct.name"
                    :index="direct.name"
                >
                    <template slot="title"><span>{{direct.title}}</span></template>
                    <el-menu-item 
                        v-for="(sub, sindex) in direct.sub"
                        :key="sub.name"
                        :index="direct.name+'/'+sub.name"
                    >{{sub.title}}</el-menu-item>
                </el-submenu>
                <el-menu-item 
                    v-else
                    :key="direct.name"
                    :index="direct.name"
                >{{direct.title}}</el-menu-item>
            </template>
        </el-menu>
    </div>
    <div class="lib-content">
        <div class="lib-readme">
            <div class="lib-readme-body">
                <div class="markdown-body" v-html="content"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.bootcdn.net/ajax/libs/axios/0.26.1/axios.min.js"></script>
<script src="https://cdn.bootcdn.net/ajax/libs/vue/2.6.9/vue.min.js"></script>
<script src="https://cdn.bootcdn.net/ajax/libs/element-ui/2.15.7/index.min.js"></script>


<script type="module">
//Vue.use(Element);
const lib = new Vue({
    el: '#lib-app',
    data() {return {
        directory: <?php echo a2j($doc->directory); ?>,
        uri: '<?php echo $doc->uri; ?>',
        content: ''
    }},
    computed: {
        
    },
    mounted() {
        this.loadhash();
        window.addEventListener('hashchange', (evt) => {
            this.loadhash();
        });
    },
    methods: {
        selectdoc(...args) {
            //console.log(args);
            window.location.hash = '#/'+args[0];
        },
        
        loadhash() {
            let hash = window.location.hash.replace('#/','');
            if (hash=='') {
                this.selectdoc('index');
            } else {
                let url = `/doc/file/${this.uri}?format=json&file=${hash}`;
                console.log(hash, url);
                axios.get(url).then(res => {
                    //console.log(res);
                    let d = res.data;
                    this.content = d.data;
                });
            }
        }

    }
});

window.lib = lib;

</script>

</body>
</html>