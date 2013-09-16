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
                throw new Exception('Unable to create PHAR archive, must be phar.readonly=Off option in php.ini');
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

                if(php_sapi_name()=="cli" && preg_match("/clue/i", $argv[0])){
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
        // TODO: 使用单独的方法族，例如 help_compress()...
        function help(){
            echo "
Version: ".CLUE_VERSION."
Usage: clue [command] {arguments...}
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

        /**
         * 推断APP ROOT所在目录
         * 假设config.php在且仅在APP ROOT目录下
         * @return 返回推测的APP ROOT路径（完整）
         */
        function root(){
            $root=getcwd();
            while(is_dir($root) && !is_file("$root/config.php")){
                $root=dirname($root);
            }

            if($this->command=='root'){
                exit($root."\n");
            }

            return realpath($root);
        }

        function db($target_version=null){
            // Determine app root
            $app_root=$this->root();

            if(!file_exists("$app_root/config.php")) throw new \Exception("config not found");
            include "$app_root/config.php" ;

            // Detect current database
            $db=\Clue\Database::create(array('type'=>"mysql", 'host'=>DB_HOST, 'db'=>DB_NAME, 'username'=>DB_USER, 'password'=>DB_PASS));
            if(!$db) throw new \Exception(sprintf("Can't connect to database %s:\"%s\"@%s/%s", DB_USER, DB_PASS, DB_HOST, DB_NAME));

            $current_version=$db->get_var("select value from config where name='DB_VERSION'");
            if($current_version===null) throw new \Exception("Can't detect current version through config table (name=DB_VERSION)");

            if(empty($target_version)){
                echo("Current database scheme version: $current_version\n");
                exit();
            }

            // Execute Migration
            $scripts=array();

            if($target_version==$current_version){
                throw new \Exception("Same version, no need to migrate");
            }
            elseif($target_version > $current_version){
                $range=range($current_version+1, $target_version, 1);
                $action='upgrade';
            }
            else{
                $range=range($current_version, $target_version+1, -1);
                $action="downgrade";
            }

            foreach($range as $v){
                $t=$action=='upgrade' ? $v : $v - 1;

                $s="$app_root/script/sql/$v.$action.php";
                if(!file_exists($s)) throw new \Exception("Missing $action script ($s)");

                echo '['.strtoupper($action)."] to version $t\n";

                include $s;
                // 更新数据库版本
                $db->exec("update config set value=%d where name='DB_VERSION'", $t);
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
