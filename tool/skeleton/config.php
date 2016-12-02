<?php
    @include __DIR__.'/config.local.php';

	@define("APP_STAGE", "DEVELOPING");

	define("IS_DEVELOPING", preg_match('/^DEV/i', APP_STAGE));
	define("IS_PRODUCTION", preg_match('/^PROD/i', APP_STAGE));
	define("IS_TESTING", preg_match('/^TEST/i', APP_STAGE));

    @define("DB_HOST", "localhost");
    @define("DB_NAME", "ifancy");
    @define("DB_USER", "root");
    @define("DB_PASS", "");

    $config=array(
        'debug'=>true,
        'database'=>array(
            'type'=>'mysql',
            'host'=>'127.0.0.1',
            'db'=>'test',
            'username'=>'root',
            'password'=>''
        ),
    );

    return $config;
?>
