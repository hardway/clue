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
        echo '<div class="toast toast-success">✅ 当前服务器时间：' . date('Y-m-d H:i:s') . '</div>';
    }

    function __catch_params(){
        $path=implode('/', array_map('rawurldecode', func_get_args()));

        $page=$this->book->lookup($path);

        $sidebar=$this->book->index($path);
        $sidebar=$sidebar->render_content();

        $this->render($page->view, ['page'=>$page, 'sidebar'=>$sidebar, 'search'=>Clue\url_for('doc', 'search')]);
    }
}
