<?php

/**
 * 虚拟表/报表 基类
 */

namespace Atto\Box\db;

use Atto\Box\Request;
use Atto\Box\Response;
use Atto\Box\Db;
use Atto\Box\db\Table;
use Atto\Box\Uac;

class VTable /*extends Table*/
{
    //db instance
    public $db = null;

    //curd instance
    //protected $_curd = null;

    //tbn
    public $name = "";

    //call path like: dbn/tbn
    public $xpath = "";
    
    /**
     * table info config
     */
    public $config = null;

    //convertor 数据格式转器 实例
    //public $convertor = null;

    //查询器，build 查询参数
    //public $query = null;

    //虚拟表标记
    public $isVirtual = true;

    /**
     * construct
     */
    public function __construct()
    {
        
    }

    /**
     * 初始化 config 参数，来自 [dbpath]/config/[dbn].json
     * @param Bool $reload      强制重新生成 config 参数
     * @return $this
     */
    public function initConfig($reload = false)
    {
        //只进行一次 initConfig，或强制 reload
        if (!$reload && !is_null($this->config)) return $this;

        $tbn = $this->name;
        $conf = $this->db->conf("table/$tbn");
        $this->config = [];
        $ks = ["name","title","desc","fields","creation"];
        for ($i=0;$i<count($ks);$i++) {
            $ki = $ks[$i];
            if (isset($conf[$ki])) {
                $this->config[$ki] = $conf[$ki];
            }
        }
        $this->config["xpath"] = $this->xpath;
        $this->config["isVirtual"] = $this->isVirtual;
        $this->config["highlight"] = $conf["highlight"] ?? [];
        $this->config["field"] = [];

        /* 在输出 _export_ 时检查 directedit 权限，否则 Uac 加载数据表时会 反复 Uac::grant() 死循环
        $dire = isset($conf["directedit"]) ? $conf["directedit"] : false;
        $udire = Uac::grant("db-".$this->db->name."-".$this->name."-directedit");
        $this->config["directedit"] = $dire || $udire;
        */

        $configer = new Configer($this->db, $this);
        $configer->parse();
        $this->config["form"] = $configer->parseTableForm();
        //$this->config["detail"] = $configer->parseTableDetail();

        return $this;
    }

    /**
     * get table config
     * @param String $key       like: foo/bar  -->  db->conf(tbn/foo/bar)
     * @return Array table info
     */
    public function conf($key = null)
    {
        //$conf = $this->getConfig()->config;
        $conf = $this->initConfig()->config;
        if (empty($key) || !is_notempty_str($key)) return $conf;
        switch ($key) {
            //输出到前端的 table config 通常用于 vue 组件
            case "_export_" :
                $oc = [
                    "db" => [
                        "name" => $this->db->name,
                        "title" => $this->db->title,
                        "desc" => $this->db->desc,
                    ],
                    "table" => []
                ];
                $cks = explode(",", "name,xpath,title,desc,isVirtual,form,detail,fields,field,virtualFields,mode,highlight,default");
                foreach ($cks as $ck) {
                    if (is_def($conf, $ck)) {
                        $oc["table"][$ck] = $conf[$ck];
                    }
                }
                //$oc["api"] = Url::mk("/".DB_ROUTE."/api"."/".$this->db->name."/".$this->name."/")->full;
                $oc["table"]["idf"] = $this->ik();
                //输出当前用户是否有当前表的 directedit 权限，（通过前端表格组件直接编辑 的权限）
                $cf = $this->db->conf("table/".$this->name);
                $dire = isset($cf["directedit"]) ? $cf["directedit"] : false;
                $udire = Uac::grant("db-".$this->db->name."-".$this->name."-directedit");
                //$this->config["directedit"] = $dire || $udire;
                $oc["table"]["directedit"] = $dire || $udire;
                return $oc;
                break;

            default:
                $karr = explode("/", $key);
                if (in_array($karr[0], $conf["fields"]) || in_array($karr[0], $conf["virtualFields"])) {
                    array_unshift($karr, "field");
                }
                $key = implode("/", $karr);
                return arr_item($conf, $key);
                break;
        }
    }



    /**
     * 通过计算，创建虚拟表记录集
     * 子类必须实现并覆盖此方法
     * @param Array $params 计算参数，null 则尝试从 post 中读取
     * @return Array [ "params"=>[], "rs"=>[ ["field1"=>"val1", "field2"=>"val2", ...], [...], [...], ... ] ]
     */
    public function Calc($params=null)
    {
        //子类覆盖
        //...
        return [];
    }

    /**
     * Calc 方法参数解析器
     * 子类必须实现并覆盖此方法
     * @param Array $params 计算参数，null 则尝试从 post 中读取
     * @return Array 解析后的计算参数
     */
    public function paramsParser($params=null)
    {
        $params = empty($params) ? Request::input("json") : $params;
        if (empty($params)) return [];
        //虚拟表不分页
        if (isset($params["page"])) unset($params["page"]);
        $ks = ["sort","filter","sk"];
        $np = [
            "extra" => [],
            "calc" => []
        ];
        foreach ($params as $k => $v) {
            if (in_array($k, $ks)) {
                $np["extra"][$k] = $v;
            } else {
                $np["calc"][$k] = $v;
            }
        }
        return $np;
    }

    /**
     * 根据计算参数 sort,filter,sk 对计算结果进行 过滤/排序 然后输出
     * 响应前端 vtable 组件的 query 操作
     * @param Array $result 计算结果，rs 数据表记录集形式
     * @param Array $params 计算参数
     * @return Array 过滤/排序后的计算结果
     */
    public function extraProcessResult($result=[], $params=null)
    {
        $ps = $this->paramsParser($params);
        if (empty($ps) || empty($result)) return $result;
        $extra = $ps["extra"];
        $ks = ["sk","filter","sort"];
        //必须按顺序处理
        for ($i=0;$i<count($ks);$i++) {
            $ki = $ks[$i];
            $ei = $extra[$ki] ?? null;
            if (empty($ei)) continue;
            $mi = "extra".ucfirst($ki)."Result";
            if (method_exists($this, $mi)) {
                $result = $this->$mi($result, $ei);
            }
        }
        //处理完成后统一修改 id
        if (count($result)>0 && isset($result[0]["id"])) {
            for ($i=0;$i<count($result);$i++) {
                $result[$i]["id"] = $i+1;
            }
        }

        return $result;
    }

    //处理 sk 关键字搜索
    protected function extraSkResult($result=[], $sk=[])
    {
        if (empty($result) || empty($sk)) return $result;
        $fd = $this->conf("field");
        $fds = [];
        foreach ($fd as $fdn => $fdi) {
            if (!isset($fdi["searchable"]) || $fdi["searchable"]!=true) continue;
            $fds[] = $fdn;
        }
        if (empty($fds)) return $result;
        $nrst = [];
        for ($i=0;$i<count($result);$i++) {
            $rsi = $result[$i];
            $fi = false;
            for ($k=0;$k<count($fds);$k++) {
                for ($j=0;$j<count($sk);$j++) {
                    if (strpos($rsi[$fds[$k]], $sk[$j])!==false) {
                        $fi = true;
                        break;
                    }
                }
                if ($fi==true) break;
            }
            if ($fi==true) {
                $nrst[] = $rsi;
            }
        }
        return $nrst;
    }

    //处理 filter 筛选显示
    protected function extraFilterResult($result=[], $filter=[])
    {
        if (empty($result) || empty($filter)) return $result;
        $fds = [];
        foreach ($filter as $fdn => $fti) {
            if (!isset($fti["logic"]) || $fti["logic"]=='' || !isset($fti["value"]) || empty($fti["value"])) continue;
            $fds[$fdn] = $fti;
        }
        if (empty($fds)) return $result;
        $nrst = [];
        for ($i=0;$i<count($result);$i++) {
            $rsi = $result[$i];
            $fi = true;
            foreach ($fds as $fdn => $fti) {
                $lgi = $fti["logic"];
                $vai = $fti["value"];
                $fii = false;
                switch ($lgi) {
                    case "~":
                        $fii = is_notempty_str($vai) && is_notempty_str($rsi[$fdn]) && strpos($rsi[$fdn], $vai)!==false;
                        break;
                    case "!~":
                        $fii = is_notempty_str($vai) && is_notempty_str($rsi[$fdn]) && strpos($rsi[$fdn], $vai)===false;
                        break;
                    case "!":
                        $fii = $rsi[$fdn] != $vai;
                        break;

                    default:
                        eval("\$fii = \$rsi[\$fdn]".$lgi."\$vai;");
                        break;
                }
                $fi = $fi && $fii;
                if ($fi==false) break;
            }
            if ($fi==true) {
                $nrst[] = $rsi;
            }
        }
        return $nrst;
    }

    //处理 sort 排序
    protected function extraSortResult($result=[], $sort=[])
    {
        if (empty($result) || empty($sort)) return $result;
        /** 
         * !!! 注意：目前只支持对一个字段排序 
         */
        $sfd = null;
        $stp = '';
        foreach ($sort as $fdn => $sti) {
            if (!is_notempty_str($sti) || !in_array(strtolower($sti), ["asc","desc"])) {
                continue;
            } else {
                $stp = strtolower($sti);
                $sfd = $fdn;
                break;
            }
        }
        if (empty($sfd) || $stp=='') return $result;

        usort($result, function ($a, $b) use ($sfd, $stp) {
            if ($a[$sfd] == $b[$sfd]) return 0;
            if ($a[$sfd] < $b[$sfd]) return $stp=='asc' ? -1 : 1;
            return $stp=='asc' ? 1 : -1;
        });

        return $result;

    }



    /**
     * 虚拟表数据 持久化
     * 保存到 [dbpath]/vtable/[dbname]/[tablename].json
     */
    //获取虚拟数据保存的 json 文件的 物理路径，不存在则创建
    protected function getSaveJsonFile() 
    {
        $cfg = $this->db->dsn->config;
        $vtdir = str_replace("db/config/", "db/", $cfg["path"]);
        $vtdir = path_find($vtdir, ["checkDir"=>true]);
        if (!is_dir($vtdir)) {
            //创建 [dbpath]/vtable 路径

        }
        $vtdir = path_fix($vtdir);
        $dbn = $this->db->name;
        $tbn = $this->name;
        $vddir = $vtdir.DS.$dbn;
        if (!is_dir($vddir)) {
            //创建 虚拟表所在数据库 的 路径
            mkdir($vddir, 0777);
        }
        $sjf = $vddir.DS.$tbn.".json";
        if (!file_exists($sjf)) {
            $sjfh = fopen($sjf, 'w+');
            fclose($sjfh);
        }
        return $sjf;
    }
    //读取 虚拟表数据
    protected function getSaveJsonData()
    {
        $sjf = $this->getSaveJsonFile();
        $vd = file_get_contents($sjf);
        if (empty($vd)) {
            $vd = [];
        } else {
            $vd = j2a($vd);
        }
        return $vd;
    }
    //保存 虚拟表数据
    public function saveVtRs($rs)
    {
        $sjf = $this->getSaveJsonFile();
        if (is_array($rs) && is_indexed($rs)) {
            $vd = a2j($rs);
        } else {
            $vd = "[]";
        }
        file_put_contents($sjf, $vd);
        return $this;
    }
    //读取 虚拟表数据
    public function getVtRs()
    {
        $vd = $this->getSaveJsonData();
        return $vd;
    }



    /**
     * 将虚拟表计算结果，输出为 csv
     */
    public function exportCsv($rs = [])
    {
        if (!is_array($rs) || !is_indexed($rs)) return "";
        $cfg = $this->config;
        $fds = $cfg["fields"];
        $vfds = $cfg["virtualFields"];
        if (!empty($vfds)) $fds = array_merge($fds, $vfds);
        $fdc = $cfg["field"];
        $csv = [];
        //生成表头行
        $fdtit = array_map(function($fdi) use ($fdc) {
            return $fdc[$fdi]["title"];
        }, $fds);
        $csv[] = implode(",",$fdtit);

        //循环输出各行记录
        for ($i=0;$i<count($rs);$i++) {
            $ric = $rs[$i];
            $csvi = [];
            for ($j=0;$j<count($fds);$j++) {
                $fdi = $fds[$j];
                $fdci = $fdc[$fdi];
                $fdvi = $ric[$fdi];
                //处理特殊字段类型
                if ($fdci["isTime"]==true && is_numeric($fdvi)) {
                    //时间日期
                    $ft = $fdci["time"];
                    if ($ft["type"]=="date") {
                        $csvi[] = date("Y-m-d", $fdvi);
                    } else if ($ft["type"]=="datetime") {
                        $csvi[] = date("Y-m-d H:i:s", $fdvi);
                    }
                } else if ($fdci["isSwitch"]==true && is_numeric($fdvi)) {
                    //布尔类型
                    $csvi[] = $fdvi>0 ? "是" : "否";
                } else if ($fdci["isJson"]) {
                    //json 类型
                    if ($fdvi=="{}" || $fdvi=="[]") {
                        $csvi[] = "";
                    } else if (is_string($fdvi)) {
                        $csvi[] = str_replace(",",";",$fdvi);
                    } else {
                        $fdvi = a2j($fdvi);
                        $csvi[] = str_replace(",",";",$fdvi);
                    }
                } else {
                    //去除字段值中的 "," 这会影响 csv 解析
                    if (is_string($fdvi)) {
                        $fdvi = str_replace(",",";",$fdvi);
                    }
                    $csvi[] = $fdvi;
                }
            }
            $csv[] = implode(",", $csvi);
        }

        //连接各行数据
        $csv = implode("\r\n", $csv);

        //增加 BOM 头，避免 excel 打开乱码
        $csv = "\xEF\xBB\xBF".$csv;

        return $csv;
    }



    /**
     * fields methods
     */

    /**
     * get autoincrement field name
     * @param Bool $findAll     get all autoincrement fields
     * @return String field name
     */
    public function autoIncrementKey($findAll = false)
    {
        $cr = $this->conf("creation");
        //var_dump($cr);
        $fds = [];
        foreach ($cr as $fdn => $cri) {
            if (strpos($cri, "AUTOINCREMENT")!==false) {
                if (!$findAll) return $fdn;
                $fds[] = $fdn;
            }
        }
        return $findAll ? $fds : null;
    }
    public function ik($fdn = null)
    {
        if (!is_notempty_str($fdn)) return $this->autoIncrementKey();
        $ks = $this->autoIncrementKey(true);
        return in_array($fdn, $ks);
    }

    /**
     * get primary key field name
     * @return String field name
     */
    public function primaryKey()
    {
        $cr = $this->conf("creation");
        foreach ($cr as $fdn => $cri) {
            if (strpos($cri, "PRIMARY KEY")!==false) {
                return $fdn;
            }
        }
        return null;
    }
    public function pk()
    {
        return $this->primaryKey();
    }

    /**
     * 判断是否存在字段名
     * @param String $fdn
     * @return Bool
     */
    public function hasField($fdn)
    {
        $fds = $this->conf("fields");
        return in_array($fdn, $fds);
    }

    /**
     * 对 fields 字段列表执行循环操作
     * @param Callable $callback
     * @return $this
     */
    public function eachField($callback = null)
    {
        if (!is_callable($callback)) return [];
        $fds = $this->fields;;
        $rst = [];
        for ($i=0;$i<count($fds);$i++) {
            $fdi = $fds[$i];
            $rsti = $callback($this, $fdi);
            if ($rsti===false) break;
            if ($rsti===true) continue;
            $rst[$fdi] = $rsti;
        }
        return $rst;
    }



    /**
     * static tools
     */

    /**
     * 根据 xpath 加载数据表
     * @param String $xpath
     * @return Table
     */
    public static function load($xpath)
    {
        $xarr = explode("/", $xpath);
        $tbn = array_pop($xarr);
        $dsn = "vtable:".implode("/", $xarr);
        $db = Db::load($dsn);
        return $db->table($tbn);
    }

}