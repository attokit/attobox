<?php

/**
 * Attobox Framework / table config data parser
 * 解析 config/dbn.json 中关于 table 的设置项
 * 按项指定 解析方法
 * 
 * 如：
 * config/dbn.json = {
 *      table: {
 *          creation: {
 *              field1: 'foobar',
 *              field2: 'fooobarrr'
 *          },
 *          selectors: {
 *              field3: {
 *                  dynamic: true,
 *                  source: {...}
 *              }
 *          },
 *      }
 * }
 * 则使用的 parse 方法为：
 *      Configer->parseCreation()
 *      Configer->parseSelectors()
 * 
 * 解析得到的参数附加到
 *      table->config["field"][fdn]
 * 
 * 可以通过 table->conf(fdn/*) 访问到
 * 
 */

namespace Atto\Box\db;

use Atto\Box\Db;
use Atto\Box\db\Table;
use Atto\Box\Request;

class Configer 
{
    /**
     * 关联的 db/table 实例
     */
    public $db = null;
    public $table = null;

    /**
     * 小数位数
     */
    public $precision = 2;

    /**
     * config data 设置内容
     * 来自 config/dbn.json
     */
    public $conf = [];

    /**
     * 解析后的字段设置项结构
     */
    public $confStructure = [
        "name" => "",
        "title" => "",
        "desc" => "",
        //字段类型
        "type" => "",       //字段在数据库中的类型，varchar,integer,float
        "isNumber" => false,//是否数字
        "number" => [],
        "isJson" => false,  //是否 json 格式数据
        "json" => [],
        "isTime" => false,  //是否 时间数据
        "time" => [],
        //数据表显示参数
        "showInTable" => true,  //是否在数据表中显示，前端可以修改
        "width" => 0,           //表格列宽度，表示字段之间的宽度比例，width=2 的宽度是 width=1 的 2 倍
        //查询参数
        "filterable" => false,  //可筛选
        "sortable" => false,    //可排序
        "sort" => "",           //排序方式
        "searchable" => false,  //可搜索
        //form 表单参数
        "showInForm" => true,   //是否在 add-form 中显示，false 则在 edit-form 中 readonly
        "formType" => "",       //字段在前端 form 中的类型，需要配合前端 UI 组件，string/array/bool...
        "inputer" => [          //form input 组件
            "type" => "",       //input 类型，UI 框架 = Element-ui，el-input/el-select/el-switch/... 
            "params" => [],     //input 组件参数
            "options" => [],    //select 组件 options
        ],
        "isSelector" => false,  //是否 select 
        "selector" => [         //input = el-select 时的 option 参数
            "dynamic" => false, //动态选项
            "cascader" => false,//级联选择
            "values" => [],     //选项内容
            "source" => [       //动态下拉列表参数
                "table" => "",        //来自表
                "where" => "all",     //where
                "export" => ""        //
            ],
        ],
        "isFile" => false,      //是否文件附件
        "file" => [
            "multiple" => false,            //是否可以多选文件
            "inputFilesMaxLength" => 0,     //最多可选文件数
            "uploadTo" => "",               //文件上传路径
            "accept" => ""                  //允许的文件类型
        ],
        "isSwitch" => false,    //是否 switch
        "isGenerator" => false, //是否自动生成
        "required" => false,    //必填字段
        "validate" => false,    //字段验证
        //特殊字段
        "isId" => false,        //id 字段
        "isPk" => false,        //primary key 关键字段
        "isEnable" => false,    //enable 字段
        //默认值
        "default" => null,

        //虚拟字段，用于快捷输出
        //虚拟字段：    type=varchar，showInTable=false，showInForm=false，formType=string
        "isVirtual" => false,   //是否虚拟字段
        "virtual" => [],        //虚拟字段参数

        //是否可以求和
        "isSum" => false,

    ];

    /**
     * 要解析的设置项目
     * 按顺序解析
     */
    public $keys = [
        "meta",     //name,title,desc
        "type",     //type,isJson,json,isTime,time
        "view",     //width,showInTable
        "query",    //filterable,sortable,sort,searchable
        "special",  //isId,isPk,isEnable
        "form",     //showInForm,isSelector,selector,isSwitch,isGenerator,required,validate
        "inputer",  //formType,inputer
        "default",  //default
        "virtual",  //isVirtual,virtual
        "sum",      //isSum

        //解析数据表显示模式
        "mode",
    ];

    /**
     * inputer ui 参数生成类实例
     */
    public $formui = null;

    public function __construct($db, $tb)
    {
        $this->db = $db;
        $this->table = $tb;
        $this->conf = $this->db->conf("table/".$this->table->name);

        //读取 DB_FORMUI 设置
        $app = Request::$current->app;
        if (empty($app)) {
            $formui = DB_FORMUI;
        } else {
            $cnst = "APP_".strtoupper($app)."_DB_FORMUI";
            $formui = defined($cnst) ? constant($cnst) : DB_FORMUI;
        }
        $uicls = cls("db/formui/$formui");
        if (empty($uicls)) {
            $formui = DB_FORMUI;
            $uicls = cls("db/formui/$formui");
        }
        if (empty($uicls)) return false;
        $this->table->config["formui"] = $formui;
        $this->formui = new $uicls($this);
    }

    /**
     * 开始解析
     * @return $this
     */
    public function parse()
    {
        $keys = $this->keys;
        for ($i=0;$i<count($keys);$i++) {
            $key = $keys[$i];
            $m = "parse".ucfirst($key);
            if (method_exists($this, $m)) {
                $this->$m();
                /*try {
                    $this->$m();
                } catch (\Exception $e) {
                    if ($this->table->name=="delivery") {
                        var_dump($key);
                        exit;
                    }
                }*/
            }
        }
        return $this;
    }

    /**
     * 单独解析 form 参数
     * @return Array
     */
    public function parseTableForm() 
    {
        $fc = isset($this->conf["form"]) ? $this->conf["form"] : [];
        return arr_extend([
            "labelWidth" => ""
        ], $fc);
    }

    /**
     * 单独解析 detail 数据记录详情页参数
     * @return Array
     */
    public function parseTableDetail() 
    {
        $dc = is_def($this->conf, "detail") ? $this->conf["detail"] : [];
        return arr_extend([
            "img" => [         //详情图片
                "src" => "",    //用作图片src的字段或字段组合，可以是虚拟字段
                "alt" => "",    //说明，不指定则使用字段desc
                "thumb" => "",  //缩略图的src
            ],
            "title" => "",      //用作标题的字段或字段组合，可以是虚拟字段
            "tags" => [         //用作tag标签的字段或字段组合，可以是虚拟字段
                //["icon"=>"cgy icon", "title"=>"标签title", "label"=>"标签label" "tag"=>"字段或字段组合，可以是虚拟字段"],
            ],
            "basic" => [        //用作基础数据的字段或字段组合，可以是虚拟字段
                //["icon"=>"cgy icon", "field"=>"字段"],
                //["icon"=>"cgy icon", "field"=>"%{字段}%组合", "label"=>""],
                //"字段",
                //"%{字段}%组合"
            ],
            "ctrl" => [         //操作按钮
                "reload" => true,
                "edit" => true,
                "del" => false
            ]
            
        ], $dc);
    }

    /**
     * 单独解析 fastfilter 快捷筛选参数
     * 应在解析完所有 field 字段后执行
     * @return Array
     */
    public function parseTableFastfilter()
    {
        $ff = $this->conf["fastfilter"] ?? [];
        if (!empty($ff)) {
            $nff = [];
            for ($i=0;$i<count($ff);$i++) {
                $ffi = $ff[$i];
                $fft = $ffi["title"] ?? null;
                $fff = $ffi["filter"] ?? null;
                if (is_null($fft) || is_null($fff) || empty($fff)) continue;
                $nffi = [
                    "title" => $fft,
                    "filter" => []
                ];
                //解析 filter
                foreach ($fff as $fdn => $opt) {
                    if (!in_array($fdn, $this->conf["fields"])) continue;
                    if (is_notempty_arr($opt)) {
                        if (isset($opt["logic"]) && isset($opt["value"])) {
                            $nffi["filter"][$fdn] = $opt;
                        } else {
                            continue;
                        }
                    } else if (is_notempty_str($opt) && strpos($opt, "::")!==false) {
                        //使用预定义的方法，解析得到 filter 参数
                        $optf = explode("::", $opt);
                        $m = "ff".ucfirst($optf[0]);
                        $args = $optf[1]=="" ? [] : arr($optf[1]);
                        if (method_exists($this, $m)) {
                            $nffi["filter"][$fdn] = $this->$m($fdn, ...$args);
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                }
                if (!empty($nffi["filter"])) {
                    $nff[] = $nffi;
                }
            }
            return $nff;
        }
        return $ff;
    }

    /**
     * 调用 fastfilter 中指定的预处理方法
     * fieldName: 方法名::参数1,参数2,...
     * @return Array ["logic" => "<>", value: [...] ]
     */
    //timedura 时间间隔
    protected function ffTimedura($fdn, $type = "today", $not = false)
    {
        if (in_array($not, ["not","no","true"])) {
            $logic = "><";
        } else {
            $logic = "<>";
        }
        $t = time();
        if ($type=="today") {
            $ts = strtotime(date("Y-m-d 00:00:00", $t));    //今天 0 点
            $te = strtotime(date("Y-m-d 23:59:59", $t));    //今天 24 点
            $value = [$ts, $te];
        } else if ($type=="week") {
            $w = date("w", $t); //星期几，0~6 日~六
            $ts = $t-(24*60*60*$w);
            $te = $t+(24*60*60*(6-$w));
            $ts = strtotime(date("Y-m-d 00:00:00", $ts));   //本周日 0 点
            $te = strtotime(date("Y-m-d 23:59:59", $te));    //本周六 24 点
            $value = [$ts, $te];
        } else if ($type=="month") {
            $mt = date("t", $t);    //本月共有几天
            $ts = strtotime(date("Y-m-01 00:00:00", $t));   //本月1号 0 点
            $te = strtotime(date("Y-m-".$mt." 23:59:59"), $t);  //本月最后一天 24 点
            $value = [$ts, $te];
        }
        return [
            "logic" => $logic,
            "value" => $value
        ];
    }



    /**
     * 将解析获得的参数附加到 table 实例
     * @param Array $conf   [ fdn => [...], ... ]
     * @return $this
     */
    public function setConf($conf = [])
    {
        $oconf = $this->table->config["field"];
        $this->table->config["field"] = arr_extend($oconf, $conf);
        return $this;
    }

    /**
     * 查找已经解析获得的 字段参数
     * @param String $key
     * @return Mixed
     */
    public function getConf($key = null)
    {
        $conf = $this->table->config["field"];
        if (!is_notempty_str($key)) return $conf;
        return arr_item($conf, $key);
    }

    /**
     * 循环 fields 执行 callback 解析某个设置项，将结果 setConf($rst)
     * @param String $key   设置项名称
     * @param Callable $callback
     * @return $this
     */
    public function eachfield($key, $callback)
    {
        $fields = $this->table->fields;
        $conf = $this->conf;
        $rst = [];
        for ($i=0;$i<count($fields);$i++) {
            $fdn = $fields[$i];
            $rst[$fdn] = $callback($fdn, $conf);
        }
        if (!empty($rst)) {
            $this->setConf($rst);
        }
        return $this;
    }



    /**
     * 解析方法
     */
    //name,title,desc
    protected function parseMeta()
    {
        return $this->eachField("meta", function($fdn, $conf) {
            $meta = $conf["meta"][$fdn];
            return [
                "name" => $fdn,
                "title" => $meta[0],
                "desc" => $meta[1]
            ];
        });
    }

    //type,isNumber,number,isJson,json,isTime,time
    protected function parseType()
    {
        return $this->eachField("type", function($fdn, $conf) {
            $rst = [];
            $ci = $conf["creation"][$fdn];
            $rst["type"] = strtolower(explode(" ", $ci)[0]);
            $numbers = isset($conf["numbers"]) ? $conf["numbers"] : [];
            $jsons = isset($conf["jsons"]) ? $conf["jsons"] : [];
            $times = isset($conf["times"]) ? $conf["times"] : [];
            $rst["isNumber"] = isset($numbers[$fdn]);
            if ($rst["isNumber"]) {
                $rst["number"] = $numbers[$fdn];
            }
            $rst["isJson"] = isset($jsons[$fdn]);
            if ($rst["isJson"]==true) {
                $ji = $jsons[$fdn];
                if (is_notempty_str($ji)) {
                    $rst["json"] = [
                        "type" => $ji,
                        "default" => $ji=="indexed" ? j2a("[]") : j2a("{}")
                    ];
                } else {
                    $rst["json"] = $ji;
                }
            }
            $rst["isTime"] = isset($times[$fdn]);
            if ($rst["isTime"]) {
                $ti = $times[$fdn];
                if (!isset($ti["type"]) && !in_array(strtolower($ti["type"]), ["date","datetime","daterange","datetimerange"])) {
                    $ti["type"] = "datetime";
                } else {
                    $ti["type"] = strtolower($ti["type"]);
                }
                if (strpos($ti["type"],"range")!==false) {
                    $ti["isRange"] = true;
                }
                if (isset($ti["isRange"]) && !$rst["isJson"]) {
                    $rst["isJson"] = true;
                    $rst["json"] = [
                        "type" => "indexed",
                        "default" => isset($ti["default"]) ? $ti["default"] : j2a("[]")
                    ];
                } else if (!isset($ti["isRange"]) && isset($ti["default"])) {
                    $rst["default"] = $ti["default"];
                }
                $rst["time"] = $ti;

            }
            return $rst;
        });
    }

    //width,showInTable
    protected function parseView()
    {
        return $this->eachField("view", function($fdn, $conf) {
            $meta = $conf["meta"][$fdn];
            $hide = isset($conf["hideintable"]) ? $conf["hideintable"] : [];
            return [
                "showInTable" => !in_array($fdn, $hide),
                "width" => isset($meta[2]) ? $meta[2]*1 : 1
            ];
        });
    }

    //filterable,sortable,sort,searchable
    protected function parseQuery()
    {
        return $this->eachField("query", function($fdn, $conf) {
            $filter = isset($conf["filter"]) ? $conf["filter"] : [];
            $sort = isset($conf["sort"]) ? $conf["sort"] : [];
            $search = isset($conf["search"]) ? $conf["search"] : [];
            $rst = [
                "filterable" => in_array($fdn, $filter) || $this->getConf("$fdn/isTime"),
                "sortable" => in_array($fdn, $sort) || $this->getConf("$fdn/isTime") || $this->getConf("$fdn/isNumber"),
                "searchable" => in_array($fdn, $search)
            ];
            if ($rst["sortable"]) {
                $rst["sort"] = "";
            }
            return $rst;
        });
    }

    //isId,isPk,isEnable
    protected function parseSpecial()
    {
        return $this->eachField("special", function($fdn, $conf) {
            $ci = $conf["creation"][$fdn];
            $rst = [
                "isId" => strpos($ci, "AUTOINCREMENT")!==false,
                "isPk" => strpos($ci, "PRIMARY KEY")!==false,
                "isEnable" => $fdn=="enable"
            ];

            if ($rst["isId"] || $rst["isEnable"]) {
                $rst = arr_extend($rst, [
                    "sortable" => true,
                    "sort" => "",
                    "filterable" => true
                ]);
            }

            return $rst;
        });
    }

    //showInForm,isSelector,selector,isFile,file,isSwitch,isGenerator,required,validate
    protected function parseForm()
    {
        return $this->eachField("form", function($fdn, $conf) {
            $rst = [];
            $isid = $this->getConf("$fdn/isId");
            $json = $this->getConf("$fdn/json");
            $isen = $this->getConf("$fdn/isEnable");
            $ci = $conf["creation"][$fdn];
            $hide = isset($conf["hideinform"]) ? $conf["hideinform"] : [];
            $gens = isset($conf["generators"]) ? $conf["generators"] : [];
            $sels = isset($conf["selectors"]) ? $conf["selectors"] : [];
            $fils = isset($conf["files"]) ? $conf["files"] : [];
            $swts = isset($conf["switchs"]) ? $conf["switchs"] : [];
            $vlis = isset($conf["validators"]) ? $conf["validators"] : [];
            $rst["showInForm"] = !in_array($fdn, $hide) && /*!in_array($fdn, $gens)*/!isset($gens[$fdn]) && !$isid;
            if (isset($fils[$fdn])) {
                $rst["isFile"] = true;
                $rst["file"] = $fils[$fdn];
                if (!isset($rst["file"]["multiple"]) || $rst["file"]["multiple"]==false) {
                    $rst["file"]["inputFilesMaxLength"] = 1;
                    $rst["file"]["multiple"] = false;
                } else {
                    $rst["file"]["multiple"] = true;
                    if (isset($rst["file"]["inputFilesMaxLength"]) && $rst["file"]["inputFilesMaxLength"] == 1) {
                        $rst["file"]["inputFilesMaxLength"] = 0;
                    }
                    $rst["isJson"] = true;
                    $rst["json"] = [
                        "type" => "indexed",
                        "default" => []
                    ];
                }
                /*if (!empty($json) && $json["type"]=="indexed") {
                    $rst["file"]["multiple"] = true;
                    if (isset($rst["file"]["inputFilesMaxLength"]) && $rst["file"]["inputFilesMaxLength"]==1) {
                        $rst["file"]["inputFilesMaxLength"] = 0;
                    }
                } else {
                    $rst["file"]["multiple"] = false;
                    $rst["file"]["inputFilesMaxLength"] = 1;
                }*/
                //默认app参数
                if (!isset($rst["file"]["app"]) || $rst["file"]["app"]=="") {
                    $dx = $this->db->xpath;
                    if (substr($dx, 0, 4)=="app/") {
                        $rst["file"]["app"] = explode("/",$dx)[1];
                    }
                }
                //默认附件文件上传路径：[app/appname/]assets/files/db/dbname/tablename/fieldname
                $topre = "db/".$this->db->name."/".$this->table->name."/".$fdn;
                if (!isset($rst["file"]["uploadTo"]) || $rst["file"]["uploadTo"]=="") {
                    $rst["file"]["uploadTo"] = $topre;
                } else {
                    $uto = $rst["file"]["uploadTo"];
                    if (strpos($uto,"__assets_files__/")===false) {
                        $rst["file"]["uploadTo"] = $topre."/".trim($uto,"/");
                    } else {
                        //指定 uploadTo = __assets_files__/foo/bar 则 uploadTo = foo/bar
                        $rst["file"]["uploadTo"] = trim(str_replace("__assets_files__/", "", $uto), "/");
                    }
                }
            } else {
                $rst["isFile"] = false;
            }
            if (isset($sels[$fdn])) {
                $rst["isSelector"] = true;
                $rst["selector"] = $sels[$fdn];
                if (!isset($rst["selector"]["values"])) {
                    $rst["selector"]["values"] = [];
                }
                $rst["selector"]["useApi"] = isset($rst["selector"]["source"]) && isset($rst["selector"]["source"]["api"]);
                $rst["selector"]["useTable"] = isset($rst["selector"]["source"]) && isset($rst["selector"]["source"]["table"]);
            } else if (!empty($json) && $json["type"]=="indexed") {
                $rst["isSelector"] = true;
                $rst["selector"] = [
                    "multiple" => true,
                    "filterable" => true,
                    "clearable" => true,
                    "allow-create" => true,
                    "values" => []
                ];
            } else {
                $rst["isSelector"] = false;
            }
            if ($rst["isSelector"]) {
                $rst["filterable"] = true;
            }
            $rst["isSwitch"] = $isen || in_array($fdn, $swts);
            $rst["isGenerator"] = isset($gens[$fdn]);   //in_array($fdn, $gens);
            if ($rst["isGenerator"]) {
                $rst["generator"] = empty($gens[$fdn]) ? $fdn : $gens[$fdn];
            }
            $rst["required"] = strpos($ci, "NOT NULL")!==false && !in_array($fdn, $gens);
            $rst["validate"] = isset($vlis[$fdn]) ? $vlis[$fdn] : false;
            
            return $rst;
        });
    }

    //formType,inputer
    protected function parseInputer()
    {
        return $this->eachField("inputer", function($fdn, $conf) {
            return $this->formui->createInputer($fdn, $conf);
        });
    }

    //default
    protected function parseDefault()
    {
        return $this->eachField("default", function($fdn, $conf) {
            $dft = $this->getConf("$fdn/default");
            $ci = $conf["creation"][$fdn];
            $isJson = $this->getConf("$fdn/isJson");
            $isSelector = $this->getConf("$fdn/isSelector");
            $isTime = $this->getConf("$fdn/isTime");
            if ($this->getConf("$fdn/isJson")==true) {
                $dft = $this->getConf("$fdn/json/default");
            }
            if (empty($dft) && strpos($ci, "DEFAULT ")!==false) {
                if (strpos($ci, "DEFAULT '")!==false) {
                    $dft = explode("DEFAULT ", $ci)[1];
                    $dft = trim($dft, "'");
                    $dft = is_json($dft) ? j2a($dft) : $dft;
                } else {
                    $dft = explode("DEFAULT ", $ci)[1];
                    if (is_numeric($dft)) {
                        $dft = $dft*1;
                    } else {
                        $dft = is_json($dft) ? j2a($dft) : null;
                    }
                }
            }
            if (empty($dft) && $isSelector && !empty($this->getConf("$fdn/selector/values"))) {
                $vals = $this->getConf("$fdn/selector/values");
                $dft = $vals[0]["value"];
            }
            //处理 time 默认初始值
            if ($isTime) {

            }
            return [
                "default" => $dft
            ];
        });
    }

    //virtual
    protected function parseVirtual()
    {
        if (isset($this->conf["virtual"])) {
            $conf = $this->conf["virtual"];
            $fds = $conf["fields"];
            $fd = $conf["field"];
            //$this->table->config["virtualFields"] = array_map(function($fi) {
            //    return "_".$fi;
            //}, $fds);
            $this->table->config["virtualFields"] = $fds;

            for ($i=0;$i<count($fds);$i++) {
                $fi = $fds[$i];
                $fdi = $fd[$fi];
                $val = $fdi["value"];
                unset($fdi["value"]);
                $fdi = arr_extend($fdi, [
                    //"name" => "_".$fi,
                    "name" => $fi,
                    "isVirtual" => true,
                    "isJson" => false,
                    "isTime" => false,
                    "isSelector" => false,
                    "isFile" => false,
                    "isSwitch" => false,
                    "isGenerator" => false,
                    "isPk" => false,
                    "isId" => false,
                    "isEnable" => false,
                    "filterable" => false,
                    "sortable" => false,
                    "searchable" => false,
                    "virtual" => $val,
                    "type" => "varchar",
                    "formType" => "string",
                    "showInTable" => false,
                    "showInForm" => false
                ]);
                if (!isset($fdi["width"]) || !is_numeric($fdi["width"])) {
                    $fdi["width"] = 8;
                } else {
                    $fdi["width"] = $fdi["width"]*1;
                }
                //$this->table->config["field"]["_".$fi] = $fdi;
                $this->table->config["field"][$fi] = $fdi;
            }
        } else {
            $this->table->config["virtualFields"] = [];
        }
    }

    //sum
    protected function parseSum()
    {
        return $this->eachField("sum", function($fdn, $conf) {
            $sum = isset($conf["sum"]) ? $conf["sum"] : [];
            return [
                "isSum" => in_array($fdn, $sum)
            ];
            /*$sort = isset($conf["sort"]) ? $conf["sort"] : [];
            $search = isset($conf["search"]) ? $conf["search"] : [];
            $rst = [
                "filterable" => in_array($fdn, $filter) || $this->getConf("$fdn/isTime"),
                "sortable" => in_array($fdn, $sort) || $this->getConf("$fdn/isTime") || $this->getConf("$fdn/isNumber"),
                "searchable" => in_array($fdn, $search)
            ];
            if ($rst["sortable"]) {
                $rst["sort"] = "";
            }
            return $rst;*/
        });
    }

    //mode
    protected function parseMode()
    {
        $this->table->config["mode"] = [];
        $full = [
            "name" => "full",
            "isClassic" => true,
            "title" => "完整显示数据表模式",
            "fields" => $this->conf["fields"]
        ];
        if (isset($this->conf["mode"])) {
            $conf = $this->conf["mode"];
            if (isset($conf["default"])) {
                $dft = $conf["default"];
                unset($conf["default"]);
            } else {
                $dft = $full;
                $full = null;
            }
        } else {
            $conf = null;
            $dft = $full;
            $full = null;
        }

        $dft["name"] = "default";
        if (!isset($dft["isClassic"])) $dft["isClassic"] = false;
        $this->table->config["mode"][] = $dft;
        if (!empty($conf)) {
            foreach ($conf as $mn => $md) {
                if (!isset($md["isClassic"])) {
                    $md["isClassic"] = false;
                }
                $this->table->config["mode"][] = arr_extend($md, [
                    "name" => $mn
                ]);
            }
        }
        if (!empty($full)) {
            $this->table->config["mode"][] = $full;
        }
    }


}