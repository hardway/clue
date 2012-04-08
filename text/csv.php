<?php  
namespace Clue{
    class Clue_Text_CSV{
        public $filename;
        
        public $columns;
        public $rows;
        
        function __construct($filename){
            $this->columns=array();
            $this->rows=array();
            
            $this->filename=$filename;
        }
        
        function read(){
            if(file_exists($this->filename)){
                $f=fopen($this->filename, "r");
                
                $this->columns=fgetcsv($f);
                while(!feof($f)){
                    $r=fgetcsv($f);
                    if(is_array($r)) $this->rows[]=$r;
                }
                
                fclose($f);
            }
        }
    }
}
?>
