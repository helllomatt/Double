<?php

namespace Double;

class ConnectionTest extends \PHPUnit_Framework_TestCase {

    public function testBadDatabaseHostConnection() {
        $this->expectException("\Exception");
        (new Connection())->establish();
    }

    public function testBadDatabaseUsername() {
        $this->expectException("\Exception");
        (new Connection(database_host))
                ->establish();
    }

    public function testBadDatabaseName() {
        $this->expectException("\Exception");
        (new Connection(database_host, database_username))
                ->establish();
    }

    public function testDatabaseConnection() {
        $connection = (new Connection(database_host, database_username, database_password, database_name))
                ->establish();

        $this->assertInstanceOf("\PDO", $connection->get());
    }

}
