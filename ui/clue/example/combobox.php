<html>
<head>
	<meta charset="UTF-8">
	<title>Clue Example</title>
	<link rel="stylesheet" href="../clue.css">
	<script src='../../mootools/mootools-core-1.4.5.js'></script>
	<script src='../../mootools/mootools-more-1.4.0.1-yc.js'></script>
	<script src='../clue.js'></script>
</head>
<body>
	<h1>Clue Example</h1>
	<input type='text' name='test' id='simple-test' value='' placeholder='try "al" here'/>
	<input type='text' name='test' id='output-test' value='' placeholder='type in color'/>
	<input type='text' name='test' id='ajax-test' value='' placeholder='type in anything'/>
	<script>
		window.addEvent('domready', function(){
			new ComboBox($("simple-test"), {data: ['shanghai', 'beijing', 'guangzhou', 'alibaba', 'china', 'denmark', 'erdors']});
		});
	</script>
</body>
</html>
