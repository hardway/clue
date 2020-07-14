<?php
	require_once dirname(__DIR__).'/stub.php';

	class Test_URL extends PHPUnit_Framework_TestCase{
		protected function temp_folder($prefix){
			$folder=tempnam(sys_get_temp_dir(), $prefix);
			if(is_file($folder)) unlink($folder);
			mkdir($folder);

			return is_dir($folder) ? $folder : null;
		}

		protected function temp_file($path, $content=""){
			$folder=dirname($path);
			if(!is_dir($folder)) mkdir($folder, 0755, true);

			file_put_contents($path, $content);

			return filesize($path);
		}

		function test_path_normalize(){
			$test_cases=[
				'//abc//def'=>'/abc/def',
				'/////abc/////def'=>'/abc/def',
				'abc/////def'=>'abc/def',
				'abc/../def'=>'def',
				'./abc/////def'=>'abc/def',
				'//abc/./../def'=>'/def',
				'//abc/./../.././../../def'=>'/def',
			];

			foreach($test_cases as $from=>$to){
				$this->assertEquals($to, path_normalize($from));
			}
		}

		function test_normalize(){
			$test_cases=[
				'http://abc//def'=>'http://abc/def',
				'file:/abc//def'=>'file:///abc/def',
				'http://abc.com///def/./.././abc?n=1#qqq'=>'http://abc.com/abc?n=1#qqq',
				'//abc//def'=>'//abc/def',
			];

			foreach($test_cases as $from=>$to){
				$this->assertEquals($to, url_normalize($from));
			}
		}

		function test_url_path(){
			$this->assertEquals('/a/b/c', url_path(APP_ROOT.'/a/b/c', '/'));
			$this->assertEquals('/test/a/b/c', url_path(APP_ROOT.'/a/b/c', '/test'));
			$this->assertEquals('http://test/a/b/c', url_path(APP_ROOT.'/a/b/c', 'http://test'));
		}

        function test_url_follow(){
            $this->assertEquals('http://abc.com/test.htm', url_follow('/test.htm', 'http://abc.com/'));
            $this->assertEquals('http://abc.com/test.htm', url_follow('../test.htm', 'http://abc.com/no'));

            $this->assertEquals('http://abc.com/test.htm', url_follow('//abc.com/test.htm', 'http://def.net'));
            $this->assertEquals('https://abc.com/test.htm', url_follow('//abc.com/test.htm', 'https://def.net'));
        }

		function test_asset(){
			/**
			 * 目录结构
			 * 	root
			 * 	   \_ base
			 * 	   \_ over
			 *			 \_ sub
			 */
			$base_folder=$this->temp_folder("base");
			$over_folder=$this->temp_folder("over");
			$map_folder=$this->temp_folder("map");
			$sub_folder=$over_folder.'/sub';

			\Clue\add_site_path($base_folder, '/');
			\Clue\add_site_path($over_folder, '/');
			\Clue\add_site_path($map_folder, '/map');
			\Clue\add_site_path($sub_folder, '/sub');

			$paths=\Clue\get_site_path();
			$this->assertEquals($base_folder, $paths[3]);
			$this->assertEquals($over_folder, $paths[2]);
			$this->assertEquals($map_folder, $paths[1]);
			$this->assertEquals($sub_folder, $paths[0]);

			@define("APP_URL", "/");
			$this->assertEquals(APP_URL."/asset/missing.js", asset('missing.js'), '不存在的文件，仍然能够得到网址');

			$this->temp_file($base_folder.'/asset/base.js', '');
			$mtime=filemtime($base_folder.'/asset/base.js');
			$this->assertEquals("/asset/base.js?$mtime", asset('base.js'), '基准测试');


			$this->temp_file($over_folder.'/asset/over.js', '');
			$mtime=filemtime($over_folder.'/asset/over.js');
			$this->assertEquals("/asset/over.js?$mtime", asset('over.js'), 'Overlay文件');

			$this->temp_file($sub_folder.'/asset/sub.js', '');
			$mtime=filemtime($sub_folder.'/asset/sub.js');
			$this->assertEquals("/sub/asset/sub.js?$mtime", asset('sub.js'), '映射到子文件夹');

			$this->temp_file($map_folder.'/asset/map.js', '');
			$mtime=filemtime($map_folder.'/asset/map.js');
			$this->assertEquals("/map/asset/map.js?$mtime", asset('map.js'), '映射到其它目录');

			\Clue\Tool::remove_directory($base_folder);
			\Clue\Tool::remove_directory($over_folder);
		}
	}
