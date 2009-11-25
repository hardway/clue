<?php  
	class Clue_UI_SimpleSkin extends Clue_UI_Skin{
		function __construct($option=array()){
			if(isset($option['template'])){
				$this->template=$option['template'];
			}
			else
				$this->template='skin';
			
			$this->template_path="view".DS.$this->template;
			
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
				$styles.='<script type="text/javascript" charset="utf-8">'.implode("\n", $this->header['script']).'</script>';
			}
			
			include($this->template_path . DS . 'header.tpl');
		}
		
		protected function render_footer(){
			include($this->template_path . DS . 'footer.tpl');
		}
	}
?>