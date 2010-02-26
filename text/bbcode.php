<?php  
	class Clue_Text_BBCode{
		private $inputRule=array(
			// Text Formatting
			'/\[[Bb]\](.+)\[\/[Bb]\]/',
			'/\[[Uu]\](.+)\[\/[Uu]\]/',
			'/\[[Ii]\](.+)\[\/[Ii]\]/',
			'/\[color=(.+)\](.+)\[\/color\]/',
			'/\[size=(.+)\](.+)\[\/size\]/',
			
			// Links
			'/\[url\](.+)\[\/url\]/',
			'/\[url=(.+)\](.+)\[\/url\]/',
			'/\[email\](.+)\[\/email\]/',
		);
		
		private $outputRule=array(
			// Text Formatting
			'<strong>\\1</strong>',
			'<u>\\1</u>',
			'<i>\\1</i>',
			"<font color='\\1'>\\2</font>",
			"<font size='\\1'>\\2</font>",
			
			// Links
			"<a href='\\1'>\\1</a>",
			"<a href='\\1'>\\2</a>",
			"<a href='mailto:\\1'>\\1</a>",
		);
		
		function to_html($input){
			return preg_replace($this->inputRule, $this->outputRule, $input);
		}
	}
?>
