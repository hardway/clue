<?php  
namespace Clue{
	/*
		For windows platform: 
			install ANSICON at http://adoxa.110mb.com/ansicon/index.html
			and check ANSICON environment before using
	*/
	class Clue_Console{
		function enable_output_encoding($output_encoding='GBK'){
			iconv_set_encoding("internal_encoding", "UTF-8");
			iconv_set_encoding("output_encoding", $output_encoding);
			ob_start("ob_iconv_handler");
		}
		
		function set_fg(){
			
		}
		
		function set_bg(){
			
		}
	}
}
?>
