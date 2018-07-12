<?php
namespace Clue\Database{

/**
 * ClickHouse
 *
 * 类似MySQL的接口，但是不支持Update
 */
class ClickHouse extends \Clue\Database{
    private $endpoint;

    function __construct(array $options=[]){
        $default_options=[
            'host'=>'127.0.0.1',
            'port'=>8123,
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

    function _api($type, $param=[], $content=null){
        $url="$this->endpoint";
        if(isset($param['query'])){
            $this->last_query=$param['query'];
            if($type=='query') $param['query'].=" FORMAT TSVWithNames";
        }

        if($param) $url.="?".http_build_query($param);

        curl_reset($this->curl);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $this->options['connection_timeout']);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->options['timeout']);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

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


        curl_setopt($this->curl, CURLOPT_URL, $url);
        $raw=curl_exec($this->curl);


        $this->http_status=intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE));

        if(!in_array($this->http_status, [200, 204])){
            $error=$raw;

            if($this->http_status==0 && empty($raw)){
                $error="Timeout";
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
                    $table[]=explode("\t", $line);
                }
                return $table;

            default:
        }

        return $raw;
    }

    function ping(){
        $param=[];

        return $this->_api("ping", $param);
    }

    function get_var($sql){
        $rs=$this->query($sql);
        $cols=array_shift($rs);

        return @$rs[0][0];
    }

    function get_col($sql){
        $rs=$this->query($sql);
        $cols=array_shift($rs);

        return array_map(function($r){return $r[0];}, $rs);
    }

    function get_row($sql, $mode=OBJECT){
        $rs=$this->query($sql);
        $cols=array_shift($rs);

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
        $rs=$this->query($sql);
        $cols=array_shift($rs);

        $ret=[];
        foreach($rs as $r){
            switch($mode){
                case OBJECT:
                    $ret[]=\Clue\ary2obj(array_combine($cols, $r));
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
     * 插入数据
     * @param $table 表名
     * @param $row 单行（也可以是多行数据）
     *          [1, 'a', ...] 或 [[1,'a'], [2, 'b'], ...]
     * @param $columns 列名(默认为表格的列顺序)
     */
    function insert($table, $row, array $columns=[]){
        $processed=0;

        $query="insert into $table";
        if($columns) $query.="(".implode(',', $columns).")";
        $query.=" FORMAT TabSeparated";

        $rows=is_array(@$row[0]) ? $row : [$row];
        $data="";
        foreach($rows as $row){
            $data.=implode("\t", $row)."\n";
        }

        $this->_api('write', ['database'=>$this->db, 'query'=>$query], $data);

        return $processed;
    }

    function exec($sql){
        return $this->_api('write', ['database'=>$this->db, 'query'=>$sql]);
    }

    function query($sql){
        return $this->_api("query", ['database'=>$this->db, 'query'=>$sql]);
    }
}
}
