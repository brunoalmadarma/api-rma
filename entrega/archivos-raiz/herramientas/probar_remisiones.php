<?php
// Prueba de integración aislada. Exclusivo CLI; nunca acepta llamadas HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/../api-rma/db_config.php';
$pdo=rmaConexion('rma');
$dir=__DIR__.'/../tmp/remisiones';
if (!is_dir($dir)) mkdir($dir,0777,true);
$archivo=$dir.'/fixture.json';
$modo=$argv[1] ?? 'verificar';
if ($modo==='preparar') {
    if (is_file($archivo)) exit("Ya existe una prueba preparada.\n");
    $usuarios=$pdo->query("SELECT id,usuario FROM usuarios WHERE usuario IN ('bruno','aferreira')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $ids=array_flip($usuarios);
    if (!isset($ids['bruno'],$ids['aferreira'])) throw new RuntimeException('Faltan las cuentas de prueba.');
    $cliente=$pdo->query('SELECT id FROM clientes ORDER BY id LIMIT 1')->fetchColumn();
    $tipo=$pdo->query('SELECT id FROM tipos_caso ORDER BY id LIMIT 1')->fetchColumn();
    $estado=$pdo->query('SELECT id FROM estados_caso ORDER BY id LIMIT 1')->fetchColumn();
    $otro=$pdo->prepare('SELECT id FROM usuarios WHERE id_rol=1 AND id<>? ORDER BY id LIMIT 1');
    $otro->execute([$ids['bruno']]);
    $responsable=$otro->fetchColumn() ?: $ids['bruno'];
    $tag='QA-NR-'.bin2hex(random_bytes(5));
    $s=$pdo->prepare('INSERT INTO casos (numero_caso,id_cliente,id_tecnico,id_tipo_caso,id_estado_actual,equipo,descripcion_problema) VALUES (?,?,?,?,?,?,?)');
    $s->execute([$tag,$cliente,$responsable,$tipo,$estado,'Equipo de prueba de remisiones','Prueba automática temporal.']);
    $fixture=['caso'=>(int)$pdo->lastInsertId(),'tag'=>$tag,'bruno'=>(int)$ids['bruno'],'inventario'=>(int)$ids['aferreira']];
    file_put_contents($archivo,json_encode($fixture));
    echo "Caso temporal creado: $tag\n"; exit;
}
if (!is_file($archivo)) throw new RuntimeException('Primero ejecutar preparar.');
$fixture=json_decode(file_get_contents($archivo),true);
if ($modo==='limpiar') {
    $pdo->beginTransaction();
    $s=$pdo->prepare('SELECT numero_caso FROM casos WHERE id=? FOR UPDATE'); $s->execute([$fixture['caso']]);
    if ($s->fetchColumn()!==$fixture['tag']) throw new RuntimeException('No coincide el caso de prueba.');
    $s=$pdo->prepare('DELETE FROM notas_remision WHERE id_caso=? AND numero_caso=?'); $s->execute([$fixture['caso'],$fixture['tag']]); $notas=$s->rowCount();
    $s=$pdo->prepare('DELETE FROM historial_estados WHERE id_caso=?'); $s->execute([$fixture['caso']]);
    $s=$pdo->prepare('DELETE FROM casos WHERE id=? AND numero_caso=?'); $s->execute([$fixture['caso'],$fixture['tag']]);
    $pdo->commit(); unlink($archivo);
    foreach (['bruno.cookies','aferreira.cookies'] as $cookie) {
        if (is_file($dir.'/'.$cookie)) unlink($dir.'/'.$cookie);
    }
    echo "Retirados: $notas notas de prueba y un caso temporal. Ningún caso previo fue modificado.\n"; exit;
}
$password=$argv[2] ?? '';
if ($password==='') throw new RuntimeException('Indicar contraseña de las cuentas de prueba.');
$base='http://localhost/rma-app';
function check(bool $value,string $message): void { if (!$value) throw new RuntimeException($message); echo "OK: $message\n"; }
function http(string $url,string $cookie,?array $body=null,?string $csrf=null,bool $form=false): array {
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>60]);
    if ($body!==null) {
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$form?http_build_query($body):json_encode($body));
        curl_setopt($ch,CURLOPT_HTTPHEADER,[$form?'Content-Type: application/x-www-form-urlencoded':'Content-Type: application/json','X-CSRF-TOKEN: '.($csrf??'')]);
    }
    $text=curl_exec($ch); if ($text===false) throw new RuntimeException(curl_error($ch));
    $result=['code'=>curl_getinfo($ch,CURLINFO_HTTP_CODE),'text'=>$text,'type'=>curl_getinfo($ch,CURLINFO_CONTENT_TYPE),'data'=>json_decode($text,true)];
    curl_close($ch); return $result;
}
$sessions=[];
foreach (['bruno','aferreira'] as $user) {
    $cookie=$dir.'/'.$user.'.cookies';
    $r=http("$base/api-rma/sesiones/funLogin.php",$cookie,['usuario'=>$user,'password'=>$password],null,true);
    check(($r['data']['success']??false)===true,"Login $user");
    $page=http("$base/adm-rma/index.php?ruta=remisiones",$cookie);
    preg_match('/window\.rmaCsrf = "([a-f0-9]+)"/',$page['text'],$m);
    check(!empty($m[1]),"CSRF $user");
    $sessions[$user]=['cookie'=>$cookie,'csrf'=>$m[1]];
}
$api="$base/api-rma/inventario/remisiones.php";
$tec=$sessions['bruno']; $inv=$sessions['aferreira'];
$stockBefore=$pdo->query('SELECT id,cantidad FROM stock ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$cases=http("$api?action=casos",$tec['cookie']);
check(in_array($fixture['caso'],array_column($cases['data']['casos']??[],'id')),'Formulario incluye caso abierto');
$body=['id_caso'=>$fixture['caso'],'cantidad'=>2,'insumo_nombre'=>'Repuesto QA: memoria DDR4','observacion'=>'Verificación técnica (temporal).'];
check(http("$api?action=solicitar",$tec['cookie'],$body)['code']===403,'Se bloquea envío sin CSRF');
check(http("$api?action=solicitar",$inv['cookie'],$body,$inv['csrf'])['code']===403,'Inventario no suplanta al solicitante');
check(http("$api?action=solicitar",$tec['cookie'],array_replace($body,['cantidad'=>-1]),$tec['csrf'])['code']===422,'Se rechaza cantidad negativa');
$ids=[];
foreach (['CERRADA','CANCELADA','RECHAZADA'] as $state) {
    $r=http("$api?action=solicitar",$tec['cookie'],$body,$tec['csrf']);
    check($r['code']===200 && isset($r['data']['id']),"Solicitud creada para prueba $state");
    $id=$r['data']['id']; $ids[]=$id;
    check(http("$api?action=cambiar_estado",$tec['cookie'],['id'=>$id,'estado'=>$state],$tec['csrf'])['code']===403,'Técnico no cambia estados');
    $all=http("$api?action=listar&estado=TODAS",$inv['cookie']);
    check(in_array($id,array_column($all['data']['remisiones']??[],'id')),'Depósito ve solicitud recién creada');
    if ($state==='CERRADA') {
        $r=http("$api?action=cambiar_estado",$inv['cookie'],['id'=>$id,'estado'=>'ACTIVA'],$inv['csrf']);
        check($r['code']===200,'Depósito acepta solicitud');
    }
    $r=http("$api?action=cambiar_estado",$inv['cookie'],['id'=>$id,'estado'=>$state,'observacion_cierre'=>'Prueba temporal de '.$state],$inv['csrf']);
    check($r['code']===200,"Depósito cambia estado a $state");
    $r=http("$api?action=listar&estado=$state",$tec['cookie']);
    check(in_array($id,array_column($r['data']['remisiones']??[],'id')),"Técnico ve su nota $state");
    check(http("$api?action=cambiar_estado",$inv['cookie'],['id'=>$id,'estado'=>'ACTIVA'],$inv['csrf'])['code']===409,'Estado final no se reabre');
}
// Creada por otro usuario, aunque el técnico responsable sea Bruno.
$s=$pdo->prepare("INSERT INTO notas_remision (id_caso,numero_caso,insumo_nombre,id_tecnico,id_usuario_creador,cantidad) VALUES (?,?,?,?,?,1)");
$s->execute([$fixture['caso'],$fixture['tag'],'Nota ajena temporal',$fixture['bruno'],$fixture['inventario']]); $ajena=(int)$pdo->lastInsertId();
$own=http("$api?action=listar&estado=TODAS",$tec['cookie']);
check(!in_array($ajena,array_column($own['data']['remisiones']??[],'id')),'Mis remisiones filtra por creador');
foreach ($own['data']['remisiones'] as $n) check((int)$n['id_usuario_creador']===$fixture['bruno'] && !isset($n['costo_unitario']),'Nota propia sin costo interno');
foreach (['detalle','pdf'] as $a) check(http("$api?action=$a&id=$ajena",$tec['cookie'])['code']===404,"Bloqueado $a de nota ajena");
foreach (['stock-catalogo','stock-alertas','stock-movimientos','stock-asignaciones','inventario','alertas','asignaciones'] as $endpoint) {
    check(http("$base/api-rma/inventario/$endpoint.php?action=listar",$tec['cookie'])['code']===403,"Técnico sin acceso a $endpoint");
}
$pdf=http("$api?action=pdf&id=".$ids[0],$tec['cookie']);
check($pdf['code']===200 && str_starts_with($pdf['text'],'%PDF-1.4') && str_contains($pdf['type'],'application/pdf'),'PDF autenticado de nota propia');
file_put_contents($dir.'/nota-api.pdf',$pdf['text']);
check($stockBefore===$pdo->query('SELECT id,cantidad FROM stock ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Solicitudes y estados no descuentan stock');
echo "Todas las verificaciones pasaron. Ejecutar limpiar al finalizar la revisión visual.\n";
