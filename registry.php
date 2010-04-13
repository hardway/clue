<?php  
    class Clue_Registry{
        protected $_store;  // Storage is an associated array
        
        public function __construct($ary=null){
            $this->_store=is_array($ary) ? $ary : array();
        }
        
        public function get($path){
            $store=&$this->_store;

            if(strlen($path)>0) foreach(explode('.', $path) as $k){
                if(!isset($store[$k])) return null;
                $store=&$store[$k];
            }
            
            return $store;
        }
        
        public function set($path, $value){
            $store=&$this->_store;
            $change=null;
            
            foreach(explode('.', $path) as $k){
                if(!is_array($store)) $store=array();
                if(!isset($store[$k])) $store[$k]=null;
                
                $change=&$store;
                $store=&$store[$k];
            }
            
            $old=$change[$k];
            $change[$k]=$value;
            
            return $old;
        }
        
        public function __get($path){
            $r=$this->get($path);
            return is_array($r) ? new Clue_Registry($r) : $r;
        }
    }
?>