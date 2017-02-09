<?php
/**
 * connection.class.php - Creates a connection to a database
 *
 * @package Double
 */
namespace Double;

use Exception;
use PDO;
use PDOException;

class Connection {

    /**
     * The type of database to connect to (in driver speak)
     *
     * @var string
     */
    public $driver = "mysql";

    /**
     * Any connection failure information
     *
     * @var array
     */
    private $failure = [];

    /**
     * The database handle
     *
     * @var \PDO
     */
    public static $dbh;

    /**
     * The database host address
     *
     * @var string
     */
    private $host;

    /**
     * The database login username
     *
     * @var string
     */
    private $username;

    /**
     * The database login password
     *
     * @var string
     */
    private $password;

    /**
     * The database name
     *
     * @var string
     */
    private $name;

    /**
     * Boolean to say if the connection failed
     *
     * @var boolean
     */
    public $failed = false;

    /**
     * Constructs the connection, defining critical connection information
     *
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $name
     * @return Double\Connection
     */
    public function __construct($host = "", $username = "", $password = "", $name = "") {
        $this->host     = $host;
        $this->username = $username;
        $this->password = $password;
        $this->name     = $name;
        return $this;
    }

    /**
     * Sets the driver, if it should be something other than MySQL
     *
     * @param string $driver
     * @return Double\Connection
     */
    public function set_driver($driver) {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Returns the type of drive that should be used
     *
     * @return string
     */
    public function get_driver() {
        return $this->driver;
    }

    /**
     * Validates the required connection parameters
     *
     * @throws Exception
     */
    private function validate_parameters() {
        if ($this->driver == null) throw new Exception("Invalid database driver defined.");
        if ($this->host == null) throw new Exception("Invalid database host defined.");
        if ($this->username == null) throw new Exception("Invalid database username defined.");
        if ($this->name == null) throw new Exception("Invalid database name defined.");
    }

    /**
     * Establishes the connection to the database using the given information
     *
     * @return Double\Connection
     */
    public function establish() {
        $this->validate_parameters();
        $dsn = sprintf("%s:host=%s;dbname=%s", $this->driver, $this->host, $this->name);

        try {
            static::$dbh = @new PDO($dsn, $this->username, $this->password);
            static::$dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $e) {
            $this->failed = true;
            $this->fail($this->define_friendly_message($e->getCode()), $e);
        }
        return $this;
    }

    /**
     * Defines more friendly error messages based on the PDO error codes
     *
     * @param int $code
     * @return string
     */
    private function define_friendly_message($code = 0) {
        switch ($code) {
            case 2002:
                return "Failed to make a connection to the database host server.";
            case 1045:
                return "Failed to make a connection to the database because of a bad username/password combo.";
            case 1049:
                return "Failed to connect to the database, because the database doesn't exist.";
            default: return "";
        }
    }

    /**
     * Defines the failure information
     *
     * @param string $message
     * @param PDOException $exception
     * @return Double\Connection
     */
    public function fail($message, \PDOException $exception) {
        $this->failure = [
            "message"   => $message,
            "exception" => [
                "message"   => $exception->getMessage(),
                "code"      => $exception->getCode()
            ]
        ];
        return $this;
    }

    /**
     * Returns the value of the connection failure status
     *
     * @return boolean
     */
    public function failed() {
        return $this->failed;
    }

    /**
     * Returns the failure information
     *
     * @return array
     */
    public function failed_because() {
        return $this->failure;
    }

    /**
     * Returns the database handle
     *
     * @return \PDO
     */
    public function get() {
        return static::$dbh;
    }

    /**
     * Starts a database transaction
     *
     * @return Double\Connection
     */
    public function start_transaction() {
        static::$dbh->beginTransaction();
        return $this;
    }

    /**
     * Commits the query to the database
     *
     * @return Double\Connection
     */
    public function commit() {
        static::$dbh->commit();
        return $this;
    }

    /**
     * Rolls back any changes to the database
     *
     * @return Double\Connection
     */
    public function roll_back() {
        static::$dbh->rollBack();
        return $this;
    }
}
