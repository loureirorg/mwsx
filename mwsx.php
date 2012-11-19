<?php
/*-------------------------------------------------------------------------
 *
 * mwsx.php
 *		php server / client for mwsx
 * 
 * Copyleft 2012 - Public Domain
 * Original Author: Daniel Loureiro
 *
 * version 2.0a @ 2012-11-16
 *
 * https://github.com/loureirorg/mwsx
 *-------------------------------------------------------------------------
 */

// configuration
$mwsx_memcache_host = ""; //"myhost.com:port";
$mwsx_memcache_timeout = 60;
 
// error/warning control
global $ws_result;
$ws_result = array("result" => null, "error" => null, "warns" => array(), "signals" => array());

/*
 * SERVER
 */

function cache_load($key)
{
	global $mem;
	
	if ($mem) 
	{
		$result = $mem->get($key);
		if ($result !== false) { 
			return	unserialize($result);
		}
	}
	return false;
}

function cache_save($key, $value)
{
	global $mwsx_memcache_timeout;
	global $mem;
	
	if ($mem) {
		$mem->set($key, serialize($value), 0, $mwsx_memcache_timeout); 
	}
}
 
/*
 * published_functions
 * 		search for "_EXPORT_" in source-code
 */
function published_functions() 
{	
	$path = pathinfo($_SERVER['PHP_SELF']);

	// try cache first
	$cache_key = md5($path['basename']."/published");
	$result = cache_load($cache_key);
	if ($result !== false) {
		return	$result;
	}

	// cache not found, we'll produce new list based on source
	$source = file_get_contents($path['basename']);

	// list of published functions
	preg_match_all('/\/\* _EXPORT_ \*\/[ \t\r\n]*function[ \t\r\n]?(.+)[ \t\r\n]*\((.*)\)/', $source, $matches);
	$str_args = $matches[2];
	$str_fncs = $matches[1];

	// split arguments and format in mwsd
	$args = array_map(create_function('$str_args', 'return	$str_args == "" ? array() : explode(",", preg_replace(\'/[\$ \n]/\', \'\', $str_args));' ), $str_args);
	$fncs = array_map(create_function('$a, $b', "return	array('name' => \$a, 'args' => \$b);"), $str_fncs, $args);

	// save cache
	cache_save($cache_key, $fncs);

	// return list of functions (actually mwsd, but not in json form)
	return $fncs;
}


/*
 * accept data in json or php ("domain.com/?arg_1=xxx&arg_2=yyy")
 */
function get_data()
{
	$post	= @json_decode(file_get_contents('php://input'), TRUE);
	$get	= @json_decode(urldecode($_SERVER['QUERY_STRING']), TRUE);
	return	array_merge(is_array($post)?$post:array(), is_array($get)?$get:array(), $_REQUEST, $_FILES);
}

/*
 * stop script and report error
 */
function error($msg)
{
	// system log
	error_log(date("Y-m-d H:i:s")."; ".$_SERVER['REMOTE_ADDR']."; ".$msg.";");

	// stop script and report error
	global $ws_result;
	$ws_result = array("result" => null, "error" => $msg, "warns" => array(), "signals" => $ws_result['signals']);
	die(json_encode($ws_result));
}


function warn($msg) 
{
	global $ws_result;
	$ws_result['warns'][] = $msg;
}


function signal($signal) 
{
	global $ws_result;
	$ws_result['signals'][] = $signal;
}

// cache object
if ((!empty($mwsx_memcache_host))AND(($_SERVER['QUERY_STRING'] == "mwsd") OR (isset($_REQUEST['mws']))))
{
	$mem = new Memcache;
	$mem->addServer($mwsx_memcache_host);
}
else {
	$mem = null;
}

if ($_SERVER['QUERY_STRING'] == "mwsd") 
{
 	// mwsd request (list of funtions)
	$server_port = ($_SERVER['SERVER_PORT'] == 80) ? "" : $_SERVER['SERVER_PORT'];
	$default_url = "http://".$_SERVER['HTTP_HOST'].$server_port.$_SERVER['PHP_SELF'];

	// try cache
	$cache_key = md5($default_url."/mwsd");
	$result = cache_load($cache_key);
	if ($result !== false) {
		die($result);
	}
	
	// not in cache, we'll generate
	$fncs = published_functions();
	$fncs_with_url = array_map(create_function('$item', '$item["url"] = "'.$default_url.'?mws=".$item["name"]; return	$item;'), $fncs);
	$mwsd = json_encode($fncs_with_url);
	cache_save($cache_key, $mwsd);
    die($mwsd);
}

elseif (isset($_REQUEST['mws']))
{
 	// calling a method
    $fncs = published_functions();
	$fnc = array_filter($fncs, create_function('$item', 'return	$item["name"] == "'.$_REQUEST['mws'].'";'));
	if ($fnc == array()) {
		error("MWSX: function ".$_REQUEST['mws']." not found !");
	}
	$fnc = array_pop($fnc);
	
	// order arguments in the same order of source
   	$ordered_args = array();
	if ($fnc['args'] != array())
	{
		$data = get_data();
    	foreach ($fnc['args'] as $arg) {
        	$ordered_args[] = $data[$arg];
		}
	}
	
	// calling function, show results in mwsx style
	$ws_result['result'] = call_user_func_array($_REQUEST['mws'], $ordered_args);
 	die(json_encode($ws_result));
}


/*
 * CLIENT
 */
function parse_result($result)
{
	$content = (array)json_decode($result, true);
	global $ws_result;
	$ws_result = array("result" => $content['result'], "error" => $content['error'], "warns" => $content['warns'], "signals" => $content['signals']);
	return	$content['result'];
}

 
function ws_call($url, $key_args, $value_args)
{
	$key_args = explode(',', $key_args);
	$data = array();
	foreach ($key_args as $i => $key_arg) {
		$data[$key_arg] = $value_args[$i];
	}
	return	parse_result(http_read($url, json_encode($data)));
}
 
function ws($url) 
{	
	$mwsd = (array)json_decode(http_read($url, ""), true);
	
	$sources = array();
	foreach ($mwsd as $fnc) {
		$sources[] = "function ".$fnc["name"]."() { return	ws_call('".$fnc["url"]."', '".implode(",", $fnc["args"])."', func_get_args()); }";
	}	
	
	$class = uniqid("class");
	eval("class $class { ".implode("\n", $sources)." }");
	$obj = new $class();
	return	$obj;
}

function ws_include($url)
{	
	$mwsd = (array)json_decode(http_read($url, ""), true);
	
	$sources = array();
	foreach ($mwsd as $fnc) {
		$sources[] = "function ".$fnc["name"]."() { return	ws_call('".$fnc["url"]."', '".implode(",", $fnc["args"])."', func_get_args()); }";
	}	
	eval(implode("\n", $sources));
	return	true;
}

function ws_require($url)
{
	return	ws_include($url);
}

function http_read($url, $raw_post_data)
{
	$headers = array();

	// it's a relative url (curl don't support relative url)
	if (stripos($url, "http") !== 0)
	{
		$server_port = ($_SERVER['SERVER_PORT'] == 80) ? "" : $_SERVER['SERVER_PORT'];
		$s = empty($_SERVER["HTTPS"]) ? "" : ($_SERVER["HTTPS"] == "on") ? "s" : "";
		$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"], 0, strpos($_SERVER["SERVER_PROTOCOL"], "/"))).$s;
		$absolute_url = $protocol."://".$_SERVER['HTTP_HOST'].$server_port.$_SERVER['PHP_SELF'];
		$path = pathinfo($_SERVER['PHP_SELF']);
		$url = substr($absolute_url, 0, strrpos($absolute_url, $path["basename"])).$url;
	}
	
	// cookie
	if (session_id() == "") {
		session_start();
	}
	$ws_cookie = (array_key_exists('ws_cookie', $_SESSION)) ? $_SESSION['ws_cookie'] : null;
	if ($ws_cookie != null) {
		$headers[] = 'Cookie: '.$ws_cookie;
	}
	
	// headers
	$headers[] = "Content-Type: text/xml; charset=utf-8";
	$headers[] = "Expect: ";
	
	// server comunication
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);	
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($curl, CURLOPT_HEADER, true); 
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $raw_post_data);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_TIMEOUT, 15);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	$result = curl_exec($curl);
	curl_close($curl);
	
	// head + body split
	$buffer = explode("\r\n\r\n", $result, 2);

	// cookies
	$cookie_pos = strpos($buffer[0], 'Set-Cookie');
	if ($cookie_pos !== false) 
	{
		$value_pos_start = $cookie_pos+strlen('Set-Cookie: ');
		$_SESSION['ws_cookie'] = substr($buffer[0], $value_pos_start, strpos($buffer[0], ';', $cookie_pos)-$value_pos_start);
	}
	
	// only returns the body
	return $buffer[1];
} 
 
function ws_error()
{
	global $ws_result;
	return	$ws_result['error'];
}

function ws_fetch_warn()
{
	global $ws_result;
	return	array_pop($ws_result['warns']);
}

function ws_warns()
{
	global $ws_result;
	return	$ws_result['warns'];
}

function ws_has_signal($signal)
{
	global $ws_result;
	return	in_array($signal, $ws_result['signals']);
}

?>