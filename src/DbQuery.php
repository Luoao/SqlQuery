<?php

namespace kernel;

class DbQuery {
    private $query = [];
    private $where_arr = null;
    private $join = [];
    protected $table = null;
    private $order_by = [];
    private $limit = [];
    private $mysql_db = null;

    function __construct($mysql_db = null) {
        $this->mysql_db = $mysql_db;
    }

    function Table($table) {
        $this->table = $table;
        return $this;
    }

    function Select($field = '*') {
        $this->query = ['type' => 'SELECT', 'field' => $field];
        return $this;
    }

    function Get() {
        $sql = $this->GetSql();
        return $this->mysql_db->Select($sql);
    }

    function First() {
        $this->Limit(0, 1);
        $sql = $this->GetSql();
        $res = $this->mysql_db->Select($sql);
        return current($res);
    }

    function Delete() {
        $this->query = ['type' => 'DELETE'];
        $sql = $this->GetSql();
        return $this->mysql_db->Update($sql);
    }

    function Update($data) {
        $this->query = ['type' => 'UPDATE', 'data' => $data];
        $sql = $this->GetSql();
        return $this->mysql_db->Update($sql);
    }

    function Insert($field, $data = null) {
        if (!isset($data)) {
            $data = $field;
            $field = null;
        }
        if (!is_callable($data) && !isset($field)) {
            if (is_array(current($data))) {
                $field = array_keys(current($data));
            } else {
                $field = array_keys($data);
                $data = [$data];
            }
        }
        $this->query = ['type' => 'INSERT', 'field' => $field, 'data' => $data];
        $sql = $this->GetSql();
        return $this->mysql_db->Insert($sql);
    }

    function LeftJoin($table, $field_a, $field_b = null) {
        return $this->AddJoinList('LEFT JOIN', $table, $field_a, $field_b);
    }

    function Join($table, $field_a, $field_b = null) {
        return $this->AddJoinList('JOIN', $table, $field_a, $field_b);
    }

    private function AddJoinList($type, $table, $field_a, $field_b = null) {
        $join = ['conn' => "{$type}", 'table' => $table];
        $join['sql'] = $this->WhereField($field_a, $field_b);
        $this->join[] = $join;
        return $this;
    }

    function OrderBy($field, $type = 'ASC') {
        $this->order_by[$field] = $type;
        return $this;
    }

    function Where($field, $conn = null, $value = null) {
        $this->where_arr[] = ['conn' => 'AND', 'sql' => $this->WhereField($field, $conn, $value)];
        return $this;
    }

    function WhereNull($field) {
        $this->where_arr[] = ['conn' => 'AND', 'sql' => "{$field} IS NULL"];
        return $this;
    }

    function WhereNotNull($field) {
        $this->where_arr[] = ['conn' => 'AND', 'sql' => "{$field} IS NOT NULL"];
        return $this;
    }

    function OrWhere($field, $conn = null, $value = null) {
        $this->where_arr[] = ['conn' => 'OR', 'sql' => $this->WhereField($field, $conn, $value)];
        return $this;
    }

    function Limit($start, $count = null) {
        if ($count === null) {
            $count = $start;
            $start = 0;
        }
        $this->limit = ['start' => $start, 'count' => $count];
        return $this;
    }

    function GetSql() {
        $table = $this->table;
        $sql = '';
        if (!empty($this->query)) {
            $sql .= $this->query['type'] . ' ';
            switch ($this->query['type']) {
                case 'SELECT':
                    if (is_array($this->query['field'])) {
                        $sql .= implode(',', $this->query['field']);
                    } else {
                        $sql .= $this->query['field'];
                    }
                    break;
                case 'UPDATE':
                    $sql .= $table . ' ';
                    $table = null;

                    $sql .= 'SET ';
                    $temp = '';
                    foreach ($this->query['data'] as $key => $val) {
                        if (strlen($temp) > 0) {
                            $temp .= ',';
                        }
                        if ($val === null) {
                            $val = 'NULL';
                        } else if (is_string($val)) {
                            $val = "'{$val}'";
                        }
                        $temp .= "{$key}={$val}";
                    }
                    $sql .= $temp;
                    break;
                case 'INSERT':
                    $sql .= ' INTO ';
                    $sql .= $table . ' ';
                    $table = null;

                    if (is_array($this->query['field'])) {
                        $sql .= '(' . implode(',', $this->query['field']) . ')';
                    }
                    if (is_callable($this->query['data'])) {
                        $sql .= $this->GetChildren($this->query['data']);
                    } else {
                        $sql .= 'VALUES ';
                        foreach ($this->query['data'] as $item) {
                            $temp = '';
                            foreach ($this->query['field'] as $key) {
                                if (strlen($temp) > 0) {
                                    $temp .= ',';
                                }
                                $temp .= is_string($item[$key]) ? "'{$item[$key]}'" : $item[$key];
                            }
                            if (!empty($temp)) {
                                $sql .= '(' . $temp . ')';
                            }
                        }
                    }
                    break;
            }
        }

        if ($table !== null) {
            if (!empty($sql)) {
                $sql .= ' FROM ';
            }
            $sql .= "{$table}";

            if (!empty($this->join)) {
                $join_sql = '';
                foreach ($this->join as $join) {
                    $join_sql .= " {$join['conn']} ";
                    $join_sql .= "{$join['table']} ON ";
                    $join_sql .= $join['sql'];
                }
                if (!empty($join_sql)) {
                    $sql .= $join_sql;
                }
            }
        }

        if (!empty($this->where_arr)) {
            $where_sql = '';
            foreach ($this->where_arr as $where) {
                if (!empty($where_sql)) {
                    $where_sql .= " {$where['conn']} ";
                }
                $where_sql .= $where['sql'];
            }
            if (!empty($where_sql)) {
                if (!empty($sql)) {
                    $sql .= ' WHERE';
                }
                $sql .= " {$where_sql} ";
            }
        }

        if (!empty($this->order_by)) {
            $order_by = '';
            foreach ($this->order_by as $field => $type) {
                if (!empty($order_by)) {
                    $order_by .= ',';
                }
                $order_by .= "{$field} {$type}";
            }
            if (!empty($order_by)) {
                if (!empty($sql)) {
                    $sql .= ' ORDER BY ';
                }
                $sql .= $order_by;
            }
        }

        if (!empty($this->limit)) {
            $sql .= " LIMIT {$this->limit['start']},{$this->limit['count']}";
        }
        return $sql;
    }

    private function GetChildren($fun) {
        $obj = $fun();
        return $obj->GetSql();
    }

    private function WhereField($field, $conn = null, $value = null) {
        if ($value == null) {
            if (is_array($conn)) {
                $value = $conn;
                $conn = 'IN';
            } else if ($conn !== null) {
                $value = $conn;
                $conn = '=';
            } else {
                $value = $field;
                $field = null;
                $conn = '=';
            }
        }
        if (is_callable($value)) {
            $value = '(' . $this->GetChildren($value) . ')';
        }
        else if ($value === null) {
            $value = 'NULL';
        } else if (is_string($value)) {
            $value = "'{$value}'";
        } else if (is_array($value)) {
            $value = '(' . implode(',', $value) . ')';
        }
        return "{$field} {$conn} {$value}";
    }
}

