<?php
    class ViewTest extends PHPUnit_Framework_TestCase{
        protected $tmpDir;

        protected function setUp(): void{
            $this->tmpDir = sys_get_temp_dir().'/clue-view-test-'.uniqid();
            mkdir($this->tmpDir, 0775, true);

            // 注册为 VIEW_PATH（优先）和 SITE_PATH（兜底）
            \Clue\add_view_path($this->tmpDir.'/views');
            \Clue\add_site_path($this->tmpDir);

            // 创建测试视图文件
            mkdir($this->tmpDir.'/views', 0775, true);
            mkdir($this->tmpDir.'/views/layout', 0775, true);
            file_put_contents($this->tmpDir.'/views/hello.htm', '<h1>Hello <?=$name?></h1>');
            file_put_contents($this->tmpDir.'/views/with_logic.php', '<?php $message="from php"; ?>');
            file_put_contents($this->tmpDir.'/views/with_logic.htm', '<p><?=$message?></p>');
            file_put_contents($this->tmpDir.'/views/content.htm', '<main>content</main>');
            file_put_contents($this->tmpDir.'/views/parent.htm', '<div class="parent"><?php $this->incl("child"); ?></div>');
            file_put_contents($this->tmpDir.'/views/child.htm', '<span class="child"><?=$msg?></span>');
            file_put_contents($this->tmpDir.'/views/override.php', '<?php $extra="woof"; ?>');
            file_put_contents($this->tmpDir.'/views/override.htm', 'bark <?=$extra?>');

            // SITE_PATH 兜底：source/view/ 下的视图
            mkdir($this->tmpDir.'/source/view', 0775, true);
            file_put_contents($this->tmpDir.'/source/view/fallback.htm', 'fallback');
        }

        protected function tearDown(): void{
            $it = new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach($files as $f){
                $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
            }
            rmdir($this->tmpDir);
        }

        // ─── find_view ─────────────────────────────────────────────

        function test_find_view_absolute(){
            $path = \Clue\View::find_view('/hello');
            $this->assertNotNull($path);
            $this->assertContains('hello', $path);
        }

        function test_find_view_not_found(){
            $path = \Clue\View::find_view('/nonexistent');
            $this->assertNull($path);
        }

        function test_find_view_relative_with_parent(){
            $parent = new \Clue\View('/parent');
            $path = \Clue\View::find_view('child', $parent);
            $this->assertNotNull($path);
            $this->assertContains('child', $path);
        }

        function test_find_view_site_path_fallback(){
            $path = \Clue\View::find_view('/fallback');
            $this->assertNotNull($path);
            $this->assertContains('fallback', $path);
        }

        // ─── __construct ─────────────────────────────────────────

        function test_construct_success(){
            $view = new \Clue\View('/hello');
            $this->assertNotNull($view);
        }

        function test_construct_throws_on_missing(){
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('View does not exist');
            new \Clue\View('/i_do_not_exist');
        }

        function test_construct_throws_on_null(){
            $this->expectException(\InvalidArgumentException::class);
            new \Clue\View(null);
        }

        function test_construct_throws_on_empty_string(){
            $this->expectException(\InvalidArgumentException::class);
            new \Clue\View('');
        }

        // ─── set / bind ──────────────────────────────────────────

        function test_set_single_var(){
            $view = new \Clue\View('/hello');
            $view->set('name', 'World');

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertContains('Hello World', $output);
        }

        function test_bind_merge(){
            $view = new \Clue\View('/hello');
            $view->bind(['name' => 'Alice']);

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertContains('Hello Alice', $output);
        }

        function test_bind_chaining(){
            $view = new \Clue\View('/hello');
            $view->bind(['first' => 'a'])->bind(['name' => 'Bob']);

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertContains('Hello Bob', $output);
        }

        function test_render_vars_override_bind(){
            $view = new \Clue\View('/hello');
            $view->set('name', 'before');

            $output = $this->capture(function() use($view){ $view->render(['name' => 'after']); });
            $this->assertContains('Hello after', $output);
        }

        // ─── render ──────────────────────────────────────────────

        function test_render_htm_only(){
            $view = new \Clue\View('/content');

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertContains('<main>content</main>', $output);
        }

        function test_render_code_behind(){
            $view = new \Clue\View('/with_logic');

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertContains('from php', $output);
        }

        function test_render_multiple_calls(){
            $view = new \Clue\View('/content');

            $first = $this->capture(function() use($view){ $view->render(); });
            $second = $this->capture(function() use($view){ $view->render(); });
            $this->assertEquals($first, $second);
        }

        function test_render_empty_no_crash(){
            $view = new \Clue\View('/content');

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertNotEmpty($output);
        }

        // ─── incl ────────────────────────────────────────────────

        function test_incl_subview(){
            $view = new \Clue\View('/parent');
            $view->set('msg', 'sub!');

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertContains('<span class="child">sub!</span>', $output);
        }

        function test_incl_null_renders_content(){
            // 模拟 layout 场景：incl(null) 渲染 $this->vars['content']
            $view = new \Clue\View('/content');
            $layout = new \Clue\View('/hello');
            $layout->set('content', $view);
            $layout->set('name', 'WORLD');  // hello view needs \$name

            $output = $this->capture(function() use($layout){ $layout->incl(null); });
            $this->assertContains('content', $output);
        }

        function test_incl_null_throws_on_missing_content(){
            $this->expectException(\RuntimeException::class);
            $view = new \Clue\View('/hello');
            $view->incl(null);
        }

        function test_incl_null_throws_on_non_object_content(){
            $this->expectException(\RuntimeException::class);
            $view = new \Clue\View('/hello');
            $view->set('content', 'not an object');
            $view->incl(null);
        }

        function test_incl_array_candidates(){
            $view = new \Clue\View('/hello');

            $output = $this->capture(function() use($view){ $view->incl(['/nonexistent', '/hello']); });
            $this->assertContains('Hello', $output);
        }

        function test_incl_throws_no_match(){
            $this->expectException(\Exception::class);
            $view = new \Clue\View('/hello');
            $view->incl(['/nope', '/nada']);
        }

        // ─── php + htm override ──────────────────────────────────

        function test_php_and_htm_override(){
            $view = new \Clue\View('/override');

            $output = $this->capture(function() use($view){ $view->render(); });
            $this->assertContains('bark woof', $output);
        }

        // ─── helper ──────────────────────────────────────────────

        private function capture(callable $fn): string{
            ob_start();
            $fn();
            return ob_get_clean();
        }
    }
?>
