	<html>
		<head>
			<meta charset='utf-8'>
			<meta name="viewport" content="width=device-width, user-scalable=no,initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0">
			<title><?=TITLE?></title>

			<script src='https://ajax.googleapis.com/ajax/libs/mootools/1.6.0/mootools.min.js'></script>
			<style>
				body {font-size:12px; font-family:"Graphik Web","Arial","Helvetica",sans-serif; line-height: 16px; margin:10px auto; width:960px;}

				h1 {font-size:24px; padding: 18px 0 18px 0; }
				h2 {font-size:16px; margin-top:2em;}

				ul {padding: 0 10px 0 0; margin: 0;}
				li {list-style: none; line-height: 18px; display: block;}
				li:hover {font-weight:bold;}
				li a {display:block;}
				a {color:#0192b5; text-decoration:none;}

				table {
					width:100%; font-size:12px; border-collapse:collapse; border-spacing:0;
					border-top:1px solid #DEDEDB; margin:10px 0;
				}
				th, td{
					border-bottom: 1px solid #DEDEDB;
				}
				th {padding:5px 8px; vertical-align:top; background:#f5f5f1; font-weight:bold; text-align:left;}
				tr > th:first-child {width:150px;}
				td {padding:5px 8px; vertical-align:top; background:#FBFBF9;}

				hr {height:0; border:0; border-bottom:1px solid #CCC;}

				pre {border-left:2px solid #DDD; padding-left:2em;}

				#reference .revision {float:right;}

				.hide {display:none;}
			</style>
			<script>
				function toggle_detail(el){
					el.getElement('.detail').toggleClass('hide');
				}
			</script>
		</head>
		<body>
			<h1><?=TITLE?></h1>
			<div id='reference'><ul>
				<span class='revision' style='border-bottom:1px solid #DDD;'>Revision</span>
				<li><a href='#intro'>Introduction</a></li>
				<?php
					foreach($calls as $name=>$doc){
						$rev=filemtime($doc);
						echo "<li>";
						echo "<span class='revision' title='".date("Y-m-d H:i:s", $rev)."'>".date("Y.m.d", $rev)."</span>";

						if($doc){
							echo "<a href='#$name'>$name</a>";
						}
						else{
							echo "$name";
						}
						echo "</li>";
					}
				?>
			</ul></div>

			<div id='revision'></div>

			<a name='intro'></a>
			<?php
				$d=new Clue\Text\APIDocParser($README);
				$d->render_html();

				// 针对每个api输出文档
				foreach($calls as $name=>$doc){
					echo "<a name='$name'></a>";
					$d=new Clue\Text\APIDocParser($doc);
					$d->render_html();
				}
			?>
	</body>
</html>

