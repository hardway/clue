<?php
namespace Clue\RPC;
include __DIR__."/common.php";

/**
 * Example:
 *
 * server.php:
 * -------------------------------
 * class TestService{
 *   function reverse($str){
 *     return implode("", array_reverse(str_split($str)));
 *   }
 * }
 *
 * // Put this code in handler of url /api/test
 * Clue\RPC\Server::bind(new TestService);
 *
 * client.php:
 * -------------------------------
 * $c=new Clue\RPC\Client("http://example.com/api/test", array('debug'=>true, 'secret'=>'abc', 'username'=>"hardway"));
 * echo $c->reverse("hello");
 *
 * > olleh
 *
 */
class Client{
	use \Clue\Logger;
	/**
	 * @param $endpoint	远程调用端的URL，格式 https://127.0.0.1/api/EchoService
	 * @param $options 	选项，比如是否加密会话，通讯格式为php或者json
	 */
	function __construct($endpoint, $options=array()){
		$this->endpoint=$endpoint;
		$this->debug=@$options['debug']===true;
		$this->client=@$options['client'];		// 客户ID和令牌，用于身份认证
		$this->token=@$options['token'];
		$this->secret=@$options['secret'];		// 预共享密钥，用于加密通信数据
	}

	function __call($name, $arguments){
		$this->log("[RPC] Connecting to $this->endpoint");

		$payload=array('method'=>$name, 'params'=>$arguments);
		if($this->client){
			$payload['client']=$this->client;
			$payload['token']=$this->token;
		}

		$payload=json_encode($payload);
		if($this->secret){
			$payload=clue_rpc_encrypt($payload, $this->secret);
		}

		$c=curl_init($this->endpoint);
		curl_setopt($c, CURLOPT_POST, 1);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_POSTFIELDS, $payload);

		$response=curl_exec($c);
		$header = curl_getinfo($c);
		curl_close($c);

		if($header['http_code']!=200) throw new \Exception("HTTP {$header['http_code']} $response");

		if($this->secret){
			if($this->debug){
				$this->log(sprintf("[RPC] ENCRYPTED RESPONSE:\n====================\n%s\n\n", $response));
			}
			$response=clue_rpc_decrypt($response, $this->secret);
		}

		if($this->debug){
			$this->log(sprintf("[RPC] RESPONSE:\n====================\n%s\n\n", $response));
		}

		return json_decode($response, true);
	}
}
