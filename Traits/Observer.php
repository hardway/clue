<?php
namespace Clue\Traits;

trait Observer{
    function notify($event, $data=null){
        // Observer always listen on itself by default
        if(method_exists($this, "on$event")){
            call_user_func([$this, "on$event"], $this, $data);
        }

        if(is_array(@$this->_events)) foreach($this->_events as $name=>$listeners){
            if(preg_match('/'.$name.'/', $event)){
                if(is_array($listeners)) foreach($listeners as $l){
                    if(method_exists($l, "on$event")){
                        call_user_func([$l, "on$event"], $this, $data);
                    }
                }
            }
        }
    }

    function attach($listener, $events){
        $events=is_string($events) ? [$events] : $events;
        foreach($events as $e){
            if(!isset($this->_events[$e])) $this->_events[$e]=[];
            if(!in_array($listener, $this->_events[$e])) $this->_events[$e][]=$listener;
        }
    }

    function detach($listener, $events=null){
        if($events==null){
            $events=array_keys(@$this->_events) ?: [];
        }
        elseif(is_string($events)){
            $events=[$events];
        }

        foreach($events as $e){
            if(!isset($this->_events[$e])) continue;
            $listeners=array_filter($this->_events[$e], function($l) use($listener){return $l!=$listener;});
            $this->_events[$e]=$listeners;
        }
    }
}
