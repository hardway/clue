<?php 
namespace Clue{
    class Clue_Form{
        protected $fields;

        function __construct($fields){
            $this->fields=$fields;
        }
        
        function to_html(){
            $html="";

            foreach($this->fields as $name=>$prop){
                $html.=$this->draw_field($name, $prop);
            }
            
            return $html;
        }
        
        function draw_field($name, $prop){
            $type=$prop['type'];
            if(!method_exists($this, "draw_{$type}_field")){
                throw new Exception("Can't handle form field type: $type.");
            }
            
            return call_user_func(array($this, "draw_{$type}_field"), $name, $prop);
        }
        
        function draw_line_field($name, $prop){
            $html="<div><label><input type='' value='' name='' /></label></div>";
            return $html;
        }
        
        function draw_text_field($name, $prop){
            $html="<div><label><textarea name='$name'></textarea></label></div>";
            return $html;
        }
        
        function draw_select_field($name, $prop){
            $html="<div><label><select name='$name'></select></label></div>";
            return $html;
        }
    }
}
?>