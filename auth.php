<?php 
namespace Clue;

# This is a sample implementation
# Might use trait when PHP5.4 is popular

# Resource, might be an URI or a feature code
# Verb:
#   a   access, used in Application by default
#   m   manage
#   p   post, HTTP VERB

class Auth{
    static function current(){
        return isset($_SESSION['user']) ? unserialize($_SESSION['user']) : new static("Guest");
    }

    static function encrypt_password($raw){
        return sha1($raw);
    }

    protected $acl_rules=array();

    function __construct($name='Guest', $username=null){
        $this->name=$name;
        $this->username=$username;
    }

    function login($info){
        global $app;

        $username=$info['username'];
        $password=self::encrypt_password($info['password']);

        if($u=$app['user_class']::find_one(array('username'=>$username, 'password'=>$password))){
            $this->id=$u->id;
            $this->username=$u->username;
            $this->name=$u->name;

            $_SESSION['user']=serialize($this);

            return true;
        }       
        return false;
    }

    function logout(){
        unset($_SESSION['user']);
        session_destroy();
    }

    function allow($resource, $verb='a'){
        $hash=md5(serialize(array($resource, $verb)));
        $this->acl_rules[$hash]=array($resource, $verb, true);
    }

    function deny($resource, $verb='a'){
        $hash=md5(serialize(array($resource, $verb)));
        $this->acl_rules[$hash]=array($resource, $verb, false);
    }

    function authorize($resource, $verb='a'){
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
                    return false;
                }
            }
        }
        return false;
    }

    function authorize_failed($resource, $verb){
        // Handler code
        global $app;
        header("Location: ".$app['webbase']);
        exit();
    }
}
