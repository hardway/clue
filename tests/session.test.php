<?php
    class Test_SESSION extends PHPUnit_Framework_TestCase{
        private $folder='/tmp/test_session';
        private $db;

        function setUp():void{
            @mkdir($this->folder, 0775, true);
            global $db;
            $this->db=$db;

            // 模拟 Web 服务器环境（Session 读写依赖这些）
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
        }

        function tearDown():void{
            if(is_dir($this->folder)){
                foreach(scandir($this->folder) as $f){
                    if($f[0]!='.') unlink("$this->folder/$f");
                }
                rmdir($this->folder);
            }
        }

        /** @return \Clue\FileSession */
        private function makeFileSession(array $extra=[]){
            $s=\Clue\Session::init('test', array_merge([
                'storage'=>'FILE', 'ttl'=>30, 'folder'=>$this->folder,
            ], $extra));
            $s->open(null, null);
            return $s;
        }

        function test_file_session_rw(){
            $id='test_rw';
            $data='session_data_value';

            $s=$this->makeFileSession();
            $s->write($id, $data);
            $this->assertEquals($data, $s->read($id));

            // 重复读取
            $this->assertEquals($data, $s->read($id));

            // 销毁
            $s->destroy($id);
            $this->assertFalse($s->read($id));

            $s->close();
        }

        function test_file_session_ttl_expired(){
            $id='test_ttl';
            $data='ttl_data';

            $s=$this->makeFileSession(['ttl'=>0]);
            $s->write($id, $data);

            // 把 mtime 改到10秒前，确保超越 ttl=0
            touch("$this->folder/$id", time() - 10);

            $this->assertFalse($s->read($id));

            $s->close();
        }

        function test_file_session_retention(){
            $id='test_retain';
            $data='retain_data';

            $s=$this->makeFileSession(['ttl'=>0]);
            $s->write($id, $data);

            // 手动往文件写入 retention 字段（模拟 remember 的效果）
            $path="$this->folder/$id";
            $json=json_decode(file_get_contents($path), true);
            $json['retention']=1;
            file_put_contents($path, json_encode($json));

            // 把 mtime 改到10秒前，让 ttl 过期
            touch($path, time() - 10);

            // ttl=0 但 retention=1天 → 可恢复
            $this->assertEquals($data, $s->read($id));

            $s->close();
        }

        function test_dbsession_rw(){
            global $db;
            if(!$db){
                $this->markTestSkipped('No database connection available');
                return;
            }

            $id='test_db';
            $data='db_session_data';

            $s=new \Clue\DBSession(['db'=>$db, 'ttl'=>30]);
            $s->open(null, null);
            $s->write($id, $data);

            $this->assertEquals($data, $s->read($id));

            $s->destroy($id);
            $this->assertFalse($s->read($id));

            $s->close();
        }
    }
