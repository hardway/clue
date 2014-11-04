<?php
namespace Clue{
	class Router{
		protected $app;
		protected $translates=array();

		function __construct($app){
			$this->app=$app;
			$this->debug=defined("CLUE_ROUTE_DEBUG") && CLUE_ROUTE_DEBUG;

			$this->rules=array();
		}

		function alias($from, $to){
			$this->translates[]=array('from'=>$from, 'to'=>$to);
		}

		function connect($url){
			$args=func_get_args();
			$url=array_shift($args);

			$mapping=is_array($args[count($args)-1]) ? array_pop($args) : array();

			list($c, $a)=$args;
			$mapping=array_merge(array('controller'=>$c, 'action'=>$a), $mapping);

			// decode url pattern into name list
			$pattern=preg_replace_callback('/:([a-zA-Z0-9_]+)/', function($m) use(&$names, $mapping){
			    $name=$m[1];
				$names[]=$name;

				if($name=='controller' || $name=='action')
					return '([^/]*)';
				else{
				    if(isset($mapping[$name])){
				        $p=$mapping[$name];
				        return "($p)";
			        }
			        else
					    return '([^/]+)';
				}
			}, $url);

			$pattern="^$pattern\$";

			$this->rules[]=array(
				'reformation'=>$url,
				'pattern'=>$pattern,
				'names'=>$names,
				'mapping'=>$mapping
			);

			return true;
		}

		function controller(){
			return $this->controller;
		}

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
				if(isset($params[0]) && $params[0]=='index') array_shift($params);	// eg, /controller/test/ ==> controller::__catch_params('test')

				array_unshift($params, $action);
				$action="__catch_params";
			}
			else
				return $this->app->http_error(404, "Can't find action or view $action of $controller");

			// detect parameters using reflection
			$callArgs=array();

			$rfxMethod=new \ReflectionMethod($class, $action);

			// 1st round, take named variables
			foreach($rfxMethod->getParameters() as $idx=>$rfxParam){
				if(isset($params[$rfxParam->name])){
					if(is_string($params[$rfxParam->name])){
						$callArgs[$idx]=urldecode($params[$rfxParam->name]);
					}
					else{
						$callArgs[$idx]=$params[$rfxParam->name];
					}

					unset($params[$rfxParam->name]);
				}
				else{
					$callArgs[$idx]=null;
				}
			}

			// remove named variables
			foreach($params as $k=>$v){
				if(!is_int($k)) unset($params[$k]);
			}

			// 2nd round, take by position
			foreach($rfxMethod->getParameters() as $idx=>$rfxParam){
				if($callArgs[$idx]===null){
					if(count($params)>0){
						$callArgs[$idx]=urldecode(array_shift($params));
					}
					elseif($rfxParam->isDefaultValueAvailable()){
						$callArgs[$idx]=$rfxParam->getDefaultValue();
					}
				}
			}

			// 3rd, append all remaining params
			$callArgs=array_merge($callArgs, $params);

			// Initialize controller and
			$obj=new $class($controller, $action);

			// invoke action
			$obj->app=$this->app;
			$obj->params=$params;
			$obj->controller=$controller;
			$obj->view=$view;
			$obj->action=$action;
			if(@$layout) $obj->layout=$layout;

			return call_user_func_array(array($obj, $obj->action), $callArgs);
		}

		function reform($controller, $action, $params=array()){
			// one argument shortcut
			// eg. reform('c', 'a', 'hello') ==> c/a/hello
			if(is_string($params)) $params=array($params);

			foreach($this->rules as $r){
				if(
					isset($r['mapping']['controller']) &&
					strcasecmp($r['mapping']['controller'], $controller)!=0
				) continue;

				if(
					isset($r['mapping']['action']) &&
					strcasecmp($r['mapping']['action'], $action)!=0
					//!preg_match('/'.$r['mapping']['action'].'/i',$action)
				) continue;

				$allParamsAreMet=true;
				foreach(array_keys($params) as $name){
				    if(isset($r['mapping'][$name]) && !preg_match('!'.$r['mapping'][$name].'!i', $params[$name])) continue;
				}

				$params['controller']=$controller=='index' ? '' : $controller;
				$params['action']=$action=='index' ? '' : $action;

				$url=preg_replace_callback('/\:([a-zA-Z0-9_]+)/', function($m) use(&$params, &$allParamsAreMet, $r){
				    $name=$m[1];

					if(isset($params[$name])){
						$ret=$params[$name];
						if($name!='controller' && $name!='action'){
							$ret=urlencode($ret);
						}

						unset($params[$name]);
						return $ret;
					}
					else{
					    $allParamsAreMet=false;
					    return "";
						// TODO: Clue_RouterException
						// throw new Exception("Couldn't found parameter '$name' in mapping rule.");
					}
				}, $r['reformation']);

				if(!$allParamsAreMet) continue;

				$query=array();
				if($params) foreach($params as $n=>$v){
					if($n=='controller' || $n=='action') continue;
					$query[]="$n=".urlencode($v);
				}
				if(count($query)>0)
					$url.='?'.implode('&', $query);

				return $url;
			}

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

		/**
		 * 将URL转换为controller和action，不做实际route
		 * 对于/a/b/c的url，应该依次寻找:
		 *	controller=a/b/c, action=index
		 * 	controller=a/b, action=c
		 *	controller=a/b, action=index, param=c 			TODO，等待实现
		 *	controller=a, action=b, param=c
		 * 	controller=a, action=index, param=b/c 			TODO，等待实现
		 *	controller=index, action=a, param=b/c
		 *	controlelr=index, action=index, param=a/b/c 	所有的not found url都会fallback到这里，反而有副作用，应该禁止
		 */
		function resolve($url){
			global $app;

            // Strip base directory, eg, the application is located at http://localhost/portal/app
            $base=preg_replace('|[\\\/]+|', '/', dirname($_SERVER['SCRIPT_NAME']));
            if($base!='/' && strpos($url, $base)===0){
                $url=substr($url, strlen($base));
            }

			$parts=parse_url($url);
            parse_str(@$parts['query'], $query);
            // Use controller/action in query string will override PATH_INFO or URL_REWRITE
            if(isset($query['_c'])){
                return array(
                	'controller'=>$query['_c'],
                	'action'=>$query["_a"] ?: "index",
                	'params'=>$query
                );
            }


			// strip query from url
			if(($p=strpos($url, '?'))!==FALSE){
				$url=substr($url, 0, $p);
			}


			// Translate url，只针对URL部分，不包括query
			foreach($this->translates as $tr){
				if(preg_match($tr['from'], $url)){
					$url=preg_replace($tr['from'], $tr['to'], $url);
					break;	// Only match first one
				}
			}
            // url translate后生成的?query也要合并到GET中
            $_GET=array_merge($_GET, $query);


			# Try default controller
			$candidates=explode("/", $url);
			$last=array_pop($candidates);
			$candidates[]=$last?:"index";

			$candidates[]="";	// Append pseudo action index
			$params=array();

			while(count($candidates)>=2){
				if(!empty($action)) array_unshift($params, $action);
				$action=array_pop($candidates);
				$controller=trim(implode('/', $candidates), '/');

				$mapping['controller']=$controller ?: 'index';
				$mapping['action']=$action ?: 'index';

				if(@$_SERVER['REQUEST_METHOD']=='POST') $mapping['action']="_".$mapping['action'];

				$mapping['params']=array_map(function($v){if(is_string($v)) return rawurldecode($v); else return $v;}, array_merge($params, $_GET, $_POST));

				$control_file=Controller::find_controller($mapping['controller']);

				if(file_exists($control_file)){
					// return with found controller/view

					// DEPRECATED 20141103，不需要在这里进行跳转，应该由controller自行处理
					// if($mapping['action']=='index' && !preg_match('/\/$/', $url)){
					// 	$app->redirect($url.'/');
					// }
					return $mapping;
				}
			}

			// 没有找到最合适的，返回最后匹配的一个
			return $mapping;
			// throw new \Exception("No route found.");
		}
	}
}
?>
