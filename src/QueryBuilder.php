<?php
/**
 * query-builder.class.php - Query building for MySQL
 *
 * @package Database
 */

namespace Database;

class MySQLQueryBuilder {
    /**
     * The injected Query object
     *
     * @var Database\Query
     */
    private $query;

    /**
     * The SQL statment all put together
     *
     * @var string
     */
    private $sql = "";

    /**
     * Holds values from parent query and sub queries
     *
     * @var array
     */
    public $values = [];

    /**
     * Constructs the class, sending the query on it's way to be built
     *
     * @param Database\Query $query
     * @return Database\MySQLQueryBuilder
     */
    public function __construct(Query $query) {
        $this->query = $query;
        $this->build($query->type);
        return $this;
    }

    /**
     * Returns the built SQL statement
     *
     * @return string
     */
    public function get() {
        return $this->sql;
    }

    /**
     * Based on the type of query requested, builds the query
     *
     * @param string $type
     * @return Database\MySQLQueryBuilder
     */
    private function build($type) {
        switch (strtolower($type)) {
            case "select":
                $this->build_select();
                break;
            case "insert":
                $this->build_insert();
                break;
            case "insert ignore":
                $this->build_insert(true);
                break;
            case "update":
                $this->build_update();
                break;
            case "delete":
                $this->build_delete();
                break;
            case "verbatim":
                $this->build_verbatim();
                break;
            case "count":
                $this->build_count();
                break;
        }
        return $this;
    }

    /**
     * Combines SQL JOIN information into a string
     *
     * @return string
     */
    private function combine_joins() {
        $joins = [];
        if (!empty($this->query->joins)) {
            foreach ($this->query->joins as $join) {
                $joins[] = sprintf("%s JOIN %s ON %s",
                                    strtoupper($join[0]),
                                    str_replace(" as", " AS", $join[1]),
                                    $join[2]);
            }
        }
        return implode(" ", $joins);
    }

    /**
     * Combines the SET information for update queries
     *
     * @return string
     */
    private function combine_sets() {
        $sets = [];
        if (!empty($this->query->sets)) {
            foreach ($this->query->sets as $set) {
                $sets[] = sprintf("%s = %s", $set[0], $set[1]);
            }
        }
        return implode(", ", $sets);
    }

    /**
     * Combines the VALUES information for insert queries
     *
     * @return string
     */
    private function combine_values() {
        $values = $this->values;
        foreach ($this->query->values as $value) {
            $values[] = sprintf("(%s)", implode(", ", $value));
        }
        return sprintf("VALUES %s", implode(", ", $values));
    }

    /**
     * Defines the LIMIT for the statement
     *
     * @return string
     */
    private function get_limit() {
        if (is_array($this->query->limit)) {
            return sprintf("LIMIT %u, %u", $this->query->limit[0], $this->query->limit[1]);
        } else {
            return sprintf("LIMIT %u", $this->query->limit);
        }
    }

    /**
     * Builds a SELECT query for MySQL
     *
     * @return Database\MySQLQueryBuilder
     */
    public function build_select() {
        $q = $this->query;

        $sql = array(
            empty($q->columns)  ? "*"   : implode(", ", $q->columns),
            $q->table == ""      ? ""    : sprintf("FROM %s", $q->table),
            $this->combine_joins(),
            $q->clause != "" && $q->against == ""     ?  sprintf("WHERE %s", $q->clause) : "",
            $q->against == ""   ? "" : sprintf("WHERE MATCH (%s) AGAINST (%s)", $q->clause, $q->against),
            empty($q->order_by) ? ""    : sprintf("ORDER BY %s %s",
                                                $q->order_by[0],
                                                strtoupper($q->order_by[1])),
            empty($q->limit)    ? ""    : $this->get_limit()
        );

        $this->values = array_merge($this->values, $this->query->clause_parameters);
        $this->sql = sprintf("SELECT %s;", implode(" ", array_filter($sql)));
        return $this;
    }

    /**
     * Builds a SELECT COUNT(*) query for MySQL
     *
     * @return Database\MySQLQueryBuilder
     */
    public function build_count() {
        $q = $this->query;

        $sql = array(
            "COUNT(".(empty($q->columns)  ? "*" : implode(", ", $q->columns)).")",
            $q->table == ""      ? ""    : sprintf("FROM %s", $q->table),
            $this->combine_joins(),
            $q->clause != "" && $q->against == ""     ?  sprintf("WHERE %s", $q->clause) : "",
            $q->against == ""   ? "" : sprintf("WHERE MATCH (%s) AGAINST (%s)", $q->clause, $q->against),
            empty($q->order_by) ? ""    : sprintf("ORDER BY %s %s",
                                                $q->order_by[0],
                                                strtoupper($q->order_by[1])),
            empty($q->limit)    ? ""    : $this->get_limit()
        );

        $this->values = array_merge($this->values, $this->query->clause_parameters);
        $this->sql = sprintf("SELECT %s;", implode(" ", array_filter($sql)));
        return $this;
    }

    /**
     * Builds an UPDATE query for MySQL
     *
     * @return Database\MySQLQueryBuilder
     */
    public function build_update() {
        $q = $this->query;

        $sql = array(
            $q->table == ""      ? ""    : $q->table,
            empty($q->sets)     ? ""    : sprintf("SET %s", $this->combine_sets()),
            $q->clause == ""     ? ""    : sprintf("WHERE %s", $q->clause),
            empty($q->limit)    ? ""    : $this->get_limit()
        );

        $this->values = array_merge($this->values, $this->query->clause_parameters);
        $this->sql = sprintf("UPDATE %s;", implode(" ", array_filter($sql)));
        return $this;
    }

    /**
     * Builds a DELETE query for MySQL
     *
     * @return Database\MySQLQueryBuilder
     */
    public function build_delete() {
        $q = $this->query;

        $sql = array(
            $q->table == ""      ? ""    : sprintf("FROM %s", $q->table),
            $q->clause == ""     ? ""    : sprintf("WHERE %s", $q->clause),
            empty($q->limit)    ? ""    : $this->get_limit()
        );

        $this->values = array_merge($this->values, $this->query->clause_parameters);
        $this->sql = sprintf("DELETE %s;", implode(" ", array_filter($sql)));
        return $this;
    }

    /**
     * Builds an INSERT query for MySQL
     *
     * @return Database\MySQLQueryBuilder
     */
    public function build_insert($ignore = false) {
        $q = $this->query;

        $sql = array(
            $q->table == ""      ? ""    : sprintf("INTO %s", $q->table),
            empty($q->columns)  ? "*"   : sprintf("(%s)", implode(", ", $q->columns)),
            !empty($q->selects()) ? $this->combine_selects() : $this->combine_values()
        );

        $this->values = array_merge($this->values, $this->query->clause_parameters);
        $this->sql = sprintf("INSERT%s%s %s;", ($q->delayed() ? " DELAYED" : ""), ($ignore ? " IGNORE" : ""), implode(" ", array_filter($sql)));
        return $this;
    }

    /**
     * Combines sub-query SELECTS to the current query
     *
     * @return srting
     */
    public function combine_selects() {
        $i = 0;
        $selects = [];
        foreach ($this->query->selects() as $select) {
            $selects[] = $select->get();
            if ($i < count($this->query->selects()) - 1) $selects[] = "UNION";
            $this->values = array_merge($this->values, $select->clause_parameters);
            $i++;
        }

        $this->query->clause_parameters = array_merge($this->query->clause_parameters, $this->values);
        return trim(implode(" ", $selects), ";");
    }

    /**
     * Takes what the developer gave, and "builds" it.
     *
     * @return Database\MySQLQueryBuilder
     */
    public function build_verbatim() {
        $this->sql = sprintf("%s;", rtrim($this->query->sql, ";"));
        return $this;
    }
}
