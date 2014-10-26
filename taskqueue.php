<?php
namespace Clue{
    define("TASKQUEUE_TABLE", "_taskqueue");

    class TaskQueue{
        static $DB;

        static function init($db=null){
            // 检查数据库连接是否存在
            if($db) self::$DB=$db;
            if(!self::$DB) throw new \Exception("Database required for queue storage");

            if(!self::$DB->has_table(TASKQUEUE_TABLE)){
                self::$DB->exec("
                    create table ".TASKQUEUE_TABLE."(
                        id int not null primary key auto_increment,
                        src varchar(64),
                        dest varchar(64),
                        name varchar(64) not null,
                        recv_time datetime,
                        schedule_time datetime,
                        exec_time datetime,
                        done_time datetime,
                        payload text,
                        output text,
                        error text,
                        fails int,
                        expire int,
                        priority int not null default 0
                    )
                ");
            }
        }

        /**
         * 处理队列中所有的任务
         */
        static function process($options=[]){
            $default_options=['delay'=>5];
            $options=$default_options+$options;

            while(true){
                $task=self::pull_queue();
                if(!$task){
                    if(@$options['verbose']) error_log("No tasks found, wait {$options['delay']} seconds ...");
                    sleep($options['delay']); continue;
                }

                // 查找class是否存在
                list($class, $method)=explode("::", $task->name);
                if(!class_exists($class) || !method_exists($class, $method)){
                    throw new \Exception("Don't know how to workout $task->name");
                    sleep(1); // wait 1 seconds to give time for recovery
                }

                // 开始执行
                $payload=self::start_task($task->id);
                try{
                    $result=call_user_func_array([$class, $method], $payload);
                    self::done_task($task->id, $result);
                }catch(\Exception $e){
                    self::fail_task($task->id, $e->getMessage());
                }
            }
        }

        // 队列

        /**
         * 插入队列
         * @param $queue queue的名字或者完整配置
         * @param $args... 调用函数的完整参数
         * @return 任务ID
         */
        static function push_queue($queue, $args=null){
            $default=[// 默认属性
                'recv_time'=>date("Y-m-d H:i:s"),
            ];

            self::init();

            // 所有的参数作为Payload
            $args=func_get_args();
            $queue=array_shift($args);

            if(is_string($queue)) $queue=['name'=>$queue];
            $queue=$default+$queue;
            $queue['payload']=json_encode($args);

            $id=self::$DB->insert(TASKQUEUE_TABLE, $queue);
            if(!$id) throw new \Exception("Create task queue failed");

            return $id;
        }

        /**
         * 获取队列任务
         * @param $queue 队列名称或者复杂的组合条件
         */
        static function pull_queue($queue=null){
            self::init();

            $sql="
                select id, name, src, dest, fails, recv_time, schedule_time from ".TASKQUEUE_TABLE." where
                    dest is null ".($queue ? self::$DB->format(" and name=%s", $queue) : "")."
                    and done_time is null and exec_time is null
                order by priority desc, id asc
                limit 1
            ";

            return self::$DB->get_row($sql);
        }

        // 任务

        /**
         * 开始任务，记录exec_time，避免任务重复执行
         */
        static function start_task($taskid){
            self::init();

            $payload=self::$DB->get_var("
                select payload from ".TASKQUEUE_TABLE." where id=%d
            ", $taskid);

            self::$DB->exec("update ".TASKQUEUE_TABLE." set exec_time=now() where id=%d", $taskid);

            return json_decode($payload);
        }

        /**
         * 完成任务
         */
        static function done_task($taskid, $output){
            self::init();

            self::$DB->exec("
                update ".TASKQUEUE_TABLE." set done_time=now(), output=%s where id=%d
            ", json_encode($output), $taskid);
        }

        /**
         * 任务失败
         */
        static function fail_task($taskid, $error){
            self::init();

            self::$DB->exec("
                update ".TASKQUEUE_TABLE." set done_time=now(), fails=ifnull(fails, 0)+1, error=%s where id=%d
            ", json_encode($error), $taskid);
        }

        /**
         * 重新尝试任务
         */
        static function retry_task($taskid){
            self::init();

            self::$DB->exec("
                update ".TASKQUEUE_TABLE." set exec_time=null, done_time=null, error=null where id=%d
            ", $taskid);

            return self::start_task($taskid);
        }
    }
}
