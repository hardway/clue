<?php
namespace Clue\Grid;

/*
create table grid_job(
    id int unsigned not null primary key auto_increment,
    worker varchar(32) not null,
    type varchar(32) not null,
    begin_time datetime not null,
    end_time datetime,
    status enum('success', 'fail', 'pending'),
    index idx_grid_job(worker, type)
);

create table grid_task(
    id serial,
    job_id int unsigned not null,
    refid varchar(64),
    status enum('success', 'fail', 'pending'),
    index idx_grid_task(refid)
);
*/
abstract class JobServer{
    protected $db;

    protected $task_type=null;
    protected $task_limit=20;
    protected $task_timeout=3600;   // 默认超时1小时后认为失败

    function __construct($db){
        $this->db=$db;
    }

    function auth($client, $token){
        $this->worker=$client;

        return true;
    }

    /**
     * 根据type (table)和id(s)创建Job，并且返回job id
     */
    function create_job($task_type, $refids){
        $job=$this->db->insert('grid_job', [
            'worker'=>$this->worker,
            'begin_time'=>date("Y-m-d H:i:s"),
            'type'=>$task_type,
            'status'=>'pending'
        ]);
        if(!$job) return null;

        $tasks=[];
        foreach($refids as $id){
            $tasks[]=$this->db->insert('grid_task', [
                'job_id'=>$job,
                'refid'=>$id,
                'status'=>'pending'
            ]);
        }

        return ['id'=>$job, 'tasks'=>$this->get_task_data($tasks)];
    }

    function pending_job($task_type=null){
        $task_type=$task_type ?: $this->task_type;

        $job=$this->db->get_var("
            select id from grid_job where worker=%s and type=%s and status='pending'
        ", $this->worker, $task_type);

        if(!$job) return null;

        $ids=$this->db->get_col("select id from grid_task where job_id=%d and status='pending'", $job);
        if(empty($ids)) return null;

        return ['id'=>$job, 'tasks'=>$this->get_task_data($ids)];
    }

    function job_reserve(){
        // 处理中的任务，尚未完成（例如worker意外终止）
        $job=$this->pending_job();
        if($job) return $job;

        // 等待处理的website
        $tasks=$this->get_task_todo();
        if(!$tasks) return null;

        return $this->create_job($this->task_type, $tasks);
    }

    function job_done($job_id, $status='success'){
        $this->db->exec("
            update grid_job set end_time=now(), status=%s where id=%d
        ", $status, $job_id);

        $this->db->exec("
            update grid_task set status=%s where job_id=%d and status='pending'
        ", $status, $job_id);
    }

    function task_done($task_id, $status='success'){
        $this->db->exec("
            update grid_task set status=%s where id=%d
        ", $status, $task_id);
    }

    abstract protected function get_task_data($task_ids);
    abstract protected function get_task_todo();
}
