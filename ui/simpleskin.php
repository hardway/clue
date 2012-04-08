<?php  
namespace Clue{
	class Clue_UI_SimpleSkin extends Clue_UI_Skin{
		function __construct($option=array()){
			if(isset($option['template'])){
				$this->template=$option['template'];
			}
			else
				$this->template='skin';
			
			$this->template_path=APP_ROOT . "/view".DS.$this->template;
			
			// check if skin exists.
			if(!is_dir($this->template_path)) exit("template missing: $this->template_path");
		}
		
		function render(){
			$this->render_header();
			echo $this->body;
			$this->render_footer();
		}
		
		protected function render_header(){
			$title=implode("\n", $this->header['title']);
			
			$styles=implode("\n", $this->header['styleSheets']);
			if(count($this->header['style'])>0){
				$styles.='<style type="text/css" media="screen">'.implode("\n", $this->header['style']).'</style>';
			}
			
			$scripts=implode("\n", $this->header['scripts']);
			if(count($this->header['script'])>0){
				$scripts.='<script type="text/javascript" charset="utf-8">'.implode("\n", $this->header['script']).'</script>';
			}
		
            $view=$this->template_path.DS.'header.tpl';
            $php_view=$view.".php";    

            if(file_exists($php_view))
                include($php_view);
            else
                include($view);
		}
		
		protected function render_footer(){
            $view=$this->template_path.DS.'footer.tpl';
            $php_view=$view.".php";    

            if(file_exists($php_view))
                include($php_view);
            else
                include($view);
		}
	}
}
?>
