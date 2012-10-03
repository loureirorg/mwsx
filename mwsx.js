/*
 * cria um vetor sem métodos (necessário para a biblioteca "prototype")
 */
function array_clear ( )
{
	this.prototype = new Array ();
	
	var CMD;
	var PROTOTYPE = Array.prototype;
	for ( var i in PROTOTYPE )
	{
		CMD = "delete this." + i + ";";
		eval ( CMD );
	}
	
	delete this.prototype;
}


/*
 * interpreta o retorno em MWS
 */
function mws_array ( RETORNO_MWS )
{
	/* cabeçalho inválido */
	if ( RETORNO_MWS.substr ( 0, 11 ) != "8;698DC19D;" )
	{
	    __WS__ERROS = RETORNO_MWS;
		return ( '' );
	}
	
	/* pega o conteúdo */
	var LITERAL_TAMANHO_CONTEUDO = RETORNO_MWS.substr ( 11, RETORNO_MWS.indexOf ( ';', 11 ) - 11 );
	var TAMANHO_CONTEUDO = parseInt ( LITERAL_TAMANHO_CONTEUDO, 16 );
	
	var POSICAO_INICIO_CONTEUDO = RETORNO_MWS.indexOf ( ';', 11 ) + 1;
	var CONTEUDO = JSON.parse ( RETORNO_MWS.substr ( POSICAO_INICIO_CONTEUDO, TAMANHO_CONTEUDO ) );
	
	/* pega o erro */
	var POSICAO_TAMANHO_ERROS = POSICAO_INICIO_CONTEUDO + TAMANHO_CONTEUDO + 1;
	var LITERAL_TAMANHO_ERROS = RETORNO_MWS.substr ( POSICAO_TAMANHO_ERROS, RETORNO_MWS.indexOf ( ';', POSICAO_TAMANHO_ERROS ) - POSICAO_TAMANHO_ERROS );
	var TAMANHO_ERROS = parseInt ( LITERAL_TAMANHO_ERROS, 16 );
	
	var POSICAO_INICIO_ERROS = RETORNO_MWS.indexOf ( ';', POSICAO_TAMANHO_ERROS ) + 1;
	var ERROS = RETORNO_MWS.substr ( POSICAO_INICIO_ERROS, TAMANHO_ERROS );
	__WS__ERROS = new Array ();
	__WS__ERROS [ 'ERROR' ] = JSON.parse ( ERROS );

	/* pega os avisos */
	__WS__INDICE_WARN = 0;

	/* exceção em caso de erro ? */
	if ( ( __WS__EXCEPTION_ON_ERROR ) && ( ws_error () ) )
		throw { 'name': ( ( CONTEUDO == '' ) ? 'WS_ERROR' : CONTEUDO ), 'message': ws_error () };

	return ( CONTEUDO );
}

__WS__EXCEPTION_ON_ERROR = false;
function exception_on_error ( EXCEPTION )
{
	__WS__EXCEPTION_ON_ERROR = EXCEPTION;
}

LST_SEMAFORO = new array_clear ();
function __ws__semaforo ( NOME_SEMAFORO, METODO )
{
	if ( ( typeof ( LST_SEMAFORO [ NOME_SEMAFORO ] ) == "undefined" ) ||
		 ( typeof ( LST_SEMAFORO [ NOME_SEMAFORO ] [ METODO ] ) == "undefined" ) ||
		 ( typeof ( LST_SEMAFORO [ NOME_SEMAFORO ] [ METODO ] [ "STAT" ] ) == "undefined" ) ||
		 ( LST_SEMAFORO [ NOME_SEMAFORO ] [ METODO ] [ "STAT" ] == 0 ) )
	{
		LST_SEMAFORO [ NOME_SEMAFORO ] [ METODO ] [ "STAT" ] = 1;
		return ( true );
	}
	else if ( LST_SEMAFORO [ NOME_SEMAFORO ] [ METODO ] [ "STAT" ] == 1 )
		LST_SEMAFORO [ NOME_SEMAFORO ] [ METODO ] [ "STAT" ] = 2;
	
	return ( false );
}

function le_http ( URL, VETOR_POST, OPCIONAL_FUNCAO_ASYNC, METODO, ID )
{
	var XML_HTTP = null;
	
	try 
	{
		XML_HTTP = new XMLHttpRequest ();
	}
	catch ( e ) 
	{
	    try 
		{
			XML_HTTP = new ActiveXObject ( "Msxml2.XMLHTTP" ); 
		}
	    catch ( e ) 
		{
			XML_HTTP = new ActiveXObject ( "Microsoft.XMLHTTP" );
		}
	}

	var FONTE = 
	"XML_HTTP.onreadystatechange = function ( ) " +
	"{ " +
	"	var executar = function ( ID, METODO, OPCIONAL_FUNCAO_ASYNC ) \n" +
	"	{ \n";
	
	if ( typeof ( OPCIONAL_FUNCAO_ASYNC ) == 'function' ) 
		FONTE += "	OPCIONAL_FUNCAO_ASYNC ( mws_array ( XML_HTTP.responseText ) ); \n ";
	
	if ( ID != null )	
		FONTE += 
		"		if ( LST_SEMAFORO [ '" + ID + "' ] [ '" + METODO + "' ] [ 'STAT' ] == 2 ) \n" +
		"		{ \n " +
		"			LST_SEMAFORO [ '" + ID + "' ] [ '" + METODO + "' ] [ 'STAT' ] = 1; \n" +
		"			le_http ( LST_SEMAFORO [ '" + ID + "' ] [ '" + METODO + "' ] [ 'URL' ], LST_SEMAFORO [ '" + ID + "' ] [ '" + METODO + "' ] [ 'PARAM' ], OPCIONAL_FUNCAO_ASYNC, '" + METODO + "', '" + ID + "' ); \n" +
		"		} \n" +
		"		else \n " +
		"			LST_SEMAFORO [ '" + ID + "' ] [ '" + METODO + "' ] [ 'STAT' ] = 0; \n";
	
	FONTE += 
	"	} \n" +	
	"	if ( ( XML_HTTP.readyState == 4 ) && ( XML_HTTP.status == 200 ) ) \n";
	
	if ( typeof ( OPCIONAL_FUNCAO_ASYNC ) == 'function' )
		FONTE += "		executar ( '" + ID + "', '" + METODO + "', " + OPCIONAL_FUNCAO_ASYNC + " ); \n";
	else
		FONTE += "		executar ( '" + ID + "', '" + METODO + "', null ); \n";
	
	FONTE += "}; \n ";
	
	eval ( FONTE );
	
	var ASYNC = true;
	if ( typeof ( OPCIONAL_FUNCAO_ASYNC ) == "undefined" )
		ASYNC = false;

	var DADOS = JSON.stringify ( VETOR_POST );

	XML_HTTP.open ( "POST", URL , ASYNC );
	XML_HTTP.setRequestHeader ( "Content-type", "application/x-www-form-urlencoded" );
	XML_HTTP.setRequestHeader ( "Content-length", DADOS.length );
	XML_HTTP.setRequestHeader ( "Connection", "close" );
	
	XML_HTTP.send ( DADOS );
	
	if ( ! ASYNC )
		return ( XML_HTTP.responseText );
	else
		return ( XML_HTTP );
} 


function ws ( URL )
{	
	var MWSD = le_http ( URL, null );

	var LST_FUNCAO_MWSD = JSON.parse ( MWSD );

	var LST_FUNCAO = new array_clear ();
	var TAMANHO_FUNCAO = 0;
	for ( var INDICE in LST_FUNCAO_MWSD [ 'LIST_FUNCTIONS' ] )
	{
		var FUNCAO_MWSD = LST_FUNCAO_MWSD [ 'LIST_FUNCTIONS' ] [ INDICE ];
		var FUNCAO = new array_clear ();
		
		FUNCAO [ 'NOME' ] = FUNCAO_MWSD [ 'NAME' ];
		FUNCAO [ 'URL' ] = FUNCAO_MWSD [ 'URL' ];
		FUNCAO [ 'PARAM' ] = new array_clear ();
		var TAMANHO_PARAM = 0;
		for ( var INDICE_PARAM in FUNCAO_MWSD [ 'LIST_PARAMETERS' ] )
			FUNCAO [ 'PARAM' ] [ TAMANHO_PARAM ++ ] = FUNCAO_MWSD [ 'LIST_PARAMETERS' ] [ INDICE_PARAM ];
		
		LST_FUNCAO [ TAMANHO_FUNCAO ++ ] = FUNCAO;
	}

	var PREFIXO = '__ws__' + parseInt ( Math.random () * 999999 ) + '_';
	for ( var INDICE in LST_FUNCAO )
	{
		var FUNCAO = LST_FUNCAO [ INDICE ];
		var STR_PARAM = "";
		var STR_PARAM_VETOR = "";
		var STR_PARAM_ENVIADOS = "";
		var STR_TESTE_VARS = "";
		
		for ( var INDICE_PARAMETRO in FUNCAO [ 'PARAM' ] )
		{
			var PARAMETRO = FUNCAO [ 'PARAM' ] [ INDICE_PARAMETRO ];
			STR_PARAM += PARAMETRO + ',';
			STR_PARAM_VETOR += '\'' + PARAMETRO + '\',';
			STR_PARAM_ENVIADOS += '\'' + PARAMETRO + '\':' + PARAMETRO + ',';
			STR_TESTE_VARS += 'if ( typeof ' + PARAMETRO + ' != "function" ) PARAM_VETOR [ LST_PARAMETROS [ INDICE++ ] ] = ' + PARAMETRO + '; else CALLBACK = ' + PARAMETRO +'; ';
		}
		STR_TESTE_VARS += 'if ( typeof __ws__callback != "function" ) PARAM_VETOR [ LST_PARAMETROS [ INDICE++ ] ] = __ws__callback; else CALLBACK = __ws__callback; ';
		STR_PARAM += '__ws__callback';
		STR_PARAM_VETOR += '\'__ws__callback\'';
		STR_PARAM_ENVIADOS = STR_PARAM_ENVIADOS.substr ( 0, STR_PARAM_ENVIADOS.length - 1 );
	
		var FONTE_FUNCAO = 
			'function ' + PREFIXO + FUNCAO [ 'NOME' ] + ' ( ' + STR_PARAM + ' ) { '+
				'var INDICE = 0;' +
				'var CALLBACK = __ws__callback; ' +
				'var LST_PARAMETROS = new Array ( ' + STR_PARAM_VETOR + ' ); ' + 
				'var PARAM_VETOR = new array_clear (); ' +
				STR_TESTE_VARS +
				'return ( mws_array ( le_http ( \'' + FUNCAO [ 'URL' ] + '\', '+
					'PARAM_VETOR, CALLBACK, null, null ) ) );' +
			'}';		
		eval ( FONTE_FUNCAO );
		
		var FONTE_FUNCAO_PIPE = 
			'function ' + PREFIXO + 'pipe_' + FUNCAO [ 'NOME' ] + ' ( ID, ' + STR_PARAM + ' ) { '+
				'var INDICE = 0;' +
				'var CALLBACK = __ws__callback; ' +
				'var LST_PARAMETROS = new Array ( ' + STR_PARAM_VETOR + ' ); ' + 
				'var PARAM_VETOR = new array_clear (); ' +
				STR_TESTE_VARS +
				'if ( typeof ( LST_SEMAFORO [ ID ] ) == "undefined" ) LST_SEMAFORO [ ID ] = new array_clear (); ' +
				'if ( typeof ( LST_SEMAFORO [ ID ] [ "' + FUNCAO [ 'NOME' ] + '" ] ) == "undefined" ) LST_SEMAFORO [ ID ] [ "' + FUNCAO [ 'NOME' ] + '" ] = new array_clear (); ' +
				'LST_SEMAFORO [ ID ] [ "' + FUNCAO [ 'NOME' ] + '" ] [ "URL" ] = "' + FUNCAO [ 'URL' ] + '"; '+
				'LST_SEMAFORO [ ID ] [ "' + FUNCAO [ 'NOME' ] + '" ] [ "PARAM" ] = PARAM_VETOR; '+
				'if ( ! __ws__semaforo ( ID, "' + FUNCAO [ 'NOME' ] + '" ) ) return ( false ); ' +
				'return ( mws_array ( le_http ( \'' + FUNCAO [ 'URL' ] + '\', '+
					'PARAM_VETOR, CALLBACK, "' + FUNCAO [ 'NOME' ] + '", ID ) ) );' +
			'}';		
		eval ( FONTE_FUNCAO_PIPE );
		
		eval ( 'this.' + FUNCAO [ 'NOME' ] + '=' + PREFIXO + FUNCAO [ 'NOME' ] );
		eval ( 'this.pipe_' + FUNCAO [ 'NOME' ] + '=' + PREFIXO + 'pipe_' + FUNCAO [ 'NOME' ] );
	}
}

function ws_error ( )
{
	if ( typeof ( __WS__ERROS [ 'ERROR' ] ) != "undefined" )
		return ( __WS__ERROS [ 'ERROR' ] );
	else
		return ( false );
}

function ws_warn_lst ( )
{
	return ( __WS__ERROS [ 'LIST_WARN' ] [ 'WARN' ] );
}

function ws_warn_count ( )
{
	return ( __WS__ERROS [ 'LIST_WARN' ] [ 'WARN' ].length );
}

function ws_warn ( INDICE_OPCIONAL )
{
	if ( typeof INDICE_OPCIONAL == "undefined" )
		INDICE_OPCIONAL = __WS__INDICE_WARN;
	__WS__INDICE_WARN ++;
	
	if ( __WS__ERROS [ 'LIST_WARN' ] [ 'WARN' ].length <= __WS__INDICE_WARN )
		return ( null );
	
	return ( __WS__ERROS [ 'LIST_WARN' ] [ 'WARN' ] [ __WS__INDICE_WARN ] );
}

__WS__ERROS = new Array ();
__WS__INDICE_WARN = 0;

/* 
 * JSON mified (if browser doesnt support natively) 
 */
 if(!this.JSON){JSON={};}(function(){function f(n){return n<10?'0'+n:n;}if(typeof Date.prototype.toJSON!=='function'){Date.prototype.toJSON=function(key){return this.getUTCFullYear()+'-'+f(this.getUTCMonth()+1)+'-'+f(this.getUTCDate())+'T'+f(this.getUTCHours())+':'+f(this.getUTCMinutes())+':'+f(this.getUTCSeconds())+'Z';};String.prototype.toJSON=Number.prototype.toJSON=Boolean.prototype.toJSON=function(key){return this.valueOf();};}var cx=/[\u0000\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,escapable=/[\\\"\x00-\x1f\x7f-\x9f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,gap,indent,meta={'\b':'\\b','\t':'\\t','\n':'\\n','\f':'\\f','\r':'\\r','"':'\\"','\\':'\\\\'},rep;function quote(string){escapable.lastIndex=0;return escapable.test(string)?'"'+string.replace(escapable,function(a){var c=meta[a];return typeof c==='string'?c:'\\u'+('0000'+a.charCodeAt(0).toString(16)).slice(-4);})+'"':'"'+string+'"';}function str(key,holder){var i,k,v,length,mind=gap,partial,value=holder[key];if(value&&typeof value==='object'&&typeof value.toJSON==='function'){value=value.toJSON(key);}if(typeof rep==='function'){value=rep.call(holder,key,value);}switch(typeof value){case'string':return quote(value);case'number':return isFinite(value)?String(value):'null';case'boolean':case'null':return String(value);case'object':if(!value){return'null';}gap+=indent;partial=[];if(Object.prototype.toString.apply(value)==='[object Array]'){length=value.length;for(i=0;i<length;i+=1){partial[i]=str(i,value)||'null';}v=partial.length===0?'[]':gap?'[\n'+gap+partial.join(',\n'+gap)+'\n'+mind+']':'['+partial.join(',')+']';gap=mind;return v;}if(rep&&typeof rep==='object'){length=rep.length;for(i=0;i<length;i+=1){k=rep[i];if(typeof k==='string'){v=str(k,value);if(v){partial.push(quote(k)+(gap?': ':':')+v);}}}}else{for(k in value){if(Object.hasOwnProperty.call(value,k)){v=str(k,value);if(v){partial.push(quote(k)+(gap?': ':':')+v);}}}}v=partial.length===0?'{}':gap?'{\n'+gap+partial.join(',\n'+gap)+'\n'+mind+'}':'{'+partial.join(',')+'}';gap=mind;return v;}}if(typeof JSON.stringify!=='function'){JSON.stringify=function(value,replacer,space){var i;gap='';indent='';if(typeof space==='number'){for(i=0;i<space;i+=1){indent+=' ';}}else if(typeof space==='string'){indent=space;}rep=replacer;if(replacer&&typeof replacer!=='function'&&(typeof replacer!=='object'||typeof replacer.length!=='number')){throw new Error('JSON.stringify');}return str('',{'':value});};}if(typeof JSON.parse!=='function'){JSON.parse=function(text,reviver){var j;function walk(holder,key){var k,v,value=holder[key];if(value&&typeof value==='object'){for(k in value){if(Object.hasOwnProperty.call(value,k)){v=walk(value,k);if(v!==undefined){value[k]=v;}else{delete value[k];}}}}return reviver.call(holder,key,value);}cx.lastIndex=0;if(cx.test(text)){text=text.replace(cx,function(a){return'\\u'+('0000'+a.charCodeAt(0).toString(16)).slice(-4);});}if(/^[\],:{}\s]*$/.test(text.replace(/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g,'@').replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g,']').replace(/(?:^|:|,)(?:\s*\[)+/g,''))){j=eval('('+text+')');return typeof reviver==='function'?walk({'':j},''):j;}throw new SyntaxError('JSON.parse');};}})();
