<?php
namespace Clue\Text{
    class CSV{
        public $filename;

        public $columns;
        public $rows;

        function __construct($filename, $options=array('header'=>true)){
            $this->columns=array();
            $this->rows=array();

            $this->filename=$filename;

            // 读取首行，标题
            $f=fopen($this->filename, "r");
            if($f){
                $this->columns=fgetcsv($f);
                fclose($f);
            }
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

            return $name;
        }

        function read($callback=null){
            $batch=!is_callable($callback);

            $f=fopen($this->filename, "r");
            if(!$f) return false;

            $this->columns=fgetcsv($f);
            while(!feof($f)){
                $r=fgetcsv($f);
                if(!is_array($r)) continue;

                if($batch){
                    $this->rows[]=$r;
                }
                else{
                    $callback($r);
                }
            }

            fclose($f);

            return $batch ? $this->rows : true;
        }
    }
}
?>
