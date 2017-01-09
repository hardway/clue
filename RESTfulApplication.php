<?php
namespace Clue{
    require_once __DIR__."/application.php";

    class RESTfulApplication extends Application{
        static protected $_INSTANCE=null;

        /**
         * @param $options[title]   API文档名称
         * @param $options[docs]    文档目录
         */
        function __construct($options=array()){
            parent::__construct($options);

            $this->guard();

            // 获取原始请求内容
            $this->request=file_get_contents('php://input');

            // 试图解压为JSON
            $this->request=json_decode($this->request, true) ?: $this->request;
        }

        function guard(){
            // 设置Singleton保护
            if(is_object(self::$_INSTANCE)) throw new Exception("RestfulAPI is a singleton");
            self::$_INSTANCE=$this;

            // 系统运行时错误返回500错误
            set_error_handler(function($errno, $errstr, $errfile, $errline){
                error_log("$errno: $errstr ($errfile:$errline)");
                if($errno & (E_ERROR | E_USER_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_PARSE))
                    self::$_INSTANCE->error($errstr, 500);
            });

            set_exception_handler(function($e){
                error_log(sprintf("%s (%d)", $e->getMessage(), $e->getCode()));
                error_log($e->getFile().":".$e->getLine());
                error_log($e->getTraceAsString());

                self::$_INSTANCE->error($e->getMessage(), 500);
            });

            // 数据验证通过assert来保证，返回400 Bad Request
            assert_options(ASSERT_ACTIVE, 1);
            assert_options(ASSERT_CALLBACK, function($script, $line, $message, $description){
                error_log("[ASSERT/FAIL] $script:$line");
                self::$_INSTANCE->error($description, 400);
            });

            register_shutdown_function(function(){
                // 最后的机会捕捉到fatal error
                $fatal=error_get_last();
                if(is_array($fatal)){
                    error_log(sprintf("[Fatal %s] %s @ %s:%s", $fatal['type'], $fatal['message'], $fatal['file'], $fatal['line']));
                    self::$_INSTANCE->error($$fatal['message'], 500);
                }
            });
        }

        function help(){
            // 扫描文档
            function scan_api(&$calls, $folder, $base=''){
                foreach(scandir($folder) as $f){
                    if($f[0]=='.') continue;
                    if(is_dir("$folder/$f")){
                        scan_api($calls, "$folder/$f", "$base/$f");
                    }
                    else{
                        $filename=pathinfo($f, PATHINFO_FILENAME);
                        $call="$base/$filename";
                        $calls[$call]=realpath("$folder/$filename.md");
                    }
                }
            }

            $calls=[];
            scan_api($calls, $this['docs']);

            define('TITLE', $this['title'] ?: "API Documentation");

            $README=@$calls['/README'];
            unset($calls['/README']);

            $view=new \Clue\View("clue/apidoc");
            $view->render(compact('README', 'calls'));
            exit();
        }

        // 实现认证
        function auth(){
        }

        function error($message, $code=500){
            http_response_code($code);
            exit($message);
        }

        function success($result, $code=200){
            http_response_code($code);
            header("Content-Type: text/json");
            exit(json_encode($result));
        }
    }
}
