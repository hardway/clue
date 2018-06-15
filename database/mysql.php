<?php
namespace Clue\Database{
    class Mysql extends \Clue\Database{
        protected $_result;
        protected $_savepoints=[];  //用于追踪Transaction和Savepoint

        function __construct(array $param){
            $defaults=['host'=>'127.0.0.1','username'=>"root", 'password'=>'', 'encoding'=>'utf8'];
            $param=$param+$defaults;

            // Make sure mysqli extension is enabled
            if(!extension_loaded('mysqli'))
                throw new \Exception(__CLASS__.": extension mysqli is missing!");

            $this->config=$param;

            $this->connect();
        }

        function __destruct(){
            $this->close();
        }

        function connect(){
            $param=$this->config;
            // Check Parameter, TODO
            // echo "Creating MySQL Connection.\n";
            $this->dbh=@mysqli_connect(
                $param['host'],
                $param['username'], $param['password'],
                $param['db'],
                isset($param['port']) ? $param['port'] : null
            );

            if(!$this->dbh){
                $errno=mysqli_connect_errno();
                $error=mysqli_connect_error();

                // 尝试创建数据库
                $this->dbh=@mysqli_connect($param['host'], $param['username'], $param['password'], null, @$param['port']);
                if($this->dbh){
                    $this->exec("create database {$param['db']} default character set utf8");
                    $this->dbh=@mysqli_connect($param['host'], $param['username'], $param['password'], $param['db'], @$param['port']);
                }

                if(!$this->dbh){
                    $this->setError(array('code'=>$errno, 'error'=>$error));
                }
            }

            // set default client encoding as UTF8
            if(isset($param['encoding'])){
                $encoding=$param['encoding'];
                $this->exec("set names $encoding");
            }
        }

        function close(){
            $this->free_result();

            if($this->dbh){
                if(@mysqli_ping($this->dbh)){
                    $this->rollback();
                }

                @mysqli_close($this->dbh);
                $this->dbh=null;
            }
        }

        protected function free_result(){
            if(is_object($this->_result)){
                $this->_result->close();
                $this->_result=null;
            }
        }

        /**
         * 数据库事务
         */
        function begin($name=null){
            if(empty($this->_savepoints)){
                // 首次开始事务
                mysqli_autocommit($this->dbh, false);
                mysqli_begin_transaction($this->dbh);
                array_push($this->_savepoints, '');
            }
            else{
                // 后续事务直接作为SavePoint执行
                $name=$name ?: "autosave_".count($this->_savepoints);
                $this->savepoint($name);
                array_push($this->_savepoints, $name);
            }
        }

        function savepoint($name){
            mysqli_savepoint($this->dbh, $name);
        }

        function commit(){
            if(count($this->_savepoints)>1){
                // 回退一个savepoint，假装已经commit
                array_pop($this->_savepoints);
            }
            else{
                $this->commit_all();
            }
        }

        function commit_all(){
            mysqli_commit($this->dbh);
            mysqli_autocommit($this->dbh, true);
            $this->_savepoints=[];
        }

        function rollback($name=null){
            if(count($this->_savepoints)<=1){
                $this->rollback_all();
                return;
            }

            if($name===null){
                // 默认回退一个savepoint

                // BUG: this api not working properly
                // $ret=mysqli_release_savepoint($this->dbh, array_pop($this->_savepoints));

                // Workaround
                $this->exec("rollback to ".array_pop($this->_savepoints));
            }
            else{
                // 查找并回退savepoint
                for($i=count($this->_savepoints)-1; $i>=1; $i--){
                    if($this->_savepoints[$i]==$name){
                        $this->exec("rollback to ".$name);
                        $this->_savepoints=array_splice($this->_savepoints, 0, $i);
                        return;
                    }
                }

                // 没有找到savepoint，抛出异常
                throw new Exception("MYSQL Transaction savepoint $name not found");
            }
        }

        function rollback_all(){
            mysqli_rollback($this->dbh);
            mysqli_autocommit($this->dbh, true);
            $this->_savepoints=[];
        }

        function insert_id(){
            return mysqli_insert_id($this->dbh);
        }

        function replace($table, $fields){
            return $this->insert($table, $fields, 'replace');
        }

        function insert_ignore($table, $fields){
            return $this->insert($table, $fields, 'insert ignore');
        }

        function insert($table, $fields, $verb='insert'){
            $cols=array();
            $vals=array();
            foreach($fields as $c=>$v){
                $cols[]='`'.trim($c, '`').'`';
                $vals[]=$this->quote($v);
            }
            $sql="$verb into `$table`(".implode(',', $cols).") values(".implode(',', $vals).")";
            $this->exec($sql);

            return mysqli_errno($this->dbh) ? null : $this->insert_id();
        }

        function update($table, $fields, $where){
            $updates=array();

            foreach ($fields as $c=>$v) {
                $updates[]="`$c`=".$this->quote($v);
            }
            $sql="
                update `$table` set ".implode(', ', $updates)."
                where $where
            ";

            return $this->exec($sql);
        }

        function remove($table, $where){
            $sql="
                delete from `$table` where $where
            ";
            $this->exec($sql);
        }

        function affected_rows(){
            return mysqli_affected_rows($this->dbh);
        }

        function format($sql){
            $args=func_get_args();
            $me=$this;

            $idx=1;
            $sql=preg_replace_callback('/%(t|c|s|d|f|%)/', function($m) use($me, $args, &$idx){
                if($m[1]=='%') return '%';

                if(!array_key_exists($idx, $args)){
                    throw new \Exception("Not enough arguments for SQL statement.");
                }

                $var="";
                switch($m[1]){
                    case 't':   // Table/View
                    case 'c':   // Column
                        $var="`".$args[$idx]."`";
                        break;
                    case 's':
                        $var=$me->quote($args[$idx]);
                        break;
                    case 'd':
                        $var=intval($args[$idx]);
                        break;
                    case 'f':
                        $var=floatval($args[$idx]);
                        break;
                }

                $idx++;
                return $var;
            }, $sql);
            return $sql;
        }

        function exec($sql)
        {
            $this->free_result();
            $this->last_error=null;
            $this->result_mode=MYSQLI_USE_RESULT;

            if(func_num_args()>1){
                $sql=call_user_func_array(array($this, "format"), func_get_args());
            }

            if(!@mysqli_ping($this->dbh)){
                $this->connect();
            }

            $query_begin=microtime(true);
            $this->_result=mysqli_query($this->dbh, $sql, $this->result_mode);
            $query_end=microtime(true);

            // Backtrace调用位置
            $location=null;
            $bt=debug_backtrace();
            for ($i=0; $i<count($bt); $i++) {
                if (isset($bt[$i]['file']) && $bt[$i]['file']!=__FILE__) {
                    $location=$bt[$i]['file'] .':'.$bt[$i]['line'];
                    break;
                }
            }

            $this->audit($sql, $query_end - $query_begin, $location);

            if (!$this->_result) {
                $this->setError(
                    array(
                        'code'=>mysqli_errno($this->dbh),
                        'error'=>mysqli_error($this->dbh),
                        'location'=>$location
                    )
                );

                return false;
            }

            // NOTE: should not free result since it might be used in get_var...
            // TODO: caused resouce lock, and leak maybe

            return true;
        }


        function get_var($sql)
        {
            if (!call_user_func_array(array($this, "exec"), func_get_args())) {
                return false;
            }

            $row=mysqli_fetch_row($this->_result);
            $this->free_result();

            return empty($row) ? null : $row[0];
        }

        function get_row($sql, $mode=OBJECT)
        {
            if (!call_user_func_array(array($this, "exec"), func_get_args())) {
                return false;
            }

            $result=false;

            $mode=func_get_arg(func_num_args()-1);
            if($mode!=OBJECT && $mode!=ARRAY_A && $mode!=ARRAY_N){
                $mode=OBJECT;
            }

            if ($mode==OBJECT) {
                $result=mysqli_fetch_object($this->_result);
            } elseif ($mode==ARRAY_A) {
                $result=mysqli_fetch_assoc($this->_result);
            } elseif ($mode==ARRAY_N) {
                $result=mysqli_fetch_row($this->_result);
            } else {
                $result=mysqli_fetch_array($this->_result);
            }

            $this->free_result();
            return $result;
        }

        function get_col($sql)
        {
            if (!call_user_func_array(array($this, "exec"), func_get_args())) {
                return false;
            }

            $result=array();
            while ($r=mysqli_fetch_row($this->_result)) {
                $result[]=$r[0];
            }

            $this->free_result();
            return $result;
        }

        function get_results($sql, $mode=OBJECT)
        {
            if (!call_user_func_array(array($this, "exec"), func_get_args())) {
                return false;
            }

            if($this->_result===true) return [];

            $result=array();

            $mode=func_get_arg(func_num_args()-1);
            if($mode!=OBJECT && $mode!=ARRAY_A && $mode!=ARRAY_N){
                $mode=OBJECT;
            }

            if ($mode==OBJECT) {
                while ($r=mysqli_fetch_object($this->_result)) {
                    $result[]=$r;
                }
            } elseif ($mode==ARRAY_A) {
                while ($r=mysqli_fetch_assoc($this->_result)) {
                    $result[]=$r;
                }
            } elseif ($mode==ARRAY_N) {
                while ($r=mysqli_fetch_row($this->_result)) {
                    $result[]=$r;
                }
            }

            $this->free_result();
            return $result;
        }

        # Memeory optimized
        function foreach_row($sql, $handler, $mode=OBJECT)
        {
            $args=func_get_args();

            $mode=array_pop($args);
            if($mode!=OBJECT && $mode!=ARRAY_A && $mode!=ARRAY_N){
                array_push($args, $mode);
                $mode=OBJECT;
            }

            $handler=array_pop($args);

            if (!call_user_func_array(array($this, "exec"), $args)) {
                return false;
            }

            $cnt=0;

            $res=$this->_result; $this->_result=null;
            while (true) {
                switch($mode){
                case OBJECT:
                    $r=mysqli_fetch_object($res);
                    break;
                case ARRAY_A:
                    $r=mysqli_fetch_assoc($res);
                    break;
                case ARRAY_N:
                    $r=mysqli_fetch_row($res);
                    break;
                }

                if (empty($r)) {
                    break;
                }

                $handler($r); $cnt++;
            }
            $res->close();

            return $cnt;
        }

	    /**
	     * Result generator
	     *
	     * @param string $sql     SQL Statement
	     * @param string $handler Callback function which accepts row data as parameter
	     * @param string $mode    Row data type
	     *
	     * @return int Row count
	     */
	    function iterate_results($sql, $mode=OBJECT)
	    {
            $args=func_get_args();

            $mode=array_pop($args);
            if($mode!=OBJECT && $mode!=ARRAY_A && $mode!=ARRAY_N){
                array_push($args, $mode);
                $mode=OBJECT;
            }

            if (!call_user_func_array(array($this, "exec"), $args)) {
                return;
            }

	        // 长期保持的结果集，避免被清除
	        $persistent_rs=$this->_result;
	        $this->_result=null;

	        while (true) {
	            switch($mode){
	            case OBJECT:
	                $r=mysqli_fetch_object($persistent_rs);
	                break;
	            case ARRAY_A:
	                $r=mysqli_fetch_assoc($persistent_rs);
	                break;
	            case ARRAY_N:
	                $r=mysqli_fetch_row($persistent_rs);
	                break;
	            }

	            if (empty($r)) {
	                break;
	            }

                yield $r;
	        }

	        $persistent_rs->close();
	    }

        function has_table($table){
            $tables=$this->get_col("show tables");
            return in_array($table, $tables);
        }

        private function get_schema_field_type($text){
            if(($p=strpos($text, '('))>0)
                return substr($text, 0, $p);
            else
                return $text;
        }

        private function get_schema_field_length($text){
            if(($p=strpos($text, '('))>0)
                return intval(substr($text, $p+1, strpos($text, ')')-$p));
            else
                return 0;
        }

        function get_schema($table){
            $schema=array(
                'type'=>'table',
                'name'=>$table,
                'column'=>array(),  // array style
                'col'=>array(), // hash map style
                'pkey'=>array()
            );

            $cols=$this->get_results("desc $table");
            $idx=0;
            foreach($cols as $c){
                $column=array(
                    "name"=>$c->Field,
                    "type"=>$this->get_schema_field_type($c->Type),
                    "default"=>$c->Default,
                    "length"=>$this->get_schema_field_length($c->Type),
                    "precision"=>null,
                    "nullable"=> strtoupper($c->Null)=='YES',
                    "idx"=>$idx
                );
                $schema['column'][]=$column;
                $schema['col'][$c->Field]=$column;

                if(strtoupper($c->Key)=='PRI')
                    $schema['pkey'][]=$c->Field;
            }

            return $schema;
        }
    }
}
?>
