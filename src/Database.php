<?php

namespace carl;

class Database {
    private $ip;
    private $user;
    private $pwd;
    private $database;
    private $port;
    private $mysqli;

    function __construct($ip, $user, $pwd = null, $database = null, $port = 3306) {
        $this->ip = $ip;
        $this->user = $user;
        $this->pwd = $pwd;
        $this->database = $database;
        $this->port = $port;
    }

    private function GetConn() {
        if (!empty($this->mysqli)) {
            return $this->mysqli;
        }
        $this->mysqli = new \mysqli($this->ip, $this->user, $this->pwd, $this->database, $this->port);
        if ($this->mysqli->connect_errno) {
            throw new \Exception('Connect mysql faild', $this->mysqli->connect_error);
        }
        $this->mysqli->set_charset('utf8');
        return $this->mysqli;
    }

    function Query($sql) {
        $conn = $this->GetConn();
        $result = $conn->query($sql);
        if (!$result) {
            $msg = 'Database query failed: ' . $sql;
            throw new \Exception($msg);
        }
        return $result;
    }

    function Insert($sql) {
        $conn = $this->GetConn();
        $conn->Query($sql);
        return mysqli_insert_id($conn);
    }

    function Select($sql) {
        $result = $this->Query($sql);
        $ret = [];
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $ret[] = $row;
        }
        return $ret;
    }

    function Update($sql) {
        $this->Query($sql);
    }
}
