<?php
require_once 'clue/stub.php';

class Boss{
    use Clue\Traits\Observer;

    public $busy=false;

    function __construct(){
        $this->busy=false;
        // $this->attach($this, ['BossComing', 'BossLeft']);
    }

    function meeting($where, $seconds){
        $this->notify("BossComing", [$seconds, $where]);
        sleep($seconds);
        $this->notify("BossLeft");
    }

    function on_bosscoming($boss, $data){
        list($seconds, $where)=$data;
        echo "Bossing coming to $where for $seconds seconds\n";
    }

    function on_bossLeft(){
        echo "Bossing left\n";
    }
}

class Employee{
    function __construct($name){
        $this->name=$name;
    }
}

class GoodEmployee extends Employee{
    function on_bosscoming($boss, $data){
        if($boss->busy){
            echo " > $this->name helping busy boss\n";
        }
        else{
            echo " > $this->name already working\n";
        }
    }

    function on_bossLeft($boss){
        echo " > $this->name keep working\n";
    }
}

class BadEmployee extends Employee{
    function on_bosscoming($boss, $data){
        echo " > $this->name pretend working\n";
    }

    function on_bossLeft($boss){
        echo " > $this->name going home\n";
    }
}

$boss=new Boss();
$tom=new GoodEmployee("Tom");
$jerry=new BadEmployee("Jerry");

$boss->attach($tom, ['BossComing', 'BossLeft']);
$boss->attach($jerry, 'BossComing');
$boss->attach($jerry, 'BossLeft');
$boss->detach($tom);

$boss->meeting("Meeting Room", 2);
echo "------------------------------------\n";
echo "Now the boss is busy\n";
$boss->busy=true;
$boss->attach($tom, ['BossComing', 'BossLeft']);

$boss->meeting("Meeting Room", 3);



