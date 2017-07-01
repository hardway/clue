<?php
namespace Clue\Tool{
    use Clue\CLI as CLI;
    use Clue\Tool as Tool;

    class Constructor{
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
}
