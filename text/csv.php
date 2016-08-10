<?php
namespace Clue\Text{
	class CSV{
		public $filename;

		public $columns;
		public $rows;

		static $DEFAULT_OPTIONS=array('header'=>true, 'length'=>4096, 'delimiter'=>",", 'enclosure'=>'"', 'escape'=>'\\');

		function __construct($filename, $options=array()){
			// 自动识别TSV和CSV
			$ext=pathinfo($filename)['extension'];
			if($ext=='tsv'){
				self::$DEFAULT_OPTIONS['delimiter']="\t";
			}

			$this->options=array_merge(self::$DEFAULT_OPTIONS, $options);
			$this->columns=array();
			$this->rows=array();

			$this->filename=$filename;

			if($this->options['header']){
				// 读取首行，标题
				$f=fopen($this->filename, "r");
				if(!$f) throw new \Exception("Can't open CSV file: $this->filename");

				$this->columns=$this->parse_row($f);
				fclose($f);
			}
		}

		function parse_row($f){
			return fgetcsv($f, $this->options['length'], $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
		}

		function write_row($f, $fields){
			return fputcsv($f, $fields, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
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

		function read($batch=false){
			$f=fopen($this->filename, "r");

			try{
				// TODO: BUG 第一行会被扔掉
				$this->columns=$this->parse_row($f);
				while(!feof($f)){
					$r=$this->parse_row($f);
					if(!is_array($r)) continue;

					if($batch){
						$this->rows[]=$r;
					}
					else{
						yield $r;
					}
				}
			}
			finally{
				fclose($f);
			}
		}

		function write($table){
			$f=fopen($this->filename, "w");

			try{
				foreach($table as $row){
					$this->write_row($f, $row);
				}
			}
			finally{
				fclose($f);
			}
		}
	}
}
?>
