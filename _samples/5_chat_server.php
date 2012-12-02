<?php
include_once("../mwsx.php");

/* _EXPORT_ */
function login($username)
{
	// only characters/numbers/underscore/space accepted
	if ($username != preg_replace("/[^A-Za-z0-9_ ]/", "", $username)) {
		error("Only characters/numbers/underscore/space are accepted in username.");
	}
	
	// user session
	session_start();
	$_SESSION["username"] = $username;
	
	// register in our "database"
	$db_path = sys_get_temp_dir(). "/chat_server_db.dat";
	$db = file_exists($db_path)? unserialize(file_get_contents($db_path)): array("users" => array(), "messages" => array());
	$db["users"][$username] = true;
	$db["messages"][$username] = array();
	file_put_contents($db_path, serialize($db));
	
	// database creation failed
	if (!file_exists($db_path)) {
		error("We have not permission to write on $db_path.");
	}
	
	// success
	return	true;
}

/* _EXPORT_ */
function user_list()
{
	$db_path = sys_get_temp_dir(). "/chat_server_db.dat";
	$db = file_exists($db_path)? unserialize(file_get_contents($db_path)): array("users" => array(), "messages" => array());
	return	array_keys($db["users"]);
}

/* _EXPORT_ */
function send_to($to, $message)
{
	session_start();
	if (!isset($_SESSION["username"])) {
		error("You need to register first.");
	}
	
	// save in our "database"
	$db_path = sys_get_temp_dir(). "/chat_server_db.dat";
	$db = unserialize(file_get_contents($db_path));
	$db["messages"][$to][$_SESSION["username"]][] = array("time" => date("Y-m-d H:i:s"), "message" => $message);
	file_put_contents($db_path, serialize($db));
	
	// success
	return	true;
}

/* _EXPORT_ */
function receive()
{
	session_start();
	if (!isset($_SESSION["username"])) {
		return	false;
	}
	
	// pop message from our "database"
	$db_path = sys_get_temp_dir(). "/chat_server_db.dat";
	$db = unserialize(file_get_contents($db_path));
	$lst = array();
	foreach ($db["messages"][$_SESSION["username"]] as $from => $pool) 
	{
		while (is_array($pool) AND count($db["messages"][$_SESSION["username"]][$from])) 
		{
			$item = array_pop($db["messages"][$_SESSION["username"]][$from]);
			$lst[] = $from. "@". $item["time"]. ": ". $item["message"]. "<br />";
		}
	}
	file_put_contents($db_path, serialize($db));
	
	// return messages
	return	implode("", $lst);
}
?>