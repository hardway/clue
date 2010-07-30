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

    if(isset($_GET['all'])){
        require_once('simpletest/autorun.php');
        
        class AllTests extends TestSuite {
            function AllTests() {
                $this->TestSuite('All tests');
                foreach(find_tests() as $test){
                    $this->addFile(TEST_LOCATION . '/' . $test);
                }
            }
        }
    }
    else{
?>
        <div style='float: left;'>
            <h1>Testing Arena</h1>
                <a href='?all' target='console'>Run all the tests</a>
            <?php  
                foreach(find_tests() as $test){
                    $name=substr($test, 0, 0-strlen(".stest.php"));
                    echo <<<END
                    <li><a href='$test' target='console'>$name</a></li>
END;
                }
            ?>
        </div>
        <iframe name='console' style='float: right; width: 80%; height: 90%; border-left: 1px solid #CCC;' frameborder="none" src='about:blank'></iframe>
<?php 
    } 
?>