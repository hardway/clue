<?php
namespace Clue\Text{
    class CSV{
        public $filename;

        public $columns;
        public $rows;

        function __construct($filename, $options=array()){
            $this->columns=array();
            $this->rows=array();

            $this->filename=$filename;
        }

        function col($name){
            // Search for exact match
            foreach($this->columns as $i=>$col){
                if(strtolower($col)==strtolower($name)){
                    return $i;

                }
            }

            // Search for rough match
            foreach($this->columns as $i=>$col){
                if(strpos(strtolower($col), strtolower($name))!==false){
                    return $i;
                }
            }
            return null;
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
