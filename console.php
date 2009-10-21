<?php  
	class Clue_Console{
		static function init($output_encoding='GBK'){
			iconv_set_encoding("internal_encoding", "UTF-8");
			iconv_set_encoding("output_encoding", $output_encoding);
			ob_start("ob_iconv_handler");
		}
	}
?>