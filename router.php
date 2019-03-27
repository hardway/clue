<?php
namespace Clue{
    class Router{
        protected $app;
        protected $translates=array();

        function __construct($app){
            $this->app=$app;
            $this->debug=false;

            $this->connection=[];   // 连接规则
            $this->translates=[];   // URL重写
        }

        /**
         * 别名，直接转换URL格式（类似url-rewrite）
         */
        function alias($from, $to){
            $this->translates[]=array('from'=>$from, 'to'=>$to);
        }

        /**
         * 连接URL和后端处理程序（可以是controller/action，也可以是任意callable）
         */
        function connect($url){
            $args=func_get_args();
            $url=array_shift($args);
            $mapping=[];
            $verb="*";

            if(is_callable($args[0])){
                $handler=$args[0];
                $verb=@$args[1] ?: "*"; // GET, POST, HEAD, PUT, DELETE, TRACE, OPTIONS, CONNECT, PATCH
            }
            else{
                $mapping=is_array($args[count($args)-1]) ? array_pop($args) : array();

                list($c, $a)=$args;
                // 检查Controller/Action
                $handler=$this->determine_handler($c, $a);
            }

            // decode url pattern into name list
            $pattern=preg_replace_callback('/:([a-zA-Z0-9_]+)/', function($m) use(&$names, $mapping){
                $name=$m[1];
                $names[]=$name;

                if($name=='controller' || $name=='action')
                    return '([^/]*)';
                else{
                    if(isset($mapping[$name])){
                        return "({$mapping[$name]})";
                    }
                    else
                        return '([^/]+)';
                }
            }, $url);

            $pattern="^$pattern\$";

            $this->connection[]=array(
                'verb'=>$verb,
                'pattern'=>$pattern,
                'names'=>$names,
                'handler'=>$handler
            );

            return true;
        }

        // 根据controller/action定位并返回相应的callable handler
        function determine_handler($controller, $action){
            // TODO
        }


        /**
         * 定位到controll/action/params并执行
         */
        function route($controller, $action, $params=array()){
            $path=Controller::find_controller($controller);
            $view=$action;

            // 确认control所在文件存在
            if(!file_exists($path))
                return $this->app->http_error(404, "No controller found: $controller");

            require_once $path;
            $source=file_get_contents($path);

            // 确定类名
            // TODO: 更好的reflective或者规定controller的类名必须符合PSR-0
            preg_match('/namespace\s+([a-z0-9_\\\\]+)/i', $source, $n);
            preg_match('/class\s+([a-z0-9_]*)/i', $source, $m);
            $class=($n?$n[1].'\\':'').$m[1];

            // 形如 abc.htm 的Action将被拆分为 action=abc, layout=htm
            $core_action=null;
            $layout=null;

            if(preg_match("/(.+?)\.(.+)/", $action, $m)){
                $core_action=$m[1];
                $layout=$m[2];
            }

            // action方法存在
            if(method_exists($class, $action)){

            }
            // 或者action.layout存在
            elseif(method_exists($class, $core_action)){
                $action=$core_action;
            }
            // 如果view存在，仍然可以直接调用
            elseif(View::find_view("/$controller/$action")){
                $view=$action;
                $action="__catch_view";
            }
            // 或者core_action视图存在
            elseif(View::find_view("/$controller/$core_action")){
                $view=$core_action;
                $action="__catch_view";
            }
            // 最后的尝试
            // 访问 /catch_controller/a/b/c ==> 调用 catch_controller::__catch_params(['a', 'b', 'c'])
            elseif(method_exists($class, '__catch_params')){
                if(isset($params[0]) && $params[0]=='index') array_shift($params);  // eg, /controller/test/ ==> controller::__catch_params('test')

                array_unshift($params, $action);
                $action="__catch_params";
            }
            else
                return $this->app->http_error(404, "Can't find action or view $action of $controller");

            // Initialize controller and
            $obj=new $class($controller, $action);

            // invoke action
            $obj->params=$params;
            $obj->controller=$controller;
            $obj->view=$view;
            $obj->action=$action;
            if(@$layout) $obj->layout=$layout;

            // 执行Controll::Action(Params)
            $ret=null;
            try{
                $callArgs=$this->resolve_params([$obj, $action], $params);
                $ret=call_user_func_array(array($obj, $obj->action), $callArgs);
            }
            catch(\Exception $e){
                if($obj->catch_exception){
                    $obj->error($e->getMessage());
                    $obj->redirect_return();
                }
                else{
                    throw $e;
                }
            }

            return $ret;
        }

        function reform($controller, $action, $params=array()){
            // one argument shortcut
            // eg. reform('c', 'a', 'hello') ==> c/a/hello
            if(is_string($params)) $params=array($params);

            # Try to reform in default way
            $query=array();
            $path=array("");

            if($controller!='index') $path[]=$controller;
            if($action=='index') $action="";
            $path[]=$action;

            foreach($params as $k=>$v){
                if(is_numeric($k)){
                    $path[]=urlencode($v);
                }
                else{
                    $query[$k]=urlencode($v);
                }
            }

            $url=implode("/", $path) . (empty($query) ? "" : '?'.http_build_query($query));
            $url=preg_replace('/\/+/', '/', $url);

            return $url;
        }

        function handle($callable, $params){
            // detect parameters using reflection
            $args=$this->resolve_params($callable, $params);

            return call_user_func_array($callable, $args);
        }

        /**
         * 根据callable的参数构型，准备正确的参数队列，方便后续函数调用
         *
         * @param $callable Closure或者[Class, Action]
         */
        function resolve_params($callable, $params){
            $callArgs=array();

            if(is_array($callable)){
                $rfx=new \ReflectionMethod($callable[0], $callable[1]);
            }
            else{
                $rfx=new \ReflectionFunction($callable);
            }

            // 1. 填充命名变量
            foreach($rfx->getParameters() as $idx=>$rfxParam){
                if(isset($params[$rfxParam->name])){
                    $callArgs[$idx]=$params[$rfxParam->name];

                    unset($params[$rfxParam->name]);
                }
                else{
                    $callArgs[$idx]=null;
                }
            }

            // 2. 命名变量无法匹配则抛弃
            if($params) foreach($params as $k=>$v){
                if(!is_int($k)) unset($params[$k]);
            }

            // 3. 传入剩余变量（填充默认值）
            foreach($rfx->getParameters() as $idx=>$rfxParam){
                if($callArgs[$idx]===null){
                    if(count($params)>0){
                        $callArgs[$idx]=array_shift($params);
                    }
                    elseif($rfxParam->isDefaultValueAvailable()){
                        $callArgs[$idx]=$rfxParam->getDefaultValue();
                    }
                }
            }

            // 3rd, append all remaining params
            $callArgs=array_merge($callArgs, $params);

            return $callArgs;
        }

        /**
         * 将URL转换为controller和action（不做实际route），或者返回已连接的callable
         *
         * 对于/a/b/c的url，应该依次寻找:
         *  controller=a/b/c, action=index
         *  controller=a/b, action=c
         *  controller=a/b, action=index, param=c           TODO，等待实现
         *  controller=a, action=b, param=c
         *  controller=a, action=index, param=b/c           TODO，等待实现
         *  controller=index, action=a, param=b/c
         *  controlelr=index, action=index, param=a/b/c     所有的not found url都会fallback到这里，反而有副作用，应该禁止
         */
        function resolve($url){
            global $app;

            $mapping=[
                'controller'=>null,
                'action'=>null,
                'params'=>null
            ];

            // Strip base directory, eg, the application is located at http://localhost/portal/app
            $base=preg_replace('|[\\\/]+|', '/', APP_BASE);
            if($base!='/' && strpos($url, $base)===0){
                $url=substr($url, strlen($base));
            }

            $parts=parse_url($url);
            $query=[];
            if(isset($parts['query'])) parse_str($parts['query'], $query);

            // strip query from url
            if(($p=strpos($url, '?'))!==FALSE){
                $url=substr($url, 0, $p);
            }

            // 优先尝试用url来match connection
            $rules=array_filter($this->connection, function($c){return $c['verb']==$_SERVER['REQUEST_METHOD'];});
            $rules=array_merge($rules, array_filter($this->connection, function($c){return $c['verb']=='*';}));
            foreach($rules as $c){
                // 检查HTTP Verb
                if($c['verb']!='*' && $c['verb']!=$_SERVER['REQUEST_METHOD']) continue;

                if(preg_match(chr(27).$c['pattern'].chr(27), $url, $m)){
                    array_shift($m);    // 去除完整匹配
                    $params=[];

                    // 匹配的命名变量
                    if(!empty($c['names'])){
                        for($i=0; $i<count($c['names']); $i++){
                            $params[$c['names'][$i]]=$m[$i];
                        }
                        // 查询条件中的变量
                        foreach($query as $k=>$v) $params[$k]=$v;
                        // 剩余的参数（若有）
                        for(; $i<count($m); $i++){
                            $params[]=$m[$i];
                        }
                    }

                    return [
                        'handler'=>$c['handler'],
                        'params'=>$params
                    ];
                }
            }

            // 按照HMVC目录结构解析

            // Use controller/action in query string will override PATH_INFO or URL_REWRITE
            if(isset($query['_c'])){
                return array(
                    'controller'=>$query['_c'],
                    'action'=>$query["_a"] ?: "index",
                    'params'=>$query
                );
            }

            // Translate url，只针对URL部分，不包括query
            foreach($this->translates as $tr){
                if(preg_match($tr['from'], $url)){
                    $url=preg_replace($tr['from'], $tr['to'], $url);
                    break;  // Only match first one
                }
            }

            // url translate后生成的?query也要合并到GET中
            $_GET=array_merge($_GET ?: [], $query);
            $candidates=explode("/", "/".trim($url,'/'));
            $params=array();

            // 尝试最长匹配
            $candidates[]='index';
            $action='';

            while(count($candidates)>=1){
                // 寻找Controller

                $controller=trim(implode('/', $candidates), '/');
                $mapping['controller']=$controller ?: 'index';
                // error_log("Searching ".$mapping['controller']."::$action(".json_encode($params).")");

                $control_file=Controller::find_controller($mapping['controller']);
                if(!$control_file || !file_exists($control_file)){
                    // 不匹配的文字可以加入参数
                    if($action && $action!='index') array_unshift($params, $action);

                    // 继续向上递归
                    $action=array_pop($candidates);
                    // $controller=trim(implode('/', $candidates), '/');

                    continue;
                }

                $mapping['action']=$action ?: 'index';

                // POST方法加上前缀_
                // TODO: GET和PUT加上什么？
                if(@$_SERVER['REQUEST_METHOD']=='POST') $mapping['action']="_".$mapping['action'];

                // 将GET/POST一并加入参数中
                $mapping['params']=array_map(function($v){if(is_string($v)) return rawurldecode($v); else return $v;}, array_merge($params, $_GET ?: [], $_POST ?: []));

                return $mapping;
            }

            // 没有找到最合适的，返回最后匹配的一个
            return $mapping;
        }
    }
}
?>
