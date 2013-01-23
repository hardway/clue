<?php  
namespace Clue{
    class Application implements \ArrayAccess{
        private $_values;

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
        
        function __construct($values=array()){
            $this->_values=$values;

            if(!isset($this['webbase'])) $this['webbase']=str_replace("\\", '/', dirname($_SERVER['SCRIPT_NAME']));
            if(!isset($this['user_class'])) $this['user_class']="Clue\\User";
            if(!isset($this['user'])){
                $this['user']=$this->share(function($c){ 
                    $cls=$c['user_class'];
                    return $cls::current();
                });
            }
            if(!isset($this['router'])){
                $this['router']=new Router();            
            }

            $this->init();
        }

        function offsetGet($id){
            $callable = is_object($this->_values[$id]) && method_exists($this->_values[$id], '__invoke');
            return $callable ? $this->_values[$id]($this) : $this->_values[$id];
        }

        function offsetSet($id, $val)   { $this->_values[$id]=$val; }
        function offsetExists($id)      { return array_key_exists($id, $this->_values); }
        function offsetUnset($id)       { unset($this->_value[$id]); }
        // REF: Pimple
        public function share(\Closure $callable){
            return function ($c) use ($callable) {
                static $object;
                if (null === $object) {
                    $object = $callable($c);
                }
                return $object;
            };
        }

        function init(){
            $this->guard=new Guard($this['config']);
            
            if($this['config']['debug']===false){
                $this->guard->display_level=0;
                $this->guard->stop_level=0;
            }
            
            $this->session=new Session();
            
            if($this['config']['database']){
                $cfg=$this['config']['database'];
                $this['db']=array(
                    'default'=>Database::create($cfg['type'], $cfg)
                );
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
        
        function before_dispatch($callback=null){
            $this->beforeDispatchHandler=$callback;
        }
        
        function prepare(){
            parse_str($_SERVER['QUERY_STRING'], $query);
            
            // Use controller/action in query string will override PATH_INFO or URL_REWRITE
            if(isset($query['_c'])){
                $this->controller=$query["_c"];
                $this->action=$query["_a"] ?: "index";

                unset($query['_c']); unset($query['_m']);
                $this->params=$query;
            }
            else{
                $this->url=$_SERVER['PATH_INFO'];
                if(empty($this->url)) $this->url=$_SERVER['HTTP_X_REWRITE_URL'];
                if(empty($this->url)) $this->url=$_SERVER['REQUEST_URI'];

                $map=$this['router']->resolve($this->url);

                $this->controller=$map['controller'];
                $this->action=$map['action'];
                $this->params=$map['params'];
            }
        }
        
        function dispatch(){
            $resource=substr($this->url, strlen($this['webbase']));

            if($this['user'] && !$this['user']->authorize($resource, 'a')){
                $this['user']->authorize_failed($resource, 'a');
            }
            
            $r=$this['router']->route($this->controller, $this->action, $this->params);
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
    // global short cut
    function url_for($controller, $action='index', $params=array()){
        global $app;
        return $app['router']->url_for($controller, $action, $params);
    }
    
    function assets($asset=null){
        global $app;

        $url=(APP_BASE=="\\" || APP_BASE=='/') ? '/assets' : $app['webbase']."/assets";
        if(!empty($asset)) $url.="/$asset";
        
        return $url;
    }
}
?>
