<?php 
namespace Clue{
    class Layout extends View{
        protected $name;
        protected $title;
        
        function __construct($name=null){
            $this->name='/layout/' . (empty($name) ? 'default' : $name);
            parent::__construct($this->name);
            
            $this->vars['title']='';
            $this->vars['scripts']='';
            $this->vars['styles']='';            
        }
        
        function set_title($title){
            $this->vars['title']=$title;
        }
        
        function add_script($script){
            $this->vars['scripts'].="<script type=\"text/javascript\" charset=\"utf-8\">$script</script>";
        }
        
        function add_script_file($file){
            $this->vars['scripts'].="<script type=\"text/javascript\" charset=\"utf-8\" src=\"$file\"></script>";
        }
        
        function add_style($style){
            $this->vars['styles'].="<style type=\"text/css\">$style</style>";
        }
        
        function add_style_file($file){
            $this->vars['styles'].="<link rel=\"stylesheet\" href=\"$file\" type=\"text/css\" />";
        }
    }
}
?>
