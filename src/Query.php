<?php
/**
 * query.class.php - Database query class
 *
 * @package Double
 */
namespace Double;

use SqlFormatter;
use PDO;

class Query {
    /**
     * The database handler to use when performing queries
     *
     * @var PDO
     */
    private $dbh;

    /**
     * The statement handle for the query
     *
     * @var PDOStatement
     */
    private $sth;

    /**
     * Information about any errors the query caused
     *
     * @var array
     */
    private $error_info;

    /**
     * Whether or not the query failed
     *
     * @var boolean
     */
    public $failed = false;

    /**
     * The type of query being performed
     *
     * @var string
     */
    public $type = "";

    /**
     * The generated SQL statement
     *
     * @var string
     */
    public $sql = "";

    /**
     * The table to perform the query on
     *
     * @var string
     */
    public $table = "";

    /**
     * The columns to return from a SELECT query
     *
     * @var array
     */
    public $columns = [];

    /**
     * The clause to perform the query with (WHERE)
     *
     * @var string
     */
    public $clause = "";

    /**
     * The parameters to send to the dbh->execute() function
     *
     * @var array
     */
    public $clause_parameters = [];

    /**
     * How to order the results
     *
     * @var array
     */
    public $order_by = [];

    /**
     * How to limit the results
     *
     * @var array
     */
    public $limit = [];

    /**
     * Holds information to JOIN tables together for results
     *
     * @var array
     */
    public $joins = [];

    /**
     * Holds information to UPDATE rows with
     *
     * @var array
     */
    public $sets = [];

    /**
     * Holds information to INSERT rows with
     *
     * @var array
     */
    public $values = [];

    /**
     * The driver of the database that is being used
     *
     * @var string
     */
    private $driver;

    /**
     * Defines what to query against (for searching)
     *
     * @var string
     */
    public $against = "";

    /**
     * The table prefix
     *
     * @var string
     */
    private $_prefix = "";

    /**
     * Defines the ability to make queries DELAYED
     *
     * @var boolean
     */
    private $_delayed = false;

    /**
     * Array of SELECT queries to append to the current query
     *
     * @var array
     */
    private $_sub_selects = [];

    /**
     * Constructs the query, defining the type of query to perform
     *
     * @param string $type
     * @return Double\Query
     */
    public function __construct($type, Connection $connection = null) {
        $this->type = strtoupper($type);

        if ($connection == null) $this->dbh = Connection::$dbh;
        else {
            $this->driver = $connection->get_driver();
            $this->dbh = $connection->get();
        }

        return $this;
    }

    /**
     * Returns the table prefix to use
     *
     * @return string
     */
    private function prefix() { return $this->_prefix; }

    /**
     * Defines the table prefix to use
     *
     * @param string $prefix
     * @return Double\Query
     */
    public function set_prefix($prefix = "") {
        $this->_prefix = $prefix;
        return $this;
    }

    /**
     * For verbatim queries, just defines the SQL and execute parameters
     *
     * @param string $sql
     * @param array $parameters
     * @return Double\Query
     */
    public function sql($sql, array $parameters = []) {
        $this->sql = $sql;
        $this->clause_parameters = $parameters;
        return $this;
    }

    /**
     * Defines the table to insert data into
     *
     * @param string $table
     * @return Double\Query
     */
    public function into($table) {
        $this->table = $table;
        return $this;
    }

    /**
     * Defines the table to select data from
     *
     * @param string $table
     * @return Double\Query
     */
    public function from($table) {
        $this->table = $table;
        return $this;
    }

    /**
     * Defines the table to perform queries on
     *
     * @param string $table
     * @return Double\Query
     */
    public function table($table) {
        $this->table = $table;
        return $this;
    }

    /**
     * Defines the columns to select
     *
     * @param array $columns
     * @return Double\Query
     */
    public function columns(array $columns = []) {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Defines the WHERE clause
     *
     * @param string $clause
     * @param array $parameters
     * @return Double\Query
     */
    public function where($clause = "", array $parameters = []) {
        $this->clause = $clause;
        $this->clause_parameters = array_merge($parameters, $this->clause_parameters);
        return $this;
    }

    /**
     * Defines the information to order the results by
     *
     * @param string $column
     * @param string $direction
     * @return Double\Query
     */
    public function order_by($column, $direction) {
        $this->order_by = [$column, $direction];
        return $this;
    }

    /**
     * Defines the information to limit the results with
     *
     * @param int $start
     * @param int $amount
     * @return Double\Query
     */
    public function limit($start, $amount = null) {
        if ($amount == null) $this->limit = $start;
        else $this->limit = [$start, $amount];
        return $this;
    }

    /**
     * Defines the information to JOIN tables together
     *
     * @param string $type
     * @param string $table
     * @param string $on
     * @return Double\Query
     */
    public function join($type, $table, $on) {
        $this->joins[] = [$type, $table, $on];
        return $this;
    }

    /**
     * Returns the built query
     *
     * @return string
     */
    public function get($format = false, $values = false) {
        switch ($this->driver) {
            case "mysql":
            default:
                $info = [];
                $query = new MySQLQueryBuilder($this);
                $info['query'] = $query->get();
                if ($values) $info['values'] = $query->values;
                if ($format) $info['query'] = SqlFormatter::format($info['query'], false);

                return $values ? $info : $info['query'];
        }
    }

    /**
     * Returns the execute parameters
     *
     * @return array
     */
    public function get_parameters() {
        return $this->clause_parameters;
    }

    /**
     * Sets information to UPDATE records with
     *
     * @param string $column
     * @param mixed $value
     * @return Double\Query
     */
    public function set($column, $value) {
        $generated_key = sprintf(":%u", mt_rand());
        if (is_array($value)) {
            $this->sets[] = [$column, $value[0]];
        } else {
            $this->sets[] = [$column, $generated_key];
            $this->clause_parameters[$generated_key] = $value;
        }
        return $this;
    }

    /**
     * Sets information to INSERT records with
     *
     * @param array $values
     * @return Double\Query
     */
    public function values(array $values = []) {
        $record_values = [];
        foreach ($values as $value) {
            if (is_array($value) !== false) {
                $record_values[] = $value[0];
            } else {
                $generated_key = sprintf(":%u", mt_rand());
                $record_values[] = $generated_key;
                $this->clause_parameters[$generated_key] = $value;
            }
        }
        $this->values[] = $record_values;
        return $this;
    }

    /**
     * Defines a match for searching
     *
     * @param  array $columns
     * @return Double\Query
     */
    public function where_match(array $columns = []) {
        $this->clause = implode(",", $columns);
        return $this;
    }

    /**
     * Sets the query up for searching
     *
     * @param  string  $text
     * @param  boolean $unl
     * @return Double\Query
     */
    public function against($text, $unl = true) {
        if ($unl) {
            $this->against = ":against IN NATURAL LANGUAGE MODE";
        } else $this->against = ":against";

        $this->clause_parameters[":against"] = $text;
        return $this;
    }

    /**
     * Executes the query to the database
     *
     * @return Double\Query
     */
    public function execute() {
        $sql = (new MySQLQueryBuilder($this))->get();

        if (!$this->dbh) {
            $this->failed = true;
            $this->fail(["message" => "No connection to the database.", "code" => 0]);
            return $this;
        }

        $sth = $this->dbh->prepare($sql);

        if (!$sth) {
            $this->failed = true;
            $error = $this->dbh->errorInfo();
            $this->fail(["message" => $error[2], "code" => $error[0]]);
            return $this;
        } else {
            $sth->execute($this->clause_parameters);
            $this->sth = $sth;
            return $this;
        }
    }

    /**
     * Defines information about the query failure
     *
     * @param type $info
     */
    private function fail($info) {
        $this->error_info = $info;
    }

    /**
     * Returns the value of the failure status
     *
     * @return boolean
     */
    public function failed() {
        return $this->failed;
    }

    /**
     * Returns information about why the query failed
     *
     * @return array
     */
    public function failed_because() {
        return $this->error_info;
    }

    /**
     * Fetches the results from the database as an associative array
     *
     * @return array
     */
    public function fetch($type = PDO::FETCH_ASSOC) {
        return $this->sth->fetchAll($type);
    }

    /**
     * Returns the number of records that were found with the query
     *
     * @return int
     */
    public function count() {
        return $this->sth->rowCount();
    }

    /**
     * Returns the last inserted ID caused by the query
     *
     * @return id
     */
    public function id() {
        return $this->dbh->lastInsertId();
    }

    /**
     * Makes queries DELAYED
     *
     * @return \Double\Query
     */
    public function make_delayed() {
        $this->_delayed = true;
        return $this;
    }

    /**
     * Returns the DELAYED query status
     *
     * @return boolean
     */
    public function delayed() {
        return $this->_delayed;
    }

    /**
     * Adds a SELECT query to the query
     *
     * @param  Double\Query $query
     * @return \Double\Query
     */
    public function select(\Double\Query $query) {
        $this->_sub_selects[] = $query;
        return $this;
    }

    /**
     * Returns all of the added selects
     *
     * @return array
     */
    public function selects() {
        return $this->_sub_selects;
    }
}
