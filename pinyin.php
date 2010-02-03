<?php
	require_once 'clue/core.php';
	
	class Clue_PinYin{
		private $db;
		
		function __construct($db=null){
			$this->db=$db;
		}
		
		/* Return PinYin soundex for chinese characters */
		function soundex($words){
			$sound="";
			
			$len=mb_strlen($words, 'UTF-8');
			for($i=0; $i<$len; $i++){
				$ch=mb_substr($words, $i, 1, 'UTF-8');
				
				$py=$this->db->get_var("
					select py from pinyin where ch='$ch'
				");
				
				if($py){
					if(strlen($sound)>0) $sound.=' ';
					$sound.=$py;
				}
			}
			
			return $sound;
		}
		
		/* Only First Capital in Soundex */ 
		function soundex2($words){
			$abbr="";
			foreach(explode(" ", $this->soundex($words)) as $w){
				if(strlen($w)>0){
					$abbr.=$w[0];
				}
			}
			return $abbr;
		}
	}
?>
