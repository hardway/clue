<?php
namespace Clue\Mail;

class Address{
    function __construct($email, $name=null){
        $this->email=$email;
        $this->name=$name;
    }

    function __toString(){
        return trim("<$this->email> $this->name");
    }
}
