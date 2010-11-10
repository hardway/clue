<?php      
	class Clue_PinYin{
	    static $sound;
	    static $pinyin;
	    
		//罗马字母
		static $roman=array(
			"1","2","3","4","5","6","7","8","9","10","","","","","","",
			"1","2","3","4","5","6","7","8","9","10","11","12","13","14","15","16","17","18","19","20",
			"1","2","3","4","5","6","7","8","9","10","11","12","13","14","15","16","17","18","19","20",
			"1","2","3","4","5","6","7","8","9","10","","",
			"1","2","3","4","5","6","7","8","9","10","","",
			"1","2","3","4","5","6","7","8","9","10","11","12","",""
		);

		//希腊字母
		static $greek=array(
			"a","b","g","d","e","z","e","th","i","k","l","m","n","x","o","p","r",
			"s","t","u","ph","kh","ps","o"
		);
		
		/* Return PinYin soundex for chinese characters */
		static function soundex($utf){
			$sound="";
			
			$gb=mb_convert_encoding($utf, "GB2312", "UTF-8");
			if($gb===FALSE) return FALSE;	// Invalid encoding
			
			$len=strlen($gb);
			$i=0;
			
			while($i<$len){
				$hb=ord($gb[$i]);
				$lb=($i+1<$len) ? ord($gb[$i+1]) : 0;
				
				if($hb>=129 && $lb>=64){
					switch($hb){
						case 0xA3:	// 全角ASCII
							break;
						case 0xA2:	// 罗马数字
							break;
						case 0xA6:	// 希腊字母
							break;
						default:
							$idx=self::$pinyin[$hb - 129][$lb - 64] - 1;
							$sound.=self::$sound[$idx]." ";
							break;
					}
					$i+=2;
				}
				else{// 标准ASCII(半角)
					$sound.=$gb[$i];
					$i++;
				}
			}
			
			return trim($sound);
		}
		
		/* Only First Capital in Soundex */ 
		static function soundex2($utf){
			$abbr="";
			
			foreach(explode(" ", self::soundex($utf)) as $w){
				$abbr.=$w[0];
			}
			return $abbr;
		}
	}
	
    Clue_Pinyin::$sound=require(__DIR__."/support/pinyin.sound.php");
	Clue_Pinyin::$pinyin=require(__DIR__."/support/pinyin.gb2312.php");
?>
