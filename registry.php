<?php  
    class Clue_Registry{        
        public function __construct($ary=null){
            if(is_array($ary)) foreach($ary as $k=>$v){
                $this->$k=is_array($v) ? $this->_ary_to_obj($v) : $v;
            }
        }
        
        private function _ary_to_obj(array $ary){
            $obj=new Clue_Registry;
            foreach($ary as $k=>$v){
                $obj->$k=is_array($v) ? $this->_ary_to_obj($v) : $v;
            }
            
            return $obj;
        }
        
        public function __clone(){
            foreach(array_keys((array)$this) as $k){
                if(is_object($this->$k))
                    $this->$k=clone $this->$k;
            }
        }
        
        public function get($path){
            $store=&$this;

            if(strlen($path)>0) foreach(explode('.', $path) as $k){
                if(!isset($store->$k)) return null;
                $store=&$store->$k;
            }
            
            return $store;
        }
        
        public function set($path, $value){
            $store=&$this;
            $change=null;
            
            foreach(explode('.', $path) as $k){
                if(!$store instanceof Clue_Registry) $store=new Clue_Registry;
                if(!isset($store->$k)) $store->$k=null;
                
                $change=&$store;
                $store=&$store->$k;
            }
            
            $change->$k=is_array($value) ? $this->_ary_to_obj($value) : $value;
            
            return $this;
        }
    }
?>