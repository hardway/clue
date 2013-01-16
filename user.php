<?php 
namespace Clue;

# This is a sample implementation
# Might use trait when PHP5.4 is popular

class User{
	static function current(){
		return isset($_SESSION['user']) ? unserialize($_SESSION['user']) : new static("Guest");
	}

	protected $acl_rules=array();

	function __construct($name='Guest', $username=null){
		$this->name=$name;
		$this->username=$username;
	}

	function login($name, $pass){
		$this->username=$this->name=$name;
		$_SESSION['user']=serialize($this);

		return true;
	}

	function logout(){
		unset($_SESSION['user']);
		session_destroy();
	}

	function allow($resource, $verb='r'){
		$hash=md5(serialize(array($resource, $verb)));
		$this->acl_rules[$hash]=array($resource, $verb, true);
	}

	function deny($resource, $verb='r'){
		$hash=md5(serialize(array($resource, $verb)));
		$this->acl_rules[$hash]=array($resource, $verb, false);
	}

	function authorize($resource, $verb='r'){
		$rules=array_values($this->acl_rules);

		usort($rules, function($a, $b){
			return strlen($a[0]) < strlen($b[0]);
		});

		foreach($rules as $r){
			$pattern=str_replace("*", ".*", $r[0]);
			if(preg_match("|$pattern|", $resource) && $verb==$r[1]){
				if($r[2]){
					return true;
				}
				else{
					$this->authorize_failed($resource, $verb);
				}
			}
		}
		$this->authorize_failed($resource, $verb);
	}

	function authorize_failed($resource, $verb){
		// Handler code
		global $app;
		header("Location: ".$app['webbase']);
	}
}
