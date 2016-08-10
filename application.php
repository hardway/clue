<?php
namespace Clue{
    require_once __DIR__."/asset.php";
    require_once __DIR__."/url.php";

    class Application implements \ArrayAccess{
        use \Clue\Traits\Logger;
        use \Clue\Traits\Events;

        private $_values;   # DI

        public $controller;
        public $action;
        public $params;

        function __construct($values=array()){
            $this->_values=$values;

            $this['router']=new Router($this);
            if(is_array(@$this['config']['route'])) foreach($this['config']['route'] as $pattern=>$route){
                $this['router']->alias($pattern, $route);
            }

            $this['referer_url']=isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
            $this['return_url']=urldecode(POST('return_url') ?: GET('return_url') ?: $this['referer_url']);

            if($this['config']['database']){
            	$default_db=Database::create($this['config']['database']);
                $this['db']=array('default'=>$default_db);

                // 加载config表中的设定
                if($default_db && $default_db->has_table('config')){
                	foreach($default_db->get_hash("select name, value from config") as $name=>$value){
                		if(!defined($name)) define($name, $value);
                	}
                }
            }

            // $this['profiler']=new Profiler();
        }

        // Dependency Injection, Ref: Pimple
        function offsetGet($id){
            if(!isset($this->_values[$id])) return null;

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

        /**
         * 使用APC做缓存，apt-get install php5-apcu
         */
        function cache(\Closure $callable, $key, $ttl){
            if(!extension_loaded('apc')){
                error_log("Extension APC missing!");
                // Fallback
                return $this->share($callable);
            }

            return function ($c) use ($callable, $key, $ttl) {
                if (!apc_exists($key)) {
                    apc_store($key, $callable($c), $ttl);
                }
                return apc_fetch($key);
            };
        }

        function start_session($timeout=null, $name=null, $storage=null){
            if($timeout!==null){
                ini_set('session.gc_maxlifetime', $timeout);
            }

            if($name!==null){
                session_name($name);
            }

            // TODO: session storage
            session_start();

		    if (!isset($_SESSION['security_token'])) {
		        $_SESSION['security_token'] = md5(uniqid(rand(), true));
		    }
        }

        function redirect($url){
        	session_write_close();

            if(!headers_sent()){
                header("Status: 302 Found");
                header("Location: $url");
            }
            else{
                echo "<script>window.location=\"$url\"</script>";
            }

            exit();
        }

        /**
         * 跳转返回
         * 优先顺序：$default_url > $app['return_url'] > $_SERVER['HTTP_REFERER']
         * 典型场景：限制页面跳转到登录页面，成功后返回原页面
         */
        function redirect_return($default_url=null){
            $this->redirect($this['return_url'] ?: $default_url ?: $this['referer_url']);
        }

        /**
         * DEPRECATE
         */
        function redirect_referer($default_url=null){
            $this->redirect($this['referer_url'] ?: $default_url);
        }

        function cache_get($name){
            // TODO: use memcache or apc instead
            $path=sys_get_temp_dir().'/'.APP_NAME.'/'.$name;

            if(empty($path) || !file_exists($path) || time() > filemtime($path)) return false;

            return file_get_contents($path);
        }

        function cache_set($name, $data, $expire=null){
            // TODO: use memcache or apc instead
            $path=sys_get_temp_dir().'/'.APP_NAME.'/'.$name;

            $expire=$expire?:time()+3600;   // default expires in 1 hour

            file_put_contents($path, $data);
            touch($path, $expire);
        }

        function on_http_error($code, $handler){
            $this->http_handler[$code]=$handler;
        }

        function http_error($code, $message){
            header("http/1.0 $code");

            if(isset($this->http_handler[$code]))
                call_user_func($this->http_handler[$code], $message);
            else
                echo $message;

            exit();
        }

        // 一般信息
        function alert($messages, $context='website', $level='alert'){
            if(!is_array($messages)) $messages=array($messages);
            foreach($messages as $m){
                $_SESSION["app_msg"][$level][$context][]=$m;
            }
        }

        function display_alerts($context_pattern='.*', $level='alert'){
            $messages=$this->get_alerts($context_pattern, $level);
            if(empty($messages)) return false;

            foreach($messages as $message){
                $alert=new View("clue/alert");
                $alert->render(compact("message", 'level'));
            }
        }

        function get_alerts($context_pattern=".*", $level='alert'){
            $messages=array();
            if(is_array(@$_SESSION['app_msg'][$level])) foreach($_SESSION['app_msg'][$level] as $context=>$msgs){
                if(!preg_match('/'.$context_pattern.'/i', $context)) continue;

                $messages=array_merge($messages, $msgs);
                unset($_SESSION['app_msg'][$level][$context]);
            }
            return $messages;
        }

        // 错误信息
        function error($message, $context='website'){ $this->alert($message, $context, 'error'); }
        function display_errors($context_pattern='.*'){ $this->display_alerts($context_pattern, 'error'); }
        function get_errors($context_pattern='.*'){ return $this->get_alerts($context_pattern, 'error'); }

        // 成功信息
        function success($message, $context='website'){ $this->alert($message, $context, 'success'); }
        function display_succeeds($context_pattern='.*'){ $this->display_alerts($context_pattern, 'success'); }
        function get_succeeds($context_pattern='.*'){ return $this->get_alerts($context_pattern, 'success'); }

        function run(){
            if(isset($_SERVER['PATH_INFO']))
                $url=$_SERVER['PATH_INFO'];
            else{
                $url=isset($_SERVER['HTTP_X_REWRITE_URL']) ? $_SERVER['HTTP_X_REWRITE_URL'] : $_SERVER['REQUEST_URI'];
                if($url==$_SERVER['PHP_SELF']) $url='/';
            }

            $map=$this['router']->resolve($url);

            // Controller / Action在认证资源的时候需要用到
            $this->controller=@$map['controller'];
            $this->action=@$map['action'];
            $this->params=$map['params'];

            // TODO: deprecate this callback, since we can always use event listener to archive
            if(isset($this['authenticator']) && is_callable($this['authenticator'])){
                call_user_func_array($this['authenticator'], $this);
            }

            $this->fire_event("before_route");

            if(is_callable(@$map['handler'])){
				$ret=$this['router']->handle($map['handler'], $map['params']);
	        }
	        else{
	            // 执行Controll::Action(Params)
	            $ret=$this['router']->route($this->controller, $this->action, $this->params);
	        }

            $this->fire_event("after_route", $ret);

            return $ret;
        }
    }
}
?>
