<?php
/**
 * Sample Markdown

	TITLE
	=======================
	Synopsis1
	Synopsis2

	Synopsis3


	Options:
	------------------------
	URI: /foder/point
	HTTP Method: GET, POST, ...


	Example Output:
	-------------------------
	[
		... code ...
	]

 */
namespace Clue\Text{
	class APIDocParser{
		function __construct($filename){
			$this->options=[];
			$this->parameters=[];
			$this->examples=[];
			$this->synopsis="";

			$this->parse_markdown($filename);
		}

		function parse_markdown($filename){
			$lines=explode("\n", file_get_contents($filename));

			$state='title';
			while(count($lines)){
				$line=array_shift($lines);

				if(strlen(trim($line))==0 && strlen(trim($last))==0){
					$state="detect";
				}

				switch($state){
					case 'title':   // 首先是Title
						if(preg_match('/=+/', $line)){
							$state='synopsis';
						}
						else{
							$this->title=$line;
						}
						break;

					case 'synopsis': // 跟在后面的是Synopsis
						if(empty($line) && empty($last)){
							$state="detect";
						}
						else{
							$this->synopsis.=$line."<br/>";
						}
						break;

					case 'detect':  // 自动识别段落
						if(preg_match('/\-+/', $line)){
							$state=strtolower(trim($last, ' :'));
						}
						break;

					case 'options':
						if(empty($line) && empty($last)){
							$state='detect';
						}
						elseif(empty($line)){
							continue 2;
						}
						else{
							list($option, $desc)=explode(":", $line, 2);
							$this->options[$option]=$desc;
						}
						break;

					case 'parameters':
						if(empty($line) && empty($last)){
							$state='detect';
						}
						elseif(preg_match('/(Parameters|\-+)/', $line) || empty($line)){
							continue 2;
						}
						else{
							list($fields, $desc)=explode(":", $line, 2);
							list($field, $occur, $type)=preg_split('/\s+/', $fields);
							$this->parameters[$field]=compact('field', 'type', 'occur', 'desc');
						}
						break;

					case 'example input':
						@$this->examples['Example Input'].=htmlspecialchars($line)."\n";
						break;

					case 'example output':
						@$this->examples['Example Output'].=htmlspecialchars($line)."\n";
						break;

					default:
						throw new \Exception("Parse error: $filename ($state)");
				}

				$last=$line;
			}
		}

		function render_html(){
			echo "<h2>$this->title</h2>";
			echo "<p>$this->synopsis</p>";
			echo "<table>";

			if($this->options) foreach($this->options as $option=>$desc){
				echo "<tr><th>$option</th><td colspan='4'>$desc</td></tr>";
			}
			if($this->parameters){
				printf("<tr><th rowspan='%d'>Parameters</th><th>Name</th><th>Occurence</th><th>Type</th><th>Description</th></tr>", count($this->parameters)+1);
				foreach($this->parameters as $field=>$param){
					$field=$param['occur']=='required' ? "<b>$field</b>" : $field;
					$occur=$param['occur']=='optional' ? "" : $param['occur'];
					echo "
						<tr>
							<td><code>$field</code></td>
							<td>$occur</td>
							<td><code>{$param['type']}</code></td>
							<td>{$param['desc']}</td>
						</tr>
					";
				}
			}
			echo "</table>";

			foreach($this->examples as $example=>$code){
				echo "<h4>$example</h4>";
				echo "<pre><code>$code</code></pre>";
			}
		}
	}
}
