<?php  
	class Clue_Creeper{
		public $response;
		public $content;
		
		private $curl;
		
		function __construct(){
			$this->curl=curl_init();
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		}
		
		function __destruct(){
			curl_close($this->curl);
		}
		
		function open($url){
			curl_setopt($this->curl, CURLOPT_URL, $url);
			$this->content=curl_exec($this->curl);
		}
	}
?>
