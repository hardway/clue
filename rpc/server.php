<?php
namespace Clue\RPC;
include_once __DIR__."/common.php";

class Server{
	use \Clue\Traits\Logger;

	static function error_acl($err){
		header("HTTP/1.0 403 Forbidden.");
		exit($err);
	}

	static function error_rpc($err){
		header("HTTP/1.0 422 Unprocessable entity.");
		exit($err);
	}

	static function error_app($err){
		header("HTTP/1.0 500 Application error.");
		error_log($err);
		exit($err);
	}

	static function bind($svc, $options=array()){
		self::log("[RPC] Endpoint: ".$_SERVER['REQUEST_URI']);
		self::log("[RPC] Connected from: ".$_SERVER['REMOTE_ADDR']);

		$payload=file_get_contents("php://input");
		if(isset($options['secret'])){
			$payload=clue_rpc_decrypt($payload, $options['secret']);
		}

		$payload = json_decode($payload, true);
		$method=$payload['method'];
		$params=$payload['params'];

		if(empty($method)){
			self::error_rpc("Empty Method.");
		}
		elseif(!method_exists($svc, $method)){
			self::error_rpc("Invalid Method.");
		}

		try{
			if(method_exists($svc, "auth")){
				self::log("[RPC] Client ID: ".$payload['client']);
				self::log("[RPC] Client Token: ".$payload['token']);

				$pass=$svc->auth($payload['client'], $payload['token']);
				if(!$pass) self::error_acl("Invalid combination of CLIENT and TOKEN");
			}

			$r=call_user_func_array(array($svc, $method), $params);
		}
		catch(\Exception $e){
			self::error_app(sprintf("%03d %s", $e->getCode(), $e->getMessage()));
		}

		$r=json_encode($r);

		if(isset($options['secret'])){
			$r=clue_rpc_encrypt($r, $options['secret']);
		}
		exit($r);
	}
}
