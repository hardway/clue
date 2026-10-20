<?php
// find_view / site_file 的查找结果与命中缓存行为测试

class Test_View_Cache extends PHPUnit_Framework_TestCase{
    protected $tmpDir;

    protected function setUp(): void{
        $this->tmpDir=sys_get_temp_dir().'/clue-viewcache-'.uniqid();
        mkdir($this->tmpDir.'/views', 0775, true);
        mkdir($this->tmpDir.'/source/view', 0775, true);

        \Clue\add_view_path($this->tmpDir.'/views');
        \Clue\add_site_path($this->tmpDir);
    }

    protected function tearDown(): void{
        $this->rrmdir($this->tmpDir);
    }

    protected function rrmdir($dir){
        if(!is_dir($dir)) return;
        foreach(array_diff(scandir($dir), ['.','..']) as $f){
            $p="$dir/$f";
            is_dir($p) ? $this->rrmdir($p) : unlink($p);
        }
        rmdir($dir);
    }

    function test_find_view_returns_path_without_extension(){
        $name='vc_'.uniqid();
        file_put_contents("{$this->tmpDir}/views/$name.htm", 'x');

        $this->assertEquals("{$this->tmpDir}/views/$name", \Clue\View::find_view($name));
    }

    function test_find_view_repeated_call_is_stable(){
        $name='vc_'.uniqid();
        file_put_contents("{$this->tmpDir}/views/$name.htm", 'x');

        $first=\Clue\View::find_view($name);
        $second=\Clue\View::find_view($name);
        $this->assertEquals($first, $second);
    }

    function test_find_view_deleted_file_returns_null(){
        $name='vc_'.uniqid();
        $path="{$this->tmpDir}/views/$name.htm";
        file_put_contents($path, 'x');

        \Clue\View::find_view($name);   // 命中并缓存
        unlink($path);

        $this->assertNull(\Clue\View::find_view($name));
    }

    function test_find_view_falls_back_to_site_view_after_deletion(){
        $name='vc_'.uniqid();
        $view="{$this->tmpDir}/views/$name.htm";
        $site="{$this->tmpDir}/source/view/$name.htm";
        file_put_contents($view, 'view');
        file_put_contents($site, 'site');

        $this->assertEquals("{$this->tmpDir}/views/$name", \Clue\View::find_view($name));

        unlink($view);
        $this->assertEquals("{$this->tmpDir}/source/view/$name", \Clue\View::find_view($name));
    }

    function test_site_file_returns_existing_file(){
        $rel='vc_'.uniqid().'/data.txt';
        mkdir(dirname("{$this->tmpDir}/$rel"), 0775, true);
        file_put_contents("{$this->tmpDir}/$rel", 'x');

        $this->assertEquals("{$this->tmpDir}/$rel", \Clue\site_file($rel));
    }

    function test_site_file_deleted_file_returns_null(){
        $rel='vc_'.uniqid().'/data.txt';
        mkdir(dirname("{$this->tmpDir}/$rel"), 0775, true);
        $path="{$this->tmpDir}/$rel";
        file_put_contents($path, 'x');

        \Clue\site_file($rel);   // 命中并缓存
        unlink($path);

        $this->assertNull(\Clue\site_file($rel));
    }

    function test_site_file_missing_returns_null(){
        $this->assertNull(\Clue\site_file('does/not/exist/'.uniqid().'.txt'));
    }
}
