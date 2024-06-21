<?php

/**
 * attokit / attobox / db / driver / Mysql
 */

namespace Atto\Box\db\driver;

use Atto\Box\Db;

class Mysql extends Db 
{




    /**
     * static
     */

    /**
     * db initialize
     * must override by db driver class
     * @param String $dsn   db connection string
     * @param Array $opt    db connection options
     * @return Db instance  or  null
     */
    public static function initialize($dsn, $opt = [])
    {
        if (strpos($dsn, ":") === false) {
            $dsn = "mysql:".$dsn;
        }
        $user = isset($opt["user"]) ? $opt["user"] : null;
        $pass = isset($opt["pass"]) ? $opt["pass"] : null;
        if (!is_notempty_str($user) || !is_notempty_str($pass)) trigger_error("db/mysqlnoauth", E_USER_ERROR);
        unset($opt["user"]);
        unset($opt["pass"]);
        try {
            $dbh = new \PDO($dsn, $user, $pass, $opt);
            $db = new Mysql();
            $db->dtp = "mysql";
            $db->dsn = $dsn;
            $db->pdo = $dbh;
            $db->opt = arr_extend($opt, [
                "user" => $user,
                "pass" => $pass
            ]);
            return $db;
        } catch (\PDOException $e) {
            trigger_error("db/nopdo::".$dsn, E_USER_ERROR);
        }
        
    }
}