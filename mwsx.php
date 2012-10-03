<?php
/*-------------------------------------------------------------------------
 *
 * mwsx.php
 *		faz com que o script PHP que incluiu esta biblioteca se torne um 
 * fornecedor de WS.
 * 
 * Copyleft 2012 - Domínio público
 * Autor original: Daniel Loureiro
 *
 * Versão 1.2
 *-------------------------------------------------------------------------
 */

/*
 * torna o cliente resistente à erros.	Idéia é que o cliente busque por esta 
 * assinatura e a partir dela comece a tratar o retorno.
 */
define( 'SIGNATURE', '8;698DC19D;' );
define( 'SIGNATURE_PUBLIC', '\/\* _EXPORT_ \*\/' );

/*
 * pilha de avisos, sinais e erro
 */
global $WS_ERROR;
global $WS_LST_WARN;
global $WS_LST_SIGNAL;

$WS_ERROR = null;
$WS_LST_SIGNAL = array();
$WS_LST_SIGNAL = array();

function save_error_file( $ERROR )
{
	$FILENAME = session_save_path() . '/ws_error.log';
	
	$DATA	= date( "Y-m-d H:i:s" );
	$IP		= $_SERVER['REMOTE_ADDR'];
	
	$fp = fopen( $FILENAME, "a" );
	fputs( $fp, $DATA . '; '. $IP. '; ' . $ERROR . "\n" );
	fclose( $fp );
}

function read_error_file()
{
	$FILENAME = session_save_path() . '/ws_error.log';
	return	file_get_contents( $FILENAME );
}

/*
 * #client
 * chama uma página pelo método POST
 * * ex.: HOST: www.google.com.br/test.php, RAW_POST_DATA: a=10&b=20&c=30
 * ciclo de vida está muito longo
 * bug em URLs relativas
 */
define( 'PROTOCOL', 1 );
define( 'HOST',		2 );
define( 'PORT',		3 );
define( 'PATH',		4 );

function safe_post( $URL, $RAW_POST_DATA )
{
	// fragmenta URL em:
	// [1] protocolo
	// [2] host
	// [3] porta
	// [4] path	
	preg_match( '/^(.+:\/\/)?([^:\/]+)(:[0-9]+)?(\/?.*)/', $URL, $MATCHES );
	
	// #relative-path
	// descobre se URL é relativa ou absoluta
	$IS_RELATIVE = ( $MATCHES[ PROTOCOL ] == '' );
	
	// #relative-path
	// normaliza porta (padrão = 80)
	if ( $IS_RELATIVE ) {
		$PORT = $_SERVER[ 'SERVER_PORT' ];
	}
	if ( ( ! $IS_RELATIVE ) AND ( $MATCHES[ PORT ] == '' ) ) {
		$PORT = 80;
	}
	if ( ( ! $IS_RELATIVE ) AND ( $MATCHES[ PORT ] != '' ) ) {
		$PORT = substr( $MATCHES[ PORT ], 1 );
	}
	
	// #relative-path
	// normaliza path/host
	if ( $IS_RELATIVE ) 
	{
		$PATH = $URL;
		if ( substr( $URL, 1, 1 ) != '/' ) {
			$PATH = '/' . $PATH;
		}
		$HOST = $_SERVER[ 'HTTP_HOST' ];
		$PROTOCOL = ( array_key_exists( 'HTTPS', $_SERVER )? 'https://': 'http://' );
	}
	else 
	{
		$PATH = $MATCHES[ PATH ];
		$HOST = $MATCHES[ HOST ];
		$PROTOCOL = $MATCHES[ PROTOCOL ];
	}
	
	// #cookie
	if( session_id() == '' ) {
		session_start();
	}
	if ( array_key_exists( 'WS_COOKIE', $_SESSION ) ) {
		$WS_COOKIE = $_SESSION[ 'WS_COOKIE' ];
	}
	else {
		$WS_COOKIE = null;
	}
	
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $PROTOCOL . $HOST . ':' . $PORT . $PATH );
	$HEADER = array( "Content-Type: text/xml; charset=utf-8", "Expect: " );
	// #cookie
	if ( $WS_COOKIE != null ) {
		$HEADER[] = 'Cookie: '. $WS_COOKIE;
	}
	
	curl_setopt($curl, CURLOPT_HTTPHEADER, $HEADER );
	curl_setopt($curl, CURLOPT_HEADER, true ); 
	curl_setopt($curl, CURLOPT_POST, true );
	curl_setopt($curl, CURLOPT_POSTFIELDS, $RAW_POST_DATA );
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
	curl_setopt($curl, CURLOPT_TIMEOUT, 15 );
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false );
	
	$result = curl_exec($curl);
	//print_r($result);
	curl_close($curl);
	
	// separa em "cabeçalho + corpo"
	$BUFFER = explode( "\r\n\r\n", $result );

	// #cookie
	$POS_COOKIE = strpos( $BUFFER[0], 'Set-Cookie' );
	if ( $POS_COOKIE !== false )
	{
		$STR_COOKIE = substr( $BUFFER[0], $POS_COOKIE + strlen( 'Set-Cookie: ' ) );
		$POS_FIM = strpos( $STR_COOKIE, ';' );
		$_SESSION[ 'WS_COOKIE' ] = substr( $STR_COOKIE, 0, $POS_FIM );
	}
	
	// retorna somente o corpo
	return $BUFFER[1];
} 
 
 
/*
 * pega_listagem_de_funcoes_publicas_do_proprio_fonte_
 * 		abre o próprio fonte e pega uma lista as funções que tenham o 
 * comentário "_EXPORT_"
 * 
 * Saída
 * 	vetor com listagem de funções a publicar.	Cada elemento do vetor contém: 
 *		* o nome da função
 *		* um vetor com o nome de cada parâmetro (sem o "$")
 * 
 * TODO
 * 	 * fazer com regex
 */
function list_published_functions_( ) 
{
    /* 
	 * descobre nome do próprio script e pega seu conteúdo 
	 */
    $PATH = pathinfo( $_SERVER[ 'PHP_SELF' ] );
    $CODIGO_FONTE = file_get_contents( $PATH[ 'basename' ] );
    
    /*
     * desmembra fonte pegando funções exportadas
     * [1] = nome funções publicadas
     * [2] = parâmetros funções publicadas (não tratadas como lista)
     */
    preg_match_all( '/'. SIGNATURE_PUBLIC .'[ \t\r\n]*function[ \t\r\n]?(.+)[ \t\r\n]*\((.*)\)/', $CODIGO_FONTE, $MATCHES );
    
    /*
     * converte literal dos parâmetros para listagem
     */
    $LIST_PARAM = array_map( create_function( '$STR', 'return( $STR == "" ? array() : explode( ",", preg_replace( \'/[\$ \n]/\', \'\', $STR ) ) );' ), $MATCHES[ 2 ] );
    
    /*
     * consome listas de nome função e de listagem de parâmetros, unificando em um literal
     */
    $LIST_FUNCTION = array_map( create_function( '$A, $B' , 'return( array( \'NAME\' => $A, \'LIST_PARAMETERS\' => $B ) );' ), $MATCHES[ 1 ], $LIST_PARAM );

    return $LIST_FUNCTION;
}


/*
 * pega_os_dados_passados_por_http_
 * 		retorna, em um vetor de dados, os dados passados por GET / POST. 
 * 
 * NOTA: os dados podem estar codificados JSON ou no formato PHP
 * 
 * TODO
 *   só aceitar 1 formato
 */
function pega_os_dados_passados_por_http_()
{
    $VETOR = array();    

    /* 
     * POST
     */
    $POST_PURO	= file_get_contents( 'php://input' );
    $JSON		= @json_decode( $POST_PURO, TRUE );
    $VETOR		= array_merge( $VETOR, is_array ( $JSON ) ? $JSON : array () );
        
    /* 
     * GET
     */
	$GET_PURO	= urldecode( $_SERVER[ 'QUERY_STRING' ] );
	$JSON		= @json_decode( $GET_PURO, TRUE );
    $VETOR		= array_merge( $VETOR, is_array ( $JSON ) ? $JSON : array () );
	
	/*
	 * PHP
	 */
	if ( count ( $VETOR ) == 0 )
		$VETOR = array_merge ( $VETOR, $_REQUEST );
	
    return ( $VETOR );
}


function converte_resultado_para_mws_( $WS_RETORNO )
{
	global $WS_ERROR;
	global $WS_LST_WARN;
	global $WS_LST_SIGNAL;
	
	return ( SIGNATURE . json_encode ( array( 
		'RESULT'	=> $WS_RETORNO, 
		'ERROR'		=> $WS_ERROR, 
		'WARNS'		=> $WS_LST_WARN, 
		'SIGNALS'	=> $WS_LST_SIGNAL 
	) ) );
}


function context_to_mws( $WS_RETORNO )
{
	global $WS_ERROR;
	global $WS_LST_WARN;
	global $WS_LST_SIGNAL;
	
	return( SIGNATURE . json_encode ( array( 
		'RESULT'	=> $WS_RETORNO, 
		'ERROR'		=> $WS_ERROR, 
		'WARNS'		=> $WS_LST_WARN, 
		'SIGNALS'	=> $WS_LST_SIGNAL 
	) ) );
}


/*
 * error: pára a execução da função.
 *
 * Sintaxe: mensagem de erro para o usuário, retorno da função
 */
function error( $MENSAGEM )
{
	// salva msg
	save_error_file( $MENSAGEM );
	
	// nova mensagem de erro
	global $WS_ERROR;
	$WS_ERROR = $MENSAGEM;
	
	// zera pilha de avisos
	global $WS_LST_WARN;
	$WS_LST_WARN = array();
	
	// pára tudo
	die( context_to_mws( '' ) );
}


function warn( $MENSAGEM ) 
{
	global $WS_LST_WARN;
	$WS_LST_WARN[] = $MENSAGEM;
}


function signal( $SIGNAL ) 
{
	global $WS_LST_SIGNAL;
	$WS_LST_SIGNAL[] = $SIGNAL;
}


/*
 * estamos no fonte do usuário e podemos ter 3 ações: 
 * (1) mostrar nossa listagem de funções em MWSD; 
 * (2) executar algum método MWS; 
 * (3) não fazer coisa alguma, pois é uma execução normal do script, e não um 
 * uso do WS;
 */
/*
 * estamos no fonte do usuário e podemos ter 3 ações: 
 * (1) mostrar nossa listagem de funções em MWSD; 
 * (2) executar algum método MWS; 
 * (3) não fazer coisa alguma, pois é uma execução normal do script, e não um 
 * uso do WS;
 */
if ( $_SERVER[ 'QUERY_STRING' ] == "mwsd" ) 
{
 	/* 
 	 * mostrar a listagem de funções em MWSD
 	 */
	$LST_FUNCOES = list_published_functions_( );

	$PORTA = '';
	if( $_SERVER[ 'SERVER_PORT' ] != 80 ) {
		$PORTA = ':' . $_SERVER[ 'SERVER_PORT' ];
	}
	foreach ( $LST_FUNCOES as $NUMERO_DA_FUNCAO => $FUNCAO ) {
		$LST_FUNCOES[ $NUMERO_DA_FUNCAO ][ 'URL' ] = "http://${_SERVER['HTTP_HOST']}${PORTA}${_SERVER['PHP_SELF']}?mws=${FUNCAO['NAME']}";
	}

    die( json_encode( array( 'LIST_FUNCTIONS' => $LST_FUNCOES ) ) );
}

else if ( isset( $_REQUEST [ 'mws' ] ) )
{
 	/* 
 	 * é para chamar método do WS (se ele existir)
 	 */
 	
	/*
	 * buscamos o método aqui no fonte (índice na listagem)
	 * todo: programação funcional
	 */
    $NOME_FUNCAO_QUE_USUARIO_CHAMOU = $_REQUEST [ 'mws' ];
    $LISTA_DE_FUNCOES_DESTE_FONTE = list_published_functions_( );
    foreach ( $LISTA_DE_FUNCOES_DESTE_FONTE as $INDICE_DA_FUNCAO => $FUNCAO_AQUI_NO_FONTE ) 
	{
    	if ( $NOME_FUNCAO_QUE_USUARIO_CHAMOU == $FUNCAO_AQUI_NO_FONTE [ 'NAME' ] )
    		break;
	}
    
	if ( $NOME_FUNCAO_QUE_USUARIO_CHAMOU != $LISTA_DE_FUNCOES_DESTE_FONTE[ $INDICE_DA_FUNCAO ][ 'NAME' ] )
		die ( "MWSX: function $NOME_FUNCAO_QUE_USUARIO_CHAMOU not found !" );
	
	$FUNCAO_AQUI_NO_FONTE = $LISTA_DE_FUNCOES_DESTE_FONTE [ $INDICE_DA_FUNCAO ];
	
	/*
	 * colocamos os parâmetros na ordem em que aparecem no fonte
	 */
	if ( is_array ( $FUNCAO_AQUI_NO_FONTE [ 'LIST_PARAMETERS' ] ) )
	{
    	$DADOS_RECEBIDOS_POR_HTTP = pega_os_dados_passados_por_http_ ( );
		
    	$DADOS_NA_ORDEM_DA_FUNCAO = array ();
    	foreach ( $FUNCAO_AQUI_NO_FONTE [ 'LIST_PARAMETERS' ] as $PARAMETROS )
        	$DADOS_NA_ORDEM_DA_FUNCAO [] = $DADOS_RECEBIDOS_POR_HTTP [ $PARAMETROS ];
	}
	
	/* 
	 * chama a função, mostra resultados no padrão MWS
	 */
	$WS_LST_WARN = array ();
	$WS_ERROR = null;	
	$WS_RETORNO = call_user_func_array( $NOME_FUNCAO_QUE_USUARIO_CHAMOU, $DADOS_NA_ORDEM_DA_FUNCAO );
 	die( context_to_mws( $WS_RETORNO ) );
}


/*
 * ws
 * 	usado se seu script vai consumir WS, e não apenas atuar como servidor
 * 
 * Entrada
 * 	um link http, que aponta ao mwsd
 * 
 * Saída
 * 	um objeto, com métodos que chamam as funções do WS e retornam suas 
 * respostas
 */
function ws( $URL ) 
{
	/* 
	 * lê o link, que retorna um vetor das funções publicadas (mwsd)
	 */
    $MWSD = json_decode( safe_post( $URL, '' ), TRUE );
    
    /*
	 * com os dados do link, criamos código fonte de classe
	 * e métodos que retornam as funções via HTTP
	 */
	$LST_FUNCAO = array ();
    
    /* os métodos */
    foreach ( $MWSD[ 'LIST_FUNCTIONS' ] as $NUMERO_FUNCAO => $FUNCAO ) 
	{
		/* 
		 * cria literal dos parâmetros da função.	ex: 
		 * "$P1, $P2" 
		 */
        $LITERAL_DOS_PARAMETROS = '';
		if ( is_array ( $FUNCAO[ 'LIST_PARAMETERS' ] ) AND ( count( $FUNCAO[ 'LIST_PARAMETERS' ] ) != 0 ) )
		{
	        foreach ( $FUNCAO[ 'LIST_PARAMETERS' ]as $PARAMETRO )
    	        $LITERAL_DOS_PARAMETROS .= "\$${PARAMETRO}, ";
		}
        
        /* tira a última vírgula */
        $LITERAL_DOS_PARAMETROS = substr ( $LITERAL_DOS_PARAMETROS, 0, strlen ( $LITERAL_DOS_PARAMETROS ) - 2 );
		
        /*
		 * cria literal, que é o código-fonte da função (sem os 
		 * parâmetros)
		 */
		$LITERAL_DO_FONTE = '$VETOR_PARAM = array(); ';
		if ( is_array ( $FUNCAO[ 'LIST_PARAMETERS' ] ) AND ( count( $FUNCAO[ 'LIST_PARAMETERS' ] ) != 0 ) )
		{
			foreach ( $FUNCAO [ 'LIST_PARAMETERS' ] as $PARAMETRO )
				$LITERAL_DO_FONTE .= "\$VETOR_PARAM[ '${PARAMETRO}' ] = \$${PARAMETRO}; "; 
		}
		
        $LITERAL_DO_FONTE .= "\$URL=\"${FUNCAO['URL']}\"; ";
        $LITERAL_DO_FONTE .= "\$RAW_POST=json_encode( \$VETOR_PARAM ); ";
        $LITERAL_DO_FONTE .= '
			global $WS_LST_WARN;
			global $WS_LST_SIGNAL;
			global $WS_ERROR;
			
			$RETORNO = safe_post( $URL, $RAW_POST );
			
			$INICIO = strpos( $RETORNO, SIGNATURE );
			if ( ( $INICIO === false ) OR ( $INICIO != 0 ) )
			//if ( $INICIO === false )
			{
				$WS_ERROR = $RETORNO;
				save_error_file("URL:\n".$URL."\n\nPOST:\n".$RAW_POST."\n\nRETORNO:\n".$RETORNO);
				return( null ); 
			}
			
			$RETORNO = (array)json_decode( substr( $RETORNO, $INICIO + strlen( SIGNATURE ) ) ); 
			
			$WS_ERROR		= $RETORNO[\'ERROR\'];
			$WS_LST_SIGNAL	= $RETORNO[\'SIGNALS\'];
			$WS_LST_WARN	= $RETORNO[\'WARNS\'];
			
			return( (array)$RETORNO[\'RESULT\'] ); 
		';
         
        /*
		 * soma os parâmetros com o fonte, e transforma em 
		 * método
		 */
		if( $LITERAL_DOS_PARAMETROS == '$' ) {
			$LITERAL_DOS_PARAMETROS = '';
		}
		
		$LST_FUNCAO[] = "function ${FUNCAO['NAME']} ($LITERAL_DOS_PARAMETROS) { $LITERAL_DO_FONTE }";
    }
    
    /*
	 * cria classe/objeto através do fonte gerado
	 */
	$SRC = '';
	foreach ( $LST_FUNCAO as $FUNCAO ) {
		$SRC .= $FUNCAO;
	}
	$NOME_CLASSE = uniqid( "class" );
	eval( "class $NOME_CLASSE { $SRC }" );
	
	$OBJETO = new $NOME_CLASSE();
	
	/*
	 * retorna o objeto com os métodos
	 */
    return ( $OBJETO );
}

function ws_error()
{
	global $WS_ERROR;
	return( $WS_ERROR );
}

function ws_warn( )
{
	global $WS_LST_WARN;
	return( array_pop( $WS_LST_WARN ) );
}

function ws_has_signal( $SIGNAL )
{
	global $WS_LST_SIGNAL;

	return ( in_array( $SIGNAL, $WS_LST_SIGNAL ) );
}
?>