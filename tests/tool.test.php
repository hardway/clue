<?php
    require_once dirname(__DIR__).'/stub.php';
	use Clue\Tool as Tool;

	class ToolTest extends PHPUnit_Framework_TestCase{
		protected function setUp(){
			$this->folder="/tmp/delete-test";
			mkdir("$this->folder", 0775);
			touch("$this->folder/file");
			mkdir("$this->folder/folder", 0775);
			touch("$this->folder/folder/file");

			$this->folder2="/tmp/copy-test";
		}

		protected function tearDown(){
			@unlink("$this->folder/folder/file");
			@unlink("$this->folder/file");
			@rmdir("$this->folder/folder");
			@rmdir("$this->folder");

			@unlink("$this->folder2/folder/file");
			@unlink("$this->folder2/file");
			@rmdir("$this->folder2/folder");
			@rmdir("$this->folder2");
		}

		function test_recursive_delete(){
			Tool::remove_directory($this->folder);

			$this->assertFalse(is_dir("$this->folder"));
		}

		function test_recursive_copy(){
			Tool::copy_directory($this->folder, $this->folder2);

			$this->assertTrue(is_file("$this->folder2/folder/file"));
			$this->assertTrue(is_file("$this->folder2/file"));
		}
	}
?>
