<?php  
    class Clue_HTML{
        static function html_options($options, $selected){
		    if(!is_array($selected)) $selected=array($selected);
		    
		    $html="";
		    foreach($options as $value=>$name){
		        $value=is_int($value) ? $name : $value;
		        
		        $html.="<option value='$value' ".(in_array($value, $selected) ? "selected='1'":"").">$name</option>";
		    }
		    
		    return $html;
        }
        
        static function format_text($text){
            $text=str_replace(
                    array('<', '>', "\n", ' ', "\r"), 
                    array("&lt;", '&gt;', "<br/>\n", '&nbsp;', ''), 
                    $text
            );
            return $text;
        }
    }
?>
