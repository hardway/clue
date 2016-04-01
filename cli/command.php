<?php
// TODO: 允许生成bash complete配置文件
// TODO: 允许设置参数为flag，不再支持waiting_option，但是支持-vvvv的特性
// http://fahdshariff.blogspot.sg/2011/04/writing-your-own-bash-completion.html

namespace Clue\CLI{
	class Command{
		function __construct($description=null, $app=null){
			$this->description=$description;
			$this->app=$app;

			$this->global_options=[];
			// $this->tree=$this->_build_tree($this->_list_function(), $this->app);
		}

		/**
		 * 生成命令树
		 */
		function _build_tree($funcs, $prefix){
			$tree=[];

			$commands=[];
			$stripped_funcs=[];

			foreach($funcs as $func){
				$func=substr($func, strlen($prefix)+1);
				$stripped_funcs[]=$func;

				list($cmd)=explode('_', $func, 2);
				if(!empty($cmd) && !in_array($cmd, $commands)) $commands[]=$cmd;
			}

			foreach($commands as $cmd){
				$tree[$cmd]=$this->_build_tree(array_filter($stripped_funcs, function($f) use($cmd){return strpos($f, $cmd)===0;}), $cmd);
			}

			return $tree;
		}

		/**
		 * 找到所有函数
		 */
		function _list_function(){
			$prefix=$this->app.'_';

			return array_filter(get_defined_functions()['user'], function($f) use($prefix){
				return strpos($f, $prefix)===0;
			});
		}

		/**
		 * 从HereDoc获得帮助信息
		 */
		function _parse_command($func){
			$r=new \ReflectionFunction($func);

			$comment=$r->getDocComment();
			$comment=explode("\n", $comment);
			$comment=array_slice($comment, 1, -1);  // 跳过首尾行
			$comment=array_map(function($c){return trim($c, '* ');}, $comment);

			$help=[
				'summary'=>'',
				'detail'=>[],
				'param'=>[],
				'options'=>[],
			];

			foreach($comment as $line){
				if(empty($line)) continue;

				if(preg_match('/^@([a-z0-9_]+)\s+\$([a-z0-9_]+)\s+(.*)$/i', $line, $m)){
					$help[$m[1]][$m[2]]=$m[3];
				}
				else{
					$help['detail'][]=$line;
				}
			}

			$help['summary']=@array_shift($help['detail']);

			foreach($r->getParameters() as $idx=>$para){
				if($para->isDefaultValueAvailable()){
					$help['options'][$para->name]=[
						'default'=>$para->getDefaultValue(),
						'summary'=>$help['param'][$para->name],
						'idx'=>$idx
					];
					unset($help['param'][$para->name]);
				}
			}

			return $help;
		}

		/**
		 * 显示帮助信息
		 *
		 * 默认显示整个app的帮助 $this->help
		 * 只有cmd，显示cmd的信息以及附带的subcmd
		 * 有subcmd，显示完整的subcmd和option帮助
		 */
		function help(){
			if($this->description){
				echo "\n";

				$lines=explode("\n", $this->description);
				foreach($lines as $l){
					printf(" %s\n", $l);
				}
				if(count($lines)>1) echo "\n";

				printf("%s\n", str_repeat('=', 30));
			}

			if($this->global_options){
				echo "\n Global Options: \n\n";

				foreach($this->global_options as $gk=>$gv){
					printf("  %-30s  %s\n", "--$gk", $gv['summary']);
				}
			}

			$this->help_command_list($this->_best_match_command(['.']));

			return true;
		}

		function help_command_list(array $funcs){
			echo "\n Supported Commands: \n\n";

			foreach($funcs as $func){
				$h=$this->_parse_command($func);

				$func=str_replace("_", " ", substr($func, strlen($this->app)+1));
				if($h) printf("  %-30s  %s\n", $func, $h['summary']);
			}
			echo "\n";
		}

		function help_command($func){
			$help=$this->_parse_command($func);

			printf("\n %s\n%s\n\n", $help['summary'], str_repeat('-', 40));
			printf(" usage: %s %s\n\n",
				str_replace('_', ' ', substr($func, strlen($this->app)+1)),
				implode(" ", array_map('strtoupper', array_keys($help['param'])))
			);

			foreach($help['detail'] as $line){
				printf("  %s\n", $line);
			}

			if(@$help['param']){
				echo " parameters:\n\n";
				foreach($help['param'] as $param=>$h){
					printf("   %-12s   %s\n", strtoupper($param), $h);
				}
				echo "\n";
			}

			if(@$help['options']){
				echo " options:\n\n";
				foreach($help['options'] as $opt=>$h){
					printf("   --%-10s   %s %s\n", $opt, $h['summary'], $h['default']!==null ? "(default: ".json_encode($h['default']).")" : "");
				}
				echo "\n";
			}
		}

		/**
		 * 允许命令缩写例如msent message test(在不产生歧义的情况下)可以缩写为msent me t
		 */
		function _best_match_command(array $cmds){
			$regexp='!^'.$this->app.implode('', array_map(function($c){return "_({$c}[^_]*)";}, $cmds)).'(_[^_]+)?!';
			$candidates=$this->_list_function();

			$matches=[];
			foreach($candidates as $func){
				if(@preg_match($regexp, $func, $m)){
					$matches[]=$func;
				}
			}

			return $matches;
		}

		/**
		 * 允许选项缩写，例如msent_mta_config($server, $throttle)可以讲--throttle缩写为-t
		 */
		function _best_match_option($options, $opt){
			$matches=[];
			$opt=trim($opt, "-");

			// 检查command有关的option
			foreach($options as $o=>$_){
				if(strpos($o, $opt)===0){
					$matches[]=[$o, 'local'];
				}
			}

			// 检查global option
			foreach($this->global_options as $o=>$_){
				if(strpos($o, $opt)===0){
					$matches[]=[$o, 'global'];
				}
			}

			return $matches;
		}

		/**
		 * 添加全局变量
		 * cli可以通过 --variable=value 来设置
		 *
		 * @param $variable 名称
		 * @param $description 说明
		 * @param $required 是否必须提供
		 */
		function add_global($variable, $description, $required=false){
			$this->global_options[$variable]=[
				'summary'=>$description,
				'required'=>$required
			];
		}

		function set_global($variable, $value){
			$this->global_options[$variable]['value']=$value;
		}

		function get_global($variable){
			$missing=false;

			if(!isset($this->global_options[$variable])) $missing=true;
			if($this->global_options[$variable]['required'] && empty($this->global_options[$variable]['value'])) $missing=true;

			if($missing){
				throw new \Exception("Missing global option: --$variable");
			}

			return @$this->global_options[$variable]['value'];
		}

		function _set_option(&$options, $name, $value, $scope){
			if($scope=='global'){
				$this->set_global($name, $value);
			}
			else{
				$options[trim($name, '- ')]=$value;
			}
		}

		function handle($argv){
			$app=array_shift($argv);
			$this->app=$this->app ?: pathinfo($app)['filename'];

			// 将arguments拆分成cmds和带有"-"开头的args
			$cmds=$args=[];
			foreach($argv as $a){
				if(strpos($a, '-')===0){
					$args[]=$a;
				}
				else{
					$cmds[]=$a;
				}
			}

			$help_mode=false;

			// 是否帮助模式
			if(!empty($cmds) && $cmds[0]=='help'){
				$help_mode=true;
				array_shift($cmds);
			}

			// 识别command chain
			$matches=[];
			while(!empty($cmds)){
				$matches=$this->_best_match_command($cmds);
				if($matches) break;

				if(empty($matches)){
					array_unshift($args, array_pop($cmds));
				}
			}

			if(count($matches)==0){
				return $this->help();
			}
			elseif(count($matches)>1){
				return $this->help_command_list($matches);
			}

			$func=$matches[0];
			if($help_mode){
				return $this->help_command($func);
			}

			// var_dump($func, $args);exit();

			$help=$this->_parse_command($func);

			$params=[]; $idx=0;
			$options=[];

			// 解析options
			$waiting_option=null;

			foreach($args as $a){
				if(strpos($a, '-')===0){
					if(strpos($a, '=')>0){
						@list($k, $v)=explode("=", $a, 2);
						$waiting_option=false;
					}
					else{
						// 形如 --debug ，一定是boolean
						$k=$a;
						$v=1;

						// 也可能值在后面
						$waiting_option=$k;
					}

					$matches=$this->_best_match_option($help['options'], $k);

					if(count($matches)==0){
						// 不存在的option作为definition
						@define(strtoupper(trim($k, '-')), $v);

						continue;
					}
					elseif(count($matches)>1){
						printf("Ambigous options for: $a\n");
						return $this->help_command($func);
					}

					@list($k, $scope)=$matches[0];
					if($waiting_option) $waiting_option=$matches[0];

					$this->_set_option($options, $k, $v, $scope);
				}
				elseif($waiting_option){
					// 这不是param，而是之前option的value
					@list($k, $scope)=$waiting_option;
					$this->_set_option($options, $k, $a, $scope);
					$waiting_option=null;
				}else{
					array_push($params, $a);
				}
			}

			// 检查parameter不足，则显示帮助信息
			if(count($params) < count($help['param'])){
				return $this->help_command($func);
			}

			// 将option转换为parameter
			if($options){
				foreach($options as $k=>$v){
					$idx=$help['options'][trim($k, '-')]['idx'];

					for($i=0; $i<=$idx; $i++){
						if(!isset($params[$i])) $params[$i]=null;
					}
					$params[$idx]=$v;
				}
			}

			return call_user_func_array($func, $params);
		}
	}
}
