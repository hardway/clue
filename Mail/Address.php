<?php
namespace Clue\Mail;

class Address{
    function __construct($email, $name=null){
        if($email instanceof Address){
            $copy=$email;
            $this->email=$copy->email;
            $this->name=$copy->name;
        }
        // RFC2822
        elseif($name==null && preg_match('/(.*?)<([^>]+)>(.*?)/', $email, $m)){
            $this->email=$m[2];
            $this->name=trim($m[1].$m[3], " \"'<>");
        }
        else{
            $this->email=$email;
            $this->name=$name;
        }

        @list($this->user, $this->domain)=explode('@', $this->email);
    }

    function __toString(){
    	if(empty($this->name)){
    		return $this->email;
    	}
    	else
        	return trim("\"$this->name\"<$this->email>");
    }
}
