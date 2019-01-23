<?php
namespace Lib;

use PDO;
use PDOException;
use Closure;

class Pdox
{
    public $pdo = null;

    protected $select = '*';
    protected $from = null;
    protected $where = null;
    protected $limit = null;
    protected $join = null;
    protected $orderBy = null;
    protected $groupBy = null;
    protected $having = null;
    protected $grouped = false;
    protected $numRows = 0;
    protected $insertId = null;
    protected $error = null;
    protected $result = [];
    protected $prefix = null;
    protected $op = ['=', "!=", '<', '>', "<=", ">=", "<>"];
    protected $queryCount = 0;
    protected $query='';

    public function __construct(array $config)
    {
        $config["driver"] = (isset($config["driver"]) ? $config["driver"] : "mysql");
        $config["host"] = (isset($config["host"]) ? $config["host"] : "localhost");
        $config["charset"] = (isset($config["charset"]) ? $config["charset"] : "utf8");
        $config["collation"] = (isset($config["collation"]) ? $config["collation"] : "utf8_general_ci");
        $config["prefix"] = (isset($config["prefix"]) ? $config["prefix"] : '');
        $this->prefix = $config["prefix"];
        $config["port"] = (isset($config["port"]) ? $config["port"] : "3306");

        $dsn = '';

        if ($config["driver"] == "mysql" || $config["driver"] == '' || $config["driver"] == "pgsql") {
            $dsn = $config["driver"] . ":host=" . $config["host"] . ';'
                . (($config["port"]) != '' ? "port=" . $config["port"] . ';' : '')
                . "dbname=" . $config["database"];
        } elseif ($config["driver"] == "sqlite") {
            $dsn = "sqlite:" . $config["database"];
        } elseif ($config["driver"] == "oracle") {
            $dsn = "oci:dbname=" . $config["host"] . '/' . $config["database"];
        }

        $this->pdo = new PDO($dsn, $config["username"], $config["password"], [PDO::MYSQL_ATTR_LOCAL_INFILE => true, PDO::ATTR_TIMEOUT => 10,]);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("SET NAMES '" . $config["charset"] . "' COLLATE '" . $config["collation"] . "'");
        $this->pdo->exec("SET CHARACTER SET '" . $config["charset"] . "'");

        return $this->pdo;
    }

    public function error()
    {
        return $this->error;
    }

    public function table($table)
    {
        if (is_array($table)) {
            $f = '';
            foreach ($table as $key) {
                $f .= $this->prefix . $key . ", ";
            }

            $this->from = rtrim($f, ", ");
        } else {
            $this->from = $this->prefix . $table;
        }

        return $this;
    }

    public function select($fields)
    {
        $select = (is_array($fields) ? implode(", ", $fields) : $fields);
        $this->select = ($this->select == '*' ? $select : $this->select . ", " . $select);

        return $this;
    }

    public function selectDistinct($fields)
    {
        $select = (is_array($fields) ? implode(", ", $fields) : $fields);
        $this->select = " distinct " . ($this->select == '*' ? $select : $this->select . ", " . $select);

        return $this;
    }


    public function count($field, $name = null)
    {
        $func = "COUNT(" . $field . ')' . (!is_null($name) ? " AS " . $name : '');
        $this->select = ($this->select == '*' ? $func : $this->select . ", " . $func);

        return $this;
    }

    public function join($table, $field1 = null, $op = null, $field2 = null, $type = '')
    {
        $on = $field1;
        $table = $this->prefix . $table;

        if (!is_null($op)) {
            $on = (!in_array($op, $this->op) ? $this->prefix . $field1 . " = " . $this->prefix . $op : $this->prefix . $field1 . ' ' . $op . ' ' . $this->prefix . $field2);
        }

        if (is_null($this->join)) {
            $this->join = ' ' . $type . "JOIN" . ' ' . $table . " ON " . $on;
        } else {
            $this->join = $this->join . ' ' . $type . "JOIN" . ' ' . $table . " ON " . $on;
        }

        return $this;
    }

    public function innerJoin($table, $field1, $op = '', $field2 = '')
    {
        $this->join($table, $field1, $op, $field2, "INNER ");

        return $this;
    }

    public function leftJoin($table, $field1, $op = '', $field2 = '')
    {
        $this->join($table, $field1, $op, $field2, "LEFT ");

        return $this;
    }

    public function rightJoin($table, $field1, $op = '', $field2 = '')
    {
        $this->join($table, $field1, $op, $field2, "RIGHT ");

        return $this;
    }

    public function fullOuterJoin($table, $field1, $op = '', $field2 = '')
    {
        $this->join($table, $field1, $op, $field2, "FULL OUTER ");

        return $this;
    }

    public function leftOuterJoin($table, $field1, $op = '', $field2 = '')
    {
        $this->join($table, $field1, $op, $field2, "LEFT OUTER ");

        return $this;
    }

    public function rightOuterJoin($table, $field1, $op = '', $field2 = '')
    {
        $this->join($table, $field1, $op, $field2, "RIGHT OUTER ");

        return $this;
    }

    public function listWhere($where)
    {
        if (is_array($where)) {
            foreach ($where as $column => $data) {
                $_where = $data[0];
                $op = empty($data[1]) ? false : $data[1];
                $val = empty($data[2]) ? "" : $data[2];
                $type = empty($data[3]) ? "" : $data[3];
                $and_or = empty($data[4]) ? "AND" : $data[4];
                if ($op == 'like') {
                    $this->like($_where, $val, $type, $and_or);
                } else {
                    $this->where($_where, $op, $val, $type, $and_or);
                }
            }
            return $this;
        }
    }

    public function where($where, $op = null, $val = null, $type = '', $and_or = "AND")
    {
        if (is_array($where)) {
            $_where = [];

            foreach ($where as $column => $data) {
                $_where[] = $type . $column . '=' . $this->escape($data);
            }

            $where = implode(' ' . $and_or . ' ', $_where);
        } else {
            if (is_array($op)) {
                $x = explode('?', $where);
                $w = '';

                foreach ($x as $k => $v) {
                    if (!empty($v)) {
                        $w .= $type . $v . (isset($op[$k]) ? $this->escape($op[$k]) : '');
                    }
                }

                $where = $w;
            } elseif ($op == 'is null' || $op == false) {
                $where = $type . $where . ' ' . $op;
            } elseif (in_array($op, $this->op) ) {
                $where = $type . $where . ' ' . $op . ' ' . $this->escape($val);
            } else {
                $where = $type . $where . " = " . $this->escape($op);
            }
        }

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        if (is_null($this->where)) {
            $this->where = $where;
        } else {
            $this->where = $this->where . ' ' . $and_or . ' ' . $where;
        }

        return $this;
    }

    public function orWhere($where, $op = null, $val = null)
    {
        $this->where($where, $op, $val, '', "OR");

        return $this;
    }

    public function notWhere($where, $op = null, $val = null)
    {
        $this->where($where, $op, $val, "NOT", "AND");

        return $this;
    }

    public function orNotWhere($where, $op = null, $val = null)
    {
        $this->where($where, $op, $val, "NOT", "OR");

        return $this;
    }

    public function grouped(Closure $obj)
    {
        $this->grouped = true;
        call_user_func_array($obj, [$this]);
        $this->where .= ')';

        return $this;
    }

    public function in($field, array $keys, $type = '', $and_or = "AND")
    {
        if (is_array($keys)) {
            $_keys = [];

            foreach ($keys as $k => $v) {
                $_keys[] = (is_numeric($v) ? $v : $this->escape($v));
            }

            $keys = implode(", ", $_keys);


            $where = $field . ' ' . $type . " IN (" . $keys . ')';

            if ($this->grouped) {
                $where = '(' . $where;
                $this->grouped = false;
            }

            if (is_null($this->where)) {
                $this->where = $where;
            } else {
                $this->where = $this->where . ' ' . $and_or . ' ' . $where;
            }
        }

        return $this;
    }

    public function notIn($field, array $keys)
    {
        $this->in($field, $keys, "NOT", "AND");

        return $this;
    }

    public function orIn($field, array $keys)
    {
        $this->in($field, $keys, '', "OR");

        return $this;
    }

    public function orNotIn($field, array $keys)
    {
        $this->in($field, $keys, "NOT", "OR");

        return $this;
    }

    public function between($field, $value1, $value2, $type = '', $and_or = "AND")
    {

        $where = $field . ' ' . $type . " BETWEEN " . $this->escape($value1) . " AND " . $this->escape($value2);

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        if (is_null($this->where)) {
            $this->where = $where;
        } else {
            $this->where = $this->where . ' ' . $and_or . ' ' . $where;
        }

        return $this;
    }

    public function notBetween($field, $value1, $value2)
    {
        $this->between($field, $value1, $value2, "NOT", "AND");

        return $this;
    }

    public function orBetween($field, $value1, $value2)
    {
        $this->between($field, $value1, $value2, '', "OR");

        return $this;
    }

    public function orNotBetween($field, $value1, $value2)
    {
        $this->between($field, $value1, $value2, "NOT", "OR");

        return $this;
    }

    public function like($field, $data, $type = '', $and_or = "AND")
    {
        $like = $this->escape($data);

        $where = $field . ' ' . $type . " LIKE " . $like;

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        if (is_null($this->where)) {
            $this->where = $where;
        } else {
            $this->where = $this->where . ' ' . $and_or . ' ' . $where;
        }

        return $this;
    }

    public function orLike($field, $data)
    {
        $this->like($field, $data, '', "OR");

        return $this;
    }

    public function notLike($field, $data)
    {
        $this->like($field, $data, "NOT", "AND");

        return $this;
    }

    public function orNotLike($field, $data)
    {
        $this->like($field, $data, "NOT", "OR");

        return $this;
    }

    public function limit($limit, $limitEnd = null)
    {
        if (!is_null($limitEnd)) {
            $this->limit = $limit . ", " . $limitEnd;
        } else {
            $this->limit = $limit;
        }

        return $this;
    }

    public function orderBy($orderBy, $order_dir = null)
    {
        if (!is_null($order_dir)) {
            $this->orderBy = $orderBy . ' ' . strtoupper($order_dir);
        } else {
            if (stristr($orderBy, ' ') || $orderBy == "rand()") {
                $this->orderBy = $orderBy;
            } else {
                $this->orderBy = $orderBy . " ASC";
            }
        }

        return $this;
    }

    public function groupBy($groupBy)
    {
        if (is_array($groupBy)) {
            $this->groupBy = implode(", ", $groupBy);
        } else {
            $this->groupBy = $groupBy;
        }

        return $this;
    }

    public function having($field, $op = null, $val = null)
    {
        if (is_array($op)) {
            $x = explode('?', $field);
            $w = '';

            foreach ($x as $k => $v) {
                if (!empty($v)) {
                    $w .= $v . (isset($op[$k]) ? $this->escape($op[$k]) : '');
                }
            }

            $this->having = $w;
        } elseif (!in_array($op, $this->op)) {
            $this->having = $field . " > " . $this->escape($op);
        } else {
            $this->having = $field . ' ' . $op . ' ' . $this->escape($val);
        }

        return $this;
    }


    public function insert($data)
    {
        $columns = array_keys($data);
        $column = implode(',', $columns);
        $val = implode(", ", array_map([$this, "escape"], $data));

        $query = "INSERT INTO " . $this->from . " (" . $column . ") VALUES (" . $val . ')';

        $result = $this->exec($query);

        if ($result) {
            $this->insertId = $this->pdo->lastInsertId();
            return $this->insertId();
        } else {
            return false;
        }
    }

    public function update($data)
    {
        $query = "UPDATE " . $this->from . " SET ";
        $values = [];

        foreach ($data as $column => $val) {
            if (is_null($val)) {
                $values[] = $column . '= null ';
            } else {
                $values[] = $column . '=' . $this->escape($val);
            }

        }

        $query .= (is_array($data) ? implode(',', $values) : $data);

        if (!is_null($this->where)) {
            $query .= " WHERE " . $this->where;
        }

        if (!is_null($this->orderBy)) {
            $query .= " ORDER BY " . $this->orderBy;
        }

        if (!is_null($this->limit)) {
            $query .= " LIMIT " . $this->limit;
        }

        return $this->exec($query);
    }

    public function delete()
    {
        $query = "DELETE FROM " . $this->from;

        if (!is_null($this->where)) {
            $query .= " WHERE " . $this->where;
        }

        if (!is_null($this->orderBy)) {
            $query .= " ORDER BY " . $this->orderBy;
        }

        if (!is_null($this->limit)) {
            $query .= " LIMIT " . $this->limit;
        }

        if ($query == "DELETE FROM " . $this->from) {
            $query = "TRUNCATE TABLE " . $this->from;
        }

        return $this->exec($query);
    }


    public function getCount($field = '*')
    {
        $this->result = [];
        $count = $this->count($field, 'total')->make();
        $this->result = $this->pdo->query($count)->fetchColumn(0);
        return $this->result;
    }

    public function get($type = false)
    {

        $query = $this->make();
        $this->result = [];

        $sql = $this->pdo->query($query);
        if ($sql) {
            $this->numRows = $sql->rowCount();

            if ($this->numRows > 0) {
                $q = ($type == false) ? $sql->fetch(PDO::FETCH_OBJ) : $sql->fetch(PDO::FETCH_ASSOC);
                $this->result = $q;
            } else {
                $error = $this->pdo->errorInfo();
                $this->error = $error[2];
            }
            $this->queryCount++;
        } else {
            $error = $this->pdo->errorInfo();
            $this->error = $error[2];
            throw new \PDOException($this->error);
        }
        return $this->result;
    }

    public function getAll($type = false)
    {

        $query = $this->make();
        $this->query = $query;
        $this->result = [];

        $sql = $this->pdo->query($query);
        if ($sql) {
            $this->numRows = $sql->rowCount();

            if ($this->numRows > 0) {
                $q = [];
                while ($result = ($type == false) ? $sql->fetchAll(PDO::FETCH_OBJ) : $sql->fetchAll(PDO::FETCH_ASSOC)) {
                    $q[] = $result;
                }

                $this->result = $q[0];
            } else {
                $error = $this->pdo->errorInfo();
                $this->error = $error[2];
            }
            $this->queryCount++;
        } else {
            $error = $this->pdo->errorInfo();
            $this->error = $error[2];
            throw new \PDOException($this->error);
        }
        return $this->result;
    }

    protected function exec($query)
    {
        try {
            $this->result = $this->pdo->exec($query);
            $this->query = trim($query);
            $this->queryCount++;

            //Logger::write($query);
            $this->resetQuery();

            return $this->result;

        } catch (PDOException $e) {
            Logger::write($query);
            $error = $this->pdo->errorInfo();
            $this->error = $error[2];
            throw $e;
        }

    }


    protected function make()
    {
        $query = "SELECT " . $this->select . " FROM " . $this->from;

        if (!is_null($this->join)) {
            $query .= $this->join;
        }

        if (!is_null($this->where)) {
            $query .= " WHERE " . $this->where;
        }

        if (!is_null($this->groupBy)) {
            $query .= " GROUP BY " . $this->groupBy;
        }

        if (!is_null($this->having)) {
            $query .= " HAVING " . $this->having;
        }

        if (!is_null($this->orderBy)) {
            $query .= " ORDER BY " . $this->orderBy;
        }

        if (!is_null($this->limit)) {
            $query .= " LIMIT " . $this->limit;
        }

        $query = preg_replace("/\s\s+|\t\t+/", ' ', trim($query));

        //Logger::write($query);

        $this->resetQuery();

        return $query;
    }


    public function escape($data)
    {
        if (is_null($data)) {
            return null;
        }
        return @$this->pdo->quote(trim($data));
    }

    public function queryCount()
    {
        return $this->queryCount;
    }

    public function numRows()
    {
        return $this->numRows;
    }

    public function insertId()
    {
        return $this->insertId;
    }

    protected function resetQuery()
    {
        $this->select = '*';
        $this->from = null;
        $this->where = null;
        $this->limit = null;
        $this->orderBy = null;
        $this->groupBy = null;
        $this->join = null;
        $this->grouped = false;
        return;
    }

    function __destruct()
    {
        $this->pdo = null;
    }
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Starts the transaction
     * @return boolean, true on success or false on failure
     */
    public function begin()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     *  Execute Transaction
     * @return boolean, true on success or false on failure
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     *  Rollback of Transaction
     * @return boolean, true on success or false on failure
     */
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }
}
