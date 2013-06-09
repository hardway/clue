<?php
    if(!defined("CLUE_VERSION")){
        $version=exec("hg parent --template {latesttag}.{latesttagdistance} 2>&1", $_, $err);
        if($err==0)
            define('CLUE_VERSION', $version);
        else
            define("CLUE_VERSION", "DEVELOPMENT");
    }

    class Clue_Tool_Constructor_Minifier{
        protected $root;

        function __construct($root){
            $this->root=$root;
        }

        function build($dest){
            if(!Phar::canWrite()) {
                throw new Exception('Unable to create PHAR archive, must be phar.readonly=Off option in php.ini');
            }

            if(file_exists($dest)) unlink($dest);

            $phar = new Phar($dest);
            $phar->convertToExecutable(Phar::PHAR);
            $phar->startBuffering();
            $phar->buildFromDirectory($this->root);
            $phar->setStub('<?php Phar::mapPhar("Clue");
                define("CLUE_VERSION", "'.CLUE_VERSION.'");

                require_once "phar://Clue/stub.php";

                if(php_sapi_name()=="cli" && preg_match("/clue/i", $argv[0])){
                    require_once "phar://Clue/tool/constructor.php";

                    $ctor=new Clue_Tool_Constructor();
                    $command=isset($argv[1]) ? $argv[1] : "help";

                    if(method_exists($ctor, $command)){
                        call_user_func_array(array($ctor, $command), array_slice($argv, 2));
                    }
                    else{
                        echo "Unknown command: $command\n";
                    }
                }
                __HALT_COMPILER();
            ');
            $phar->stopBuffering();
            echo "Phar build at: $dest";
        }

        function __toString(){
            return $this->code;
        }
    }

    class Clue_Tool_Constructor{
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

    help        Display this help screen
            ";
        }

        function build($dest=null){
            if(empty($dest)) $dest=getcwd().'/clue.phar';
            $minifier=new Clue_Tool_Constructor_Minifier(dirname(__DIR__));
            $minifier->build($dest);
            //file_put_contents($dest, $minifier);
        }

        function init($path=null){
            $skeleton=__DIR__ . DIRECTORY_SEPARATOR . 'skeleton';
            $site=empty($path) ? getcwd() : $path;

            if(false==$this->_confirm("New application code skeleton will be copied into: \"$site\", continue?")){
                return $this->_cancel();
            }

            if(!is_dir($site)) mkdir($site, 0755, true);
            $this->_deepcopy($skeleton, $site);
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

        function db($target){
            // Determine app root
            // Detect current database
            // Execute Migration
        }

        static function _confirm($question){
            printf("%s (Y/n) ", $question);
            $response=fgetc(STDIN);

            return $response=='Y';
        }

        static function _cancel(){
            echo "Operation Canceled\n";
        }

        private function _deepcopy($src, $dest){
            echo "Copying $src --> $dest \n";

            if(is_file($src)){	// File Mode
                copy($src, $dest);
                touch($dest);
            }
            else if(is_dir($src)){	// Directory Mode
                // Always make sure the destination folder exists
                if(!is_dir($dest)) mkdir($dest, 755, true);

                $dh=opendir($src);
                while(($file=readdir($dh))!==false){
                    if($file=='.' || $file=='..') continue;
                    $this->_deepcopy($src.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file);
                }
                closedir($dh);
            }
        }
    }
?>
