<?php
	header("Content-Type: text/javascript");

	foreach(explode(",", $_GET['files']) as $f){
		if(file_exists(__DIR__."/$f.js")){
			include __DIR__."/$f.js";
		}
	}
?>
