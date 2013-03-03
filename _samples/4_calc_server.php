<?php
include_once __DIR__. "/../mwsx.php";

/* _EXPORT_ */
function add($a, $b)
{
	return	$a + $b;
}

/* _EXPORT_ */
function sub($a, $b)
{
	return	$a - $b;
}

/* _EXPORT_ */
function mul($a, $b)
{
	return	$a * $b;
}

/* _EXPORT_ */
function div($a, $b)
{
	return	floor($a / $b);
}

?>