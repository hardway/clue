<?php
namespace Clue\Mail;

class Address{
    function __construct($email, $name=null){
        // RFC2822
        if($name==null && preg_match('/(.*?)<([^>]+)>(.*?)/', $email, $m)){
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
        return trim("$this->name <$this->email>");
    }
}
