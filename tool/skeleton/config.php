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

    // 自定义Guard
    @define("ERROR_LOG_LEVEL", 'ERROR');
    @define("ERROR_MAIL_LEVEL", 'ERROR');
    @define("ERROR_DISPLAY_LEVEL", 'ERROR');
    @define("ERROR_MAIL_TO", null);

    // 自定义Profiler
    @define('APP_PROFILER', false);

    $config=[
        // 数据库配置（取消注释以启用）
        // 'database'=>array(
        //     'type'=>'mysql',
        //     'host'=>DB_HOST,
        //     'db'=>DB_NAME,
        //     'username'=>DB_USER,
        //     'password'=>DB_PASS,
        //     'encoding'=>"UTF8MB4"
        // ),

        // 资源文件
        'asset'=>[
            'app.css'=>['asset/css/*.css', 'asset/css/*.less'],
            'app.js'=>'asset/js/*.js',
        ],

        'route'=>[
            '/^\/.*-p-(\d+)$/i'=>'/product/view/$1',
            '/^\/.*-c-(\d+)$/i'=>'/category/view/$1',
            '/^\/terms-of-service$/i'=>'/index/terms',
        ],

        // 性能优化
        'profiler'=>APP_PROFILER,

        // 错误报告
        'guard'=>[
            'mail_to'=>ERROR_MAIL_TO,
            'log_level'=>ERROR_LOG_LEVEL,
            'email_level'=>ERROR_MAIL_LEVEL,
            'display_level'=>ERROR_DISPLAY_LEVEL,
        ]
    ];

    return $config;
