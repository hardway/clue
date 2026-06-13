<?php
    class Test_Sqlite extends PHPUnit_Framework_TestCase{
        protected static $db;
        protected static $table = 'clue_test';

        static function setUpBeforeClass(): void{
            self::$db = new \Clue\Database\Sqlite(['db' => ':memory:']);

            // 创建测试表
            self::$db->exec('
                CREATE TABLE IF NOT EXISTS ' . self::$table . ' (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    value TEXT,
                    num INTEGER
                )
            ');
        }

        static function tearDownAfterClass(): void{
            if(self::$db){
                self::$db->exec('DROP TABLE IF EXISTS ' . self::$table);
            }
        }

        // ==================== 基础方法 ====================

        function test_has_table(){
            $this->assertTrue(self::$db->has_table(self::$table));
            $this->assertFalse(self::$db->has_table('nonexistent_table'));
        }

        function test_insert_and_insert_id(){
            $id = self::$db->insert(self::$table, [
                'name' => 'foo',
                'value' => 'first entry',
                'num' => 10
            ]);
            $this->assertNotNull($id);
            $this->assertTrue($id > 0);
            $this->assertEquals(1, $id);
        }

        function test_insert_ignore(){
            $id = self::$db->insert_ignore(self::$table, [
                'name' => 'bar',
                'value' => 'second entry',
                'num' => 20
            ]);
            $this->assertNotNull($id);
            $this->assertTrue($id > 0);
        }

        function test_get_var(){
            $cnt = self::$db->get_var("SELECT COUNT(*) FROM " . self::$table);
            $this->assertEquals(2, $cnt);
        }

        function test_get_var_with_condition(){
            $sql = self::$db->format(
                "SELECT COUNT(*) FROM %t WHERE name=%s",
                self::$table, 'nonexistent'
            );
            $cnt = self::$db->get_var($sql);
            $this->assertEquals(0, $cnt);
        }

        function test_get_row(){
            $row = self::$db->get_row("SELECT * FROM " . self::$table . " WHERE id=1");
            $this->assertNotNull($row);
            $this->assertEquals('foo', $row->name);
        }

        function test_get_row_array(){
            $row = self::$db->get_row(
                "SELECT * FROM " . self::$table . " WHERE id=1",
                ARRAY_A
            );
            $this->assertNotNull($row);
            $this->assertEquals('foo', $row['name']);
        }

        function test_get_row_num(){
            $row = self::$db->get_row(
                "SELECT * FROM " . self::$table . " WHERE id=1",
                ARRAY_N
            );
            $this->assertNotNull($row);
            $this->assertEquals('foo', $row[1]);  // name 是第二列，索引 1
        }

        function test_get_results(){
            $rows = self::$db->get_results("SELECT * FROM " . self::$table . " ORDER BY id");
            $this->assertEquals(2, count($rows));
            $this->assertEquals('foo', $rows[0]->name);
            $this->assertEquals('bar', $rows[1]->name);
        }

        function test_get_results_array_a(){
            $rows = self::$db->get_results(
                "SELECT * FROM " . self::$table . " ORDER BY id",
                ARRAY_A
            );
            $this->assertEquals(2, count($rows));
            $this->assertEquals('foo', $rows[0]['name']);
        }

        function test_get_results_array_n(){
            $rows = self::$db->get_results(
                "SELECT * FROM " . self::$table . " ORDER BY id",
                ARRAY_N
            );
            $this->assertEquals(2, count($rows));
            // ARRAY_N: 每行是 [id, name, value, num]
            $this->assertEquals('foo', $rows[0][1]);  // 首行 name
            $this->assertEquals(20, $rows[1][3]);     // 次行 num
        }

        function test_get_col(){
            $names = self::$db->get_col("SELECT name FROM " . self::$table . " ORDER BY id");
            $this->assertEquals(2, count($names));
            $this->assertEquals('foo', $names[0]);
            $this->assertEquals('bar', $names[1]);
        }

        function test_update(){
            $changed = self::$db->update(self::$table, ['value' => 'updated'], "id=1");
            $this->assertEquals(1, $changed);

            $val = self::$db->get_var(
                "SELECT value FROM " . self::$table . " WHERE id=1"
            );
            $this->assertEquals('updated', $val);
        }

        function test_update_no_match(){
            $changed = self::$db->update(self::$table, ['value' => 'x'], "id=999");
            $this->assertEquals(0, $changed);
        }

        // ==================== quote / escape ====================

        function test_quote_string(){
            $this->assertEquals("'hello'", self::$db->quote('hello'));
        }

        function test_quote_with_apostrophe(){
            // SQLite3::escapeString 用双写单引号转义
            $this->assertEquals("'it''s cool'", self::$db->quote("it's cool"));
        }

        function test_quote_int(){
            $this->assertEquals("'42'", self::$db->quote(42));
        }

        // ==================== exec / format ====================

        function test_exec_format(){
            $sql = self::$db->format(
                "SELECT * FROM %t WHERE name=%s AND num=%d",
                self::$table, 'foo', 10
            );
            $this->assertContains('clue_test', $sql);
            $this->assertContains("'foo'", $sql);
            $this->assertContains('10', $sql);
        }

        function test_exec_returns_true(){
            $ret = self::$db->exec("SELECT 1");
            $this->assertTrue($ret);
        }

        // ==================== 错误处理 ====================

        function test_error_on_bad_sql(){
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('SQL ERROR');
            $this->suppressWarnings();
            try {
                self::$db->exec("SELECT FROM nonsense");
            } finally {
                $this->restoreWarnings();
            }
        }

        function test_error_reports_code(){
            $this->expectException(\Exception::class);
            $this->expectExceptionMessageMatches('/SQL ERROR: \d+/');
            $this->suppressWarnings();
            try {
                self::$db->exec("SELECT FROM nonsense");
            } finally {
                $this->restoreWarnings();
            }
        }

        // ==================== 清理 ====================

        function tearDown(): void{
            self::$db->exec("DELETE FROM " . self::$table . " WHERE id > 2");
        }
    }
