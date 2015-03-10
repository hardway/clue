<?php
require_once 'clue/stub.php';

class Clock{
    use Clue\Traits\Events;

    function on_tick(){
        echo date("Y-m-d H:i:s")."\n";
    }

    function run(){
        for($i=0; $i<5; $i++){
            $this->fire_event("tick");
            sleep(1);
        }

        $this->fire_event("broken", date("Y-m-d H:i:s"));
    }
}

class Baby{
    function on_tick(){
        echo "Happy\n";
    }

    function on_broken(){
        echo "Cry\n";
    }
}

$onTick=function($obj, $data){
    static $countdown=2;
    echo "$data Tick --".get_class($obj)."\n";

    if(--$countdown < 0){
        global $onTick;
        echo "Boring, Bye ...\n";
        // Remove Closure as listener
        $obj->remove_event_listener('tick', $onTick);
    }
};

$c=new Clock();
// Add closure as listener
$c->add_event_listener('tick', $onTick);
$c->add_event_listener("broken", function($obj, $data){
    echo get_class($obj)." Broken: $data\n";
});
// Add object as listener
$c->add_event_listener(['tick', 'broken'], new Baby());
$c->run();

