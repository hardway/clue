<?php
    require_once dirname(__DIR__).'/stub.php';

    $cmd=new Clue\CLI\Command("ClickHouse Tool", 'ch');

    try{
        printf("\n");
        $cmd->handle($argv);
        printf("\n");
    }
    catch(Exception $e){
        error_log("\n");
        Clue\CLI::banner($e->getMessage(), 'red');
        error_log($e->getTraceAsString());
    }

    /**
     * Test connection of server
     */
    function ch_test($host, $port=8123){
        $ch=Clue\Database::create(['type'=>'clickhouse', 'host'=>$host, 'port'=>$port, 'db'=>'default']);

        printf("Databases:\n%s\n", str_repeat('-', 60));
        foreach($ch->get_col("select name from system.databases") as $t){
            printf("%s\n", $t);
        }
        printf("\n");

        printf("Metrics:\n%s\n", str_repeat('-', 60));
        foreach($ch->get_hash("select metric, value from system.metrics where value<>0") as $n=>$v){
            printf("%40s\t%s\n", $n, $v);
        }
        printf("\n");
    }
