<?php
    require_once dirname(__DIR__).'/stub.php';

    use \Clue\CLI as cli;

    // 获取版本信息
    if(!defined("CLUE_VERSION")){
        $version=exec("hg parent -R ".dirname(__DIR__)." --template {latesttag}.{latesttagdistance} 2>&1", $_, $err);
        define('CLUE_VERSION', $err==0 ? $version : "unknown");
    }

    // 指定当前SITE
    if(isset($_SERVER['SITE'])){
        \Clue\add_site_path(APP_ROOT.'/'.$_SERVER['SITE']);
    }


    $cmd=new Clue\CLI\Command("Clue CLI (".CLUE_VERSION.")");

    try{
        printf("\n");
        $cmd->handle($argv);
        printf("\n");
    }
    catch(Exception $e){
        error_log("\n");
        cli::banner($e->getMessage(), 'red');
        error_log($e->getTraceAsString());
    }

    /**
     * Initialize application skeleton
     */
    function clue_init(){
        $skeleton=__DIR__ . DIRECTORY_SEPARATOR . 'skeleton';
        $site=empty($path) ? getcwd() : $path;

        if(false==CLI::confirm("New application code skeleton will be copied into: \"$site\", continue? [Y/n]", true)){
            exit("Operation Cancelled\n");
        }

        if(!is_dir($site)) mkdir($site, 0775, true);
        \Clue\Tool::copy_directory($skeleton, $site, 0775);

        printf("[DONE]");
    }

    /**
     * Build Clue.phar
     *
     * @param $dest Output phar file
     * @param $compress Using gzip compression
     */
    function clue_build($dest=null, $compress=false){
        if(empty($dest)) $dest=getcwd().'/clue.phar';

        $minifier=new Clue\Tool\Constructor(dirname(__DIR__), [
            'compress'=>$compress,
        ]);
        $minifier->build($dest);
        //file_put_contents($dest, $minifier);
    }

    /**
     * 编译Asset资源文件（根据config.php）
     *
     * @param $delete 是否删除目标文件
     * @param $compress 是否压缩（需要apt-get install node-uglify）
     */
    function clue_compile($delete=false, $compress=false){
        $config=_current_config();

        $site_path=defined('SITE') ? APP_ROOT.'/'.SITE : APP_ROOT;
        \Clue\add_site_path($site_path);

        $assets=[];
        foreach($config['asset'] as $output=>$files){
            $output=$site_path.'/asset/'.$output;
            $assets[$output]=$files;
        }

        foreach($assets as $output=>$inputs){
            if($delete){
                printf("Removing: %s\n", $output);
                unlink($output);

                continue;
            }

            printf("Compiling: %s\n", $output);

            $builder=new \Clue\Asset($inputs);
            $content=$builder->compile();

            // TODO: 支持压缩选项
            // TODO: 支持删除编译文件
            // $content=$builder->compress($content, $builder->type);
            if($compress && preg_match('/\.js$/', $output)){
                $tmp_file="/tmp/".md5($output);
                file_put_contents($tmp_file, $content);
                $cmd=sprintf("uglifyjs \"%s\" -o \"$output\"", $tmp_file, $output);
                exec($cmd);
            }
            else{
                file_put_contents($output, $content);
            }
        }
    }

    /**
     * Show Database Diagnose Info
     */
    function clue_db_diagnose(){
        $db=_current_db();

        $current_version=$db->get_version();

        if($current_version===null) throw new \Exception("Can't detect current version through config table (name=DB_VERSION)");

        echo "Database: ".$db->get_var("select database()")."\n";
        echo "Version: $current_version"."\n";

        $stat=$db->get_results("
            SELECT table_name, engine, table_rows, avg_row_length, data_length, index_length
            FROM information_schema.tables WHERE table_schema=%s
            ORDER BY data_length+index_length DESC
        ", $db->config['db']);

        usort($stat, function($a, $b){return $a->table_rows < $b->table_rows;});

        // TODO: use Clue\Text\Table to format and align
        printf("%30s %10s %10s %10s %10s %10s %10s\n", "Table Name", "Engine", "Row Cnt", "Row Len", "Data", "Index", "Total(MB)");
        printf(str_repeat('=', 85)."\n");
        foreach($stat as $r){
            printf("%30s %10s %10s %10s %10s %10s %10s\n",
                $r->table_name, $r->engine, $r->table_rows, $r->avg_row_length,
                number_format($r->data_length/1024/1024, 2), number_format($r->index_length/1024/1024, 2),
                number_format(($r->data_length + $r->index_length)/1024/1024, 2)
            );
        }
    }

    /**
     * Database Upgrade
     * @param $version Single version or all up
     */
    function clue_db_upgrade($version='all'){
        $db=_current_db();

        $current_version=$db->get_version();

        $versions=[];
        foreach(\Clue\site_file_glob("script/upgrade/*.php") as $file){
            if(preg_match("/^(\d+)/", basename($file), $m)){
                $versions[intval($m[1])]=$file;
            }
        }

        if(empty($versions)) return true;   // 没有升级脚本

        $target_version=$version=='all' ? max(array_keys($versions)) : intval($version);

        if($target_version==$current_version) exit("Already version: $target_version\n");

        for($ver=$current_version+1; $ver<=$target_version; $ver++){
            if(!isset($versions[$ver])) panic("Can't find upgrade script: $ver ...");

            $ok=include $versions[$ver];
            if(!$ok) panic("Upgrade failed: $ver");

            $db->set_version($ver);
            echo "Upgraded to $ver\n";
        }
    }

    /**
     * Databae Downgrade
     * @param $version Single version or previous version
     */
    function clue_db_downgrade($version='prev'){
        $db=_current_db();

        $current_version=$db->get_version();
        $target_version=$version=='prev' ? $current_version-1 : intval($version);

        $versions=[];
        foreach(\Clue\site_file_glob("script/downgrade/*.php") as $file){
            if(preg_match("/^(\d+)/", basename($file), $m)){
                $versions[intval($m[1])]=$file;
            }
        }

        if($target_version==$current_version) exit("Already version: $target_version\n");

        for($ver=$current_version-1; $ver>=$target_version; $ver--){
            if(!isset($versions[$ver])) panic("Can't find downgrade script: $ver ...");

            $ok=include $versions[$ver];
            if(!$ok) panic("Downgrade failed: $ver");

            $db->set_version($ver);
            echo "Downgraded to $ver\n";
        }
    }

    /**
     * Generate Database schema sql
     */
    function clue_db_schema(){
        $db=_current_db();

        $schema=["create database if not exists ".$db->config['db'].' default character set '.$db->config['encoding']];
        $schema[]="use ".$db->config['db'];

        foreach($db->get_col("show tables") as $table){
            list($_, $sql)=$db->get_row("show create table $table", ARRAY_N);
            $schema[]=$sql;
        }

        $db_version=$db->get_version();
        $schema[]="insert into config(name, value) values('DB_VERSION', $db_version);";

        echo implode(";\n\n", $schema);
    }

    /**
     * 根据Schema生成SQL
     */
    function clue_gen_sql(){
        $schema=@include "db/schema.php";

        if(!is_array($schema)) panic("Schema file not found or invalid.");

        foreach($schema as $table=>$def){
            $sql[]=\Clue\Database\MySQL::sql_to_create_table($table, $def);
        }

        file_put_contents("db/create.sql", implode("\n\n", $sql));
        echo "SQL Script generated successfully.";
    }

    /**
     * 根据SQL生成Schema
     */
    function clue_gen_schema(){
        // TODO: 从数据库将结构推倒出来，方便gen_model自动生成model
        panic("TODO");
    }

    /**
     * 生成Model文件
     *
     * TODO: 同时生成对应的Controller文件
     */
    function clue_gen_model($name="*"){
        $schema=@include "db/schema.php";
        if(!is_array($schema)) panic("Schema file not found or invalid.");

        // Determine models to generate
        if($name=="*"){
            $models=array_keys($schema);
        }
        else{
            $models=array($name);
        }

        // Remove existed model from list
        if(!is_dir("model/base")) mkdir("model/base");

        if(count($models)==0){
            exit("No models to generate");
        }

        if(count($models)>1 && !Clue\CLI::confirm("Generting models of: ".implode(", ", $models))){
            exit("Canceled");
        }

        // Generate model files
        foreach($models as $m){
            $className=str_replace(" ", "", ucwords(str_replace("_", " ", $m)));
            $fields=array_filter(array_keys(($schema[$m])), function($c){ return $c[0]!="_"; });
            $fields=implode("\n", array_map(function($f){ return "    public \$$f;"; }, $fields));

            $pkeys=array_filter(array_keys(($schema[$m])), function($c) use($schema, $m){ return isset($schema[$m][$c]['pkey']) && $schema[$m][$c]['pkey']===true; });
            $pkeys=count($pkeys)>1 ? "array('".implode("','", $pkeys)."')" : '"'.$pkeys[0].'"';

            // ReCreate base model class
            $path="model/base/".strtolower($className).".php";
            file_put_contents($path, <<<END
<?php
namespace Base;
use \Clue\ActiveRecord;
class $className extends ActiveRecord{
static protected \$_model=array(
    'table'=>'$m',
    'pkey'=>$pkeys,
);
$fields
}
END
            );
            echo "CREATED: $path\n";

            // Create model class if not exists
            $path="model/".strtolower($className).".php";
            if(file_exists($path)){
                echo "SKIPPED: $path\n";
            }
            else{
                file_put_contents($path, <<<END
<?php
class $className extends Base\\$className{
}
END
                );
                echo "CREATED: $path\n";
            }
        }
    }

    /**
     * 生成Controller和Action
     *
     * @param $controller Controller
     */
    function clue_gen_control($controller){
        $args=func_get_args();
        $controller=array_shift($args);
        $actions=count($args) > 0 ? $args : array("index");

        $className=ucwords($controller)."_Controller";
        // Create Controller Class
        $file=APP_ROOT."/source/control/$controller.php";
        if(!file_exists($file)){
            file_put_contents($file, <<<END
<?php
class $className extends Clue\Controller{

}
END
            );
        }

        // 创建对应视图文件
        $view_dir=APP_ROOT."/source/view/$controller";
        if(!is_dir($view_dir)) mkdir($view_dir, 0775, true);

        foreach($actions as $action){
            $src=file_get_contents($file);
            $src=substr($src, 0, strrpos($src, "}"));

            // Append Actions
            $signature="/function\s+$action\(/";
            if(!preg_match($signature, $src)){
                $src.=<<<END
public function $action(){
    global \$app;

    \$data=array();

    \$this->render('$action', \$data);
}
}
END;
                file_put_contents($file, $src);
            }

            // Create Default view files
            $view_file=$view_dir."/$action.html";
            if(!file_exists($view_file)){
                file_put_contents($view_file, <<<END
VIEW PATH: view/page/$controller/$action.html
END
                );
            }
        }
    }

    /**
     * 生成命令行界面
     *
     * @param $script 文件名
     * @param $title 程序名称
     */
    function clue_gen_cli($script, $title="CLI Tool"){
        if(!file_exists($script)){
            file_put_contents($script, <<<END
<?php
    require_once __DIR__.'/stub.php';

    \$cmd=new Clue\CLI\Command("$title", "cli");

    try{
        printf("\\n");
        \$cmd->handle(\$argv);
        printf("\\n");
    }
    catch(Exception \$e){
        error_log("\\n");
        cli::banner(\$e->getMessage(), 'red');
        error_log(\$e->getTraceAsString());
    }
END
            );

            printf("%d bytes written.\n", filesize($script));
        }
    }


    /**
     * RPC测试
     * @param $endpoint 服务端
     * @param $function 调用函数
     * @param $params 参数（如果是文件名，则加载其中的JSON数据）
     * @param $client 用户
     * @param $token 密钥
     */
    function clue_rpc($endpoint, $function, $params="[]", $client=null, $token=null, $secret=null){
        $c=new Clue\RPC\Client($endpoint, array('debug'=>true, 'client'=>$client, 'token'=>$token, 'secret'=>$secret));
        $c->enable_log();

        if(is_string($params) && file_exists($params)){
            $params=file_get_contents($params);
        }

        $ret=call_user_func_array(array($c, $function), json_decode($params, true));

        print_r($ret);
    }

    /**
     * 当前项目的配置内容
     */
    function _current_config(){
        $config=\Clue\site_file("config.".strtolower(APP_ENV)) ?: \Clue\site_file("config.php");

        if(!$config){
            exit(sprintf("Can't find config.php in following path: %s\n", implode(";", \Clue\get_site_path())));
        }

        return include $config;
    }

    /**
     * 当前项目的数据库
     */
    function _current_db(){
        $cfg=_current_config()['database'];

        if($cfg){
            // 首先从config.php中获取数据库配置

            $db=\Clue\Database::create($cfg);
            if(!$db) panic(sprintf(
                "Can't connect to database %s:\"%s\"@%s/%s", $cfg['username'], $cfg['password'], $cfg['host'], $cfg['db']
            ));

            return $db;
        }
        else{
            // 尝试加载stub.php
            include \Clue\site_file("stub.php");
            return $app['db'] ?: $app['mysql'];
        }
    }
