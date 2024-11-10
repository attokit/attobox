<?php
/**
 * Curd 操作类 where 参数处理
 */

namespace Atto\Orm\curd;

use Atto\Orm\Orm;
use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Atto\Orm\Curd;
use Atto\Orm\curd\Parser;
use Atto\Orm\curd\JoinParser;

class WhereParser extends Parser 
{
    //解析得到的 where 参数
    public $where = [];


    /**
     * 初始化 curd 参数
     * !! 子类必须实现 !!
     * @return Parser $this
     */
    public function initParam()
    {
        return $this;
    }

    /**
     * 设置 curd 参数
     * !! 子类必须实现 !!
     * 构造 medoo 查询 where 参数
     * @param Mixed $param 要设置的 curd 参数
     * @return Parser $this
     */
    public function setParam($param=null)
    {
        if (!is_notempty_arr($param)) return $this;
        $ow = $this->where;
        $this->where = arr_extend($ow, $param);
        return $this;
    }

    /**
     * 重置 curd 参数 到初始状态
     * !! 子类必须实现 !!
     * @return Parser $this
     */
    public function resetParam()
    {
        $this->where = [];
        return $this;
    }

    /**
     * 执行 curd 操作前 返回处理后的 curd 参数
     * !! 子类必须实现 !!
     * @return Mixed curd 操作 medoo 参数，应符合 medoo 参数要求
     */
    public function getParam()
    {
        $where = $this->where;
        if (empty($where)) return [];
        $where = $this->withTbnPre($where);
        return $where;
    }



    /**
     * 构造 where 参数 方法
     */

    /**
     * 构造 where 参数
     * whereCol("field name", "~", ["foo","bar"])  -->  where([ "field name[~]"=>["foo","bar"] ]) 
     * @param String $key 列名称
     * @param Array $args 列参数
     * @return Parser $this
     */
    public function whereCol($key, ...$args)
    {
        if (!$this->model::hasField($key) || empty($args)) return $this;
        $where = [];
        if (count($args) == 1) {
            $where[$key] = $args[0];
        } else {
            $where[$key."[".$args[0]."]"] = $args[1];
        }
        return $this->setParam($where);
    }

    /**
     * 构造 where 参数
     * 关键字搜索
     * keyword("sk,sk,...")
     * @param String $sk 关键字，可有多个，逗号隔开
     * @return Parser $this
     */
    public function keyword($sk)
    {
        if (!is_notempty_str($sk)) return false;
        $ska = explode(",", trim(str_replace("，",",",$sk), ","));
        $sfds = $this->conf->searchFields;
        if (empty($sfds)) return false;
        $or = [];
        for ($i=0;$i<count($sfds);$i++) {
            $fdi = $sfds[$i];
            $or[$fdi."[~]"] = $ska;
        }
        return $this->setParam([
            "OR #search keywords" => $or
        ]);
    }

    /**
     * 构造 where 参数
     * limit 参数 
     * @param Array $limit 与 medoo limit 参数格式一致
     * @return Parser $this
     */
    public function limit($limit=[])
    {
        if (
            (is_numeric($limit) && $limit>0) ||
            (is_notempty_arr($limit) && is_indexed($limit))
        ) {
            $this->where["LIMIT"] = $limit;
        }
        return $this;
    }

    /**
     * 构造 where 参数
     * 分页加载
     * @param Int $ipage 要加载的页码，>=1
     * @param Int $pagesize 每页记录数，默认 100
     */
    public function page($ipage=1, $pagesize=100)
    {
        $ipage = $ipage<1 ? 1 : $ipage;
        if ($ipage==1) {
            return $this->limit($pagesize);
        }
        $ps = ($ipage-1)*$pagesize;
        return $this->limit([$ps, $pagesize]);
    }

    /**
     * 构造 where 参数
     * order 参数 
     * @param Array $order 与 medoo order 参数格式一致
     * @return Parser $this
     */
    public function order($order=[])
    {
        if (
            is_notempty_str($order) ||
            (is_notempty_arr($order) && is_associate($order))
        ) {
            $this->where["ORDER"] = $order;
        }
        return $this;
    }

    /**
     * 构造 where 参数
     * orderCol("col name", "DESC")  -->  order([ "tbn.col"=>"DESC" ])
     * @param String $key 列名称
     * @param Array $args 列参数
     * @return Parser $this
     */
    public function orderCol($key, ...$args)
    {
        if (!$this->model::hasField($key)) return $this;
        $order = [];
        if (empty($args)) {
            $order = $key;
        } else {
            $order[$key] = $args[0];
        }
        return $this->order($order);
    }

    /**
     * 构造 where 参数
     * match 参数 全文搜索
     * @param Array $match 与 medoo match 参数格式一致
     * @return Parser $this
     */
    public function match($match=[])
    {
        if (!empty($match)) {
            $this->where["MATCH"] = $match;
        }
        return $this;
    }



    /**
     * tools
     */

    /**
     * 处理 where 参数 array 
     * 递归处理
     * 
     * 如果 joinParser->use==true && joinParser->available==true 则：
     *      所有字段名前加上 table.
     * 如：
     *      where = [
     *          "foo" => ["bar","tom"],
     *          "age[>]" => 20,
     *          "OR #comment" => [
     *              "status[~]" => "fin",
     *              "isfin" => 1
     *          ],
     *          "LIMIT" => 20,
     *          "ORDER" => [
     *              "cola",
     *              "colb" => "ASC"
     *          ],
     *          "MATCH" => [
     *              "columns" => [
     *                  "colc", "cold"
     *              ],
     *              "keyword" => "foobar"
     *          ]
     *      ]
     * 修改为：
     *      where = [
     *          "table.foo" => ["bar","tom"],
     *          "table.age[>]" => 20,
     *          "OR #comment" => [
     *              "table.status[~]" => "fin",
     *              "table.isfin" => 1
     *          ],
     *          "LIMIT" => 20,
     *          "ORDER" => [
     *              "table.cola",
     *              "table.colb" => "ASC"
     *          ],
     *          "MATCH" => [
     *              "columns" => [
     *                  "table.colc", "table.cold"
     *              ],
     *              "keyword" => "foobar"
     *          ]
     *      ]
     *      
     * @param Array $where where 参数
     * @return Array 修改后的 where 参数
     */
    public function withTbnPre($where=[])
    {
        $jp = $this->curd->joinParser;
        if (!$jp instanceof JoinParser) return $where;
        if (!$jp->use || !$jp->available) return $where;

        $tbn = $this->conf->table;

        foreach ($where as $col => $colv) {
            //LIMIT
            if (strtoupper($col)=="LIMIT") continue;

            //MATCH
            if (strtoupper($col)=="MATCH") {
                if (isset($colv["columns"]) && is_indexed($colv["columns"])) {
                    $where["MATCH"]["columns"] = array_map([$this, 'preTbn'], $colv["columns"]);
                }
                continue;
            }

            //ORDER
            if (strtoupper($col)=="ORDER") {
                if (is_string($colv) && $colv!="") {
                    $where["ORDER"] = $this->preTbn($colv);
                } else if (is_notempty_arr($colv)) {
                    $where["ORDER"] = $this->withTbnPre($colv);
                }
                continue;
            }

            //AND/OR
            if (strpos($col, "AND")!==false || strpos($col, "OR")!==false) {
                $where[$col] = $this->withTbnPre($colv);
                continue;
            }

            if (is_int($col) && is_notempty_str($colv)) {
                $where[$col] = $this->preTbn($colv);
            } else if (is_string($col)) {
                $cola = $this->preTbn($col);
                $where[$cola] = $colv;
                unset($where[$col]);
            }
        }

        return $where;
    }

    /**
     * col      --> table.col
     * col[>]   --> table.col[>]
     * @param String $col 列名称
     * @return String 
     */
    protected function preTbn($col)
    {
        if (!is_notempty_str($col)) return $col;
        //已经是 table.col 直接返回
        if (strpos($col,".")!==false) return $col;
        $tbn = $this->conf->table;
        $fds = $this->conf->fields;
        if (strpos($col, "[")===false) {
            if (in_array($col, $fds)) {
                return "$tbn.$col";
            }
            return $col;
        } else {
            $coa = explode("[", $col);
            $coa[0] = $this->preTbn($coa[0]);
            return implode("[", $coa);
        }
    }


}
