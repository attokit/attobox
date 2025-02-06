<?php
/**
 * 手动编辑数据库数据
 */

//var_dump($db);
//var_dump($table);
//var_dump($tables);
//var_dump($xpath);
$conf = $table->conf("_export_");
$url = \Atto\Box\Request::$current->url;
$host = $url->domain;
$xarr = explode("/", $xpath);
$tbn = array_pop($xarr);
$tbpre = implode("/", $xarr);

$sibling = $db->sibling();
$sibs = [];
foreach ($sibling as $dbn => $dbc) {
    $dbts = $dbc["tables"];
    for ($i=0;$i<count($dbts);$i++) {
        $sibs[] = [
            "label" => $dbc["title"]." ▶ ".$dbts[$i],
            "value" => $dbc["xpath"]."/".$dbts[$i]
        ];
    }
}



?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>手动编辑 <?=$conf["db"]["title"]?> <?=$conf["table"]["title"]?></title>
<!--<link rel="stylesheet" href="//cdn.bootcdn.net/ajax/libs/element-ui/2.15.13/theme-chalk/index.min.css"/>
<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/element-ui-light.css"/>
<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/base.css"/>
<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/printer.css"/>
<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/el-patch.css"/>-->
<link rel="stylesheet" href="/src/atto/vue/css/theme-dark.css" id="attovue_dark_mode_css"/>
<link rel="icon" href="//io.cgy.design/icon/cgy-qypms-favicon.svg"/>
<style>
:root {
    --ma-gap:       16px;
    --ma-gap-thin:  8px;
    --ma-height:    32px;
    --ma-height-bold:   48px;
    --ma-line-height:   18px;

    --ma-fml:       'Cascadia Code', 'Consolas', 'Courier New', 'Pingfang SC', 'Microsoft Yahei', monospace;
    --ma-size-f:    12px;
    --ma-size-f-t:  16px;

}
body {
    color: var(--color-f);
    font-family: var(--ma-fml);
    font-size: var(--ma-size-f);
}
#manual_app {
    position: relative; display: flex;
    width: 100vw; height: 100vh; overflow: hidden;
    align-items: stretch; justify-content: flex-start;
}
.flex-x {
    display: flex;
    align-items: center; 
    justify-content: flex-start;
}
    .flex-x.center {justify-content: center;}
    .flex-x.right {justify-content: flex-end;}
.flex-y {
    display: flex; flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
}
    .flex-y.center {align-items: center; justify-content: center;}
    .flex-y.center.x {justify-content: flex-start;}
    .flex-y.center.y {align-items: flex-start;}
.flex-1 {flex: 1;}
.flex-2 {flex: 2;}
.flex-3 {flex: 3;}

.nscroll {
    scroll-behavior: smooth;
}
    .nscroll::-webkit-scrollbar {
        width: 8px; height: 8px;
    }
    .nscroll::-webkit-scrollbar-thumb  {
        background-color: transparent;
        transition: all .3s;
    }
    .nscroll:hover::-webkit-scrollbar-thumb  {background-color: var(--color-bg-dark);}
    .nscroll::-webkit-scrollbar-track {
        background-color: transparent; 
        /*padding: 0 2px;*/
    }

.col {
    position: relative; display: flex;
    width: 50vw; height: 100vh;
    box-sizing: border-box;
    flex-direction: column; align-items: flex-start; justify-content: flex-start;
    transition: width .3s;
}
    .col.right {
        border-left: var(--color-bd) solid 1px;
    }
.titbar {
    position: relative;
    width: 100%; height: var(--ma-height);
    box-sizing: border-box; 
    padding: 0 var(--ma-gap);
}
    .titbar.border {border-bottom: var(--color-bd) solid 1px;}
    .titbar.border-top {border-top: var(--color-bd) solid 1px;}
    .titbar.bold {height: var(--ma-height-bold);}
    .titbar.nogap {padding: 0;}
    .titbar>.tit {
        margin: 0 var(--ma-gap);
        font-weight: 900;
        font-size: var(--ma-size-f-t);
        color: var(--color-f-darker);
    }
    .titbar>.btn {
        margin-left: calc(var(--ma-gap) * 0.5);
    }
.row {
    position: relative;
    width: 100%; min-height: var(--ma-height);
    box-sizing: border-box; 
    padding: 0 var(--ma-gap);
    cursor: default;
}
    .row.border {border-bottom: var(--color-bd) solid 1px;}
    .row.border-top {border-top: var(--color-bd) solid 1px;}
    .row.bold {min-height: var(--ma-height-bold);}
    .row.nogap {padding: 0;}
    .row.gap-top {margin-top: var(--ma-gap-thin);}
    .row.gap-inner-bottom {
        padding-bottom: var(--ma-gap-thin);
        min-height: calc(var(--ma-height) + var(--ma-gap-thin));
    }
    .row.hover:hover {
        background-color: var(--ma-color-hover);
    }
    .row>.icogap {
        width: 24px;
    }
    .row>.label {
        width: 128px;
        margin: 0 var(--ma-gap);
    }
    .row>.el-checkbox {margin-right: 0;}
    .row .el-input__inner {
        font-family: var(--ma-fml);
    }

.cm-response {
    width: 100%; height: unset; flex: 1;
    box-sizing: border-box;
    border: none;
    outline: none;
}

</style>
</head>
<body>
    
<div id="manual_app">
    <div 
        v-if="ready"
        class="col flex-1" 
        v-drag-resize:x 
    >
        <div class="titbar bold border flex-x">
            <atto-button
                icon="vant-edit"
                type="brand"
            ></atto-button>
            <span class="tit">{{ '手动编辑 '+config.db.title+' ▶ '+config.table.title }}</span>
            <span class="flex-1"></span>
            <atto-button
                icon="vant-sync"
                type="primary"
                title="刷新编辑器"
                @btn-click="reloadMa"
            ></atto-button>
        </div>
        <div class="row hover gap-top flex-x">
            <atto-button icon="vant-table"></atto-button>
            <span class="label">选择要编辑的数据表</span>
            <el-select
                size="mini"
                v-model="currentTableXpath"
                @change="gotoTable"
            >
                <el-option
                    v-for="(tbi, tbidx) in tables"
                    :key="'sel_edit_table_'+tbidx"
                    :label="tbi.label"
                    :value="tbi.value"
                ></el-option>
            </el-select>
        </div>
        <div class="row hover flex-x">
            <atto-button icon="vant-thunderbolt"></atto-button>
            <span class="label">选择编辑方法</span>
            <el-radio-group
                v-model="action"
                size="mini"
            >
                <el-radio-button label="api">Api</el-radio-button>
                <el-radio-button label="insert">新增</el-radio-button>
                <el-radio-button label="update">修改</el-radio-button>
                <el-radio-button label="select">查询</el-radio-button>
                <el-radio-button label="delete">删除</el-radio-button>
            </el-radio-group>
            <atto-button
                v-if="action=='api'"
                label="创建/重建表"
                custom-class="btn"
                @btn-click="readyToRecreateTable"
            ></atto-button>
        </div>
        <div class="row hover flex-x" v-if="action=='api'">
            <atto-button icon="vant-link"></atto-button>
            <span class="label">指定要调用的 Api</span>
            <span class="flex-1">
                <el-input v-model="actionApi" placeholder="输入目标 Api，或完整 URL" size="mini"></el-input>
            </span>
        </div>
        <div class="row hover flex-x" v-if="action=='api'">
            <span class="icogap"></span>
            <span class="label"></span>
            <el-checkbox v-model="withApiPrefix" border size="mini">自动补全</el-checkbox>
            <el-checkbox v-model="withPostData" border size="mini">提交数据</el-checkbox>
        </div>
        <div class="row hover flex-x">
            <atto-button icon="vant-flag"></atto-button>
            <span class="label">是否完整响应数据</span>
            <el-switch
                size="small"
                v-model="showFullResponse"
            ></el-switch>
        </div>
        <div class="row border gap-inner-bottom flex-x">
            <atto-button icon="vant-apartment"></atto-button>
            <span class="label">编辑 POST 数据</span>
            <span class="flex-1"></span>
            <atto-button
                :icon="requestSign.icon"
                type="danger"
                size="medium"
                :label="request.waiting?requestSign.label:(withPostData?'提交数据':'发送请求')"
                :spin="request.waiting"
                custom-class="btn"
                @btn-click="doPostData"
            ></atto-button>
        </div>
        <!--<atto-codemirror
            v-model="postJson"
            code-id="post_json"
            mode="javascript"
            mode-mime="application/json"
            :theme="cssvar.darkMode?'material-darker':'idea'"
            :dark-mode="cssvar.darkMode"
        ></atto-codemirror>-->
        <cm-json
            v-model="postJson"
            cm-id="post_json"
            mode="javascript"
            mode-mime="application/json"
            :theme="cssvar.darkMode?'material-darker':'idea'"
            :dark-mode="cssvar.darkMode"
        ></cm-json>
        <div class="row border border-top flex-x">
            <atto-button icon="vant-control"></atto-button>
            <span class="label">{{ config.table.title+'参数' }}</span>
            <span class="flex-1"></span>
            <el-switch
                size="small"
                v-model="showConf"
                :title="(showConf?'隐藏':'显示')+' 数据表参数'"
                style="margin-left: 8px;"
            ></el-switch>
        </div>
        <!--<atto-codemirror
            v-if="ready && showConf"
            v-model="configJson"
            code-id="config_json"
            mode="javascript"
            mode-mime="application/json"
            :theme="cssvar.darkMode?'material-darker':'idea'"
            :dark-mode="cssvar.darkMode"
            :read-only="true"
            custom-class="flex-3"
            :extra-options="cmeOptions"
        ></atto-codemirror>-->
        <cm-json
            v-if="ready && showConf"
            v-model="configJson"
            cm-id="config_json"
            mode="javascript"
            mode-mime="application/json"
            :theme="cssvar.darkMode?'material-darker':'idea'"
            :dark-mode="cssvar.darkMode"
            :read-only="true"
            custom-class="flex-3"
            :extra-options="cmeOptions"
        ></cm-json>
    </div>
    <div class="col right">
        <div class="titbar bold border flex-x">
            <atto-button
                :icon="requestSign.icon"
                :type="requestSign.type"
                :spin="request.waiting"
            ></atto-button>
            <span class="tit">{{ responseTime.cost<=0 ? '就绪' : requestSign.label }}</span>
            <atto-button
                v-if="requestSign.type=='success' && responseTime.cost>0"
                icon="vant-time-circle"
                :type="responseTime.cost<100?'success':(responseTime.cost<1000?'primary':(responseTime.cost<10000?'warn':'danger'))"
                :label="'耗时：'+responseTime.cost+' ms'"
            ></atto-button>
            <span class="flex-1"></span>
        </div>
        <!--<atto-codemirror
            v-model="responseJson"
            code-id="response_json"
            mode="javascript"
            mode-mime="application/json"
            :theme="cssvar.darkMode?'material-darker':'idea'"
            :dark-mode="cssvar.darkMode"
            :read-only="true"
            :extra-options="cmeOptions"
        ></atto-codemirror>-->
        <cm-json
            v-model="responseJson"
            cm-id="response_json"
            mode="javascript"
            mode-mime="application/json"
            :theme="cssvar.darkMode?'material-darker':'idea'"
            :dark-mode="cssvar.darkMode"
            :read-only="true"
            :extra-options="cmeOptions"
        ></cm-json>
    </div>
</div>

<!--<script src="https://cdn.bootcdn.net/ajax/libs/axios/1.3.6/axios.min.js"></script>
<script src="https://cdn.bootcdn.net/ajax/libs/vue/2.7.9/vue.common.dev.min.js"></script>
<script src="https://cdn.bootcdn.net/ajax/libs/element-ui/2.15.14/index.min.js"></script>-->

<!--<script src="https://cdn.bootcdn.net/ajax/libs/axios/1.3.6/axios.min.js"></script>-->
<!--<script src="https://cdn.bootcdn.net/ajax/libs/vue/2.7.9/vue.common.dev.min.js"></script>-->
<!--<script src="//cdn.bootcdn.net/ajax/libs/element-ui/2.15.14/index.min.js"></script>
<script src="//lib.cgy.design/axios/@/axios.min.js"></script>
<script src="//lib.cgy.design/vue/@/dev.min.js"></script>
<script src="//lib.cgy.design/vue/@/ui/element-ui/2.15.14/element-ui.min.js" charset="utf-8"></script>-->
<script src="//io.cgy.design/axios/@/axios.min.js"></script>
<script src="//io.cgy.design/vue/@/dev.min.js"></script>
<script src="//io.cgy.design/vue/@/ui/element-ui/2.15.14/element-ui.min.js" charset="utf-8"></script>

<!--<script src="https://cdn.bootcdn.net/ajax/libs/codemirror/6.65.7/codemirror.min.js"></script>-->

<script type="module">
    //import codemirror from '//cgy.design/src/atto/vue/plugin/codemirror.js';
    import cmJson from '//io.cgy.design/codemirror/vue/cm-json';
    import attovue from '/src/atto/vue/plugin/attovue.js';

    //import hljs from '//cdn.bootcdn.net/ajax/libs/highlight.js/11.7.0/es/core.min.js';
    //import hlJavascript from '//cdn.bootcdn.net/ajax/libs/highlight.js/11.7.0/es/languages/javascript.min.js';
    //import hlJson from '//cdn.bootcdn.net/ajax/libs/highlight.js/11.7.0/es/languages/json.min.js';

    //console.log(CodeMirror);

    //console.log(hljs);
    //hljs.registerLanguage('javascript', hlJavascript);
    //hljs.registerLanguage('json', hlJson);

    //应用 codemirror 插件
    //Vue.use(codemirror, {
    //    for: ["json", "javascript"]
    //});

    //应用 attovue 插件
    Vue.use(attovue, {
        host: '<?=$host?>',
        dbRoute: 'db',
    });

    window.app = new Vue({
        el: '#manual_app',
        components: {
            'cm-json': cmJson
        },
        data: {
            ready: false,
            url: '<?=$url->full?>',
            host: '<?=$host?>',
            config: {},
            configJson: '',
            tables: JSON.parse('<?=a2j($sibs)?>'),
            currentTableXpath: '<?=$xpath?>',
            lines: {
                config: 100,
                post: 100,
                response: 100
            },
            action: 'api',
            actionApi: '',
            withApiPrefix: true,    //是否自动添加api前缀
            withPostData: false,    //是否提交 postData 到 api
            post: {
                query: {
                    //where: {}
                    //sk: '酱油'
                },
                data: {}
            },
            postJson: '',
            responseData: {},
            responseJson: '',

            showConf: true,

            //是否显示完整的 axios 响应数据
            showFullResponse: false,

            //响应耗时
            responseTime: {
                start: 0,
                end: 0,
                cost: 0
            },

            //codemirror extra options
            cmeOptions: {
                styleActiveLine: false, //高亮当前行，在暗黑模式下不开启
            },

            //界面模式，light/dark
            /*uimode: {
                mode: 'dark',
                dark: {
                    cm: {
                        theme: 'seti'
                    }
                }
            },*/
        },
        computed: {},
        created() {
            this.$req('db/<?=$xpath?>/conf').then(res=>{
                this.config = Object.assign({}, this.config, res);
                this.configJson = JSON.stringify(this.config);
                this.postJson = JSON.stringify(this.post);
                this.ready = true;
            });
        },
        watch: {
            action(nv, ov) {
                if (['update','delete'].includes(this.action)) {
                    this.$attoAlert(
                        'warn',
                        `${this.action} 方法将直接修改数据库，无法撤销，请谨慎操作！！`,
                        '请注意'
                    );
                }
            },
            //json 数据变化
            postJson(nv, ov) {
                let jo = this.getPostData(),
                    is = this.$is;
                if (is.plainObject(jo) && !is.empty(jo)) {
                    this.post = Object.assign({}, jo);
                }
            },
        },
        beforeCreate() {
            //开启 debug
            this.$debug.on();
            /*Vue.prototype.$log.ready({
                label: 'PMS',
                sign: '▶',
                colors: {
                    brand: '#ff8600'
                }
            });*/
        },
        //mounted() {},
        methods: {
            //get post data
            getPostData() {
                let js = this.postJson,
                    jo = {};
                try {
                    jo = JSON.parse(js);
                    return jo;
                } catch(err) {
                    this.$attoAlert('error', '要提交的数据格式错误<br>必须是标准的 JSON 格式数据', '数据格式错误');
                    throw new Error(err);
                    return {};
                }
            },

            //post data
            doPostData() {
                if (this.action=='') {
                    return this.$attoAlert('error','请选择一个编辑方法','编辑方法不能为空');
                }
                let is = this.$is,
                    api = this.url,
                    pd = this.getPostData();
                //call api
                if (this.action=='api') {
                    if (this.actionApi=='') {
                        return this.$attoAlert('error','请选择一个目标 Api','Api 不能为空');
                    }
                    let aapi = this.actionApi;
                    if (aapi.includes('://') || aapi.startsWith('//')) {
                        api = aapi.startsWith('//') ? '<?=$url->protocol?>:'+aapi : aapi;
                    } else if (aapi.startsWith('/')) {
                        api = `${this.host}${aapi}`;
                    } else {
                        api = `db/${this.config.table.xpath}/`;
                        if (this.withApiPrefix) api += 'api/';
                        api += this.actionApi.trimAny('/');
                        //api = `db/${this.config.table.xpath}/api/${this.actionApi.trimAny('/')}`;
                    }
                    if (!this.withPostData) {
                        pd = {};
                    }
                } else {
                    pd.action = this.action;
                }
                //console.log(api, pd);

                //request 开始
                this.responseTime.start = new Date().getTime();
                if (this.showFullResponse) {
                    this.$request(api, pd).then(res=>{
                        this.responseTime.end = new Date().getTime();
                        this.responseTime.cost = this.responseTime.end - this.responseTime.start;

                        this.responseData = Object.assign({}, res);
                        this.responseJson = JSON.stringify(res);
                        this.$setRequestFlag('success');
                        if (this.action!='api') this.action = '';
                    });
                } else {
                    this.$req(api, pd).then(res=>{
                        this.responseTime.end = new Date().getTime();
                        this.responseTime.cost = this.responseTime.end - this.responseTime.start;

                        this.responseData = Object.assign({}, res);
                        this.responseJson = JSON.stringify(res);
                        if (this.action!='api') this.action = '';
                    });
                }
            },

            //创建/重建表 按钮动作
            readyToRecreateTable() {
                this.actionApi = `/db/reinstall/${this.currentTableXpath}`;
                this.withApiPrefix = false;
            },

            //cm start
            cms(id) {
                let cmea = document.querySelector(`#${id}`);
                //console.log(cmea);
                this.cmes = CodeMirror.fromTextArea(cmea, {
                    lineNumbers: true,
                    mode: 'application/json',
                    //theme: 'monokai',
                    extraKeys: {
                        "Ctrl": "autocomplete"
                    },
                    //代码折叠
                    lineWrapping:true,
                    foldGutter: true,
                    gutters:["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                });
                console.log(this.cmes);
            },

            //reload manual app
            reloadMa() {
                window.location.reload();
            },

            //goto table
            gotoTable(tbn) {
                //console.log(tbn);
                let u = this.url,
                    ox = '<?=$xpath?>',
                    uarr = u.split(ox),
                    xpath = this.currentTableXpath,
                    nu = `${uarr[0]}${xpath}${uarr[1]}`;
                //console.log(nu);
                window.location.href = nu;
            },
        }
    });

</script>

</body>
</html>