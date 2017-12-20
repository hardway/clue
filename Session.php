<?php
namespace Clue;

class DBSession implements \SessionHandlerInterface{
    public function __construct(array $options=[]){
        $this->db=$options['db'];

        $this->table=@$options['table'] ?: "_session";
        $this->ttl=@$options['ttl'] ?: 1440;
    }

    function remember($retention){
        $this->db->exec("update $this->table set retention=%d where id=%s", $retention, session_id());
    }

    public function close(){return true;}
    public function open($save_path, $session_name){
        // 创建Session表
        if(!$this->db->has_table($this->table)){
            $this->db->exec("
                CREATE TABLE `$this->table`(
                    id varchar(32) not null primary key,
                    ipaddr varchar(16) not null,
                    useragent varchar(256),
                    created datetime not null,
                    retention int not null default 0,   -- 多少天内可以用cookie恢复
                    last_update timestamp not null,
                    data text
                ) DEFAULT CHARSET=utf8
            ");
        }

        return true;
    }

    public function read($session_id){
        $s=$this->db->get_row("select *, now() now from $this->table where id=%s", $session_id);

        if(!$s) return null;

        // 检测是否超时
        $idle=strtotime($s->now) - strtotime($s->last_update);

        if($idle > $this->ttl){
            // 综合IP和Agent，是否可以根据Cookie恢复
            if($s->retention && $idle < $s->retention*86400 && $_SERVER['REMOTE_ADDR']==$s->ipaddr && @$_SERVER['HTTP_USER_AGENT']==$s->useragent){
                $this->write($session_id, $s->data);
            }
            else{
                $this->destroy($s->id);
                return null;
            }
        }

        return $s->data;
    }

    public function write($session_id, $session_data){
        $this->db->exec("
            insert into $this->table (id, ipaddr, useragent, created, data)
            values(%s, %s, %s, now(), %s)
            on duplicate key update data=%s, last_update=now()
        ", $session_id, $_SERVER['REMOTE_ADDR'], @$_SERVER['HTTP_USER_AGENT'], $session_data, $session_data);

        return true;
    }

    public function destroy($session_id){
        return $this->db->exec("delete from $this->table where id=%s", $session_id);
    }

    public function gc($maxlifetime){
        // 有Retention的不会被轻易gc
        return $this->db->exec("delete from $this->table where last_update + interval retention day < now() - interval %d second", $maxlifetime);
    }
}

class FileSession implements \SessionHandlerInterface{
    public function __construct(array $options=[]){
        $this->folder=$options['folder'];

        $this->ttl=@$options['ttl'] ?: 1440;
    }

    function remember($retention){
        $path="$this->folder/".session_id();
        if(!file_exists($path)) return false;

        $json=json_decode(file_get_contents($path), true);
        $json['retention']=$retention;

        return file_put_contents($path, json_encode($json));
    }

    public function close(){return true;}
    public function open($save_path, $session_name){
        // 创建Session目录
        if(!is_dir($this->folder)){
            $ok=mkdir($this->folder, 0775, true);
            if(!$ok) panic("Can't create session folder ($this->folder).");
        }

        return true;
    }

    public function read($session_id){
        $path="$this->folder/$session_id";
        if(!file_exists($path)) return null;

        $json=json_decode(file_get_contents($path), true);
        $idle=time() - filemtime($path);

        // 检测是否超时
        if($idle > $this->ttl){
            // 综合IP和Agent，是否可以根据Cookie恢复
            if(@$json['retention'] && $idle < $json['retention']*86400 && $_SERVER['REMOTE_ADDR']==$json['ipaddr'] && @$_SERVER['HTTP_USER_AGENT']==$json['useragent']){
                $this->write($session_id, $s->data);
            }
            else{
                $this->destroy($session_id);
                return null;
            }
        }

        return $json['data'];
    }

    public function write($session_id, $session_data){
        $path="$this->folder/$session_id";

        if(!file_exists($path)){
            $json=[
                'ipaddr'=>$_SERVER['REMOTE_ADDR'],
                'useragent'=>@$_SERVER['HTTP_USER_AGENT'],
                'created'=>time(),
            ];
        }
        else{
            $json=json_decode(file_get_contents($path), true);
        }

        $json['data']=$session_data;

        return file_put_contents("$this->folder/$session_id", json_encode($json));
    }

    public function destroy($session_id){
        return unlink("$this->folder/$session_id");
    }

    public function gc($maxlifetime){
        foreach(scandir($this->folder) as $f){
            if($f[0]=='.') continue;

            $path="$this->folder/$f";
            $idle=tiem() - filemtime($path);
            if($idle > $maxlifetime){
                // 检查Retention
                $json=json_decode(file_get_contents($f), true);
                if($idle > @$json['retention']*86400){
                    $this->destroy($f);
                }
            }
        }

        return true;
    }
}

class Session{
    static function init($app, $options){
        $session=null;

        switch(@$options['storage']){
            // 指定Session存储
            case 'DB':
                $session=new DBSession($options);
                break;
            case 'FILE':
                $session=new FileSession($options);
                break;
            default:
                // 使用PHP系统自带
        }

        if($session){
            session_set_save_handler($session, true);
        }

        return $session;
    }
}
