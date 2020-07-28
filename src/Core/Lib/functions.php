<?php
function pr($data){
    print "<pre>\n";
    print_r($data);
    print "</pre>\n";
}

function hule($str = NULL){
    die($str);
}

function encrypt( $value ){
	$encrypt = new \Cryptography\Encryption;
	return $encrypt->encrypt( $value );
}

function decrypt( $value ){
	$encrypt = new \Cryptography\Encryption;
	return $encrypt->decrypt( $value );
}

/*
* Pantalla de error
*/
function Error($message)
{
	print '<html><head><title>' . $message . '</title></head><body style="background-color:#ccc;">';
	print '<pre><h1> Sistema (V 1.0.0)</h1></pre>';
	pr('<span style="color:#f00; font-size:16px;"><strong>' . $message . '</strong></span>');
	pr('<em>' . $_SERVER['REQUEST_METHOD'] . " ($_SERVER[SERVER_PROTOCOL]) : " . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] . '</em>');
	print '</body></html>';
	exit;
}

function getIpAddress() {
	if(empty($_SERVER['HTTP_CLIENT_IP'])):
		if(empty($_SERVER['HTTP_X_FORWARDED_FOR'])):
			$ip = $_SERVER['REMOTE_ADDR'];
		else:
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		endif;
	else:
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	endif;
	return $ip;
}

function number($n){
	return is_numeric($n) ? number_format($n,0,"","") : 0;
}

function isValidEmail($email){
	$regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
	if(!preg_match($regex, $email)):
		return false;
	else:
		return true;
	endif;
}

function pageNumber(){
	$_GET['page']	= isset($_GET['page']) 	? $_GET['page']		: 0;
	$_POST['page']	= isset($_POST['page'])	? $_POST['page']	: 0;
	return number($_GET['page']) == 0 ? number($_POST['page']) == 0 ? 1 : number($_POST['page']) : number($_GET['page']);
}

function param($param){
	return isset($_GET[$param]) ? cleanParam($_GET[$param]) : (isset($_POST[$param]) ? cleanParam($_POST[$param]) : "");
}

function numParam($param){
	$value = number(param($param));
	if(isset($_POST[$param])):
		$_POST[$param] = $value;
	elseif(isset($_GET[$param])):
		$_GET[$param] = $value;
	endif;
	return $value;
}

function cleanParam($param)
{
    $search = array(
        '@<script[^>]*?>.*?</script>@si',   // Elimina javascript
        '@<[\/\!]*?[^<>]*?>@si',            // Elimina las etiquetas HTML
        '@<style[^>]*?>.*?</style>@siU',    // Elimina las etiquetas de estilo
        '@<![\s\S]*?--[ \t\n\r]*>@'         // Elimina los comentarios multi-línea
    );
    $param = preg_replace($search, '', $param);
    return htmlentities(stripslashes(strip_tags($param)));
}

function haveRows($arr){
	return is_array($arr) && count($arr) > 0 ? true : false;
}

function cropWords($string, $limit = 20){
	$words = explode(" ", trim($string));
	$w = array();
	$crop="";

	//limpia todos los espacios entre las palabras
	foreach($words as $word):
		if(strlen(trim($word)) > 0):
			$w[] = trim($word);
		endif;
	endforeach;

	//une las palabras
	$limit = count($words) < $limit ? count($words) : $limit;
	for($i = 0; $i < $limit; $i++):
		$crop .= $w[$i] . " ";
	endfor;

	$more = count($w) > $limit ? "..." : "";
	return substr($crop,0,-1) . $more;
}

function redirect($uri){
    session_write_close();
    clearBuffer();
    header("Location: $uri");
    exit;
}

function clearBuffer(){
    if (ob_get_length()):
        ob_end_clean();
    endif;
}

function require_folder($path, $recursive = false)
{
	if (is_dir($path)) {
		if ($dh = opendir($path)) {
			while ($file = readdir($dh)) {
				if (!preg_match('/\.+/', $file) && filetype($path . $file) == 'dir' && $recursive) {
					require_folder($path . $file . "/", $recursive);
				} else {
					if (preg_match('/\.php$/', $file)) {
						require_once $path . $file;
					}
				}
			}
			closedir($dh);
		}
	}
}

/*
* Transforma un objeto de tipo stdClass en Array
*/
function objectToArray($d)
{
	if (is_object($d)) {
		$d = get_object_vars($d);
	}

	if (is_array($d)) {
		return array_map(__FUNCTION__, $d);
	} else {
		return $d;
	}
}

function get_session_handler_token()
{
	return strrev(md5(sha1(session_id())));
}

/*
* Setea la cabecera como Server Error
*/
function setServerError($str = "Server Error")
{
	header("HTTP/1.1 500 Server Error");
}

/*
* Retorna la instancia de un controlador
*/
function get_controller($controller_name)
{

	if (controller_exists($controller_name)) {
		$controller = '\\Controller\\' . $controller_name;
		return new $controller;
	} else {
		setServerError();
		throw new Exception("Unknown controller");
	}
}

/*
* verifica si el controlador existe
*/
function controller_exists($controller_name)
{
	return class_exists('\\Controller\\' . $controller_name);
}

/*
* Establece el código de estado HTTP
*/
function setStatusCode($code, $description = null)
{
	$status_list = array(
		200 => _("OK"),
		201 => _("Created"),
		202 => _("Accepted"),
		203 => _("Non-Authoritative Information"),
		204 => _("No Content"),
		205 => _("Reset Content"),
		206 => _("Partial Content"),
		207 => _("Multi-Status"),
		208 => _("Already Reported"),
		300 => _("Multiple Choices"),
		301 => _("Moved Permanently"),
		302 => _("Found"),
		303 => _("See Other"),
		304 => _("Not Modified"),
		305 => _("Use Proxy"),
		306 => _("Switch Proxy"),
		307 => _("Temporary Redirect"),
		308 => _("Permanent Redirect"),
		400 => _("Bad request"),
		401 => _("Unauthorized"),
		403 => _("Forbidden"),
		404 => _("Not found"),
		405 => _("Method Not Allowed"),
		406 => _("Not Acceptable"),
		407 => _("Proxy Authentication Required"),
		408 => _("Request Timeout"),
		409 => _("Conflict"),
		410 => _("Gone"),
		411 => _("Length Required"),
		412 => _("Precondition Failed"),
		413 => _("Request Entity Too Large"),
		414 => _("Request-URI Too Long"),
		415 => _("Unsupported Media Type"),
		416 => _("Requested Range Not Satisfiable"),
		417 => _("Expectation Failed"),
		422 => _("Unprocessable Entity"),
		423 => _("Locked"),
		424 => _("Failed Dependency"),
		425 => _("Unassigned"),
		426 => _("Upgrade Required"),
		428 => _("Precondition Required"),
		429 => _("Too Many Requests"),
		431 => _("Request Header Fileds Too Large"),
		451 => _("Unavailable for Legal Reasons"),
		500 => _("Internal Server Error"),
		501 => _("Not Implemented"),
		502 => _("Bad Gateway"),
		503 => _("Service Unavailable"),
		504 => _("Gateway Timeout"),
		505 => _("HTTP Version Not Supported"),
		506 => _("Variant Also Negotiates"),
		507 => _("Insufficient Storage"),
		508 => _("Loop Detected"),
		509 => _("Bandwidth Limit Exceeded"),
		509 => _("Not Extended"),
		509 => _("Network Authentication Required")
	);

	if (isset($status_list[$code])) {
		header("HTTP/1.1 {$code} {$status_list[$code]}");
	} else {
		setStatusCode(406);
	}
}

/*
* Setea la cabecera como JSON
*/
function setApplicationJSON()
{
	clearBuffer();
	header("Content-Type: application/json; charset=utf-8");
}

function JSONResponse($data, $numeric_check = false)
{
	setApplicationJSON();
	if ($data instanceof stdClass || is_array($data)) {
		return @json_encode($data, JSON_UNESCAPED_UNICODE);
	} else {
		return $data;
	}
}

function JSONResponseOK()
{
	JSONResponse(
		array(
			"code" => 200,
			"message" => "OK"
		)
	);
}

function JSONResponseCreated($data, $numeric_check = false)
{
	setApplicationJSON();
	setStatusCode(201);

	if ($data instanceof stdClass || is_array($data)) {
		return json_encode($data, JSON_UNESCAPED_UNICODE);
	} else {
		return $data;
	}
}

/*
* Retorna mensaje de error en formato json
*/
function JSONResponseError($code, $message, $extras = null)
{
	setStatusCode($code);
	header('Content-Type: application/vnd.error+json; charset=utf-8');

	$response = array(
		"code" => $code,
		"message" => $message,
	);

	if (is_array($extras)) {
		$response['extras'] = $extras;
	}

	return @json_encode($response, JSON_UNESCAPED_UNICODE, JSON_NUMERIC_CHECK);
}

/*
* Retorna el request body
*/
function getRequestBody()
{
	return @file_get_contents('php://input');
}

/*genera codigo aleatorio*/
/*Longitud variable entre (int)@minLen y (int)@maxLen*/
function uniqcode($minLen=5,$maxLen=9,$sym=true){

	// [1] Letras Mayusculas de la A a la Z (65-90)
	// [2] Letras Minusculas de la A a la Z (97-122)
	// [3] Numeros del 0 al 9 (48-57)
	// [4] Caracterees ()*+ (40-43)
	// [5] Caracteres -. ()(45-46)
	$ret = "";
	$totalLen = ($minLen == $maxLen) ? $maxLen : rand($minLen, $maxLen);
	$maxblock = $sym ? 5 : 3;
	for($len = 1; $len <= $totalLen; $len++):
		$block = rand(1,$maxblock);
		switch($block):
			case 1:
				$char = chr(rand(65,90));
				break;
			case 2:
				$char = chr(rand(97,122));
				break;
			case 3:
				$char = chr(rand(48,57));
				break;
			case 4:
				$char = chr(rand(40,43));
				break;
			case 5:
				$char = chr(rand(45,46));
				break;
		endswitch;

		$ret .= $char;

	endfor;

	return $ret;
}