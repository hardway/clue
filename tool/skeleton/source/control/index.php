<?php
class Controller extends Clue\Controller{
	function index(){
		$this->render();
	}

    function doc(){
        // TODO: 确保只有开发测试环境可以查看
        // TODO: 使用path决定文档可见范围
        $path=implode('/', func_get_args());

        $this->render('/clue/book');
    }
}
