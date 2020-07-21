<?php
class Controller extends Clue\Controller{
    function __init(){
        $this->book=new Clue\Text\Book(CLUE_ROOT.'/doc/');
    }

    function search($q){
        $page=$this->book->search($q);

        $this->render($page->view, ['content'=>$page->content, 'search'=>'search']);
    }

    function __catch_params(){
        $path=implode('/', array_map('rawurldecode', func_get_args()));

        $page=$this->book->lookup($path);

        $sidebar=$this->book->index($path);
        $sidebar=$sidebar->render_content();

        $this->render($page->view, ['page'=>$page, 'sidebar'=>$sidebar, 'search'=>url_for('doc', 'search')]);
    }
}
