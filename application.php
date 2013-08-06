<?php
namespace Clue{
    class Application implements \ArrayAccess{
        private $_values;   # DI

        public $controller;
        public $action;
        public $params;

        function __construct($values=array()){
            $this->_values=$values;

            $this['router']=new Router($this);
            $this['referer_url']=@$_SERVER['HTTP_REFERER'];
            $this['return_url']=@$_POST['return_url'] ?: @$_GET['return_url'];

            if($this['config']['database']){
                $this['db']=array(
                    'default'=>Database::create($this['config']['database'])
                );
            }
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

        function get_alerts($context_pattern=".*", $level='alert'){
            $messages=array();
            if(is_array($_SESSION['app_msg'][$level])) foreach($_SESSION['app_msg'][$level] as $context=>$msgs){
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

        // 辅助信息
        function info($message, $context='website'){ $this->alert($message, $context, 'info'); }
        function display_infos($context_pattern='.*'){ $this->display_alerts($context_pattern, 'info'); }
        function get_infos($context_pattern='.*'){ return $this->get_alerts($context_pattern, 'info'); }

        function run(){
            if(isset($_SERVER['PATH_INFO']))
                $url=$_SERVER['PATH_INFO'];
            else{
                $url=isset($_SERVER['HTTP_X_REWRITE_URL']) ? $_SERVER['HTTP_X_REWRITE_URL'] : $_SERVER['REQUEST_URI'];
                if($url==$_SERVER['PHP_SELF']) $url='/';
            }

            $map=$this['router']->resolve($url);

            $this->controller=$map['controller'];
            $this->action=$map['action'];
            $this->params=$map['params'];

            return $this['router']->route($this->controller, $this->action, $this->params);
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

        return "http://".APP_SERVER.$url;
    }

    function url_for_ssl(){
        global $app;

        $url=call_user_func_array("url_for", func_get_args());

        if($app['config']['ssl'])
            $url=preg_replace('/^http/', 'https', $url);

        return $url;
    }
}
?>
