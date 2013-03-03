<?php
include_once __DIR__. "/../mwsx.php";

/* _EXPORT_ */
function search($key)
{
	$key = preg_replace("/[^A-Za-z ]/", "", $key); // strip non-characters
	$database = explode(",", file_get_contents("countries_db.txt"));
	$lst_with_keys = array_filter($database, create_function('$item',"return (stripos(\$item, '$key')!==false);"));
	return	explode(",", implode(",", $lst_with_keys)); //return without index keys
}

?>