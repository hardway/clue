<?php  
    require_once 'simpletest/autorun.php';
	require_once 'clue/text/diff.php';
		
	class Test_Text_Diff extends UnitTestCase{
	    private $text1=<<<END
<html>
<head><title>Text</title></head>
<body>
code a
code b
code c
code d
code e
code g
</body>
</html>
END;
    
        private $text2=<<<END
<html>
<head><title>Text</title></head>
<body>
code a
code c
code dde
code e
code e
code g
code e
code e
code e
</body>
</html>
END;

		function setUp(){
		}
		
		function tearDown(){
		}
		
		function test_basic_diff_and_patch(){
		    $patch1_2=Clue_Text_Diff::diff($this->text1, $this->text2);
		    $newText2=Clue_Text_Diff::patch($this->text1, $patch1_2);
		    $this->assertEqual($this->text2, $newText2);

		    $patch2_1=Clue_Text_Diff::diff($this->text2, $this->text1);
		    $newText1=Clue_Text_Diff::patch($this->text2, $patch2_1);
		    $this->assertEqual($this->text1, $newText1);
		    
			return $this->assertTrue(true);
		}
	}
?>
