<?php  
namespace Clue{
    class HTML{
        static function select_options($options, $selected=null, $singleOrPair=null){
		    if(!is_array($selected)) $selected=array($selected);
		    
		    $html="";
		    foreach($options as $value=>$name){
		        if($name instanceof ActiveRecord){  // 自动处理activerecord对象
		            $obj=$name;
		            $model=$obj->model();
		            $pkey=$model['pkey'];
		            $value=$obj->$pkey;
		            $name=(string)$obj;
		        }
		        elseif($singleOrPair=='single')
		            $value=$name;
		        else if($singleOrPair=='pair')
		            $value=$value;
		        else
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
}
?>
