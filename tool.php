<?php  
	class Clue_Tool{
		static function uuid(){
			return sha1(getmypid().uniqid(rand()).@$_SERVER['SERVER_NAME']);
		}
	}
?>
