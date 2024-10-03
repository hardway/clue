<?php
namespace Clue;
class Daemon{
    /**
     * 确保用户必须是DAEMON用户
     */
    static function require_system_user($username){
        $user = posix_getpwnam($username);

        // 尝试切换用户
        $ru=posix_setuid($user['uid']);
        $rg=posix_setgid($user['gid']);

        if(!$ru && !$rg) panic(sprintf(
            "Can't switch to user ".$username." (%d, %d)\n", posix_getuid(), posix_getgid()
        ));
    }

    /**
     * 按需升级
     *
     * 如果配置变更则直接退出，upstart或者systemd会自动重启，以便加载最新的配置
     */
    static function auto_upgrade(){
        global $db;
        static $last_signature;

        $memory_limit=1000000000;
        $code_version=self::code_version();
        if(empty($code_version) || memory_get_usage() > $memory_limit){
            exit("Out of memory, restart.");
        }

        // 如果代码或者配置发生变更，则自动退出并重启
        $signature=$code_version.":".self::cfg_signature();

        if($last_signature && $last_signature!=$signature){
            exit("Config changed, restart.");
        }

        $last_signature=$signature;
    }

    /**
     * 配置版本，用MD5计算签名
     */
    static function cfg_signature(){
        global $db;

        if(!$db) return null;

        // 全部的配置
        $signature=md5(json_encode($db->get_results("select * from config")));
    }

    /**
     * 代码版本，通过mercurial获得
     */
    static function code_version(){
        if(is_dir(APP_ROOT.'/.hg')){
            $revision=exec("hg --cwd ".APP_ROOT." parent --template {rev} 2>/dev/null");
        }
        elseif(is_dir(APP_ROOT.'/.git')){
            $revision=exec('git -C '.APP_ROOT.' rev-parse --short HEAD');
        }

        return $revision;
    }

    public $timeout;

    function __construct(array $option=[]){
        $this->timeout=@$option['timeout'] ?: 10;
    }

    function set_loop_function(callable $func){
        $this->loop_func=$func;
    }

    function loop_check(){
        echo ".";
        self::auto_upgrade();
        sleep($this->timeout);
        // GEARMAN_WORK_FAIL!=$worker->work()

        return true;
    }

    function loop(){
        do{
            call_user_func($this->loop_func);
        } while($this->loop_check());
    }
}
