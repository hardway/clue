<?php
namespace {
    if(!defined("CLUE_VERSION")){
        $version=exec("hg parent -R ".dirname(__DIR__)." --template {latesttag}.{latesttagdistance} 2>&1", $_, $err);
        define('CLUE_VERSION', $err==0 ? $version : "unknown");
    }
}

namespace Clue\Tool{
    use Clue\CLI as CLI;
    use Clue\Tool as Tool;

    class Constructor_Minifier{
        protected $root;

        protected $build_exclude=array(
            '/ui\/(clue|mooeditor|mootools)\//',
            '/\.hg\//'
        );
        protected $strip_exclude=array(
            "/tool\/skeleton\/.*/"
        );

        function __construct($root){
            $this->root=$root;
        }

        function build($dest){
            if(!\Phar::canWrite()) {
                throw new \Exception('Unable to create PHAR archive, must be phar.readonly=Off option in php.ini');
            }

            if(file_exists($dest)) unlink($dest);

            $phar = new \Phar($dest);
            $phar->convertToExecutable(\Phar::PHAR);
            $phar->startBuffering();

            # Simple Build whole directory
            # $phar->buildFromDirectory($this->root, '/\.php$/');

            $iter = new \RecursiveIteratorIterator (new \RecursiveDirectoryIterator ($this->root), \RecursiveIteratorIterator::SELF_FIRST);

            foreach ($iter as $file) {
                if(!is_file($file)) continue;

                $exclude=false;
                foreach($this->build_exclude as $pat){ if(preg_match($pat, $file)) $exclude=true;}
                if($exclude) continue;

                // PHP file should be stripped
                $include=preg_match ('/\\.php$/i', $file);
                $exclude=false;
                // Files matching "strip_exclude" list shall keep as is
                foreach($this->strip_exclude as $pat){
                    if(preg_match($pat, $file)) $exclude=true;
                }

                if ($include && !$exclude) {
                    $phar->addFromString(substr($file, strlen ($this->root) + 1), php_strip_whitespace($file));
                }
                else{
                    $phar->addFromString(substr($file, strlen ($this->root) + 1), file_get_contents($file));
                }
            }

            # Add stub to bootstrap
            $phar->setStub('<?php
                define("CLUE_VERSION", "'.CLUE_VERSION.'");

                Phar::interceptFileFuncs();
                require_once "phar://".__FILE__."/stub.php";

                if(php_sapi_name()=="cli" && preg_match("/clue/i", @$argv[0])){
                    require_once "phar://".__FILE__."/tool/clue.php";
                }
                __HALT_COMPILER();
            ');

            $phar->stopBuffering();
            echo "Phar build at: $dest";
            echo "\n";
        }

        function __toString(){
            return $this->code;
        }
    }

    class Constructor{
        function __construct(){
            if(isset($_SERVER['SITE'])){
                \Clue\add_site_path(APP_ROOT.'/'.$_SERVER['SITE']);
            }
        }

        // TODO: 使用单独的方法族，例如 help_compress()...
        function version(){
            echo "CLUE Version: ".CLUE_VERSION."\n";
        }

        function help(){
            $this->version();
            echo "--------------------------------------------\n";
            echo $this->opt->get_usage("Options Available:");
            echo "
Commands Available:
    init        Initialize application skeleton

    gen_sql     Generate SQL Script based on db/schema.php
    gen_model   Generate Model file according to schema
                eg: clue gen_model user
    gen_control Generate Controller along with Views
                eg: clue gen_control project view

    compress    build and compress asset files (js, css)
                last parameter will be the output

    db          Migrate to specified target version
                Show current version if no version is provided

    help        Display this help screen

";
        }

        function compress(){
            $files=func_get_args();
            $output=array_pop($files);
            $input=empty($files) ? array($output) : $files;

            $builder=new \Clue\Asset(func_get_args());

            $origin_content=$builder->compile();
            $compress_content=$builder->compress($origin_content, $builder->type);

            file_put_contents($output, strlen($origin_content) > strlen($compress_content) ? $compress_content : $origin_content);
        }

        function build($dest=null){
            if(empty($dest)) $dest=getcwd().'/clue.phar';
            $minifier=new Constructor_Minifier(dirname(__DIR__));
            $minifier->build($dest);
            //file_put_contents($dest, $minifier);
        }

        function init($path=null){
            $skeleton=__DIR__ . DIRECTORY_SEPARATOR . 'skeleton';
            $site=empty($path) ? getcwd() : $path;

            if(false==CLI::confirm("New application code skeleton will be copied into: \"$site\", continue? [Y/n]", true)){
                return $this->_cancel();
            }

            if(!is_dir($site)) mkdir($site, 0775, true);
            Tool::copy_directory($skeleton, $site, 0775);

            printf("\n[DONE]\n");
        }

        function gen_sql(){
            $schema=include "db/schema.php";
            if(!is_array($schema)) die("Schema file not found or invalid.");

            foreach($schema as $table=>$def){
                $sql[]=Clue\Database\MySQL::sql_to_create_table($table, $def);
            }

            file_put_contents("db/create.sql", implode("\n\n", $sql));
            echo "SQL Script generated successfully.";
        }

        function gen_schema(){
            // TODO: 从数据库将结构推倒出来，方便gen_model自动生成model
        }

        function gen_model($name="*"){
            $schema=include "db/schema.php";
            if(!is_array($schema)) die("Schema file not found or invalid.");

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

            if(count($models)>1 && !$this->_confirm("Generting models of: ".implode(", ", $models))){
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

        function gen_control(/* controller, actions ... */){
            $args=func_get_args();
            $controller=array_shift($args);
            $actions=count($args) > 0 ? $args : array("index");

            $className=ucwords($controller)."_Controller";
            // Create Controller Class
            if(!file_exists("control/$controller.php")){
                file_put_contents("control/$controller.php", <<<END
<?php
class $className extends Clue\Controller{

}
END
                );
            }


            foreach($actions as $action){
                $src=file_get_contents("control/$controller.php");
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
                    file_put_contents("control/$controller.php", $src);
                }

                // Create Default view files
                if(!is_dir("view/page/$controller")) mkdir("view/page/$controller");
                if(!file_exists("view/page/$controller/$action.html")){
                    file_put_contents("view/page/$controller/$action.html", <<<END
VIEW PATH: view/page/$controller/$action.html
END
                    );
                }
            }
        }

        function _get_db(){
            $config=\Clue\site_file("config.".strtolower(APP_ENV)) ?: \Clue\site_file("config.php");

            if(!$config){
                exit(sprintf("Can't find config.php in following path: %s\n", implode(";", \Clue\get_site_path())));
            }

            $this->appcfg=include $config;
            $cfg=$this->appcfg['database'];

            // Detect current database
            $db=\Clue\Database::create($cfg);
            if(!$db) throw new \Exception(sprintf("Can't connect to database %s:\"%s\"@%s/%s", $cfg['username'], $cfg['password'], $cfg['host'], $cfg['db']));

            return $db;
        }

        function db(){
            $db=$this->_get_db();

            if(!$db->has_table("config")){
                $db->exec("create table config(name varchar(128) not null primary key, value varchar(4096))");
                $db->exec("insert into config(name, value) values('DB_VERSION', 0)");
            }

            $current_version=$db->get_var("select value from config where name='DB_VERSION'");
            if($current_version===null) throw new \Exception("Can't detect current version through config table (name=DB_VERSION)");

            echo "Database: ".$db->get_var("select database()")."\n";
            echo "Data Version: $current_version"."\n";

            exit("\nMore Usage: clue db [upgrade | downgrade | diagnose]\n");
        }

        function db_up(){return $this->db_upgrade(); }
        function db_upgrade(){
            $db=$this->_get_db();
            $current_version=$db->get_var("select value from config where name='DB_VERSION'");

            $target_version=intval($current_version)+1;
            foreach(\Clue\site_file_glob("script/upgrade/*.php") as $file){
                if(preg_match("/^(\d+)/", basename($file), $m)){
                    if(intval($m[1])==$target_version){
                        $ok=include $file;
                        if($ok===true){
                            $db->exec("update config set value=%d where name='DB_VERSION'", $target_version);
                            echo "Upgraded to $target_version\n";
                        }
                        return true;
                    }
                }
            }
            exit("Can't find upgrade script $target_version ...\n");
        }

        function db_down(){ return $this->db_downgrade(); }
        function db_downgrade(){
            $db=$this->_get_db();
            $current_version=$db->get_var("select value from config where name='DB_VERSION'");

            $target_version=intval($current_version)-1;
            foreach(\Clue\site_file_glob("script/downgrade/*.php") as $file){
                if(preg_match("/^(\d+)/", basename($file), $m)){
                    if(intval($m[1])==$target_version){
                        $ok=include $file;
                        if($ok===true){
                            $db->exec("update config set value=%d where name='DB_VERSION'", $target_version);
                            echo "Upgraded to $target_version\n";
                        }
                        return true;
                    }
                }
            }
            exit("Can't find downgrade script $target_version ...\n");
        }

        function db_diag(){ return $this->db_diagnose(); }
        function db_diagnose(){
            $db=$this->_get_db();
            $stat=$db->get_results("
                SELECT table_name, engine, table_rows, avg_row_length, data_length, index_length
                FROM information_schema.tables WHERE table_schema=%s
                ORDER BY data_length+index_length DESC
            ", $this->appcfg['database']['db']);

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

        static function _confirm($question){
            printf("%s (Y/n) ", $question);
            $response=fgetc(STDIN);

            return $response=='Y';
        }

        static function _cancel(){
            echo "Operation Canceled\n";
        }
    }
}
?>
