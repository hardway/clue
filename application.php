<?php  
namespace Clue{
    class Application implements \ArrayAccess{
        private $_values;

        public $config;
        public $db;

        public $session;
        public $layout;
        
        public $controller;
        public $action;
        public $params;
        
        function __construct($values=array()){
            $this->_values=$values;

            if(!isset($this['auth_class'])) $this['auth_class']="Clue\\Auth";
            if(!isset($this['user_class'])) $this['user_class']="User";
            if(!isset($this['user'])){
                $this['user']=$this->share(function($c){ 
                    $cls=$c['auth_class'];
                    return $cls::current();
                });
            }
            if(!isset($this['router'])){
                $this['router']=new Router();            
            }

            $this->init();
        }

        // Dependency Injection, Ref: Pimple
        function offsetGet($id){
            $callable = is_object($this->_values[$id]) && method_exists($this->_values[$id], '__invoke');
            return $callable ? $this->_values[$id]($this) : $this->_values[$id];
        }
        function offsetSet($id, $val)   { $this->_values[$id]=$val; }
        function offsetExists($id)      { return array_key_exists($id, $this->_values); }
        function offsetUnset($id)       { unset($this->_value[$id]); }
        function share(\Closure $callable){
            return function ($c) use ($callable) {
                static $object;
                if (null === $object) {
                    $object = $callable($c);
                }
                return $object;
            };
        }

        function init(){
            // TODO: guard is optional
            // $this['guard']=new Guard(@$this['config']['guard']);
            
            if($this['config']['debug']===false){
                $this->guard->display_level=0;
                $this->guard->stop_level=0;
            }
                       
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

        function redirect($url){
            header("Status: 302 Found");
            header("Location: $url");
            exit();
        }

        static protected $instance;
        
        function alert($message, $level='success', $context='application'){
            $_SESSION['alert'][$context][]=array($level, $message);
        }

        function display_alert($context='application'){
            $messages=@$_SESSION['alert'][$context];
            if(empty($messages)) return false;

            $html="";
            foreach($messages as $m){
                list($level, $msg)=$m;
                $html.="
                    <div class='alert alert-$level'>
                        <button type='button' class='close' data-dismiss='alert'>&times;</button>
                        $msg</div>
                ";
            }

            unset($_SESSION['alert'][$context]);
            echo $html;
        }
        
        function run(){
            $map=$this['router']->resolve();

            $this->controller=$map['controller'];
            $this->action=$map['action'];
            $this->params=$map['params'];
            
            $resource=$this->controller."::".$this->action;
            if(count($this->params)>0) $resource.="(".http_build_query($this->params).")";

            // TODO: authorization is optional
            /* 
            if($this['user'] && !$this['user']->authorize($resource, 'a')){
                $this['user']->authorize_failed($resource, 'a');
            }
            */

            $r=$this['router']->route($this->controller, $this->action, $this->params);
            $ret=call_user_func_array(array($r['handler'], $r['handler']->action), $r['args']);
        }        
    }
}

namespace{    
    // global short cut
    function back_url(){
        // allow GET/POST overrides referer
        $url=isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
        if(isset($_POST['return_url'])) $url=$_POST['return_url'];
        if(isset($_GET['return_url'])) $url=$_GET['return_url'];

        return $url;
    }

    function relpath($path){
        return preg_replace('|[\\\/]+|', '/', str_replace(APP_ROOT, APP_BASE, $path));
    }

    function url_for($controller, $action='index', $params=array()){
        global $app;
        $url=APP_BASE.$app['router']->reform($controller, $action, $params);
        $url=preg_replace('/\/+/', '/', $url);

        return $url;
    }

    function url_for_ssl(){
        $url=call_user_func_array("url_for", func_get_args());
        return "https://".$_SERVER['HTTP_HOST'].$url;
    }
}
?>
