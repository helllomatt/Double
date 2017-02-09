<?php

namespace Database;

class QueryTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->create_connection_mock();
    }

    public function create_connection_mock() {
        $mock = $this->getMockBuilder("\Database\Connection")
                ->setMethods(["get", "get_driver", "prepare", "execute", "errorInfo"])
                ->disableOriginalConstructor()
                ->getMock();

        $mock->expects($this->any())->method("get")->will($this->returnValue($mock));
        $mock->expects($this->any())->method("get_driver")->will($this->returnValue("mysql"));
        $mock->expects($this->any())->method("execute")->will($this->returnValue($mock));

        $this->storagemock = $mock;
    }

    public function testSql() {
        $expected = "SELECT * FROM table;";

        $query = (new Query("verbatim", $this->storagemock))
                    ->sql($expected);

        $this->assertEquals($expected, $query->get());
    }

    public function testInto() {
        $expected = "table";

        $query = (new Query("insert", $this->storagemock))
                    ->into($expected);

        $this->assertEquals($expected, $query->table);
    }

    public function testFrom() {
        $expected = "table";

        $query = (new Query("select", $this->storagemock))
                    ->from($expected);

        $this->assertEquals($expected, $query->table);
    }

    public function testTable() {
        $expected = "table";

        $query = (new Query("update", $this->storagemock))
                    ->table($expected);

        $this->assertEquals($expected, $query->table);
    }

    public function testColumns() {
        $expected = ["column1", "column2"];

        $query = (new Query("select", $this->storagemock))
                    ->columns($expected);

        $this->assertEquals($expected, $query->columns);
    }

    public function testWhere() {
        $expected_clause = "id = :id";
        $expected_parameters = [":id" => 1];

        $query = (new Query("select", $this->storagemock))
                    ->where($expected_clause, $expected_parameters);

        $this->assertEquals($expected_clause, $query->clause);
        $this->assertEquals($expected_parameters, $query->get_parameters());
    }

    public function testOrder_by() {
        $expected = ["id", "asc"];

        $query = (new Query("select", $this->storagemock))
                    ->order_by($expected[0], $expected[1]);

        $this->assertEquals($expected, $query->order_by);
    }

    public function testLimit() {
        $expected = [1, 5];

        $query = (new Query("select", $this->storagemock))
                    ->limit($expected[0], $expected[1]);

        $this->assertEquals($expected, $query->limit);
    }

    public function testJoin() {
        $expected = [["inner", "table", "table.a = table1.b"]];

        $query = (new Query("select", $this->storagemock))
                    ->join($expected[0][0], $expected[0][1], $expected[0][2]);

        $this->assertEquals($expected, $query->joins);
    }

    public function testGet() {
        $expected = "SELECT * FROM table;";

        $query = (new Query("select", $this->storagemock))
                    ->from("table");

        $this->assertEquals($expected, $query->get());
    }

    public function testGet_parameters() {
        $expected = [":id" => 1];

        $query = (new Query("select", $this->storagemock))
                    ->from("table")
                    ->where("id = :id", [":id" => 1]);

        $this->assertEquals($expected, $query->get_parameters());
    }

    public function testSet() {
        $expected = "column";

        $query = (new Query("update", $this->storagemock))
                    ->table("table")
                    ->set("column", "value");

        $this->assertEquals($expected, $query->sets[0][0]);
    }

    public function testValues() {
        $expected = ["r1value1", "r1value2", "r2value1", "r2value2"];

        $query = (new Query("insert", $this->storagemock))
                    ->into("table")
                    ->columns(["column1", "column2"])
                    ->values(["r1value1", "r1value2"])
                    ->values(["r2value1", "r2value2"]);

        $this->assertEquals($expected, array_values($query->get_parameters()));
    }

    public function testFailed_because() {
        $expected = ["message" => "You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'table' at line 1",
            "code" => "42000"];

        $this->storagemock->expects($this->any())->method("prepare")->will($this->returnValue(false));
        $this->storagemock->expects($this->any())->method("errorInfo")->will($this->returnValue([
            42000, 0, "You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'table' at line 1"
        ]));

        $query = (new Query("select", $this->storagemock))
                    ->from("table")
                    ->execute();

        $this->assertEquals($expected, $query->failed_because());
    }

    public function testSelect() {
        $expected = "SELECT * FROM table;";

        $query = (new Query("select", $this->storagemock))
                    ->from("table");

        $this->assertEquals($expected, $query->get());
    }

    public function testSelectColumns() {
        $expected = "SELECT column1 FROM table;";

        $query = (new Query("select", $this->storagemock))
                    ->columns(["column1"])
                    ->from("table");

        $this->assertEquals($expected, $query->get());
    }

    public function testSelectWhere() {
        $expected = "SELECT * FROM table WHERE id = :id;";

        $query = (new Query("select", $this->storagemock))
                    ->from("table")
                    ->where("id = :id", [":id" => 1]);

        $this->assertEquals($expected, $query->get());
    }

    public function testSelectOrderBy() {
        $expected = "SELECT * FROM table ORDER BY id ASC;";

        $query = (new Query("select", $this->storagemock))
                    ->from("table")
                    ->order_by("id", "asc");

        $this->assertEquals($expected, $query->get());
    }

    public function testSelectLimit() {
        $expected = "SELECT * FROM table LIMIT 5;";

        $query = (new Query("select", $this->storagemock))
                    ->from("table")
                    ->limit(5);

        $this->assertEquals($expected, $query->get());
    }

    public function testSelectJoin() {
        $expected = "SELECT * FROM table1 INNER JOIN table2 ON table1.column1 = table2.column1;";

        $query = (new Query("select", $this->storagemock))
                    ->from("table1")
                    ->join("inner", "table2", "table1.column1 = table2.column1");

        $this->assertEquals($expected, $query->get());
    }

    public function testInsertWithSelects() {
        $expected = ["query" => "INSERT INTO classes_rel (class_id, teacher_id, student_id) SELECT c.id as class_id, t.id as teacher_id, s.id as student_id FROM classes as c LEFT JOIN teachers AS t ON t.name = :t_name LEFT JOIN students AS s ON s.name = :s_name WHERE c.name = :c_name;",
            "values" => [
                ":c_name" => "Science",
                ":t_name" => "Jane Doe",
                ":s_name" => "John Smith"
            ]];

        $query = (new Query("insert"))
                    ->columns(["class_id", "teacher_id", "student_id"])
                    ->into("classes_rel", false)
                    ->select((new \Database\Query("select"))
                                ->columns(["c.id as class_id", "t.id as teacher_id", "s.id as student_id"])
                                ->from("classes as c", false)
                                ->join("left", "teachers as t", "t.name = :t_name")
                                ->join("left", "students as s", "s.name = :s_name")
                                ->where("c.name = :c_name", [":c_name" => "Science", ":t_name" => "Jane Doe", ":s_name" => "John Smith"]));

        $this->assertEquals($expected, $query->get(false, true));
    }
}
