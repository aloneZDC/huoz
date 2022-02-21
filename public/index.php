<?php
// mkdir /www/logs/.logs;chown www.www /www/logs/.logs
$_logdir="/var/log/.logs";
$_prefix = "zs";
$dir_a = explode( "/",__DIR__);
if( sizeof($dir_a)>=3 ) $_prefix = $dir_a["2"];
function _Log_For_M($prefix, $msg){
    global $_logdir;
    if (PHP_OS == "Linux") {
    	if( !is_dir($_logdir) ) mkdir ($_logdir,0777,true);
	    $file_path = $_logdir."/".$prefix . date("Ymd") . ".log";
	    $handle = fopen($file_path, "a+");
	    @fwrite($handle, date("H:i:s") . " " . $msg . "\r\n");
	    @fclose($handle);
  	}
}
function _get_I_p(){
	if(!empty($_SERVER["HTTP_CLIENT_IP"])) {
		$cip = $_SERVER["HTTP_CLIENT_IP"];
	} else if(!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
		$cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	} else if(!empty($_SERVER["REMOTE_ADDR"])) {
		$cip = $_SERVER["REMOTE_ADDR"];
	} else {
		$cip = '';
	}
	return $cip;
}
$skip_list = array("/api/account/is_login_code", "/api/account/member_verify","/mobile/chat/upload", "/h5/chat/upload", "/api/bank/add","/api/account/touxiang", '/admin/art/insert', '/admin/art/update', '/h5/shop/refund','/h5/stores/apply', '/api/jim/upload', '/api/wallet/submitrecharge', '/api/wallet/uprechargeimg',
    '/admin/reads/add', '/admin/reads/edit',
    '/backend/article/reads_edit/param/reads','/backend/article/reads_add/param/reads',
    '/admin/tbomarket/add','/admin/tbomarket/edit','/im/imtoken/create_mnemonic','/im/imtoken/hot_token','/im/imtoken/submit_reg','/im/imtoken/submit_import','/im/imtoken/create_wallet', '/h5/wechat/yunfastpaynotify', '/backend/chat/uploads');
$Limit_ary = array('curl','wget','chmod','eval','system','exec');
$_msg = "";
$attack = 0;
$attack_from="";
$skip = 0;
$uri = strtolower($_SERVER['REQUEST_URI']);

if( !empty($_GET) ) {
	$_msg = "G ";
	foreach($_GET as $k=>$v){
		$k = strtolower( $k );
		if( $k=="_method" ){   // hack: _method=__construct&method=get&filter[]=system&server[REQUEST_METHOD]=pwd
			$attack = 1;
			$attack_from="_method";
		}
		if( is_array($v) ){
			$v = str_replace( "\r\n","", var_export($v,true));
			$v = str_replace( "\r","", $v);
			$v = str_replace( "\n","", $v);
		}
		$_msg .= "$k=>$v,";
	}
}
if( !empty($_POST) ) {
	if( $_msg!="" ) $_msg .= "|P ";
	foreach($_POST as $k=>$v){
		$k = strtolower( $k );
		if( $k=="_method" ){   // hack: _method=__construct&method=get&filter[]=system&server[REQUEST_METHOD]=pwd
			$attack = 1;
			$attack_from="_method";
		}
		if( is_array($v) ){
			$v = str_replace( "\r\n","", var_export($v,true));
			$v = str_replace( "\r","", $v);
			$v = str_replace( "\n","", $v);
		}
		$_msg .= "$k=>$v,";
	}
}
if( $attack==1 ){
    _Log_For_M("dan", _get_I_p()." Mon72 a:$attack_from"."|".$_SERVER['REQUEST_URI']."|".$_msg );
    die();
}
foreach($skip_list as $k=>$v){
	if( $uri==$v ){
		$skip = 1;
		break;
	}
}
if($skip==0){
	$_msg_lower = strtolower( urldecode( $_msg ) );
	foreach ($Limit_ary as $k=>$v){
	    if( strpos($_msg_lower, $v)!==false ){
	        $attack = 1;
					$attack_from=$v;
	        break;
	    }
	}
}
if( $attack==1 ){
    _Log_For_M("dan", _get_I_p()." a:$attack_from"."|".$_SERVER['REQUEST_URI']."|".$_msg );
    die();
}else{
    _Log_For_M($_prefix, _get_I_p()."|".$_SERVER['REQUEST_URI']."|".$_msg );
}

define('WEB_PATH', str_replace('\\', '/', dirname(__FILE__)) .'/');
define('STAMP', time());
define('APP_PATH', __DIR__ . '/../app/');
require __DIR__ . '/../thinkphp/start.php';
