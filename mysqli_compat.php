<?php

class MysqliCompatConnection
{
    private $conn;

    public function __construct($host, $user, $pass, $db)
    {
        if (!extension_loaded('mysqli') || !class_exists('mysqli')) {
            throw new Exception('MySQLi extension is not enabled in PHP.');
        }

        $this->conn = new mysqli($host, $user, $pass, $db);
        if ($this->conn->connect_error) {
            throw new Exception('Database connection failed: ' . $this->conn->connect_error);
        }

        $this->conn->set_charset('utf8');
    }

    public function prepare($sql)
    {
        return new MysqliCompatStatement($this->conn, $sql);
    }

    public function lastInsertId()
    {
        return $this->conn->insert_id;
    }

    public function set_charset($charset)
    {
        return $this->conn->set_charset($charset);
    }

    public function __get($name)
    {
        if ($name === 'insert_id') {
            return $this->conn->insert_id;
        }

        if ($name === 'connect_error') {
            return $this->conn->connect_error;
        }

        return null;
    }
}

class MysqliCompatStatement
{
    private $conn;
    private $sql;
    private $boundParams = [];
    private $stmt;
    private $result;

    public function __construct($conn, $sql)
    {
        $this->conn = $conn;
        $this->sql = $sql;
    }

    public function bindParam($name, &$value, $type = null)
    {
        $this->boundParams[$this->normalizeName($name)] = &$value;
        return true;
    }

    public function bindValue($name, $value, $type = null)
    {
        $this->boundParams[$this->normalizeName($name)] = $value;
        return true;
    }

    public function bind_param($types, &...$vars)
    {
        $this->stmt = $this->conn->prepare($this->sql);
        if (!$this->stmt) {
            return false;
        }

        return $this->stmt->bind_param($types, ...$vars);
    }

    public function execute($params = null)
    {
        if (!$this->stmt) {
            $executionParams = [];
            if (is_array($params)) {
                foreach ($params as $key => $value) {
                    $executionParams[$this->normalizeName($key)] = $value;
                }
            }

            $namedParams = [];
            if (preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $this->sql, $matches)) {
                $namedParams = $matches[1];
            }

            $parsedSql = preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '?', $this->sql);
            $this->stmt = $this->conn->prepare($parsedSql);
            if (!$this->stmt) {
                return false;
            }

            if (!empty($namedParams)) {
                $values = [];
                foreach ($namedParams as $name) {
                    if (array_key_exists($name, $executionParams)) {
                        $values[] = $executionParams[$name];
                    } elseif (array_key_exists($name, $this->boundParams)) {
                        $values[] = $this->boundParams[$name];
                    } else {
                        $values[] = null;
                    }
                }
                $this->bindDynamicValues($values);
            }
        }

        $ok = $this->stmt->execute();
        $this->result = $this->stmt->get_result();
        return $ok;
    }

    public function fetchAll($mode = null)
    {
        if (!$this->result) {
            return [];
        }

        $rows = [];
        while ($row = $this->result->fetch_object()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function rowCount()
    {
        if ($this->result) {
            return $this->result->num_rows;
        }

        if ($this->stmt) {
            return $this->stmt->affected_rows;
        }

        return 0;
    }

    public function get_result()
    {
        return $this->result;
    }

    private function normalizeName($name)
    {
        return ltrim((string)$name, ':');
    }

    private function bindDynamicValues(array $values)
    {
        if (empty($values)) {
            return;
        }

        $types = str_repeat('s', count($values));
        $refs = [];
        $refs[] = $types;
        foreach ($values as $idx => $value) {
            $values[$idx] = $value;
            $refs[] = &$values[$idx];
        }

        call_user_func_array([$this->stmt, 'bind_param'], $refs);
    }
}
