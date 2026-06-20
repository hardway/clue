<?php
namespace{
	if(!function_exists('debug')){
		function debug(...$vars){
			$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
			$location = isset($trace[0]['file']) ? $trace[0]['file'].':'.$trace[0]['line'] : '';

			if(php_sapi_name()=='cli'){
				foreach($vars as $i=>$var){
					$loc = $location ? " \033[90m(at $location)\033[0m" : '';
					fwrite(STDERR, "\033[90mdebug #".($i+1)."\033[0m$loc\n");
					ob_start();
					print_r($var);
					fwrite(STDERR, ob_get_clean()."\n");
				}
			}
			else{
				$export = function($var, $depth=0, &$seen=[]) use (&$export){
					$indent = str_repeat('&nbsp;&nbsp;', $depth);
					$html = '';

					if(is_null($var)){
						return $indent.'<span class="d-null">null</span>';
					}
					if(is_bool($var)){
						return $indent.'<span class="d-bool">'.($var ? 'true' : 'false').'</span>';
					}
					if(is_int($var)){
						return $indent.'<span class="d-num">'.$var.'</span>';
					}
					if(is_float($var)){
						return $indent.'<span class="d-num">'.$var.'</span>';
					}
					if(is_string($var)){
						$escaped = htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
						return $indent.'<span class="d-str-q">"</span><span class="d-str">'.$escaped.'</span><span class="d-str-q">"</span> <span class="d-str-len">(length='.strlen($var).')</span>';
					}
					if(is_array($var)){
						if(empty($var)){
							return $indent.'<span class="d-null">[]</span>';
						}
						if(in_array(spl_object_id((object)$var), $seen)){
							return $indent.'<span class="d-recursion">*RECURSION*</span>';
						}
						$seen[] = spl_object_id((object)$var);
						$toggleClass = $depth < 2 ? 'expanded' : 'collapsed';
						$childrenClass = $depth < 2 ? '' : ' collapsed';
						$html = $indent.'<span class="d-toggle '.$toggleClass.'"></span> <span class="d-type">array</span> <span class="d-str-len">('.count($var).')</span> <span class="d-bracket">[</span><span class="d-children'.$childrenClass.'"><br>';
						foreach($var as $k=>$v){
							$key = is_string($k)
								? '"<span class="d-key">'.htmlspecialchars($k).'</span>"'
								: '<span class="d-num">'.$k.'</span>';
							$html .= $indent.'&nbsp;&nbsp;'.$key.'&nbsp;=&gt;&nbsp;'.$export($v, $depth+1, $seen).',<br>';
						}
						$html .= '</span><span class="d-bracket">]</span>';
						array_pop($seen);
						return $html;
					}
					if(is_object($var)){
						$hash = spl_object_id($var);
						if(in_array($hash, $seen)){
							return $indent.'<span class="d-recursion">*RECURSION*</span>';
						}
						$seen[] = $hash;
						$class = get_class($var);
						$props = [];
						if(method_exists($var, '__debugInfo')){
							$props = $var->__debugInfo();
						}
						else{
							$ref = new \ReflectionObject($var);
							do{
								foreach($ref->getProperties() as $p){
									$p->setAccessible(true);
									$props[$p->getName()] = $p->getValue($var);
								}
							}while($ref = $ref->getParentClass());
						}
						$toggleClass = $depth < 2 ? 'expanded' : 'collapsed';
						$childrenClass = $depth < 2 ? '' : ' collapsed';
						$html = $indent.'<span class="d-toggle '.$toggleClass.'"></span> <span class="d-class">'.$class.'</span> <span class="d-bracket">{</span><span class="d-children'.$childrenClass.'"><br>';
						foreach($props as $k=>$v){
							$html .= $indent.'&nbsp;&nbsp;<span class="d-key">'.$k.'</span>: '.$export($v, $depth+1, $seen).',<br>';
						}
						$html .= '</span><span class="d-bracket">}</span>';
						array_pop($seen);
						return $html;
					}
					return $indent.print_r($var, true);
				};

				echo '<div class="clue-debug">';
				foreach($vars as $i=>$var){
					$loc = $location ? ' <span class="d-location">at '.htmlspecialchars($location).'</span>' : '';
					echo '<div class="clue-debug-item"><span class="d-label">debug #'.($i+1).'</span>'.$loc.'<br>';
					echo $export($var);
					echo '</div>';
				}
				echo '</div>';
			}
		}
	}
}
