<?php
session_start();
$PASS="losinmortales";
if(isset($_GET["logout"])){session_destroy();header("Location: ".$_SERVER["PHP_SELF"]);exit;}
if(!isset($_SESSION["ok"])){
 if($_SERVER["REQUEST_METHOD"]==="POST"){
   if(($_POST["password"]??"")===$PASS){$_SESSION["ok"]=1;header("Location: ".$_SERVER["PHP_SELF"]);exit;}
   $err="Contraseña incorrecta";
 }
?><!doctype html><html><head><meta charset="utf-8"><title>Login</title>
<style>body{font-family:Arial;background:#eef2f7;display:grid;place-items:center;height:100vh}.c{background:#fff;padding:30px;border-radius:12px;box-shadow:0 8px 30px #0002;width:320px}input,button{width:100%;padding:12px;margin:8px 0}button{background:#2563eb;color:#fff;border:0;border-radius:8px}</style></head><body>
<div class=c><h2>Los Inmortales</h2><?php if(isset($err)) echo "<p style='color:red'>$err</p>";?>
<form method=post><input type=password name=password placeholder="Contraseña"><button>Entrar</button></form></div></body></html><?php exit;}
$dataFile=__DIR__.'/data/requests.json';
$items=json_decode(@file_get_contents($dataFile),true)?:[];
$q=strtolower($_GET['q']??'');
$f=array_filter($items,function($r)use($q){
 if($q==='') return true;
 return strpos(strtolower(json_encode($r)),$q)!==false;
});
$stats=["total"=>count($items),"en_gestion"=>0,"correo_creado"=>0,"otros"=>0];
foreach($items as $r){$s=$r["status"]??""; if(isset($stats[$s]))$stats[$s]++; else $stats["otros"]++;}
?><!doctype html><html><head><meta charset="utf-8"><title>Panel</title>
<style>
body{margin:0;font-family:Arial;background:#f5f7fb}.top{background:#1e40af;color:#fff;padding:18px;display:flex;justify-content:space-between}
.wrap{padding:20px}.stats{display:flex;gap:15px;flex-wrap:wrap}.box,.card{background:#fff;border-radius:12px;box-shadow:0 2px 10px #0001}.box{padding:15px;min-width:150px}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;margin-top:20px}.card{padding:18px}.badge{padding:4px 8px;border-radius:20px;color:#fff;font-size:12px}.correo_creado{background:#16a34a}.en_gestion{background:#d97706}.otros{background:#6b7280}button{padding:8px 12px}
dialog{width:70%;border:0;border-radius:12px}
pre{white-space:pre-wrap}
</style></head><body>
<div class=top><div><b>Los Inmortales</b></div><div><a style="color:white" href="?logout=1">Salir</a></div></div>
<div class=wrap>
<form><input name=q placeholder="Buscar..." value="<?=htmlspecialchars($q)?>" style="padding:10px;width:300px"></form>
<div class=stats>
<div class=box>Total<br><b><?=$stats["total"]?></b></div>
<div class=box>En gestión<br><b><?=$stats["en_gestion"]?></b></div>
<div class=box>Correo creado<br><b><?=$stats["correo_creado"]?></b></div>
</div>
<div class=grid>
<?php foreach($f as $i=>$r):
$st=$r["status"]??"otros"; ?>
<div class=card>
<div><span class="badge <?=$st?>"><?=$st?></span></div>
<h3><?=$r["detalle"]["nombre"]??""?></h3>
<p><b>RUN:</b> <?=$r["detalle"]["run"]??""?><br>
<b>Email:</b> <?=$r["email"]??""?><br>
<b>Tel:</b> <?=$r["detalle"]["telefono"]??""?></p>
<?php if(isset($r["correo"]["direccion"])):?>
<p><b>Correo privado:</b><br><?=$r["correo"]["direccion"]?><br><small><?=$r["correo"]["password_temporal"]?></small></p>
<?php endif;?>
<button onclick="d<?=$i?>.showModal()">Ver expediente</button>
<dialog id="d<?=$i?>">
<h2><?=$r["detalle"]["nombre"]??""?></h2>
<pre><?=htmlspecialchars(json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre>
<button onclick="navigator.clipboard.writeText('<?=$r["correo"]["direccion"]??""?>')">Copiar correo</button>
<button onclick="this.parentNode.close()">Cerrar</button>
</dialog>
</div>
<?php endforeach;?>
</div></div></body></html>