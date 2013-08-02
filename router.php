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
			if($_SERVER['REQUEST_METHOD']=='POST')
				$action="_$action";

			$path=DIR_SOURCE . "/control/".strtolower($controller).".php";

			$candidates=array();
			$candidates[]=implode("_", explode("/", $controller))."_Controller";
			$candidates[]='Controller';

			$class=null;

			if(file_exists($path)) require_once $path;
			foreach($candidates as $class_name){
			    if(class_exists($class_name, false)){
			    	$class=$class_name;
			    	break;
			    }
			}

			if(empty($class))
		    	$this->app->http_error(404, "No controller found: $controller");

			$rfxClass=new \ReflectionClass($class);

			if($rfxClass->hasMethod($action)){
				$rfxMethod=new \ReflectionMethod($class, $action);

				// detect parameters using reflection
				$callArgs=array();

				// 1st round, take named variables
				foreach($rfxMethod->getParameters() as $idx=>$rfxParam){
					if(isset($params[$rfxParam->name])){
						$callArgs[$idx]=$params[$rfxParam->name];
						unset($params[$rfxParam->name]);
					}
					else{
						$callArgs[$idx]=null;
					}
				}

				// 2nd round, take by position
				foreach($rfxMethod->getParameters() as $idx=>$rfxParam){
					if($callArgs[$idx]===null){
						if(count($params)>0){
							$callArgs[$idx]=array_shift($params);
						}
						elseif($rfxParam->isDefaultValueAvailable()){
							$callArgs[$idx]=$rfxParam->getDefaultValue();
						}
					}
				}

				$callArgs=array_merge($callArgs, $params);

				$obj=new $class($controller, $action);

				// invoke action
				$obj->params=$params;
				$obj->controller=$controller;
				$obj->view=$action;
				$obj->action=$action;

				return call_user_func_array(array($obj, $obj->action), $callArgs);
			}
			else{
				$this->app->http_error(404, "Can't find action $action of $controller");
			}
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
					$path[]=$v;
				}
				else{
					$query[$k]=$v;
				}
			}

			$url=implode("/", $path) . (empty($query) ? "" : '?'.http_build_query($query));

			return $url;
		}

		function resolve(){
			global $app;

            parse_str($_SERVER['QUERY_STRING'], $query);

            // Use controller/action in query string will override PATH_INFO or URL_REWRITE
            if(isset($query['_c'])){
                return array(
                	'controller'=>$query['_c'],
                	'action'=>$query["_a"] ?: "index",
                	'params'=>$query
                );
            }

            if(isset($_SERVER['PATH_INFO']))
                $uri=$_SERVER['PATH_INFO'];
            else{
                $uri=isset($_SERVER['HTTP_X_REWRITE_URL']) ? $_SERVER['HTTP_X_REWRITE_URL'] : $_SERVER['REQUEST_URI'];
                if($uri==$_SERVER['PHP_SELF']) $uri='/';
            }

            if(APP_BASE!='/' && strpos($uri, APP_BASE)===0){
                $uri=substr($uri, strlen(APP_BASE));
            }

			// strip query from uri
			if(($p=strpos($uri, '?'))!==FALSE){
				$uri=substr($uri, 0, $p);
			}

            $params=array();

			// Translate url
			foreach($this->translates as $tr){
				$uri=preg_replace($tr['from'], $tr['to'], $uri);
			}

			# Try default controller
			$candidates=explode("/", $uri);
			$last=array_pop($candidates);
			$candidates[]=$last?:"index";

			$candidates[]="";	// Append pseudo action index
			$params=array();

			while(count($candidates)>=2){
				if(!empty($action)) $params[]=$action;
				$action=array_pop($candidates);
				$controller=trim(implode('/', $candidates), '/');

				$mapping['controller']=$controller ?: 'index';
				$mapping['action']=$action ?: 'index';
				$mapping['params']=array_map('rawurldecode', array_merge($params, $_GET, $_POST));

				if(file_exists(DIR_SOURCE.'/control/'.$mapping['controller'].".php")){
					// return with found controller/view

					if($mapping['action']=='index' && !preg_match('/\/$/', $uri)){
						$app->redirect($uri.'/');
					}
					return $mapping;
				}
			}

			throw new \Exception("No route found.");
		}
	}
}
?>
