<?php
if (PHP_SAPI!=='cli') {http_response_code(403);exit;}
require_once __DIR__.'/../api-rma/db_config.php';
$pdo=rmaConexion('inventario');
$dir=__DIR__.'/../tmp/inventario';
if (!is_dir($dir)) mkdir($dir,0777,true);
$file=$dir.'/fixture.json'; $mode=$argv[1]??'verificar';
if ($mode==='preparar') {
    if(is_file($file)) throw new RuntimeException('Hay una prueba pendiente de limpiar.');
    $tag='QA-INV-'.bin2hex(random_bytes(5)); $ids=[];
    $s=$pdo->prepare('INSERT INTO productos (codigo,nombre,categoria,marca,stock_actual,stock_rma,stock_minimo) VALUES (?,?,?,?,10,2,5)');
    foreach(['A','B'] as $suffix) {
        $s->execute([$tag.'-'.$suffix,'Producto temporal de inventario '.$suffix,'Pruebas','QA']);
        $ids[]=(int)$pdo->lastInsertId();
    }
    file_put_contents($file,json_encode(['tag'=>$tag,'ids'=>$ids]));
    echo "Productos temporales preparados: $tag\n";exit;
}
if(!is_file($file)) throw new RuntimeException('Primero preparar datos temporales.');
$fixture=json_decode(file_get_contents($file),true);
if($mode==='limpiar') {
    $pdo->beginTransaction();
    foreach($fixture['ids'] as $id) {
        $s=$pdo->prepare('SELECT codigo FROM productos WHERE id=? FOR UPDATE');$s->execute([$id]);$code=$s->fetchColumn();
        if(!is_string($code)||!str_starts_with($code,$fixture['tag'])) throw new RuntimeException('No coincide el producto temporal.');
        $s=$pdo->prepare('DELETE FROM productos WHERE id=?');$s->execute([$id]);
    }
    $pdo->commit();unlink($file);
    foreach(['bruno','aferreira'] as $u) if(is_file($dir.'/'.$u.'.cookies')) unlink($dir.'/'.$u.'.cookies');
    echo "Productos temporales y sus movimientos retirados. Productos existentes preservados.\n";exit;
}
function invCheck(bool $ok,string $msg):void {if(!$ok)throw new RuntimeException($msg);echo "OK: $msg\n";}
function invHttp(string $url,string $cookie,?array $data=null,?string $token=null,bool $form=false):array {
    $c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie]);
    if($data!==null) {curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$form?http_build_query($data):json_encode($data));curl_setopt($c,CURLOPT_HTTPHEADER,[$form?'Content-Type: application/x-www-form-urlencoded':'Content-Type: application/json','X-CSRF-TOKEN: '.($token??'')]);}
    $body=curl_exec($c);if($body===false)throw new RuntimeException(curl_error($c));
    $r=['code'=>curl_getinfo($c,CURLINFO_HTTP_CODE),'data'=>json_decode($body,true),'body'=>$body];curl_close($c);return $r;
}
$password=$argv[2]??'';if(!$password)throw new RuntimeException('Indicar contraseña de las cuentas de prueba.');
$base='http://localhost/rma-app';$api="$base/api-rma/inventario/inventario.php";$sessions=[];
foreach(['bruno','aferreira'] as $u) {
    $cookie="$dir/$u.cookies";
    $r=invHttp("$base/api-rma/sesiones/funLogin.php",$cookie,['usuario'=>$u,'password'=>$password],null,true);
    invCheck(($r['data']['success']??false)===true,"Login $u");
    $r=invHttp("$base/adm-rma/index.php?ruta=dashboardInventario",$cookie);
    preg_match('/window\.rmaCsrf = "([a-f0-9]+)"/',$r['body'],$m);
    // El técnico es redirigido; su token no es necesario para comprobar el bloqueo de rol.
    $sessions[$u]=['cookie'=>$cookie,'token'=>$m[1]??''];
}
$inv=$sessions['aferreira'];$tec=$sessions['bruno'];$id=$fixture['ids'][0];$id2=$fixture['ids'][1];
$stockRma=rmaConexion('rma')->query('SELECT id,cantidad FROM stock ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
invCheck(invHttp("$api?action=listar",'')['code']===401,'Inventario requiere sesión');
invCheck(invHttp("$api?action=detalle&id=$id",$tec['cookie'])['code']===403,'Técnico sin permisos de inventario');
$r=invHttp("$api?action=detalle&id=$id",$inv['cookie']);$original=$r['data']['producto'];
invCheck($original['stock_disponible']===8 && !empty($original['version']),'Disponible calculado y versión del producto');
$body=array_intersect_key($original,array_flip(['id','codigo','nombre','categoria','marca','stock_actual','stock_rma','stock_minimo','version']));
$body['motivo']='Prueba automática temporal de ajuste';
invCheck(invHttp("$api?action=actualizar",$inv['cookie'],$body)['code']===403,'Se exige CSRF');
invCheck(invHttp("$api?action=actualizar",$tec['cookie'],$body)['code']===403,'Técnico no edita existencias');
foreach([['stock_actual'=>-1],['stock_actual'=>2.5],['stock_rma'=>11],['motivo'=>'']] as $bad) {
    invCheck(invHttp("$api?action=actualizar",$inv['cookie'],array_replace($body,$bad),$inv['token'])['code']===422,'Datos inválidos rechazados');
}
$body=array_replace($body,['codigo'=>$fixture['tag'].'-EDIT','nombre'=>'Producto temporal actualizado','categoria'=>'Prueba editada','marca'=>'Marca QA','stock_actual'=>24,'stock_rma'=>4,'stock_minimo'=>6]);
$r=invHttp("$api?action=actualizar",$inv['cookie'],$body,$inv['token']);$p=$r['data']['producto']??[];
invCheck($r['code']===200 && ($p['stock_disponible']??null)===20 && ($p['estado_alerta']??null)==='OK','Edición real de producto y existencias en inventario');
$s=$pdo->prepare('SELECT * FROM movimientos_inventario WHERE producto_id=? ORDER BY id DESC LIMIT 1');$s->execute([$id]);$mov=$s->fetch(PDO::FETCH_ASSOC);
invCheck($mov['tipo_movimiento']==='AJUSTE' && (int)$mov['stock_anterior']===10 && (int)$mov['stock_nuevo']===24 && (int)$mov['cantidad']===14 && str_contains($mov['observacion'],$body['motivo']),'Movimiento con cantidad, antes, después y motivo');
$actor=rmaConexion('rma')->query("SELECT id FROM usuarios WHERE usuario='aferreira'")->fetchColumn();
invCheck((int)$mov['usuario_id']===(int)$actor,'Movimiento atribuido al usuario de sesión');
invCheck(invHttp("$api?action=actualizar",$inv['cookie'],$body,$inv['token'])['code']===409,'Conflicto entre dos ediciones bloqueado');
$body['version']=$p['version'];
invCheck(invHttp("$api?action=actualizar",$inv['cookie'],$body,$inv['token'])['code']===200,'Guardar sin cambios es seguro');
$s=$pdo->prepare('SELECT COUNT(*) FROM movimientos_inventario WHERE producto_id=?');$s->execute([$id]);
invCheck((int)$s->fetchColumn()===1,'Guardar sin cambios no duplica el movimiento');
foreach([[8,4,5,'ALERTA - STOCK BAJO'],[4,4,5,'SIN STOCK'],[0,0,5,'SIN STOCK']] as [$a,$reserved,$min,$state]) {
    $body=array_replace($body,['stock_actual'=>$a,'stock_rma'=>$reserved,'stock_minimo'=>$min]);
    $r=invHttp("$api?action=actualizar",$inv['cookie'],$body,$inv['token']);$p=$r['data']['producto']??[];
    invCheck($r['code']===200 && ($p['estado_alerta']??null)===$state && ($p['stock_disponible']??null)===$a-$reserved,"Alerta correcta: $state");
    $body['version']=$p['version'];
}
$duplicate=array_replace($body,['codigo'=>$fixture['tag'].'-B']);
invCheck(invHttp("$api?action=actualizar",$inv['cookie'],$duplicate,$inv['token'])['code']===422,'Código duplicado rechazado con rollback');
$r=invHttp("$base/api-rma/inventario/alertas.php?action=reabastecer",$inv['cookie'],['id'=>$id,'cantidad_ingreso'=>10,'motivo'=>'Reposición temporal de prueba'],$inv['token']);
invCheck($r['code']===200,'Reposición compatible con la estructura real');
$r=invHttp("$api?action=detalle&id=$id",$inv['cookie']);$p=$r['data']['producto'];
invCheck($p['stock_actual']===10 && $p['stock_disponible']===10 && $p['estado_alerta']==='OK','Stock persistido tras reposición');
$s=$pdo->prepare('SELECT * FROM movimientos_inventario WHERE producto_id=? ORDER BY id DESC LIMIT 1');$s->execute([$id]);$m=$s->fetch(PDO::FETCH_ASSOC);
invCheck($m['tipo_movimiento']==='REPOSICION' && (int)$m['usuario_id']===(int)$actor,'Reposición auditada');
$r=invHttp("$base/api-rma/inventario/alertas.php?action=consultar_alertas",$inv['cookie']);
$db=$pdo->query('SELECT COUNT(*) n,SUM(stock_actual) actual,SUM(stock_rma) rma FROM productos')->fetch(PDO::FETCH_ASSOC);
invCheck($r['code']===200 && $r['data']['kpis']['total_productos']==(int)$db['n'] && $r['data']['kpis']['unidades_totales']==(int)$db['actual'] && $r['data']['kpis']['en_proceso_rma']==(int)$db['rma'],'KPIs reflejan la base de inventario');
invCheck($stockRma===rmaConexion('rma')->query('SELECT id,cantidad FROM stock ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'La edición usa inventario y preserva el stock de insumos RMA');
echo "Todas las verificaciones de inventario pasaron.\n";
