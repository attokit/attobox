<?php

$host = \Atto\Box\request\Url::$current->domain;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Db.<?php echo $db->name; ?> - Attobox 数据库管理器</title>
<link rel="stylesheet" href="//cdn.bootcdn.net/ajax/libs/element-ui/2.15.13/theme-chalk/index.min.css">
<link rel="stylesheet" href="//cgy.design/src/atto/css/base.css">
<link rel="stylesheet" href="//cgy.design/src/atto/vue/css/base.css">
<link rel="stylesheet" href="//cgy.design/src/atto/css/db-manager.css">
</head>
<body>

<div id="db-manager" class="dbm">

    <div class="dbm-sideicon">
        <span class="item logo" title="Attobox 数据库管理器">
            <atto-icon icon="vsc-sequelize" :size="32"></atto-icon>
        </span>
        <span class="sep"></span>

        <atto-icobtn 
            icon="vant-table" 
            title="数据表"
            custom-class="item"
            :color="ui.btn.color.normal" 
            :active-color="ui.btn.color.active"
            :active-bg-color="ui.btn.bgc.active"
            :size="ui.btn.size.side" 
            :active="true"
        ></atto-icobtn>
        <atto-icobtn 
            icon="vant-plus" 
            title="新建数据表"
            custom-class="item"
            :size="ui.btn.size.side" 
        ></atto-icobtn>
    </div>

    <div class="dbm-main">
        <div class="dbm-tabs">
            <?php
            $db->eachTable(function($_db, $_tbn) use ($table, $manager) {
                $tit = !empty($table[$_tbn]) ? $table[$_tbn]->conf("title") : 'null';
                echo '<a href="/db/'.$_db->name.'/'.$_tbn.'" class="tab'.($manager->isActive($_tbn)?' active':'').'" title="Db.'.$_db->name.'.'.$_tbn.'">'.$tit.'</a>';
            });
            ?>
            <div class="ctrl">
                <a href="" class="btn">
                    
                </a>
            </div>
            <span class="gap"></span>
            <div class="ctrl">
                <a href="" class="btn">
                    
                </a>
            </div>
        </div>

        <atto-table
            table="<?=$manager->active()->xpath?>"
        >
        
        </atto-table>

        
    </div>

</div>

<script src="https://cdn.bootcdn.net/ajax/libs/axios/1.3.6/axios.min.js"></script>
<!--<script src="https://cdn.bootcdn.net/ajax/libs/vue/2.7.9/vue.min.js"></script>-->
<script src="https://cdn.bootcdn.net/ajax/libs/vue/2.7.9/vue.common.dev.min.js"></script>
<!--<script type="module">
    import Vue from 'https://cdn.bootcdn.net/ajax/libs/vue/2.7.9/vue.esm.browser.js';
    window.Vue = Vue;
</script>-->
<script src="//cdn.bootcdn.net/ajax/libs/element-ui/2.15.13/index.min.js"></script>
<script type="module">
    import attovue from '//cgy.design/src/atto/vue/plugin/attovue.js';
    import attoIcon from '//cgy.design/src/atto/vue/components/atto-icon.vue?export=js&name=atto-icon';
    import attoIcobtn from '//cgy.design/src/atto/vue/components/atto-icobtn.vue?export=js&name=atto-icobtn';
    import attoTable from '//cgy.design/src/atto/vue/components/atto-table.vue?export=js&name=atto-table';

    Vue.use(attovue, {
        host: '<?=$host?>',
        dbRoute: '<?=DB_ROUTE?>',
    });

    //console.log(Element);

    let app = new Vue({
        el: '#db-manager',
        components: {
            'atto-icon': attoIcon,
            'atto-icobtn': attoIcobtn,
            'atto-table': attoTable
        },

        data: {
            config,
            ui: {
                btn: {
                    color: {
                        normal: '#5f697d',
                        active: '#ffffff',

                    },
                    size: {
                        side: 22,
                        titbar: 22
                    },
                    bgc: {
                        normal: '',
                        active: '#74c0fc'
                    }
                },
                show: {
                    panel: {
                        filter: false,
                        sort: false,
                        field: false,
                        print: false,
                        excel: false
                    }
                }
            },
            //fields,
            //field: fieldConfig
        },

        computed: {
            fieldWidth() {
                let fds = this.fields,
                    fc = this.field,
                    fw = {},
                    tw = 0;
                for (let fi of fds) {
                    if (fc[fi].show) {
                        fw[fi] = fc[fi].width;
                        tw += fc[fi].width*1;
                    }
                }
                let wi = 100/tw;
                for (let [index, item] of Object.entries(fw)) {
                    if (tw<=0) {
                        fw[index] = 0;
                    } else {
                        fw[index] = item*wi;
                    }
                }
                return fw;
            }
        },

        methods: {
            togglePanel(panel) {
                this.ui.show.panel[panel] = !this.ui.show.panel[panel];
            }
        }
    });

    window.app = app;
</script>

</body>
</html>