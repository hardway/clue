<html>
<head>
	<meta charset="UTF-8">
	<title>Clue Example</title>
	<script src='../../mootools/mootools-core-1.4.5.js'></script>
	<script src='../../mootools/mootools-more-1.4.0.1-yc.js'></script>
	<script src='../clue.js'></script>
	<style>
		table {border-collapse: collapse;}
		th {background: #EEE; border: 1px solid #000;padding: 5px;}
		td {background: #FFF; }
	</style>
</head>
<body>
	<h1>Scrollable Header</h1>
	<table id='long-table' width='100%' border='1' cellspacing='0' >
		<thead>
			<tr>
				<th rowspan='2'>A</th>
				<th rowspan='2'>B</th>
				<th colspan='2'>C</th>
				<th rowspan='2'>D</th>
			</tr>
			<tr>
				<th>C1</th>
				<th>C2</th>
			</tr>
		</thead>
		<tbody>
		<?php
			for($i=0; $i<100; $i++){
				echo "<tr>";
					for($j=0; $j<5; $j++){
						echo "<td>".rand()."</td>";
					}
				echo "</tr>";
			}
		?>
		</tbody>
	</table>
	<script>
		HTML.fix_table_header($("long-table"));
	</script>
</body>
</html>
