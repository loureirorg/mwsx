<?php
include_once("../mwsx.php");
ws_include("3_php_server.php?mwsd"); // don't need stay at the same server

?>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
</head>
<body>
	<h1>PHP client example</h1>
	<p>Write an country name (or fragment like "br") and press Submit:</p>
	<form action="?">
		<input type="text" name="txt"/>
		<input type="submit" />
	</form>
	<br>
	Result: <p><?php echo implode(",", search($_REQUEST["txt"])); ?></p>
</form>
</body>
</html>