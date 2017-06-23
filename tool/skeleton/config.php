<?php
    @include __DIR__.'/config.local.php';

	@define("APP_STAGE", "DEVELOPING");

	define("IS_DEVELOPING", preg_match('/^DEV/i', APP_STAGE));
	define("IS_PRODUCTION", preg_match('/^PROD/i', APP_STAGE));
	define("IS_TESTING", preg_match('/^TEST/i', APP_STAGE));

    define("DEBUG", @$_SERVER['DEBUG'] ?: false);

    // 定义数据库连接信息
    @define("DB_HOST", "localhost");
    @define("DB_NAME", "");
    @define("DB_USER", "root");
    @define("DB_PASS", "");

    $config=[];

    if(DB_HOST && DB_NAME && DB_USER){
        $config['database']=[
            'type'=>'mysql',
            'host'=>DB_HOST,
            'db'=>DB_NAME,
            'username'=>DB_USER,
            'password'=>DB_PASS
        ];
    }

    return $config;
