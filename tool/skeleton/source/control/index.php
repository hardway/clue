<?php
class Controller extends Clue\Controller{
	function index(){
		$this->render();
	}

    function doc(){
        $this->render('/clue/developer');
    }
}
