<?php

/**
 * Attobox Framework / Route
 * route/db
 */

namespace Atto\Box\route;

use Atto\Box\Route;
use Atto\Box\Request;
use Atto\Box\request\Url;
use Atto\Box\Response;
use Atto\Box\Db;
use Atto\Box\db\Curd;
use Atto\Box\db\Dsn;
use Atto\Box\db\Table;
use Atto\Box\db\VTable;
use Atto\Box\db\Manager as DbManager;
use Atto\Box\Record;
use Atto\Box\RecordSet;
use Atto\Box\Jwt;
use Atto\Box\Uac;

use Atto\Box\resource\Uploader;

class Dbm extends Base
{
    //route info
    public $intr = "Attobox数据库路由";   //路由说明，子类覆盖
    public $name = "Dbm";                //路由名称，子类覆盖
    public $appname = "";               //App路由所属App名称，子类覆盖
    public $key = "Atto/Box/route/Dbm";  //路由调用路径

    /**
     * db 相关参数
     */
    public $db = null;
    public $table = null;
    public $vtable = null;

    /**
     * api 相关参数
     */
    public $postData = null;
    public $jwtToken = null;
    public $debug = false;      //debug 标记

    /**
     * tools
     */

    /**
     * 从 URI 解析获取 db/table 实例
     * @param String $args     URI
     * @return 解析后剩余的 $args 数组  or  null
    */
    protected function parseInstance(...$args)
    {
        $pd = $this->parseDsn(...$args);
        if (is_null($pd)) return null;
        $dsn = $pd["dsn"];
        $args = $pd["args"];
        $this->db = Db::load($dsn);
        if (empty($args)) return [];
        if ($this->db->hasTable($args[0])) {
            $this->table = $this->db->table(array_shift($args));
            return $args;
        }
        return $args;
    }

    /**
     * 从 URI 解析 dbdriver & dsn
     * @param [String] $args
     * @return Array [ "dsn"=>Dsn|null, "args"=>[...] ]  or  null
     */
    protected function parseDsn(...$args)
    {
        if (empty($args)) return null;
        if (in_array(strtolower($args[0]), explode(",", strtolower(DB_TYPES)))) {
            $drv = strtolower(array_shift($args));
            if (empty($args)) return null;
        } else {
            $drv = DB_TYPE;
        }
        $dsn = null;
        $j = -1;
        for ($i=count($args)-1;$i>=0;$i--) {
            $qs = implode("/", array_slice($args, 0, $i+1));
            $dsn = "$drv:$qs";
            if (Dsn::exists($dsn)) {
                $j = $i;
                break;
            }
        }
        if ($j<0) return null;
        return [
            "dsn" => $dsn,
            "args" => array_slice($args, $j+1)
        ];
    }



    /**
     * 路由方法
     */

    /**
     * 默认路由方法
     */
    public function defaultMethod(...$args)
    {
        $args = $this->parseInstance(...$args);
        if (is_null($args) || is_null($this->db)) Response::code(404);
        if (empty($args)) {     // DbManager 数据库管理器
            $manager = new DbManager($this->db);
            if (is_null($this->table)) {
                $tbn = $this->db->tables[0];
            } else {
                $tbn = $this->table->name;
            }
            $manager->active($tbn);
            return $manager->exportPage();
        } else {
            $m = $args[0];
            if (method_exists($this, $m)) { //访问 protected 路由方法
                array_shift($args);
                return $this->$m(...$args);
            } else if (!is_null($this->table)) {
                $tb = $this->table->new();
                if (method_exists($tb, $m)) {
                    array_shift($args);
                    return $tb->$m(...$args);
                }
            }
        }

        Response::code(404);
        exit;
    }

    /**
     * install db from config/dbn.json
     * for dev
     */
    public function install(...$args)
    {
        if (empty($args)) Response::code(404);
        $pi = $this->parseInstance(...$args);
        if (!is_null($pi)) {
            //要 install 的数据库已存在
            //Response::code(500);
            Response::str("数据库 ".implode("/", $args)." 已经存在！");
        }
        //获取 db driver
        if (in_array(strtolower($args[0]), explode(",", strtolower(DB_TYPES)))) {
            $drv = strtolower(array_shift($args));
        } else {
            $drv = DB_TYPE;
        }
        if (empty($args)) Response::code(404);
        $dsn = $drv.":".implode("/",$args);
        //开始 install 数据库
        $dsno = Dsn::load($dsn);
        //Response::dump($dsno);
        $db = $dsno->driver::install($dsno);
        if ($db!==false) {
            //Response::dump($db);
            $url = Url::mk("/dbm"."/".$db->xpath."/conf")->full;
            Response::redirect($url);
        } else {
            Response::code(500);
        }
        exit;
    }

    /**
     * reinstall 重建数据库
     * for dev
     */
    public function reinstall(...$args) 
    {
        if (is_null($this->db) && is_null($this->table)) {
            //解析 db/table 实例
            $args = $this->parseInstance(...$args);
            if (is_null($args) || is_null($this->db)) Response::code(404);
        }

        //针对新创建的表
        if (is_null($this->table) || !$this->table instanceof Table) {
            if ($this->db->hasTable($args[0])) {
                //新创建的表，在 config 中存在，在 db 中不存在
                $rst["method"] = "createTable";
                $rst["table"] = $args[0];
                $rst["rs_old"] = [];
                $rst["rs_new"] = [];
                $rst["status"] = $this->db->recreateTable($rst["table"], false);
                Response::json($rst);
                exit;
            }
        }
        
        $withrs = !(!empty($args) && $args[0]=='nors');
        $rst = [
            "method" => "reinstall",
            "withrs" => $withrs,
            //"issqlite" => $this->db instanceof \Atto\Box\db\driver\Sqlite
        ];
        if (!is_null($this->table)) {
            $rst["method"] = "recreateTable";
            $rst["table"] = $this->table->name;
            if ($withrs) {
                $rst["rs_old"] = $this->table->all();
            }
            $rst["status"] = $this->db->recreateTable($rst["table"], $withrs);
            if ($withrs) {
                $rst["rs_new"] = $this->table->all();
            }
        } else {
            $rst["status"] = $this->db->reinstall($withrs);
        }
        Response::json($rst);
    }

    /**
     * 删除某个数据表
     * for dev
     */
    public function drop(...$args)
    {
        if (is_null($this->db) && is_null($this->table)) {
            //解析 db/table 实例
            $args = $this->parseInstance(...$args);
            if (is_null($args) || is_null($this->db) || is_null($this->table)) Response::code(404);
        }
        $tbn = $this->table->name;
        $this->db->dropTable($tbn);
        Response::json([
            "method" => "drop",
            "table" => $tbn,
            "status" => true
        ]);
    }

    public function showdsn(...$args)
    {
        if (is_null($this->db) && is_null($this->table)) {
            //解析 db/table 实例
            $args = $this->parseInstance(...$args);
            if (is_null($args) || is_null($this->db)) Response::code(404);
        }
        $dsn = $this->db->dsn;
        Response::json([
            "dsn" => (array)$dsn
        ]);
    }

    public function backup(...$args)
    {
        if (is_null($this->db) && is_null($this->table)) {
            //解析 db/table 实例
            $args = $this->parseInstance(...$args);
            if (is_null($args) || is_null($this->db)) Response::code(404);
        }
        $opt = Request::input("json");
        if (empty($opt)) $opt = [];
        if (!isset($opt["options"])) $opt["options"] = [];
        if (empty($args)) {
            //备份操作
            $backup = $this->db->backup($opt);
            if (is_bool($backup)) {
                Response::json([
                    "backup" => $backup,
                    "db" => $this->db->name
                ]);
            } else {
                Response::json($backup);
            }
        } else {
            //恢复，删除备份 等其他操作
            $action = array_shift($args);
            $opt["fullbackup"] = $args[0]=="fullbackup" || empty($args);
            $opt["options"]["action"] = $action;
            if (isset($opt["idx"])) {
                $opt["options"]["idx"] = $opt["idx"];
                unset($opt["idx"]);
            }
            /*switch ($action) {
                //删除备份
                case "del":
                    if ($opt["fullbackup"]==true) {
                        //删除某个完整备份
                        $opt["options"]["idx"] = $opt["idx"];
                        if (isset($opt["idx"])) unset($opt["idx"]);
                    }
                    break;
            }*/
            $backup = $this->db->backup($opt);
            if (is_bool($backup)) {
                Response::json([
                    "backup" => $backup
                ]);
            } else {
                Response::json($backup);
            }
        }
    }

    public function restore(...$args)
    {
        if (is_null($this->db) && is_null($this->table)) {
            //解析 db/table 实例
            $args = $this->parseInstance(...$args);
            if (is_null($args) || is_null($this->db)) Response::code(404);
        }
        $opt = Request::input("json");
        $restore = $this->db->restore($opt);
        Response::json([
            "restore" => $restore,
            "db" => $this->db->name
        ]);
    }

    /**
     * db api 入口
     * * 应在此方法实现权限控制 *
     * [host]/dbm/dbn/tbn/api/[apiname][/...]
     * @override
     * @param [String] $args
     * @return Mixed
     */
    public function api(...$args)
    {
        if (is_null($this->db) && is_null($this->table)) {
            //解析 db/table 实例
            $args = $this->parseInstance(...$args);
            if (is_null($args) || is_null($this->db)) Response::code(404);
            if (empty($args)) Response::code(404);
        }
        $apin = strtolower($args[0]);
        $api = "api".($this->table->isVirtual ? "Vt" : "").ucfirst($apin);
        //route 中找不到 api
        if (!method_exists($this, $api)) {
            if ($this->table->isVirtual==false) {
                //普通表，尝试到 table record 实例中查找 api
                $record = $this->table->new();
                if ($record instanceof Record) {
                    if (method_exists($record, $api)) {
                        $uacOprName = "api-".str_replace("/","-",$this->table->xpath)."-".$apin;
                        //var_dump($uacOprName);
                        //权限控制，在 UAC_CTRL==true 的情况下生效
                        if (Uac::requiredAndReady() && Uac::grant($uacOprName)!==true) {
                            trigger_error("uac/denied::".$uacOprName, E_USER_ERROR);
                        } else {
                            array_shift($args); //api method name
                            return call_user_func_array([$record, $api], $args);
                        }
                    } else {
                        Response::code(404);
                    }
                }
            } else {
                $api = "api".ucfirst($apin);
                //虚拟表，尝试到 vtable 实例中查找 api
                if (method_exists($this->table, $api)) {
                    $uacOprName = "api-".str_replace("/","-",$this->table->xpath)."-".$apin;
                    //权限控制，在 UAC_CTRL==true 的情况下生效
                    if (Uac::requiredAndReady() && Uac::grant($uacOprName)!==true) {
                        trigger_error("uac/denied::".$uacOprName, E_USER_ERROR);
                    } else {
                        array_shift($args); //api method name
                        return call_user_func_array([$this->table, $api], $args);
                    }
                } else {
                    Response::code(404);
                }
            }
        }

        //权限控制，在 UAC_CTRL==true 的情况下生效
        $oprn = $this->table->isVirtual ? "vt".$apin : $apin;
        if ($this->apiUacCtrl($oprn)===true) {
            //开始执行 api 操作
            array_shift($args); //api method
            $this->postData = Request::input("json");
            if (!empty($args) && $args[0]=="debug") {
                $this->debug = true;
                array_shift($args);
            }
            return $this->$api(...$args);
        }

        exit;
    }

    /**
     * protected 路由方法
     * get table full config
     * !!! 待废弃 由 config 方法代替 !!!
     * [host]/dbm/dbn/tbn/conf[/...]
     * @param [String] $args
     * @return void
     */
    protected function conf(...$args)
    {
        if (is_null($this->db)) Response::code(404);
        if (is_null($this->table)) {
            //$this->table = $this->db->table($this->db->tables[0]);
            $conf = $this->db->conf(implode("/", $args));
        } else {
            if (empty($args)) {
                $key = "_export_";
            } else {
                $key = implode("/", $args);
            }
            $conf = $this->table->conf($key);
        }

        /*$uac = Uac::start();
        $uid = session_get("uac_uid");
        var_dump($uid);
        var_dump($uac->isLogin);
        var_dump($uac->usrTable->xpath);
        var_dump($uac->usrId);
        var_dump($uac->usr->uid);*/

        //当前数据表不支持直接编辑记录，检查用户是否拥有权限直接编辑记录
        if (!isset($conf["table"]["directedit"]) || $conf["table"]["directedit"]!=true) {
            if (UAC_CTRL && Uac::isLogin() && Uac::grant("sys-directedit")) {
                $conf["table"]["directedit"] = true;
            }
        }
        //Response::dump($conf);
        Response::json($conf);
        //var_dump($conf);
        exit;
    }

    /**
     * 获取数据库 config
     * 由前端直接访问的接口
     */
    public function config(...$args)
    {
        if (Uac::required()) {
            //开启 UAC_CTRL 权限控制的情况下，要获取数据库 config 必须要登录
            $uac = Uac::start();
            if (!Uac::isLogin()) return [];
        }
        if (is_null($this->db)) return [];
        if (is_null($this->table)) {
            //$this->table = $this->db->table($this->db->tables[0]);
            $conf = $this->db->conf(implode("/", $args));
        } else {
            if (empty($args)) {
                $key = "_export_";
            } else {
                $key = implode("/", $args);
            }
            $conf = $this->table->conf($key);
        }
        //当前数据表不支持直接编辑记录，检查用户是否拥有权限直接编辑记录
        if (!isset($conf["table"]["directedit"]) || $conf["table"]["directedit"]!=true) {
            if (Uac::required() && Uac::grant("sys-directedit")) {
                $conf["table"]["directedit"] = true;
            }
        }
        return $conf;
        //Response::dump($conf);
        //Response::json($conf);
        //var_dump($conf);
        //exit;
    }

    /**
     * 手动编辑数据库 手动CURD
     * 需要 db-manual 权限
     * ! 注意：此方法将直接修改数据库，谨慎使用 !
     */
    public function manual(...$args)
    {
        if (is_null($this->db) && is_null($this->table)) {
            //解析 db/table 实例
            $args = $this->parseInstance(...$args);
            if (is_null($args) || is_null($this->db)) Response::code(404);
            if (empty($args)) Response::code(404);
        }
        //权限控制，在 UAC_CTRL==true 的情况下生效
        $opr = "sys-db-manual";
        if (Uac::required() && Uac::grant($opr)!==true) {
            trigger_error("uac/denied::$opr", E_USER_ERROR);
            exit;
        }
        
        $this->postData = Request::input("json");
        if (empty($this->postData)) {
            $page = path_find("box/page/manualdb.php");
            //var_dump($page);
            if (empty($page)) Response::code(404);
            Response::page($page, [
                "db" => $this->db,
                "table" => $this->table,
                "tables" => $this->db->tables,
                "xpath" => $this->table->xpath
            ]);
            exit;
        }
        $action = $this->postData["action"] ?? "update";
        $query = $this->postData["query"] ?? [];
        $data = $this->postData["data"] ?? [];
        if ((in_array($action, ["select","update","delete"]) && empty($query)) || (in_array($action, ["update","insert"]) && empty($data))) Response::code(404);
        
        $ik = $this->table->ik();

        if ($action=="insert") {    //C
            $data = $this->table->convertor->data($data)->convTo("db")->export();
            //var_dump($data);
            //var_dump($this->table->insert($data));
            //$id = $this->db->medoo()->id();  //新建记录的id
            //$rs = $this->table->where([$ik=>$id])->single();
            $rs = $this->table->C($data);
            if (empty($rs) || !$rs instanceof Record) {
                Response::json([
                    "success" => false,
                    "msg" => "insert record failed"
                ]);
            } else {
                Response::json([
                    "success" => true,
                    "result" => $rs->export("ctx", true)
                ]);
            }
            exit;
        } else if ($action=="select") {     //R
            $rs = $this->table->query->apply($query)->select();
            if (empty($rs) || !$rs instanceof RecordSet) {
                Response::json([
                    "success" => false,
                    "msg" => "target recordset empty"
                ]);
            } else {
                Response::json([
                    "success" => true,
                    "result" => $rs->export("ctx", true)
                ]);
            }
            exit;
        } else {
            $rs = $this->table->query->apply($query)->select();
            if (empty($rs) || !$rs instanceof RecordSet) {
                Response::json([
                    "success" => false,
                    "msg" => "target recordset empty"
                ]);
            } else {
                $rst = [];
                for ($i=0;$i<count($rs);$i++) {
                    $rsi = $rs[$i];
                    $wi = [$ik=>$rsi[$ik]];
                    if ($action=="update") {    //U
                        $data = $this->table->convertor->data($data)->convTo("db")->export();
                        $this->table->where($wi)->update($data);
                        $rsti = $this->table->where($wi)->single();
                        $rst[] = $rsti->export("ctx", true);
                    } else {    //D
                        $this->table->where($wi)->$action();
                        $rst[] = $this->db->medoo()->id();
                        
                    }
                }
                Response::json([
                    "success" => true,
                    "result" => $rst
                ]);
            }
            exit;
        }
    }


    /**
     * 空路由，自动跳转到 domain/index
     * @return void
     */
    public function emptyMethod()
    {
        Response::code(404);
    }



    /**
     * db apis
     */

    /**
     * curd Create api
     * <%创建记录%>
     * @return Mixed
     */
    protected function apiCreate(...$args)
    {
        if (empty($this->table)) trigger_error("db/curd/create::缺少必要参数,null", E_USER_ERROR);
        $data = $this->postData;
        $new = $this->table->C($data);
        Response::json($new->export());
        //Response::json($new->export());
    }

    /**
     * curd Update api
     * <%修改记录%>
     * @return Mixed
     */
    protected function apiUpdate(...$args)
    {
        $data = $this->postData["data"];
        $query = $this->postData["query"];

        if ($this->debug) {
            $record = $this->table->query->apply($query)->single();
            $ors = $record->export();
            $record->setField($data);
            $nrs = $record->export();
            Response::json([
                "oldData" => $ors,
                "newData" => $nrs
            ]);
            exit;
        }

        $record = $this->table->U($data, $query);
        if (empty($record)) Response::json($record);
        Response::json($record->export());
    }

    /**
     * curd Retrieve api
     * <%查询记录%>
     * @return Mixed
     */
    protected function apiRetrieve(...$args)
    {
        $query = $this->postData;
        if (isset($query["query"])) {
            $query = $query["query"];
        }
        $api = $query["api"] ?? null;
        if (!is_null($api)) {
            unset($query["api"]);
            $qapi = $api["api"] ?? null;
            $qpd = $api["data"] ?? [];
        }

        $export = $query["export"] ?? null;
        if (!is_null($export)) unset($query["export"]);
        $exportTo = $export["to"] ?? null;
        if (is_null($exportTo)) {
            $exportTo = empty($args) ? "db" : array_shift($args);
        }
        $exportVirtual = $export["virtual"] ?? true;
        $exportRelated = $export["related"] ?? false;

        //debug
        if ($this->debug) {
            $this->table->query->apply($query);
            $qresult = $this->table->curd()->info();
            $sql = $this->table->curd()->echosql();
            Response::json([
                "query" => $qresult,
                "sql" => $sql
            ]);
            exit;
        }

        if ($exportTo=="all") { //直接 select 全部数据
            $rs = $this->table->all();
            if (!empty($args)) {
                $action = array_shift($args);
                if ($action=="fieldsonly") {
                    $rsn = [];
                    for ($i=0;$i<count($rs);$i++) {
                        $rsn[] = $this->table->dft($rs[$i], true);
                    }
                    $rs = $rsn;
                }
            }
            Response::json([
                "query" => "all",
                "rs" => $rs
            ]);
        } else if (!is_null($api)) {
            //通过调用 api 执行查询
            if ($exportRelated) $exportTo = "show";
            $qm = "api".ucfirst($qapi);
            $tb = $this->table->new();
            if (method_exists($tb, $qm)) {
                $qpd["export"] = "recordSet";
                $rtn = $tb->$qm($qpd);
                if (!$rtn instanceof RecordSet) {
                    Response::json([
                        "query" => $query,
                        "rs" => []
                    ]);
                } else {
                    Response::json([
                        "query" => $query,
                        "export" => [
                            "to" => $exportTo,
                            "virtual" => $exportVirtual,
                            "related" => $exportRelated
                        ],
                        "rs" => $rtn->export($exportTo, $exportVirtual, $exportRelated)
                    ]);
                }
            } else {
                trigger_error("db/curd/retrieve/api::接口方法不存在,".$this->table->name.",".$qapi, E_USER_ERROR);
            }
        } else {
            //var_dump($query);
            $rs = $this->table->R($query);
            $rsset = $rs["rs"];
            if ($rsset instanceof RecordSet) {
                if ($exportTo=="csv") {
                    $rsctx = $rsset->exportCsv();
                } else {
                    if ($exportRelated) $exportTo = "show";
                    $rsctx = $rsset->export($exportTo, $exportVirtual, $exportRelated);
                }
            } else {
                $rsctx = $rsset;
            }
            $rs["rs"] = $rsctx;
            $rs["export"] = [
                "to" => $exportTo,
                "virtual" => $exportVirtual,
                "related" => $exportRelated
            ];
            Response::json($rs);
        }
    }

    /**
     * curd Delete api
     * <%删除记录%>
     * @return RecordID
     */
    protected function apiDelete(...$args)
    {
        $query = $this->postData;
        $idf = $this->table->ik();
        if (!isset($query["where"]) || !isset($query["where"][$idf])) {
            trigger_error("db/delete::缺少必要参数,".$this->table->xpath.",null", E_USER_ERROR);
        }
        $ids = $query["where"][$idf];

        //debug
        if ($this->debug) {
            $this->table->query->apply($query);
            $qresult = $this->table->curd()->info();
            $sql = $this->table->curd()->echosql();
            Response::json([
                "query" => $qresult,
                "sql" => $sql
            ]);
            exit;
        }

        $rst = $this->table->D($query); //$rst 是 PDOStatement 实例组成的数组，数组长度即为被删除记录数
        if (is_array($rst)) {
            $rowCount = 0;
            foreach ($rst as $rsti) {
                if ($rsti instanceof \PDOStatement) {
                    $rowCount += $rsti->rowCount();
                } else {
                    $rowCount += 1;
                }
            }
        } else if ($rst instanceof \PDOStatement) {
            $rowCount = $rst->rowCount();
        } else {
            $rowCount = is_array($ids) ? count($ids) : 1;
        }
        $rtn = [
            "rows" => $rowCount,
            "ids" => $ids
        ];
        Response::json($rtn);
    }

    /**
     * curd 输出到 select/cascader 组件 options
     * 指定 $arg==column 时，仅输出某列数据，去重
     * <%输出记录到下拉菜单%>
     * @return void
     */
    protected function apiValues(...$args)
    {
        $type = empty($args) ? 'select' : array_shift($args);
        $m = "get".ucfirst($type)."Options";
        if (!method_exists($this->table, $m)) Response::json([]);
        $query = $this->postData;

        if ($this->debug) {
            $this->table->query->apply($query);
            $rtn = [
                "query" => $query,
                "curd" => $this->table->curd()->info()
            ];
            Response::json($rtn);exit;
        }

        if ($type=="column") {
            if (empty($args)) Response::json([]);
            $field = array_shift($args);
            $values = $this->table->$m($query, $field);
        } else {
            $values = $this->table->$m($query, $type);
        }
        Response::json($values);
        exit;
    }

    /**
     * api 方法
     * <%导入 csv 文件%>
     * [host]/dbm/dbn/tbn/api/inport[/csv]
     */
    protected function apiInport(...$args)
    {
        $uploader = new Uploader();
        $files = $uploader->upload();
        Response::json($files);
    }

    /**
     * api 方法
     * <%检查记录是否存在%>
     * 检查当前表是否包含符合 query 条件记录
     * [host]/dbm/dbn/tbn/api/has
     */
    protected function apiHas(...$args)
    {
        $query = $this->postData;
        Response::headersSent();
        Response::json([
            "has" => $this->table->query->apply($query)->has()
        ]);
        exit;
    }

    /**
     * api 方法
     * <%虚拟表方法：计算获取虚拟表记录集%>
     * 通过 post 计算参数，计算并获取虚拟表记录集，通常用于计算表、报表生成
     * [host]/dbm/vtable/dbn/tbn/api/calc
     */
    protected function apiVtCalc(...$args)
    {
        $params = Request::input("json");
        if (empty($params)) {
            //trigger_error("custom::缺少计算条件，无法计算此虚拟表", E_USER_ERROR);
            $params = null;
        }
        if (!empty($args)) {
            $action = $args[0];
        } else {
            $action = "calc";
        }

        $rtn = [];
        $rs = [];
        switch ($action) {
            case "calc" :
            case "save" :
                $rs = $this->table->Calc($params);
                if ($action=="save") {
                    $this->table->saveVtRs($rs);
                }
                break;
            case "read" :
                $rs = $this->table->getVtRs();
                break;
            case "csv" :
                if (empty($params) || !isset($params["rs"])) {
                    $rs = $this->table->Calc($params);
                } else {
                    $rs = $params["rs"];
                }
                //将计算结果输出为 csv
                $rs = $this->table->exportCsv($rs);
                break;
        }
        if (is_indexed($rs) || $action=="csv") {
            $rtn["rs"] = $rs;
        } else {
            $rtn = $rs;
        }
        $rtn["query"] = $params;
        Response::json($rtn);
        exit;
    }


    /**
     * db 路由方法
     */

    /**
     * test
     */
    public function tst(...$args)
    {
        $rs = $this->table->whereEnable(1)->select();
        $rso = $rs->exportFields("show", "skuid","_isa","rpid");
        //return $rso;
        var_dump($rso);
        

        //return (array)$this->db->dsn;
    }
}