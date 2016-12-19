<?php
namespace Clue\Traits;

trait Events{
    function fire_event($event, $data=null){
        // Always listen on itself by default
        if(method_exists($this, "on_$event")){
            call_user_func([$this, "on_$event"], $this, $data);
        }

        if(isset($this->_events) && is_array($this->_events)) foreach($this->_events as $name=>$listeners){
            if(preg_match('/'.$name.'/', $event)){
                if(is_array($listeners)) foreach($listeners as $l){
                    if(is_callable($l)){
                        call_user_func($l, $this, $data);
                    }
                    elseif(method_exists($l, "on_$event")){
                        call_user_func([$l, "on_$event"], $this, $data);
                    }
                }
            }
        }
    }

    function add_event_listener($events, $listener){
        $events=is_string($events) ? [$events] : $events;
        foreach($events as $e){
            if(!isset($this->_events[$e])) $this->_events[$e]=[];
            if(!in_array($listener, $this->_events[$e])) $this->_events[$e][]=$listener;
        }
    }

    function remove_event_listener($events, $listener=null){
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
