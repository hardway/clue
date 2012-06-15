<?php  
namespace Clue{
    $DEFAULT_APPLICATION_CONFIG=array(
        'debug'=>true,
        'maintenance'=>false
    );
    
    class Application{
        public $base;
        public $config;
        public $db;
        public $router;
        public $options=array(
            "url_rewrite"=>true
        );
        public $session;
        public $layout;
        
        public $controller;
        public $action;
        public $params;
        
        public $initialized=false;
        
        protected $beforeDispatchHandler=null;
        
        protected $restricted_zone=array();
        protected $auth;
        
        function __construct(){
        }
        
        function init($options=null){
            global $DEFAULT_APPLICATION_CONFIG;
            
            // Extend options
            if(is_array($options)) foreach($options as $o=>$v){
                $this->options[$o]=$v;
            }
            
            $this->config=$DEFAULT_APPLICATION_CONFIG;
            
            if(isset($this->options['config'])){
                $this->config=array_merge($this->config, $this->options['config']);
            }
            
            $this->router=new Router(array(
                'url_rewrite'=>$this->options["url_rewrite"]
            ));
            
            if(isset($this->config['debug']) && $this->config['debug']==true){
                require_once __DIR__."/debug.php";
                set_exception_handler(array("Clue\Debug","view_exception"));
                set_error_handler(array("Clue\Debug", "view_error"));
            }
            else if(!isset($this->config['log']) || $this->config['log']!==false){
                if(!isset($this->config['log']) || !is_string($this->config['log']))
                    $this->config['log']=APP_ROOT . DIRECTORY_SEPARATOR . 'log';

                require_once __DIR__."/log.php";
                Clue_Log::set_log_dir($this->config['log']);
                set_exception_handler(array("Clue_Log","log_exception"));
                set_error_handler(array("Clue_Log", "log_error"));
            }
            
            $this->session=new Session();
            
            if($this->config['database']){
                $this->set_default_database((array)$this->config['database']);
            }
            
            $this->auth=null;
            
            $this->initialized=true;
        }
        
        function set_default_database($param){
            $this->db=Database::create($param['type'], $param);
            
            // throw exception if database is not connectable
            if($this->db->errors){
                throw new Exception($this->db->lasterror['error']);
            }
        }

        function has_layout(){
            return $this->layout;
        }
        
        function set_layout($layout=null){
            $this->layout=new Clue_Layout($layout);
            // TODO
        }
        
        static protected $instance;
        
        function restrict_access($controller, $auth, $access=null){
            $this->restricted_zone[]=array(
                'controller'=>$controller,
                'auth'=>$auth,
                'access'=>$access
            );
            
            $ctl=empty($controller) ? 'auth' : "{$controller}_auth";
            
            $url=empty($controller) ? "/login" : "/$controller/login";
            $this->router->connect($url, array('controller'=>$ctl, 'action'=>'login'));
            
            $url=empty($controller) ? "/logout" : "/$controller/logout";
            $this->router->connect($url, array('controller'=>$ctl, 'action'=>'logout'));
        }
        
        public function identify(){
            if(isset($_COOKIE['userid'])){
                return $_COOKIE['userid'];
            }
            
            return false;
        }
        
        public function login($username, $password, $extra=array()){
            if(empty($this->auth)) return false;
            
            $r=$this->auth->authenticate($username, $password, $extra);
            
            if(!empty($r)){
                setcookie('userid', $r['id']);
                setcookie('username', $r['name']);
                return true;
            }
            
            return false;
        }
        
        public function logout(){
            setcookie("userid", "", time() - 3600);
            setcookie("username", "", time() - 3600);
        }
        
        public function authorize($resource){
            if(empty($this->auth)) return false;

            return $this->auth->authorize($resource);
        }
        
        function before_dispatch($callback=null){
            $this->beforeDispatchHandler=$callback;
        }
        
        function prepare(){
            $url=isset($_SERVER['HTTP_X_REWRITE_URL']) ? 
                    $_SERVER['HTTP_X_REWRITE_URL'] : 
                    $_SERVER['REQUEST_URI'];
                    
            $map=$this->router->resolve($url);
                        
            $this->controller=$map['controller'];
            $this->action=$map['action'];
            $this->params=$map['params'];
        }
        
        function dispatch(){            
            foreach($this->restricted_zone as $rz){
                if(preg_match('/^'.$rz['controller'].'/i', $this->controller)){
                    if(in_array($this->action, array('login', 'logout'))){
                        $this->auth=$rz['auth'];
                    }
                    else if($rz['auth']->authorize($this->identify(), $rz['access'])==false){
                        $this->router->redirect_route($rz['controller'], 'login');
                    }
                    
                    break;
                }
            }
            
            $r=$this->router->route($this->controller, $this->action, $this->params);
            $ret=call_user_func_array(array($r['handler'], $r['handler']->action), $r['args']);
        }

        function set_msg($code, $message){
            $this->session->put($code, $message);
        }

        function msg($code){
            $message=$this->session->take($code);
            if(empty($message)) return false;

            $type="info";
            if(preg_match('/error/i', $code)) $type='error';
            else if(preg_match('/warning/i', $code)) $type='warning';
            else if(preg_match('/debug/i', $code)) $type='debug';

            echo <<<MSG
            <div class='msg'><div class='$type'>$message</div></div>
MSG;
        }
        
        function run(){
            $this->prepare();
            
            // call plugin
            if(is_callable($this->beforeDispatchHandler)){
                call_user_func($this->beforeDispatchHandler, $this->controller, $this->action, $this->params);
            }
            
            $this->dispatch();
        }        
    }
    
}

namespace{
    $app=new Clue\Application(APP_ROOT);
    
    // global short cut
    function url_for($controller, $action='index', $params=array()){
        global $app;
        return $app->router->url_for($controller, $action, $params);
    }
    
    function messenger(){
        global $app;
        return $app->session;
    }
    
    function appbase(){
        return APP_BASE;
    }
    
    function assets($asset=null){
        $url=(APP_BASE=="\\" || APP_BASE=='/') ? '/assets' : appbase()."/assets";
        if(!empty($asset)) $url.="/$asset";
        
        return $url;
    }
}
?>
