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

        protected $default_config=[
            'override_definition_table'=>'config', // 加载数据库的config表以设置definition
        ];

        function __construct($values=array()){
            $values=array_merge($this->default_config, $values);
            $this->_values=$values;

            $this['router']=new Router($this);
            if(is_array(@$this['config']['route'])) foreach($this['config']['route'] as $pattern=>$route){
                $this['router']->alias($pattern, $route);
            }

            $this['referer_url']=isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
            $this['return_url']=urldecode(POST('return_url') ?: GET('return_url') ?: $this['referer_url']);

            if(@$this['config']['database']){
            	// TODO: 按需加载，不再需要设置default
            	$default_db=Database::create($this['config']['database']);
                $this['db']=array('default'=>$default_db);

                // 加载config表中的设定
                $config_table=$this['override_definition_table'];
                if($default_db && $default_db->has_table($config_table)){
                    $defines=$default_db->get_hash("select name, value from %t", $config_table);
                	foreach($defines as $name=>$value){
                		if(!defined($name)) define($name, $value);
                	}
                }
            }

            // 集成guard
            if(@$this['config']['guard']){
                $this['guard']=new Guard($this['config']['guard']);
            }

            if(@$this['config']['profiler']){
                $profiler=new Profiler;
                $profiler->start();
                $this['profiler']=$profiler;
            }
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
            if(extension_loaded('apcu')){
                return function ($c) use ($callable, $key, $ttl) {
                    if (!apcu_exists($key)) {
                        apcu_store($key, $callable($c), $ttl);
                    }
                    return apcu_fetch($key);
                };
            }
            elseif(extension_loaded('apc')){
                return function ($c) use ($callable, $key, $ttl) {
                    if (!apc_exists($key)) {
                        apc_store($key, $callable($c), $ttl);
                    }
                    return apc_fetch($key);
                };
            }
            else{
                // Fallback
                return $this->share($callable);
            }
        }

        function start_session($timeout=1800, array $options=[]){
            $this['session']=Session::init($this, $options+[
                'ttl'=>$timeout,
                'folder'=>"/tmp/session/".APP_NAME,         // FileSession默认路径
                'db'=>$this['db']['default']                // DBSession默认数据库
            ]);

            // 指定Session名称
            if(isset($options['name'])) session_name($options['name']);

            session_start();

		    if (!isset($_SESSION['security_token'])) {
		        $_SESSION['security_token'] = md5(uniqid(rand(), true));
		    }
        }

        /**
          * 记住Session多少天（通过Cookie）
          * @param $retention 天数
          */
        function remember_session($retention){
            if(!$this['session']) return false;

            session_set_cookie_params($retention * 86400);
            $this['session']->remember($retention);

            return true;
        }

        function redirect($url){
        	session_write_close();

            // 默认限制站外跳转
            // TODO: 可以通过配置修改策略以允许或替换 (deny, allow, replace)
            $app_host=parse_url(APP_URL, PHP_URL_HOST);
            $redirect_host=parse_url($url, PHP_URL_HOST);
            $referer_host=parse_url($url, PHP_URL_HOST);

            if($redirect_host && $redirect_host!=$app_host && $redirect_host!=$referer_host) panic("External redirection not allowed");

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
                if($this->is_ajax()){
                    echo "<script>alert(\"$m\", \"$level\", 10000)</script>";   // 10秒后消失
                }
                else{
                    $_SESSION["app_msg"][$level][$context][]=$m;
                }
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

        // 是否AJAX请求
        public function is_ajax(){
            return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        }

        // 是否跨域POST请求
        // TODO: 支持CORS
        public function is_csrf(){
            $actual_host=@$_SERVER['SERVER_NAME'];
            $expect_host=@parse_url(APP_URL)['host'];

            return $actual_host && $expect_host && $actual_host!=$expect_host;
        }

        // 是否现存的静态资源文件
        public function is_static(){
            $path=$_SERVER['DOCUMENT_ROOT'].'/'.$_SERVER['REQUEST_URI'];
            $path=preg_replace('/\?.+/', '', $path);

            return is_file($path);
        }

        function run(){
            if(isset($_SERVER['PATH_INFO']))
                $path=$_SERVER['PATH_INFO'];
            else{
                // Normalize Path
                $url=parse_url(preg_replace('/^\/\//', '/', @$_SERVER['HTTP_X_REWRITE_URL'] ?: $_SERVER['REQUEST_URI']));
                $path=$url['path'].(isset($url['query']) ? "?".$url['query'] : "");

                if($path==$_SERVER['PHP_SELF']) $path='/';
            }

            $map=$this['router']->resolve($path);

            // Controller / Action在认证资源的时候需要用到
            $this->controller=isset($map['controller']) ? $map['controller'] : null;
            $this->action=isset($map['action']) ? $map['action'] : null;
            $this->params=$map['params'];

            // TODO: deprecate this callback, since we can always use event listener to archive
            if(isset($this['authenticator']) && is_callable($this['authenticator'])){
                call_user_func_array($this['authenticator'], $this);
            }

            $this->fire_event("before_route");

            if($this->is_ajax() && $this['guard'] instanceof Guard){
                $this['guard']->add_event_listener('error', function($g, $e){
                    $g->summarized=true;
                    header("HTTP/1.0 ".$e['code']);
                    exit($e['message']);
                });
            }

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
