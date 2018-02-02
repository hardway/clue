<?php
namespace Clue;

class Collection implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable{
    static function make($items){
        return new self($items);
    }

    protected $items;

    function __construct($items=[]){
        if(is_array($items)){
            $this->items=$items;
        }
        else{
            throw new \Exception("Can't convert ".get_class($items)." items to colleciton");
        }
    }

    function offsetSet($k, $v){return $this->items[$k]=$v;}
    function offsetGet($k){return $this->items[$k];}
    function offsetExists($k){return isset($this->items[$k]);}
    function offsetUnset($k){unset($this->items[$k]);}

    function getIterator(){ return new \ArrayIterator($this->items); }
    function jsonSerialize(){ return json_encode($this->items); }
    function __toArray(){ return $this->items; }

    function set($k, $v){return data_set($this->items, $k, $v);}
    function get($k){return data_get($this->items, $k);}

    function all(){ return $this->items; }
    function values(){ return array_values($this->items); }

    function avg($key=null){return array_sum($this->items) / count($this->items);}
    function count(){return count($this->items);}

    function contains($val){return !!array_search($val, $this->items);}
    function chunk($size){return new static(array_chunk($this->items, $size));}
    function combine($values){ return new static(array_combine($this->items, $values));}

    function except($fields){
        $c=new static($this->items);
        $fields=is_array($fields) ? $fields : [$fields];

        foreach($fields as $f){
            unset($c[$f]);
        }

        return $c;
    }

    function filter($func){
        $callback=is_callable($func)
            ? $func
            : function($v) use($func){return $v==$func;};

        return new static(array_filter($this->items, $callback));
    }

    function reject($func){
        $callback=is_callable($func)
            ? function($v) use($func){return !$func($v);}
            : function($v) use($func){return $v!=$func;};

        return new static(array_filter($this->items, $callback));
    }

    function where($key, $val){
        if(is_callable($key)){
            return new static(array_filter($this->items, $key));
        }
        else{
            return new static(array_filter($this->items, function ($item) use ($key, $val) {
                return data_get($item, $key)==$val;
            }));
        }
    }

    function where_in($key, $values){
        return new static(array_filter($this->items, function ($item) use ($key, $values) {
            return in_array(data_get($item, $key), $values);
        }));
    }

    function where_not_in($key, $values){
        return new static(array_filter($this->items, function ($item) use ($key, $values) {
            return !in_array(data_get($item, $key), $values);
        }));
    }

    function flip(){return array_flip($this->items);}
    function map($func){return new static(array_map($func, $this->items));}
    function reverse(){return new static(array_reverse($this->items));}

    function group($field){
        $rs=[];

        foreach ($this->items as $key => $item) {
            $id = data_get($item, $field);

            if (! array_key_exists($id, $rs)) {
                $rs[$id] = new static;
            }

            $rs[$id][$key]=$item;
        }

        return new static($rs);
    }

    function sort($func){
        $callback=$func;

        $items=$this->items;

        array_multisort(array_map($callback, $items), $items);

        return new static($items);
    }

    function unique($field=null){
        if (is_null($field)) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $exists = [];

        return $this->reject(function ($item) use ($key, &$exists) {
            $id=data_get($item, $key);
            if (in_array($id, $exists)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    function random($cnt){$rand=array_rand($this->items, $cnt); return new static(array_intersect_key($this->items, array_flip($rand)));}

    function pluck($field){
        return new static(array_map(function($item) use($field){return data_get($item, $field);}, $this->items));
    }

    function implode($glue){
        return implode($glue, $this->items);
    }

    function partition($func){
        panic("Not Implemented");
    }

    function fold($m, $f){
        panic("Not Implemented");
    }

    function rfold($m, $f){
        panic("Not Implemented");
    }

    /**
     * 数组交集
     */
    function intersect($ary){
        $this->items=array_intersect($this->items, $ary);
        return $this;
    }
}

/**
 * 按路径获取对象或数组中的值
 *
 * @param  mixed   $target
 * @param  string|array  $key
 * @param  mixed   $default
 * @return mixed
 */
function data_get($target, $key, $default = null)
{
    if (is_null($key)) return $target;

    $key = is_array($key) ? $key : explode('.', $key);

    while (($segment = array_shift($key)) !== null) {
        if(is_array($target) && isset($target[$segment])) {
            $target = $target[$segment];
        } elseif (is_object($target) && isset($target->{$segment})) {
            $target = $target->{$segment};
        } else {
            return $default;
        }
    }

    return $target;
}

/**
 * 按路径设置对象或数组中的值
 *
 * @param  mixed  $target
 * @param  string|array  $key
 * @param  mixed  $value
 * @param  bool  $overwrite
 * @return mixed
 */
function data_set(&$target, $key, $value, $overwrite = true)
{
    $segments = is_array($key) ? $key : explode('.', $key);

    $segment = array_shift($segments);

    if (is_array($target)) {
        if ($segments) {
            if (!isset($target[$segment])) {
                $target[$segment] = [];
            }

            data_set($target[$segment], $segments, $value, $overwrite);
        } elseif ($overwrite || ! isset($target[$segment])) {
            $target[$segment] = $value;
        }
    } elseif (is_object($target)) {
        if ($segments) {
            if (! isset($target->{$segment})) {
                $target->{$segment} = [];
            }

            data_set($target->{$segment}, $segments, $value, $overwrite);
        } elseif ($overwrite || ! isset($target->{$segment})) {
            $target->{$segment} = $value;
        }
    } else {
        $target = [];

        if ($segments) {
            data_set($target[$segment], $segments, $value, $overwrite);
        } elseif ($overwrite) {
            $target[$segment] = $value;
        }
    }

    return $target;
}
