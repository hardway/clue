<?php
namespace Clue\RPC;
include_once __DIR__."/common.php";

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
	use \Clue\Traits\Logger;
	use \Clue\Traits\Bookkeeper;

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
		$this->timeout=@$options['timeout'] ?: 30;
		$this->proxy=@$options['proxy'];

		$this->cache_dir=null;
	}

	function enable_cache($cache_dir, $cache_ttl=3600){
        if(!is_dir($cache_dir)){
            mkdir($cache_dir, 0775, true);
        }
        $this->cache_dir=$cache_dir;
        $this->cache_ttl=$cache_ttl;
    }

	function disable_cache(){
		$this->cache_dir=null;
		$this->cache_ttl=0;
	}

	function __call($name, $arguments){
		$this->info("[RPC] Connecting to $this->endpoint");

		$payload=array('method'=>$name, 'params'=>$arguments);
		if($this->client){
			$payload['client']=$this->client;
			$payload['token']=$this->token;
		}

		// For Bookkeeping
		$record=[
			'call_time'=>date("Y-m-d H:i:s"),
			'type'=>'out',
			'endpoint'=>$this->endpoint,
			'client'=>$this->client,
			'method'=>$name,
			'request'=>json_encode($arguments)
		];

		if($this->debug) $this->debug("[RPC] Payload: ".json_encode($payload));

		$payload=json_encode($payload);

		// 检查cache是否命中
		if($this->cache_dir){
			$cache_file="$this->cache_dir/".md5($this->endpoint.$this->client.$this->token.$payload);

			if(is_file($cache_file) && time()-filemtime($cache_file)<$this->cache_ttl){
				return json_decode(file_get_contents($cache_file), true);
			}
		}

		if($this->secret){
			$payload=clue_rpc_encrypt($payload, $this->secret);
		}

		$c=curl_init();

		curl_setopt($c, CURLOPT_POST, 1);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		// 使用curl follow不会重新post
		// curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $payload);

		// RPC不能超过5秒
		curl_setopt($c, CURLOPT_CONNECTTIMEOUT, $this->timeout);
		curl_setopt($c, CURLOPT_TIMEOUT, $this->timeout);

		// No SSL Verification
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);

		// Proxy
		if($this->proxy){
			if(preg_match('/^sock[45s]?:\/\/([a-z0-9\-_\.]+):(\d+)$/i', $this->proxy, $m)){
				list($_, $host, $port)=$m;
				curl_setopt($c, CURLOPT_PROXY, $host);
				curl_setopt($c, CURLOPT_PROXYPORT, $port);

				if(!defined('CURLPROXY_SOCKS5_HOSTNAME')) define('CURLPROXY_SOCKS5_HOSTNAME', 7);
				curl_setopt($c, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
				// curl_setopt($c, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
			}
			elseif(preg_match('/^(http:\/\/)?([a-z0-9\-_\.]+):(\d+)/i', $this->proxy, $m)){
				list($_, $scheme, $host, $port)=$m;

				curl_setopt($c, CURLOPT_PROXY, $host);
				curl_setopt($c, CURLOPT_PROXYPORT, $port);
			}
		}

		// curl_setopt($c, CURLOPT_HTTPHEADER, array("Expect:"));
		curl_setopt($c, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);

		$redirect=3;
redirect:
		curl_setopt($c, CURLOPT_URL, $this->endpoint);
		$response=curl_exec($c);
		$header = curl_getinfo($c);

		if(curl_errno($c)){
			$err=sprintf("NETWORK %d: %s", curl_errno($c), curl_error($c));
			$this->bookkeep($record+['status'=>503, 'response'=>$err]);
			throw new \Exception($err);
		}

		if($header['http_code']>=400){
			$this->bookkeep($record+['status'=>$header['http_code'], 'response'=>$response]);

			if($header['http_code']==500){
				if(preg_match('/^\d+ /', $response)){
					list($code, $error)=explode(" ", $response, 2);
					throw new \Exception($error, $code);
				}
				else{
					throw new \Exception($response);
				}
			}
			else
				throw new \Exception("HTTP {$header['http_code']} $response");
		}
		elseif(preg_match('/3\d\d/', $header['http_code'])){
			// 自动Follow新的URL
			$this->endpoint=$header['redirect_url'];
			$redirect--;
			if($redirect>0) goto redirect;
		}

		curl_close($c);

		if($this->secret){
			if($this->debug){
				$this->debug(sprintf("[RPC] ENCRYPTED RESPONSE:\n====================\n%s\n\n", $response));
			}
			$response=clue_rpc_decrypt($response, $this->secret);
		}

		$this->bookkeep($record+['status'=>200, 'response'=>$response]);

		if($this->debug){
			$this->debug(sprintf("[RPC] RESPONSE:\n====================\n%s\n\n", $response));
		}

		if($this->cache_dir){
			$cache_file="$this->cache_dir/".md5($this->endpoint.$this->client.$this->token.$payload);
			file_put_contents($cache_file, $response);
		}

		return json_decode($response, true);
	}
}
