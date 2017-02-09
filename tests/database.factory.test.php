<?php

namespace Database;

class FactoryTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->object = (new DB)->connect(database_host, database_username, database_password, database_name);
    }

    public function testConnect() {
        $this->assertInstanceOf("\\Database\\Connection", $this->object->get_connection());
    }


    public function testDisconnect() {
        $this->object->disconnect();

        $this->assertNull($this->object->get_connection());
    }
}
