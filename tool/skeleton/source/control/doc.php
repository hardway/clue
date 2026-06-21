<?php
class Controller extends Clue\Controller{
    function __init(){
        $this->book=new Clue\Text\Book(CLUE_ROOT.'/doc/');
    }

    function search($q = ''){
        $q = trim($q);

        // HTMX 片段请求（不含历史恢复）：只返回 .clue-book-html 内容
        // 历史恢复含 HX-History-Restore-Request 头，降级为完整页面
        $htmxFragment = !empty($_SERVER['HTTP_HX_REQUEST'])
                     && empty($_SERVER['HTTP_HX_HISTORY_RESTORE_REQUEST']);

        if ($q === '') {
            if ($htmxFragment) {
                echo '<p><em>请输入搜索关键词</em></p>';
                return;
            }
            // 空搜索 → 直接展示首页
            $page = $this->book->lookup('index');
        } else {
            $page = $this->book->search($q);
        }

        if ($htmxFragment) {
            echo $page->render_content();
            return;
        }

        // 完整页面：带上侧边栏，保持导航一致性
        $sidebar = $this->book->index('')->render_content();
        $this->render($page->view, [
            'page' => $page,
            'sidebar' => $sidebar,
            'search' => Clue\url_for('doc', 'search')
        ]);
    }

    function htmx_time(){
        $view = new \Clue\View('clue/toast');
        $view->render([
            'level' => 'success',
            'message' => '✅ 当前服务器时间：' . date('Y-m-d H:i:s'),
        ]);
    }

    function htmx_modal_time(){
        $now = date('Y-m-d H:i:s');
        $view = new \Clue\View('clue/modal');
        $view->render([
            'id' => 'time-modal',
            'title' => '服务器时间',
            'body' => '<p>当前服务器时间：<strong>' . $now . '</strong></p>',
            'footer' => '<button type="button" class="btn btn-primary" hx-on:click="closeModal(this)">关闭</button>',
        ]);
    }

    function htmx_pagination($p = 1){
        $p = max(1, intval($p));
        header('HX-Push-Url: false');
        $total = 95;
        $pag = new \Clue\UI\Pagination($p, $total, [
            'pageSize' => 10,
            'navPages' => 7,
        ]);

        $start = ($p - 1) * $pag->pageSize + 1;
        $end = min($total, $start + $pag->pageSize - 1);

        $pageCount = ceil($total / $pag->pageSize);
        echo '<p class="text-gray">共 ' . $total . ' 条数据，第 ' . $start . '–' . $end . ' 条（第 ' . $p . ' / ' . $pageCount . ' 页）</p>';

        echo '<table class="table table-striped table-hover">';
        echo '<thead><tr><th>#</th><th>模拟数据</th><th>时间</th></tr></thead><tbody>';
        for ($i = $start; $i <= $end; $i++) {
            $time = date('H:i:s', strtotime('+' . $i . ' minutes'));
            echo '<tr><td>' . $i . '</td><td>第 ' . $i . ' 条记录</td><td>' . $time . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<div hx-boost="true" hx-target="#pagination-result">';
        $pag->render(['url_param' => 'p']);
        echo '</div>';
    }

    function htmx_tab($tab = 'server'){

        $data = [
            'server' => [
                '<h6>🌐 服务器</h6>',
                '<table class="table"><tbody>',
                '<tr><td>操作系统</td><td>' . PHP_OS . '</td></tr>',
                '<tr><td>PHP SAPI</td><td>' . php_sapi_name() . '</td></tr>',
                '<tr><td>服务器时间</td><td>' . date('Y-m-d H:i:s') . '</td></tr>',
                '<tr><td>内存使用</td><td>' . \Clue\readable_bytes(memory_get_usage()) . '</td></tr>',
                '</tbody></table>',
            ],
            'php' => [
                '<h6>🐘 PHP</h6>',
                '<table class="table"><tbody>',
                '<tr><td>PHP 版本</td><td>' . phpversion() . '</td></tr>',
                '<tr><td>已加载扩展</td><td>' . implode(', ', get_loaded_extensions()) . '</td></tr>',
                '</tbody></table>',
            ],
            'clue' => [
                '<h6>🔧 Clue</h6>',
                '<table class="table"><tbody>',
                '<tr><td>视图路径</td><td>' . implode('<br>', \Clue\get_view_path()) . '</td></tr>',
                '<tr><td>类文件路径</td><td>' . implode('<br>', \Clue\PathConfig::$CLASS_PATH ?: []) . '</td></tr>',
                '</tbody></table>',
            ],
        ];

        if (isset($data[$tab])) {
            echo implode('', $data[$tab]);
        } else {
            echo '<div class="toast toast-warning">未知标签页</div>';
        }
    }

    function htmx_source($type = '', $name = ''){

        $allowedMethods = ['htmx_time', 'htmx_modal_time', 'htmx_pagination', 'htmx_tab'];

        if ($type === 'controller' && in_array($name, $allowedMethods)) {
            try {
                $ref = new ReflectionMethod('Controller', $name);
                $file = file($ref->getFileName());
                $code = implode('', array_slice(
                    $file,
                    $ref->getStartLine() - 1,
                    $ref->getEndLine() - $ref->getStartLine() + 1
                ));
                echo '<pre><code>' . htmlspecialchars($code) . '</code></pre>';
            } catch (Exception $e) {
                echo '<pre><code>// 无法读取源码: ' . htmlspecialchars($e->getMessage()) . '</code></pre>';
            }
        } elseif ($type === 'view') {
            $name = basename($name);
            $path = CLUE_ROOT . '/source/view/clue/' . $name . '.php';
            if (file_exists($path)) {
                echo '<pre><code>' . htmlspecialchars(file_get_contents($path)) . '</code></pre>';
            } else {
                echo '<pre><code>// 视图文件未找到: clue/' . htmlspecialchars($name) . '</code></pre>';
            }
        } else {
            echo '<pre><code>// 无效参数</code></pre>';
        }
    }

    function __catch_params(){
        $path=implode('/', array_map('rawurldecode', func_get_args()));

        $page=$this->book->lookup($path);

        $sidebar=$this->book->index($path);
        $sidebar=$sidebar->render_content();

        $this->render($page->view, ['page'=>$page, 'sidebar'=>$sidebar, 'search'=>Clue\url_for('doc', 'search')]);
    }
}
