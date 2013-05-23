<?php 
namespace Clue{
    class FormElement{
        protected $prop;

        function __construct($prop){
            $this->prop=$prop;
        }

        function __toString(){
            $html="";
            $html.="<div class='field'>";
            if(isset($this->prop['label'])){
                $html.="<div class='label'><label>{$this->prop['label']}</label></div>";
            }
            $html.="<div class='input'>". $this->input_html()."</div>";
            $html.="</div>";

            return $html;
        }

        function filter_prop($excludes=array()){
            $filtered=array();
            foreach($this->prop as $p=>$v){
                if($p=='name' || $p=='type' || $p=='value' || $p=='label')
                    continue;
                
                foreach($excludes as $ex){
                    if($p==$ex) continue;
                }

                $filtered[$p]=$v;
            };

            return $filtered;
        }

        function build_tag_prop($pairs=array()){
            $prop=array();
            foreach($pairs as $p=>$v){
                $prop[]=$p.'="'.htmlspecialchars($v)."\"";
            }
            return implode(" ", $prop);
        }
    }

    class FormElementText extends FormElement{
        function input_html(){
            return "<input type='text' name='{$this->prop['name']}' value='{$this->prop['value']}' />";
        }
    }

    class FormElementHidden extends FormElement{
        function __toString(){
            return "<input type='hidden' name='{$this->prop['name']}' value='{$this->prop['value']}' />";
        }
    }

    class FormElementTextarea extends FormElement{
        function input_html(){
            $extra=$this->build_tag_prop($this->filter_prop());
            return "<textarea name='{$this->prop['name']}' $extra>{$this->prop['value']}</textarea>";
        }
    }

    class Form{
        protected $fields;

        function __construct($fields, $buttons=array()){
            $this->fields=$fields;

            $default_buttons=array(array('title'=>'Save', 'type'=>'submit'), array('title'=>'Cancel', 'type'=>'reset'));
            $this->buttons=$buttons ?: $default_buttons;
        }
        
        function __toString(){
            $html="";

            foreach($this->fields as $prop){
                $cls="Clue\\FormElement".ucfirst($prop['type']);
                $html.=new $cls($prop);
            }

            $html.="<div class='actions'>";
            foreach($this->buttons as $prop){
                $html.="<input class='btn' type='{$prop['type']}' value='{$prop['title']}' /> ";
            }
            $html.="</div>";
            
            return $html;
        }
    }
}
?>