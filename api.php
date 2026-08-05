<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Santiago');
$API_KEY='8a77e7e5-9d3d-4f36-b3ef-93d77d5e4c89';
$key=$_SERVER['HTTP_X_API_KEY']??($_GET['key']??'');
if($key!==$API_KEY){http_response_code(401);echo json_encode(["ok"=>False,"message"=>"Unauthorized"]);exit;}
$file=__DIR__.'/data/requests.json';
$data=json_decode(@file_get_contents($file),true)?:[];
function out($d,$m='OK',$ok=true){echo json_encode(["ok"=>$ok,"version"=>"1.0","timestamp"=>date('c'),"message"=>$m,"data"=>$d],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);exit;}
$a=$_GET['action']??'ping';
switch($a){
case 'ping': out(["status"=>"online"]); break;
case 'list': out(["count"=>count($data),"items"=>$data]); break;
case 'pending':
 $r=array_values(array_filter($data,function($x){return ($x['status']??'')==='en_gestion';}));
 out(["count"=>count($r),"items"=>$r]); break;
case 'stats':
 $s=["total"=>count($data),"correo_creado"=>0,"en_gestion"=>0,"otros"=>0];
 foreach($data as $x){$st=$x['status']??'otros'; if(isset($s[$st]))$s[$st]++; else $s['otros']++;}
 out($s); break;
case 'get':
 $g=$_GET['guid']??'';
 foreach($data as $x){if(($x['guid']??'')===$g) out($x);}
 http_response_code(404); out([],"No encontrado",false);
case 'search':
 $q=strtolower($_GET['q']??'');
 $r=array_values(array_filter($data,function($x)use($q){return strpos(strtolower(json_encode($x)),$q)!==false;}));
 out(["count"=>count($r),"items"=>$r]); break;
default:
 http_response_code(501);
 out([],"Acción no implementada",false);
}
