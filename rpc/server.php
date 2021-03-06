<?php
namespace Clue\RPC;
include_once __DIR__."/common.php";

/**
 * 如果需要启用BookKeeping，需要预先创建表格（见TestCase）
 * )
 */
class Server{
	use \Clue\Traits\Bookkeeper;

	static $record;	// 用于临时记录bookkeep

	static function error_acl($err){
		self::bookkeep(self::$record+['status'=>403, 'response'=>'Forbindden']);

		header("HTTP/1.0 403 Forbidden.");
		exit($err);
	}

	static function error_rpc($err){
		self::bookkeep(self::$record+['status'=>422, 'response'=>$err]);

		header("HTTP/1.0 422 $err");
		exit($err);
	}

	static function error_app($err){
		self::bookkeep(self::$record+['status'=>500, 'response'=>'Application error: '.$err]);

		header("HTTP/1.0 500 Application error.");
		error_log($err);
		exit($err);
	}

	static function bind($svc, $options=array()){
		error_log("[RPC] Endpoint: ".@$_SERVER['REQUEST_URI']);
		error_log("[RPC] Connected from: ".@$_SERVER['REMOTE_ADDR']);

		$payload=file_get_contents("php://input");

        if(isset($options['secret'])){
            $payload=clue_rpc_decrypt($payload, $options['secret']);
        }

        // 自动识别gzip压缩内容
        if("\x1f\x8b"==substr($payload, 0, 2)) $payload=gzdecode($payload);

		$payload = json_decode($payload, true);

		$method=$payload['method'];
		$params=$payload['params'];

		self::$record=[
			'call_time'=>date("Y-m-d H:i:s"),
			'type'=>'in',
			'endpoint'=>@$_SERVER['REQUEST_URI'],
			'ip'=>ip2long(@$_SERVER['REMOTE_ADDR']),
			'client'=>@$payload['client'],
			'method'=>$method,
			'request'=>json_encode($params)
		];

		if(empty($method)){
			self::error_rpc("Empty Method.");
		}
		elseif(!method_exists($svc, $method)){
			self::error_rpc("Invalid Method.");
		}

		try{
			if(method_exists($svc, "auth")){
				error_log("[RPC] Client ID: ".$payload['client']);
				error_log("[RPC] Client Token: ".\Clue\mask_string($payload['token']));

				$pass=$svc->auth($payload['client'], $payload['token']);
				if(!$pass) self::error_acl("Invalid combination of CLIENT and TOKEN");
			}

			$r=call_user_func_array(array($svc, $method), $params);
		}
		catch(\Exception $e){
			self::error_app(sprintf("[RPC] Server Error: %d %s", $e->getCode(), $e->getMessage()));
		}

		$r=json_encode($r);

		self::bookkeep(self::$record+['status'=>200, 'response'=>$r]);

		if(isset($options['secret'])){
			$r=clue_rpc_encrypt($r, $options['secret']);
		}
		exit($r);
	}
}
