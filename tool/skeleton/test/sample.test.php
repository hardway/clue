<?php
    require_once dirname(__DIR__).'/stub.php';

    class Sample_Test extends PHPUnit_Framework_TestCase{
        protected function setUp(){
            // 创建新的测试产品

            // 创建新的测试仓库和测试bin

            // 新增测试库存
        }

        protected function tearDown(){
            // 删除测试库存

            // 删除测试仓库

            // 删除测试产品
        }

        function test_fifo(){
            $this->markTestIncomplete();
        }

        function test_out_of_stock(){
            $this->markTestIncomplete();
        }

        function test_multiple_transaction(){
            $this->markTestIncomplete();
        }
    }
?>
