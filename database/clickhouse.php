<?php
namespace Clue\Database{

/**
 * ClickHouse
 *
 * 类似MySQL的接口，但是不支持Update
 * TODO: 增加binary协议
 * TODO: 与mysql保持兼容
 */
class ClickHouse extends \Clue\Database{
    private $endpoint;

    static function escape_tab_data($string){
        return str_replace(["\\", "\n", "\r", "\t"], ["\\\\", "\\n", "\\r", "\\t"], $string);
    }

    function __construct(array $options=[]){
        $default_options=[
            'host'=>'127.0.0.1',
            'port'=>8123,
            'username'=>'default',
            'password'=>null,
            'connection_timeout'=>5,
            'timeout'=>30,
            'debug'=>false,
        ];

        $this->options=$options+$default_options;

        $this->endpoint="http://{$this->options['host']}:{$this->options['port']}";
        $this->db=@$this->options['db']; // 当前数据库

        $this->curl=curl_init();

        // 尝试连接
        if(!$this->ping()){
            throw new \Exception("Can't connect to server: $this->endpoint");
        }
    }

    function _curl_init($url){
        curl_reset($this->curl);

        curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
        curl_setopt($this->curl, CURLOPT_USERPWD, $this->options['username'].($this->options['password'] ? ":".$this->options['password']: ""));
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $this->options['connection_timeout']);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->options['timeout']);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_URL, $url);
    }

    function _api($type, $param=[], $content=null){
        $url="$this->endpoint";
        if(isset($param['query'])){
            $this->last_query=$param['query'];

            if($type=='query') $param['query'].=" FORMAT TSVWithNames";
        }

        if($param) $url.="?".http_build_query($param);

        $this->_curl_init($url);

        $type=strtolower($type);
        switch($type){
            case 'write':
                curl_setopt($this->curl, CURLOPT_POST, true);
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, $content);
                break;

            case 'ping':
            case 'query':
                break;

            default:
                throw new \Exception("No API defined for $type");
        }

        $raw=curl_exec($this->curl);


        $this->http_status=intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE));

        if(!in_array($this->http_status, [200, 204])){
            $error=$raw;

            if($this->http_status==0 && empty($raw)){
                $error=curl_error($this->curl) ?: "Unknown network error";
            }

            throw new \Exception($error, $this->http_status);
        }

        switch($type){
            case 'ping':
                return preg_match('/Ok./', $raw);

            case 'query':
                // 解析表格数据
                // TODO: 列名关联 (FORMAT JSON)
                $table=[];
                foreach(explode("\n", $raw) as $line){
                    if(empty($line)) break;
                    $table[]=array_map(function($field){return str_replace("\\\\", "\\", $field);}, str_getcsv($line, "\t"));
                }
                return $table;

            default:
        }

        return $raw;
    }

    function _api_iterate(array $param){
        $url="$this->endpoint";

        assert(isset($param['query']));

        $this->last_query=$param['query'];
        $param['query'].=" FORMAT JSONEachRow";

        $url.="?".http_build_query($param);

        $this->_curl_init($url);

        $raw=curl_exec($this->curl);

        $this->http_status=intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE));

        if(!in_array($this->http_status, [200, 204])){
            $error=$raw;

            if($this->http_status==0 && empty($raw)){
                $error=curl_error($this->curl) ?: "Unknown network error";
            }

            throw new \Exception($error, $this->http_status);
        }

        foreach(explode("\n", $raw) as $line){
            if(empty($line)) break;
            yield json_decode($line, true);
        }
        return;
    }

    function ping(){
        $param=[];

        return $this->_api("ping", $param);
    }

    function get_var($sql){
        $sql=call_user_func_array(array($this, "format"), func_get_args());

        $rs=$this->_api("query", ['database'=>$this->db, 'query'=>$sql]);
        $columns=array_shift($rs);

        return @$rs[0][0];
    }

    function get_col($sql){
        $sql=call_user_func_array(array($this, "format"), func_get_args());
        $rs=$this->_api("query", ['database'=>$this->db, 'query'=>$sql]);
        $columns=array_shift($rs);

        return array_map(function($r){return $r[0];}, $rs);
    }

    function get_row($sql, $mode=OBJECT){
        $sql=call_user_func_array(array($this, "format"), func_get_args());
        $rs=$this->_api("query", ['database'=>$this->db, 'query'=>$sql]);
        $cols=array_shift($rs);

        if(empty($rs)) return null;

        $mode=func_get_arg(func_num_args()-1);
        if($mode!=OBJECT && $mode!=ARRAY_A && $mode!=ARRAY_N){
            $mode=OBJECT;
        }

        switch($mode){
            case OBJECT:
                return \Clue\ary2obj(array_combine($cols, $rs[0]));

            case ARRAY_A:
                return array_combine($cols, $rs[0]);

            default:
                return $rs[0];
        }
    }

    function get_results($sql, $mode=OBJECT){
        $sql=call_user_func_array(array($this, "format"), func_get_args());
        $rs=$this->_api("query", ['database'=>$this->db, 'query'=>$sql]);

        $cols=array_shift($rs);

        $ret=[];
        foreach($rs as $r){
            switch($mode){
                case OBJECT:
                    $ret[]=(object)array_combine($cols, $r);
                    break;

                case ARRAY_A:
                    $ret[]=array_combine($cols, $r);
                    break;

                default:
                    $ret[]=$r;
                    break;
            }
        }

        return $ret;
    }

    /**
     * Result generator
     *
     * @param string $sql     SQL Statement
     * @param string $mode    Row data type
     *
     * @return int Row count
     */
    function iterate_results($sql, $mode=OBJECT){
        $sql=call_user_func_array(array($this, "format"), func_get_args());

        $rs=$this->_api_iterate(['database'=>$this->db, 'query'=>$sql]);
        $ret=[];
        foreach($rs as $r){
            switch($mode){
                case OBJECT:
                    yield \Clue\ary2obj($r);
                    break;

                case ARRAY_A:
                default:
                    yield $r;
                    break;
            }
        }
    }

    /**
     * 插入数据
     */
    function insert($table, $row, array $columns=[]){
        if(empty($columns) && !isset($row[0])) return $this->insert_row($table, $row);

        return $this->insert_batch($table, $row, $columns);
    }

    /**
     * 插入数据
     * @param $table 表名
     * @param $row 单行（也可以是多行数据）
     *          [1, 'a', ...] 或 [[1,'a'], [2, 'b'], ...]
     * @param $columns 列名(默认为表格的列顺序)
     */
    function insert_batch($table, $row, array $columns=[]){
        $processed=0;

        $query="insert into $table";
        if($columns) $query.="(".implode(',', $columns).")";
        $query.=" FORMAT TabSeparated";

        $rows=is_array(@$row[0]) ? $row : [$row];
        $data="";
        foreach($rows as $row){
            $row=array_map([$this, 'escape_tab_data'], $row);

            // NOTE, Clickhouse不能随便加quote双引号，否则导致解析失败
            $data.=implode("\t", $row)."\n";
        }

        $this->_api('write', ['database'=>$this->db, 'query'=>$query], $data);

        return $processed;
    }

    /**
     * 插入单行记录
     */
    function insert_row($table, $row){
        $query="insert into $table FORMAT JSONEachRow";

        $ok=$this->_api('write', ['database'=>$this->db, 'query'=>$query], json_encode($row)."\n");
        return $ok;
    }

    /**
     * 插入单行记录
     */
    function insert_rows($table, $rows){
        $query="insert into $table FORMAT JSONEachRow";

        $ok=$this->_api('write', ['database'=>$this->db, 'query'=>$query], implode("\n", array_map('json_encode', $rows)));
        return $ok;
    }

    function update($table, $fields, $where){
        $updates=array();

        foreach ($fields as $c=>$v) {
            $updates[]="`$c`=".$this->quote($v);
        }
        $sql="
            ALTER TABLE `$table` UPDATE ".implode(', ', $updates)."
            WHERE $where
        ";

        return $this->exec($sql);
    }

    function exec($sql){
        $sql=call_user_func_array(array($this, "format"), func_get_args());
        $param=['database'=>$this->db, 'query'=>$sql];

        if(preg_match('/^(create|drop)\s+database/i', $sql)) unset($param['database']);

        return $this->_api('write', $param);
    }

    function query($sql){
        $sql=call_user_func_array(array($this, "format"), func_get_args());
        return $this->_api("query", ['database'=>$this->db, 'query'=>$sql]);
    }

    function has_table($table){
        $tables=$this->get_col("show tables");

        return in_array($table, $tables);
    }

    /**
     * 创建表
     */
    function create_table($table, array $fields, $extra=[]){
        $extra_default=['engine'=>'MergeTree Order By id'];
        $extra+=$extra_default;

        $field_mapping=[];
        foreach($fields as $name=>$type){
            if(preg_match('/int/i', $type)){
                $type='UInt64';
            }
            elseif(preg_match('/varchar/', $type)){
                $type='String';
            }
            elseif(preg_match('/datetime/i', $type)){
                $type='DateTime';
            }
            elseif(preg_match('/date/i', $type)){
                $type='Date';
            }

            $cols[]="`$name` $type";
        }

        $sql="CREATE TABLE `$table`(\n".implode(",\n", $cols)."\n)";
        foreach($extra as $name=>$value){
            $name=strtolower($name);
            if($name=='engine') {
                $sql.="ENGINE=$value";
            }
        }

        $this->exec($sql);
    }
}
}
