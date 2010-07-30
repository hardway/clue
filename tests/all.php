<?php  
    define('TEST_LOCATION', dirname(__FILE__));
    
    // Scan for available tests            
    function find_tests($dir=""){
        $tests=array();
        foreach(scandir(TEST_LOCATION . '/' . $dir) as $file){
            if($file=='.' || $file=='..') continue; 
            
            if(preg_match('/.stest.php$/i', $file)){
                $tests[]=empty($dir) ? $file : $dir .'/'. $file;
            }
            else{
                $folder=empty($dir) ? $file : $dir. "/". $file;
                if(is_dir(TEST_LOCATION . "/". $folder)){
                    $tests=array_merge($tests, find_tests($folder));
                }
            }
        }
        return $tests;
    }

    require_once('simpletest/autorun.php');
    
    class AllTests extends TestSuite {
        function AllTests() {
            $this->TestSuite('All tests');
            foreach(find_tests() as $test){
                $this->addFile(TEST_LOCATION . '/' . $test);
            }
        }
    }
?>