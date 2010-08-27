<?php  
    class Clue_Tool_Constructor{
        function help(){
            echo "Nothing to help you except yourself.\n";
        }
        
        function init($path=null){
            $skeleton=__DIR__ . DIRECTORY_SEPARATOR . 'skeleton';
            $site=getcwd();
            
            if(!is_dir($site)) mkdir($site, 0755, true);
            $this->deepcopy($skeleton, $site);
        }
        
        function deepcopy($src, $dest){
            echo "Copying $src --> $dest \n";
            
            if(is_file($src)){	// File Mode
                copy($src, $dest);
                touch($dest);
            }
            else if(is_dir($src)){	// Directory Mode
                // Always make sure the destination folder exists
                if(!is_dir($dest)) mkdir($dest, 755, true);
                
                $dh=opendir($src);
                while(($file=readdir($dh))!==false){
                    if($file=='.' || $file=='..') continue;
                    $this->deepcopy($src.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file);
                }
                closedir($dh);
            }
        }    
    }
?>
