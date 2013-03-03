<?php
include_once __DIR__. "/../mwsx.php";

/* _EXPORT_ */
function upload($file)
{
	// attr of $file are the same of $_FILES[0] var
	return	"File received. Internal name: ".$file["tmp_name"];
}

?>