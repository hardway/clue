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
			// load controller
			$class=str_replace('/', '_', "{$controller}_Controller");	// TODO: ucfirst each path segment?

			if($_SERVER['REQUEST_METHOD']=='POST')
				$action="_$action";

			if(!class_exists($class, false)){
				$path=DIR_SOURCE . "/control/".strtolower(str_replace('_','/',$controller)).".php";
				$class='Controller';

			    if(file_exists($path)) require_once $path;
			    if(!class_exists($class, false))
			        throw new \Exception("No controller found: $controller");
			}

			$rfxClass=new \ReflectionClass($class);

			if($rfxClass->hasMethod($action)){
				$rfxMethod=new \ReflectionMethod($class, $action);

				// detect parameters using reflection
				$callArgs=array();
				foreach($rfxMethod->getParameters() as $rfxParam){
					if(isset($params[$rfxParam->name])){
						$callArgs[]=$params[$rfxParam->name];
						unset($params[$rfxParam->name]);
					}
				}
				$callArgs=array_merge($callArgs, $params);

				$obj=new $class($controller, $action);

				// invoke action
				$obj->params=$params;
				$obj->controller=$controller;
				$obj->view=$action;
				$obj->action=$action;

				return array(
				    'handler'=>$obj,
				    'args'=>$callArgs
				);
			}
			else{
				// TODO: goto 404 error
		        throw new \Exception("Can't find action $action of $controller");
			}
		}

		function reform($controller, $action, $params=array()){
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
            else{
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

				// try to match against rules
				foreach($this->rules as $r){
					if($this->debug) $app['guard']->debug("Testing URL: '$uri' against ".$r['pattern']);

					if(preg_match("!{$r['pattern']}!i", $uri, $match)){
						if($this->debug) $app['guard']->debug("Match URL: '$uri' against ".$r['pattern']);
						array_shift($match);
						$mapping=$r['mapping'];

						$candidateViolated=false;
						for($i=0; $i<count($match); $i++){
						    $name=$r['names'][$i];
						    if(isset($mapping[$name])){
						        $candidate=$mapping[$name];
						        if(!preg_match('/'.$candidate.'/i', $match[$i])){
						            $candidateViolated=true;
						            break;
					            }
						    }
						    $mapping[$name]=$match[$i];
						}
						if($candidateViolated) continue; // Try Next Rule

						// Default controller is Index
						if(empty($mapping['controller'])) $mapping['controller']='Index';
						// Default action is index
						if(empty($mapping['action'])) $mapping['action']='index';

						foreach($mapping as $n=>$v){
							if($n=='controller'){
							    $mapping['controller']=$v;
							    continue;
							}
							else if($n=='action') continue;

							$params[$n]=urldecode($v);
							unset($mapping[$n]);
						}
						$params=array_merge($params, $_GET, $_POST);

						$mapping['params']=$params;
						return $mapping;
					}
				}
            }

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
				$mapping['params']=array_merge($params, $_GET, $_POST);

				//var_dump($mapping);
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
