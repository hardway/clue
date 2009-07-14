<?php
	define("DS", DIRECTORY_SEPARATOR);
	
	function usage(){
		echo <<<OUT
Usage: php generator.php folder-name

eg. php generator.php d:/webroot/test

OUT;
	}
	/**
	 * deep_copy("C:/test.txt", "D:/");
	 * deep_copy("C:/test.txt", "D:/test.txt");
	 * deep_copy("C:/foo", "D:/foo");
	 * deep_copy("C:/foo", "D:/foo");
	 */
	function deep_copy($src, $target){
		echo "Copying $src --> $target \n";
		//echo "\tCWD: ".getcwd()."\n";
	
		if(is_file($src)){	// File Mode
			copy($src, $target);
			touch($target);
		}
		else if(is_dir($src)){	// Directory Mode
			// Always make sure the destination folder exists
			@mkdir($target, 0644, true);
			
			$dh=opendir($src);
			while(($file=readdir($dh))!==false){
				if($file=='.' || $file=='..') continue;
				deep_copy($src.DS.$file, $target.DS.$file);
			}
			closedir($dh);
		}
	}
	
	if($argc<2){
		usage();
		exit();
	}
	
	// copy skeleton to target folder
	$target=$argv[1];
	deep_copy('skeleton', $target);
?>
