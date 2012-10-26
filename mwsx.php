<?php
/*-------------------------------------------------------------------------
 *
 * mwsx.php
 *		php server / client for mwsx
 * 
 * Copyleft 2012 - Public Domain
 * Original Author: Daniel Loureiro
 *
 * version 2.0a
 *
 * https://github.com/loureirorg/mwsx
 *-------------------------------------------------------------------------
 */

// error/warning control
global $ws_result;
$ws_result = array("result" => null, "error" => null, "warns" => array(), "signals" => array());

/*
 * SERVER
 */

/*
 * published_functions
 * 		search for "_EXPORT_" in source-code
 */
function published_functions() 
{
    /* 
	 * descobre nome do próprio script e pega seu conteúdo 
	 */
    $path = pathinfo($_SERVER['PHP_SELF']);
    $source = file_get_contents($path['basename']);
    
    /*
     * desmembra fonte pegando funções exportadas
     */
    preg_match_all('/\/\* _EXPORT_ \*\/[ \t\r\n]*function[ \t\r\n]?(.+)[ \t\r\n]*\((.*)\)/', $source, $matches);
	$str_args = $matches[2];
	$str_fncs = $matches[1];
    
    /*
     * consome literais
     */
    $args = array_map(create_function('$str_args', 'return	$str_args == "" ? array() : explode(",", preg_replace(\'/[\$ \n]/\', \'\', $str_args));' ), $str_args);
    $fncs = array_map(create_function('$a, $b', "return	array('name' => \$a, 'args' => \$b);"), $str_fncs, $args);

    return $fncs;
}


/*
 * get_data
 * 
 */
function get_data()
{
	$post	= @json_decode(file_get_contents('php://input'), TRUE);
	$get	= @json_decode(urldecode($_SERVER['QUERY_STRING']), TRUE);
    return	array_merge(is_array($post)?$post:array(), is_array($get)?$get:array(), $_REQUEST);
}

/*
 * stop script and reports error
 */
function error($msg)
{
	// system log
	error_log(date("Y-m-d H:i:s")."; ".$_SERVER['REMOTE_ADDR']."; ".$msg.";");
	
	// stop script and reports error
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


if ($_SERVER['QUERY_STRING'] == "mwsd") 
{
 	/* 
 	 * mostra listagem de funções
 	 */
	$fncs = published_functions();
	$server_port = ($_SERVER['SERVER_PORT'] == 80) ? "" : $_SERVER['SERVER_PORT'];
	$default_url = "http://".$_SERVER['HTTP_HOST'].$server_port.$_SERVER['PHP_SELF'];
	$fncs_with_url = array_map(create_function('$item', '$item["url"] = "'.$default_url.'?mws=".$item["name"]; return	$item;'), $fncs);
    die(json_encode($fncs_with_url));
}

elseif (isset($_REQUEST['mws']))
{
 	/* 
 	 * chama método
 	 */
    $fncs = published_functions();
	$fnc = array_filter($fncs, create_function('$item', 'return	$item["name"] == "'.$_REQUEST['mws'].'";'));
	if ($fnc == array()) {
		error("MWSX: function ".$_REQUEST['mws']." not found !");
	}
	$fnc = array_pop($fnc);
	
	/*
	 * ordenamos os parâmetros na ordem em que aparecem no fonte
	 */
   	$ordered_args = array();
	if ($fnc['args'] != array())
	{
		$data = get_data();
    	foreach ($fnc['args'] as $arg) {
        	$ordered_args[] = $data[$arg];
		}
	}
	
	/* 
	 * chama a função, mostra resultados no padrão MWS
	 */
	$ws_result['result'] = call_user_func_array($_REQUEST['mws'], $ordered_args);
 	die(json_encode($ws_result));
}


/*
 * CLIENT
 */
function parse_result($result)
{
	$content = (array)json_decode($result);
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
	$mwsd = json_decode(http_read($url, ""), TRUE);
	
	$sources = array();
	foreach ($mwsd as $fnc) {
		$sources[] = "function ".$fnc["name"]."() { return	ws_call('".$fnc["url"]."', '".implode(",", $fnc["args"])."', func_get_args()); }";
	}	
	
	$class = uniqid("class");
	eval("class $class { ".implode("\n", $sources)." }");
	$obj = new $class();
	return	$obj;
}

function http_read( $URL, $RAW_POST_DATA )
{
	$headers = array("Content-Type: text/xml; charset=utf-8", "Expect: ");

	// cookie
	if (session_id() == "") {
		session_start();
	}
	$ws_cookie = (array_key_exists('ws_cookie', $_SESSION)) ? $_SESSION['ws_cookie'] : null;
	if ($ws_cookie != null) {
		$headers[] = 'Cookie: '. $ws_cookie;
	}
	
	// server comunication
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);	
	curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($curl, CURLOPT_HEADER, true); 
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $RAW_POST_DATA);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_TIMEOUT, 15);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	$result = curl_exec($curl);
	curl_close($curl);
	
	// head + body split
	$buffer = explode("\r\n\r\n", $result);

	// cookies
	$cookie_pos = strpos($buffer[0], 'Set-Cookie');
	if ($cookie_pos !== false) {
		$_SESSION['ws_cookie'] = substr($buffer[0], $cookie_pos+strlen('Set-Cookie: '), strpos($buffer[0], ';'));
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