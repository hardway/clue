<?php
namespace Clue\Database{

/**
 * TODO: UDP接口
 *
 * Usage Example:
 *
 * $db=new InfluxDB('test', "db.dev");
 * $db->ping(10);
 * $db->create_database("test");
 * $db->write('cpu_load_short', 0.64, ['host'=>'server01', 'region'=>'us-west']);
 * $db->write('cpu_load_short', 0.01, ['host'=>'server01', 'region'=>'us-west'], time() - 86400);
 * $db->query("select * from cpu_load_short");
 */
class Influx{
	private $endpoint;

	function __construct(array $param){
		$server=@$param['host'] ?: '127.0.0.1';
		$port=@$param['port'] ?: 8086;

		$this->endpoint="http://$server:$port";
		$this->db=@$param['db']; // 当前数据库

		$this->curl=curl_init();

		// 尝试连接
		if(!$this->ping()){
			throw new \Exception("Can't connect to server: $server:$port");
		}
	}

	function _api($type, $param=[]){
		$url="$this->endpoint/$type";

		curl_reset($this->curl);
		curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, 5);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

		switch(strtolower($type)){
			case 'write':
				$url.="?db=$this->db";
				$payload=is_array($param) ? implode("\n", $param) : $param;

				curl_setopt($this->curl, CURLOPT_POST, true);
				curl_setopt($this->curl, CURLOPT_POSTFIELDS, $payload);
				break;

			case 'ping':
			case 'query':
				if(!empty($param)){
					$url.="?".http_build_query($param);
				}
				break;

			default:
				throw new \Exception("No API defined for $type");
		}

		if(defined("DEBUG") && DEBUG) error_log($url);

		curl_setopt($this->curl, CURLOPT_URL, $url);
		$r=curl_exec($this->curl);

		$this->http_status=intval(curl_getinfo($this->curl, CURLINFO_HTTP_CODE));
		$r=empty($r) ? true : json_decode($r);

		if(!in_array($this->http_status, [200, 204])){
			throw new \Exception($r->error, $this->http_status);
		}

		return is_object($r) ? $r->results : true;
	}

	function ping($wait=1){
		$param=[];

		if($wait > 0) $param['wait_for_leader']=$wait.'s';

		return $this->_api("ping", $param);
	}

	function create_database($name){
		return $this->_api('query', ['q'=>"CREATE DATABASE $name"]);
	}

	function write($measurement, $values=[], $tags=[], $timestamp=null){
		if(empty($timestamp)){
			$timestamp=time();
		}
		elseif(is_numeric($timestamp)){
			// Do Nothing
		}
		else{
			$timestamp=strtotime($timestamp);
		}

		$payload="$measurement";
		if($tags) foreach($tags as $k=>$v){
			$payload.=",$k=$v";
		}
		$payload.=" ";

		// Unify values to array()
		if(!is_array($values)) $values=['value'=>$values];

		foreach($values as $k=>$v){
			$payload.="$k=$v,";
		}
		$payload=trim($payload, ',');

		$payload.=" {$timestamp}000000000";

		return $this->_api("write", $payload);
	}

	function query($sql){
		return $this->_api("query", ['db'=>$this->db, 'q'=>$sql]);
	}
}
}
