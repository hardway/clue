<?php  
	require_once 'clue/text/bbcode.php';
		
	class Test_Text_BBCode extends Snap_UnitTestCase{
		private $bbcode;
		
		function setUp(){
			$this->bbcode=new Clue_Text_BBCode();
		}
		
		function tearDown(){
			$this->bbcode=null;
		}
		
		function test_text_formatting(){
			$input="[b]test[/b]";
			$output="<strong>test</strong>";
			$this->assertEqual($this->bbcode->to_html($input), $output);

			$input="[u]test[/u]";
			$output="<u>test</u>";
			$this->assertEqual($this->bbcode->to_html($input), $output);
			
			$input="[i]test[/i]";
			$output="<i>test</i>";
			$this->assertEqual($this->bbcode->to_html($input), $output);

			$input="[color=red]test[/color]";
			$output="<font color='red'>test</font>";
			$this->assertEqual($this->bbcode->to_html($input), $output);

			$input="[size=10]test[/size]";
			$output="<font size='10'>test</font>";
			$this->assertEqual($this->bbcode->to_html($input), $output);

			return $this->assertTrue(true);
		}
		
		function test_text_formatting_combination(){
			$input="[size=200][color=red][b]LOOK AT ME![/b][/color][/size]";
			$output="<font size='200'><font color='red'><strong>LOOK AT ME!</strong></font></font>";
			$this->assertEqual($this->bbcode->to_html($input), $output);

			// This is acceptable, should I raise an exception for this?
			$input="[b][u]This is wrong[/b][/u]";
			$output="<strong><u>This is wrong</strong></u>";
			$this->assertEqual($this->bbcode->to_html($input), $output);

			return $this->assertTrue(true);
		}
		
		function test_url_link(){
			$input="[url]test_link[/url]";
			$output="<a href='test_link'>test_link</a>";
			
			$this->assertEqual($this->bbcode->to_html($input), $output);
			
			$input="[url=http://www.google.com/q=abc]test[/url]";
			$output="<a href='http://www.google.com/q=abc'>test</a>";
			
			$this->assertEqual($this->bbcode->to_html($input), $output);

			$input="[email]admin@domain.com[/email]";
			$output="<a href='mailto:admin@domain.com'>admin@domain.com</a>";
			
			$this->assertEqual($this->bbcode->to_html($input), $output);
			return $this->assertTrue(true);
		}
		
		function test_nothing(){
			return $this->assertTrue(true);
		}
	}
?>
