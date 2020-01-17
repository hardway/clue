<?php
namespace Clue\Text{
    class CSV{
        public $filename;

        public $columns;
        public $rows;

        static $DEFAULT_OPTIONS=array(
            'header'=>true,
            'length'=>4096,
            'delimiter'=>",",
            'enclosure'=>'"',
            'escape'=>'\\',
            'col_index'=>false, // 使用column name作为下标，而不是数字列名
        );

        /**
         * @param $filename 文件路径（也可以是file handle）
         * @param $options
         *          header  是否读取首行作为列名
         *          columns 也可以人工指定列名
         */
        function __construct($filename, $options=array()){
            if(is_resource($filename)){
                $this->_fh=$filename;
            }
            else{
                // 自动识别TSV和CSV
                $ext=@pathinfo($filename)['extension'];
                if($ext=='tsv'){
                    self::$DEFAULT_OPTIONS['delimiter']="\t";
                }

                $this->options=array_merge(self::$DEFAULT_OPTIONS, $options);
                $this->columns=array();
                $this->rows=array();

                $this->filename=$filename;

                $this->_fh=fopen($this->filename, "r");
                if(!$this->_fh) throw new \Exception("Can't open CSV file: $this->filename");
            }

            $this->ln=1;    // 当前行数

            if($this->options['header']){
                // 读取首行，标题
                $this->columns=$this->parse_row();
            }

            // 强制使用指定的列名
            if(isset($this->options['columns'])){
                $this->columns=$this->options['columns'];
            }
        }

        function __destruct(){
            // 正确关闭文件
            if($this->_fh){
                fclose($this->_fh);
                $this->_fh=null;
            }

            return true;
        }

        /**
         * 返回列名对应的行数据
         */
        function name_row($row){
            $obj=[];
            foreach($row as $k=>$v){
                $k=@$this->columns[$k] ?: $k;
                $obj[$k]=$v;
            }

            return $obj;
        }

        function parse_row($fh=null){
            $row=fgetcsv($fh ?: $this->_fh, $this->options['length'], $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
            $this->ln++;
            return $row;
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

        function eof(){
            return feof($this->_fh);
        }

        function read($batch=false){
            while(!feof($this->_fh)){
                $r=$this->parse_row();
                if(!is_array($r)) continue;
                if($r==[null]) continue;

                if($this->options['col_index']){
                    $nr=[];
                    foreach($this->columns as $idx=>$col){
                        $nr[$col]=@$r[$idx];
                    }
                    $r=$nr;
                }

                if($batch){
                    $this->rows[]=$r;
                }
                else{
                    yield $r;
                }
            }
        }

        function write($table){
            $f=fopen($this->filename, "w");

            try{
                foreach($table as $row){
                    fputcsv($f, $row, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
                }
            }
            finally{
                fclose($f);
            }
        }
    }
}
?>
