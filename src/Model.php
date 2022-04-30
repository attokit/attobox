<?php

/**
 * Attobox Framework / Model
 */

namespace Atto\Box;

use Atto\Box\Db;
use Atto\Box\db\Table;

class Model extends Table
{
    /**
     * model desc
     */
    public $name = "";      //model name
    public $desc = "";      //model desc
    public $key = "";       //model key

    /**
     * tables needed
     */
    protected $tablesNeeded = [      //set tables needed
        //"tree" => "base/tree",
        //"sets" => "base/settings"
    ];
    protected $tables = [];

    /**
     * construct
     */
    public function __construct()
    {
        $ntbs = $this->tablesNeeded;
        $this->useTable(...$ntbs);
    }

    /**
     * set tables needed
     * @param Array $tbs    tables needed
     * @return $this
     */
    public function useTable(...$tbs)
    {
        if (!empty($tbs)) {
            for ($i=0;$i<count($tbs);$i++) {
                $tb = $tbs[$i];
                $this->loadTable($tb);
            }
        }
        return $this;
    }

    /**
     * load table
     * @param String $tbkey     tb call path like  foo/bar/db/tbn
     * @return $this
     */
    protected function loadTable($tbkey)
    {
        $tbs = $this->tables;
        $tbk = str_replace("/", "_", $tbkey);
        if (!isset($tbs[$tbk]) || empty($tbs[$tbk])) {
            $this->tables[$tbk] = Table::load($tbkey);
            if (!in_array($tbkey, $this->tablesNeeded)) $this->tablesNeeded[] = $tbkey;
        }
        return $this;
    }

    /**
     * magic method __get
     * get table instance
     */
    public function __get($key)
    {
        $tbs = $this->tables;
        if (isset($tbs[$key])) return $tbs[$key];
        return null;
    }
}