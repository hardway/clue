<?php 
namespace Clue{
    require_once 'Mustache/Autoloader.php';
    \Mustache_Autoloader::register();

    define('CLUE_VIEW_FUNCTION_FILTER', "eval, call_user_func, exec, system, passthru, pcntl_exec");

    // TODO: cache should be implemented at controller or router level.
    class View{
        protected $view;
        protected $template;
        protected $compiled;
        protected $vars;
        
        function __construct($view=null){
            $this->view=trim($view, '/');

            foreach(array("mustache", "html") as $ext){
                $this->template=APP_ROOT."/view/$view.$ext";
                if(file_exists($this->template)){
                    break;
                }
            }

            $this->compiled=APP_ROOT."/view/$view.compiled";
            
            if(!file_exists($this->template)){
				throw new Exception("View didn't exists: $view");
            }
            
            $this->vars=array();
        }
        
        function set($name, $value){
            $this->vars[$name]=$value;
        }

        function subview($view, $inherit_vars=true){
            if($view[0]!='/' && !preg_match('/:/', $view)){
                // Convert to absolute path based on VIEW_ROOT
                $view=dirname($this->view).'/'.$view;
            }

            $sv=new View($view);
            if($inherit_vars) $sv->vars=$this->vars;

            return $sv;
        }
        
        function render($vars=array()){
            if(is_array($vars))
                $this->vars=array_merge($this->vars, $vars);
            else
                $this->vars=$vars;

            if(preg_match('/\.mustache$/i', $this->template)){
                $mustache_base=dirname(APP_ROOT."/view/".$this->view);
                $m = new \Mustache_Engine(array(
                    'loader'=>new \Mustache_Loader_FilesystemLoader($mustache_base),
                    'partials_loader'=>new \Mustache_Loader_FilesystemLoader($mustache_base)
                ));
                echo $m->render(basename($this->view), $this->vars);
            }
            else{
                if(!file_exists($this->compiled) || filemtime($this->template) > filemtime($this->compiled)){
                    $this->compile();
                }
                extract($this->vars);
                include $this->compiled;
            }
        }
        
        function compile(){
            $page=file_get_contents($this->template);

            // TODO: if/else, viriable suffix
            //      http://www.raintpl.com/Documentation/
            //      http://www.smarty.net/docs/en/language.basic.syntax.tpl
            //      http://www.twig-project.org
            $syntax=array(
                // Comment: {* comment *}
                '\{\*'                                                      =>'COMMENT_BEGIN',
                '\*\}'                                                      =>'COMMENT_END',
                
                // Foreach: {foreach $array as $n}
                '\{foreach\s+\$?(?:[a-z0-9_]+)\s+as\s+\$?(?:.+)\}'          =>'FOREACH_BEGIN',
                '\{\/foreach\}'                                             =>'FOREACH_END',
                
                // Variable: {$var} {$obj->prop} {$array.index}
                '\{(?:\$[a-z0-9_.\->]+)(?:\s*\|\s*(?:.+))?\}'               =>'VARIABLE',
                
                // Include: {include somefile with var list}
                '\{include\s+(?:[\/a-z0-9_]+)(?:\s+with\s+(?:.+))?\}'            =>'INCLUDE',
                
                // Function Call: {func(arg1, arg2)}
                '\{(?:[a-z0-9_]+)\s*\((?:[^)]+)?\)\}'                       =>'FUNCTION',
            );
            
            $delimeters='('.implode(')|(', array_keys($syntax)).')';
            $source=preg_split('/'.$delimeters.'/im', $page, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            $compiled="";
            
            foreach($source as $src){
                foreach($syntax as $syn=>$syn_name){
                    $syn=str_replace('?:', '', $syn);
                    if(preg_match('/'.$syn.'/i', $src, $m)){
                        switch($syn_name){
                            case 'COMMENT_BEGIN':
                                $src="<?php /*";
                                break;
                                
                            case 'COMMENT_END':
                                $src="*/ ?>";
                                break;
                                
                            case 'FOREACH_BEGIN':
                                $src="<?php foreach(\$$m[1] as \$$m[2]){ ?>";
                                break;
                                
                            case 'FOREACH_END':
                                $src="<?php } ?>";
                                break;
                                
                            case 'VARIABLE':
                                $var=$this->parse_var($m[1]);
                                
                                if(count($m)>=4){
                                    $modifier=$m[3];
                                    $var="$modifier($var)";
                                }
                                
                                $src="<?php echo $var ?>";
                                
                                break;
                                
                            case 'INCLUDE':
                                $view=$m[1];

                                if(!empty($view)){
                                    if($view[0]!='/' && !preg_match('/:/', $view)){
                                        // Convert to absolute path based on VIEW_ROOT
                                        $view=dirname($this->view).'/'.$view;
                                    }

                                    $src ="<?php \$v=new Clue\View(\"$view\"); ";
                                    if(isset($m[3])) foreach(explode(",", $m[3]) as $v){
                                        $v=trim($v);
                                        $src.="\$v->set('$v', \$$v);";
                                    }
                                    $src.="\$v->render(\$this->vars); ?>";
                                }
                                else
                                    $src=$m;
                                    
                                break;
                                
                            case 'FUNCTION':
                                // TODO: Some functions should be forbiddened.
                                $func=$m[1];
                                
                                if($func[0]=='.'){
                                    $func='$this->'.substr($func, 1);
                                }
                                                                
                                if(strpos(CLUE_VIEW_FUNCTION_FILTER, $func)!==false){
                                    $src=$m[0];
                                }
                                else{
                                    $args=array();
                                    
                                    // TODO: what if the string parameter contains comma?
                                    if(isset($m[2])){
                                        foreach(explode(",", $m[2]) as $a){
                                            $a=trim($a);
                                            if($a[0]=='$'){
                                                $a=$this->parse_var($a);
                                                $args[]="$a";
                                            }
                                            else
                                                $args[]="'$a'";
                                        }
                                    }
                                    
                                    $src="<?php echo $func(".implode(",", $args)."); ?>"; 
                                }
                                break;
                        }
                        break;
                    }
                }
                $compiled.=$src;
            }
            
            file_put_contents($this->compiled, $compiled);
        }
        
        function parse_var($str){
            if(preg_match('/\$([a-z0-9_]+)\.([a-z0-9_]+)/i', $str, $m)){
                return "\${$m[1]}['{$m[2]}']";
            }
            else return $str;
        }
    }
}
?>