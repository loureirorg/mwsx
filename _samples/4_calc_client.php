<?php
include_once("../mwsx.php");
ws_include("4_calc_server.php?mwsd"); // don't need stay at the same server

?>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
</head>
<body>
	<h1>Calculator example</h1>
	<p>Simple calculator</p>
	<form action="?">
		A:<input type="text" name="a" value="<?php echo $_REQUEST["a"];?>" />
		<select name="operator" />
			<option value="add">+</option>
			<option value="sub">-</option>
			<option value="mul">*</option>
			<option value="div">/</option>
		</select>
		B:<input type="text" name="b" value="<?php echo $_REQUEST["b"];?>" />
		<input type="submit" value="Calc!" />
	</form>
	<br />
	
	Result:
	<?php
		switch ($_REQUEST["operator"])
		{
			case "add": $c = add($_REQUEST["a"], $_REQUEST["b"]); break;
			case "sub": $c = sub($_REQUEST["a"], $_REQUEST["b"]); break;
			case "mul": $c = mul($_REQUEST["a"], $_REQUEST["b"]); break;
			case "div": $c = div($_REQUEST["a"], $_REQUEST["b"]); break;
		}
		echo $c;
	?>
</form>
</body>
</html>