<?php
    require_once dirname(__DIR__).'/stub.php';

    class Test_SESSION extends PHPUnit_Framework_TestCase{
        function test_file_session(){
            $id="TEST";
            $data=['a'=>'b', 'id'=>$id];

            $s=\Clue\Session::init('test', ['name'=>'SESSION', 'storage'=>'FILE', 'ttl'=>30, 'folder'=>'/tmp/test']);

            $s->open(null, null);
            $s->write($id, $data);

            $this->assertEquals(json_encode($data), json_encode($s->read($id)));
            $this->assertEquals(json_encode($data), json_encode($s->read($id)));

            $s->close();
        }
    }
