<?php
/**
 * database.factory.php - Database factory class
 *
 * @package Database
 */

namespace Database;

class DB {
    /**
     * The database handle from the connection
     *
     * @var Database\Connection
     */
    private $dbh = null;

    /**
     * Table prefix for the feature
     *
     * @var string
     */
    private $_prefix = "";

    /**
     * Number of queries run
     *
     * @var integer
     */
    private $queries = 0;

    /**
     * Connects to the database
     *
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $name
     * @return Database\DB
     */
    public function connect($host, $username, $password, $name) {
        if (!$this->dbh) {
            $this->dbh = (new Connection($host, $username, $password, $name))
                            ->establish();
        }

        return $this;
    }

    /**
     * Disconnects from the database
     */
    public function disconnect() {
        $this->dbh = null;
    }

    /**
     * Gets the database connection
     *
     * @return Database\Connection
     */
    public function get_connection() {
        return $this->dbh;
    }

    /**
     * Creates a query class to be run on the database
     *
     * @param string $type
     * @return Database\Query
     */
    public function query($type = "") {
        $this->queries++;
        return (new Query($type, $this->dbh))->set_prefix($this->prefix());
    }

    /**
     * Defines the prefix to use for tables
     *
     * @param string $prefix
     * @return Database\DB
     */
    public function set_prefix($prefix) {
        if (substr($prefix, -1) !== "_") $this->_prefix = $prefix."_";
        else $this->_prefix = $prefix;
        return $this;
    }

    /**
     * Returns the prefix to use for tables
     *
     * @return string
     */
    private function prefix() { return $this->_prefix; }
}
