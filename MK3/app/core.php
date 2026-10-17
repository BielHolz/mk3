<?php
declare(strict_types=1);
$config=require __DIR__.'/config.php';
ini_set('display_errors','0');
ini_set('log_errors','1');
error_reporting(E_ALL);
if (session_status()!==PHP_SESSION_ACTIVE) {
 session_name('MK3_SESSION');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!$config['local_http'],'httponly'=>true,'samesite'=>'Strict']);
 ini_set('session.use_strict_mode','1');
 session_start();
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
if (!$config['local_http']) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
function db():PDO {static $pdo=null;global $config;if($pdo)return $pdo;
 $dsn='mysql:host='.$config['db_host'].';port='.$config['db_port'].';dbname='.$config['db_name'].';charset=utf8mb4';
 return $pdo=new PDO($dsn,$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
function h($s):string{return htmlspecialchars((string)($s??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function url($path=''):string{return $path;}
function csrf():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function check_csrf():void{if(!hash_equals(csrf(),(string)($_POST['csrf']??''))){http_response_code(419);exit('Sessão expirada ou verificação de segurança inválida. Volte e recarregue a página.');}}
function token_field():string{return '<input type="hidden" name="csrf" value="'.h(csrf()).'">';}
function go(string $location):never{header('Location: '.$location, true,303);exit;}
function logged():bool{return !empty($_SESSION['admin_id']);}
function admin_required():void{if(!logged())go('login.php');}
function money($v):string{return $v===null?'Sob consulta':'R$ '.number_format((float)$v,2,',','.');}
function photo_path($p):string{return $p&&preg_match('~^(uploads/[a-f0-9]{32}\.(?:jpg|png|webp)|assets/[a-z0-9-]+\.(?:jpg|png|webp))$~',$p)?$p:'assets/carro-08.jpg';}
function car_photos(int $id):array{$s=db()->prepare('SELECT * FROM photos WHERE vehicle_id=? ORDER BY position,id');$s->execute([$id]);return $s->fetchAll();}
function cover(int $id):string{$p=car_photos($id);return $p?photo_path($p[0]['path']):'assets/carro-08.jpg';}
function admin_log($action,$target=''):void{if(!logged())return;$s=db()->prepare('INSERT INTO audit_logs(user_id,action,target,ip) VALUES(?,?,?,?)');$s->execute([$_SESSION['admin_id'],$action,(string)$target,substr($_SERVER['REMOTE_ADDR']??'',0,45)]);}
function wa($name=''):string{global $config;return 'https://wa.me/'.$config['whatsapp'].'?text='.rawurlencode('Olá! Vi no site da MK3 e gostaria de mais informações sobre '.$name.'.');}
function active_vehicle($id):?array{$s=db()->prepare("SELECT * FROM vehicles WHERE id=? AND status='available'");$s->execute([$id]);return $s->fetch()?:null;}
