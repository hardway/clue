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
                $this['router']=new Router($this);
            }

            $this['referer_url']=@$_SERVER['HTTP_REFERER'];
            $this['return_url']=@$_POST['return_url'] ?: @$_GET['return_url'];

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
            // Guard is on by default, unless disabled explicitly
            // TODO: don't use for now
            /*
            $guard_config=@$this['config']['guard'];
            if($guard_config==null || $guard_config['disabled']!=true){
                $this['guard']=new Guard($guard_config);
            }
            */

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
            if(!headers_sent()){
                header("Status: 302 Found");
                header("Location: $url");
            }
            else{
                echo "<script>window.location=\"$url\"</script>";
            }
            exit();
        }

        function redirect_return($default_url=null){
            $this->redirect($this['return_url'] ?: $default_url);
        }
        function redirect_referer($default_url=null){
            $this->redirect($this['referer_url'] ?: $default_url);
        }

        static protected $instance;

        function cache_get($name){
            $path=realpath(DIR_CACHE.'/'.$name);

            if(empty($path) || !file_exists($path) || time() > filemtime($path)) return false;

            return file_get_contents($path);
        }

        function cache_set($name, $data, $expire=null){
            $expire=$expire?:time()+3600;   // default expires in 1 hour
            $path=DIR_CACHE.DS.$name;

            file_put_contents($path, $data);
            touch($path, $expire);
        }

        // 一般信息
        function alert($messages, $context='website', $level='alert'){
            if(!is_array($messages)) $messages=array($messages);
            foreach($messages as $m){
                $_SESSION["app_msg"][$level][$context][]=$m;
            }
        }

        function display_alerts($context_pattern='.*', $level='alert'){
            $messages=array();
            if(is_array($_SESSION['app_msg'][$level])) foreach($_SESSION['app_msg'][$level] as $context=>$msgs){
                if(!preg_match('/'.$context_pattern.'/i', $context)) continue;

                $messages=array_merge($messages, $msgs);
                unset($_SESSION['app_msg'][$level][$context]);
            }

            if(empty($messages)) return false;

            $html="";
            foreach($messages as $m){
                $html.="
                    <div class='alert alert-$level'>
                        <button type='button' class='close' data-dismiss='alert'>&times;</button>
                        $m</div>
                ";
            }

            echo $html;
        }
        // 错误信息
        function error($message, $context='website'){
            $this->alert($message, $context, 'error');
        }
        function display_errors($context_pattern='.*'){
            $this->display_alerts($context_pattern, 'error');
        }
        // 成功信息
        function success($message, $context='website'){
            $this->alert($message, $context, 'success');
        }
        function display_successes($context_pattern='.*'){
            $this->display_alerts($context_pattern, 'success');
        }
        // 辅助信息
        function info($message, $context='website'){
            $this->alert($message, $context, 'info');
        }
        function display_infos($context_pattern='.*'){
            $this->display_alerts($context_pattern, 'info');
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
        global $app;

        $url=call_user_func_array("url_for", func_get_args());

        if($app['config']['ssl'])
            return "https://".$_SERVER['HTTP_HOST'].$url;
        else
            return $url;
    }
}
?>
