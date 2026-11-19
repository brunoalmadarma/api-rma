<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../api-rma/db_config.php';
require_once __DIR__.'/../api-rma/rma-core/casos/consulta_service.php';
$f=json_decode(file_get_contents(__DIR__.'/../tmp/correcciones/fixture.json'),true);
$session=$f['sessions'][2];$base='http://localhost/rma-app';$r=rmaConexion('rma');$v=rmaConexion('inventario');
function asegurar(bool $ok,string $text):void {if(!$ok)throw new RuntimeException('FALLO: '.$text);echo "OK: $text\n";}
function consultar(string $path,?array $body=null,bool $form=false,bool $csrf=true):array {
 global $session,$base;$h=curl_init($base.$path);$headers=[];
 $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>90,CURLOPT_COOKIE=>'PHPSESSID='.$session['sid']];
 if($body!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$form?http_build_query($body):json_encode($body);if(!$form)$headers[]='Content-Type: application/json';if($csrf)$headers[]='X-CSRF-TOKEN: '.$session['token'];}
 $opts[CURLOPT_HTTPHEADER]=$headers;curl_setopt_array($h,$opts);$bytes=curl_exec($h);if($bytes===false)throw new RuntimeException(curl_error($h));
 $out=['code'=>curl_getinfo($h,CURLINFO_HTTP_CODE),'type'=>curl_getinfo($h,CURLINFO_CONTENT_TYPE),'body'=>$bytes,'data'=>json_decode($bytes,true)];curl_close($h);return $out;
}
$num='RMA-2026-710008';$_SERVER['HTTP_HOST']='localhost';$url=rmaVinculosCaso($num)['link_secure'];
asegurar(rmaNormalizarBusqueda("]C1$num\r\n")===$num,'Lector HID con prefijo y terminador');
asegurar(rmaNormalizarBusqueda($url)===$num,'Lectura de URL codificada en QR');
asegurar(rmaNormalizarBusqueda('http://localhost/rma-app/adm-rma/consulta?busqueda='.$num)===$num,'Enlace de consulta pública');
$path='/api-rma/rma-core/casos/bandejaCasos.php?action=listar_bandeja&buscar=';
foreach([$num,$url,"]C1$num\r\n"] as $search){$d=consultar($path.urlencode($search));asegurar($d['code']===200&&count($d['data']['casos']??[])===1&&$d['data']['casos'][0]['numero_caso']===$num,'Búsqueda por número, QR o lectura HID');}
$caso=$d['data']['casos'][0];$fecha=substr($caso['fecha_ingreso'],0,10);
foreach([$fecha,date('d/m/Y',strtotime($fecha))] as $date){$d=consultar($path.urlencode($date));asegurar(count($d['data']['casos']??[])>0,'Busca fecha normalizada '.$date);}
asegurar(consultar($path.'&desde=2026-12-01&hasta=2026-01-01')['code']===422,'Rechaza rango de fechas invertido');
$d=consultar($path.'&desde='.$fecha.'&hasta='.$fecha);asegurar(count($d['data']['casos']??[])>0&&count(array_filter($d['data']['casos'],fn($c)=>substr($c['fecha_ingreso'],0,10)!==$fecha))===0,'Rango inclusivo de un día');
foreach(['qr','barras'] as $type){$d=consultar('/api-rma/rma-core/casos/etiqueta.php?numero_caso='.$num.'&tipo='.$type);asegurar($d['code']===200&&$d['type']==='image/png'&&str_starts_with($d['body'],"\x89PNG"),'Imagen local de '.$type);file_put_contents(__DIR__.'/../tmp/correcciones/'.$type.'.png',$d['body']);}
$d=consultar('/comprobante-caso.php?token='.urlencode(base64_encode($num.'||'.md5($num.'MICRO_EXPRESS_SECURE_TOKEN_2026'))));asegurar($d['code']===200&&str_contains($d['body'],'data:image/png;base64,')&&!str_contains($d['body'],'qrserver.com'),'Comprobante con códigos generados localmente');
$catalog=consultar('/api-rma/rma-core/casos/casos.php?action=aux_catalogos');asegurar(in_array('Notebook',$catalog['data']['equipos'])&&in_array('Lenovo',$catalog['data']['marcas']),'Catálogos y valores iniciales');
$body=['id'=>$f['cliente'],'nombre'=>$f['tag'],'cedula'=>$f['tag'],'celular'=>'0981000000','email'=>'prueba@example.invalid'];
asegurar(consultar('/api-rma/clientes/clientes.php?action=actualizar',$body,false,false)['code']===403,'Correo de cliente exige CSRF');
$d=consultar('/api-rma/clientes/clientes.php?action=actualizar',$body);asegurar(($d['data']['status']??'')==='success','Guardar correo del cliente');
$d=consultar('/api-rma/clientes/clientes.php?action=listar');$c=array_values(array_filter($d['data']['clientes'],fn($c)=>$c['id']==$f['cliente']))[0];asegurar($c['email']===$body['email'],'Correo persistido y recuperado');
$d=consultar('/api-rma/clientes/clientes.php?action=actualizar',array_replace($body,['email'=>'no es un correo']));asegurar(($d['data']['status']??'')==='error','Correo inválido rechazado');
$d=consultar('/api-rma/notificaciones/notificaciones.php?action=listar_casos');$c=array_values(array_filter($d['data']['casos'],fn($c)=>$c['id']==$f['caso']))[0];asegurar($c['email']===$body['email'],'Mensajes disponen del correo guardado');
$prov=(int)$r->query('SELECT id FROM proveedores ORDER BY id LIMIT 1')->fetchColumn();
$body=['id_caso'=>$f['caso'],'id_usuario'=>999999,'id_proveedor'=>$prov,'id_estado_actual'=>4,'fecha_envio_proveedor'=>'2026-11-04','referencia_proveedor'=>'QA-ENVIO'];
$d=consultar('/api-rma/rma-core/casos/proveedoresRma.php?action=guardar_flujo_externo',$body,true);asegurar(($d['data']['status']??'')==='success','Guardar envío externo');
$q=$r->prepare('SELECT id_usuario FROM historial_estados WHERE id_caso=? ORDER BY id DESC LIMIT 1');$q->execute([$f['caso']]);asegurar((int)$q->fetchColumn()===$session['id'],'El envío usa al usuario autenticado');
$d=consultar('/api-rma/rma-core/casos/proveedoresRma.php?action=guardar_flujo_externo',array_replace($body,['id_proveedor'=>999999]),true);asegurar($d['code']===422,'Proveedor inexistente revierte el envío');
$d=consultar('/api-rma/rma-core/casos/remision_envio.php?id='.$f['caso']);asegurar($d['code']===200&&$d['type']==='application/pdf'&&str_starts_with($d['body'],'%PDF-'),'Remisión externa PDF');file_put_contents(__DIR__.'/../tmp/correcciones/envio-validado.pdf',$d['body']);
$d=consultar('/api-rma/rma-core/casos/remision_envio.php?ids=99999999');asegurar($d['code']===422,'Remisión no permite casos inexistentes');
$d=consultar('/api-rma/rma-core/casos/historialEstado.php?action=obtener_timeline&numero_caso='.$num);asegurar(count($d['data']['timeline']??[])>=5,'Línea de tiempo con múltiples etapas');
$d=consultar('/api-rma/bi/dashboard.php?action=cargar_metricas');asegurar(($d['data']['status']??'')==='success'&&count($d['data']['mensual']??[])>=5&&$d['data']['crm']['total_clientes']>=300,'CRM y gráficos calculados desde la base');
foreach(['clientes','proveedores','stock','casos','notas_remision','caso_insumos'] as $table)asegurar((int)$r->query("SELECT COUNT(*) FROM $table")->fetchColumn()>=300,'Mínimo 300 registros en '.$table);
asegurar((int)$v->query('SELECT COUNT(*) FROM productos')->fetchColumn()>=300,'Mínimo 300 productos centrales');
asegurar((int)$r->query("SELECT COUNT(*) FROM casos WHERE numero_caso LIKE '%DEMO%'")->fetchColumn()===0,'Sin casos DEMO en el listado');
foreach(['sla-procesos.php'=>'consultar_sla','performance-tecnico.php'=>'consultar_performance','confiabilidad-producto.php'=>'consultar_fallas','auditoria-proveedores.php'=>'consultar_proveedores','recurrencia-clientes.php'=>'consultar_recurrencia'] as $file=>$action){$d=consultar('/api-rma/bi/'.$file.'?action='.$action);asegurar(($d['data']['status']??'')==='success','Estadísticas: '.$file);}
$d=consultar('/api-rma/publico/consulta.php?busqueda='.urlencode($url));asegurar(($d['data']['status']??'')==='success','Consulta pública acepta QR y parámetros preparados');
putenv('RMA_DB_HOST=servidor-de-prueba');asegurar(rmaConfig('rma')['host']==='servidor-de-prueba','Configuración por variables de entorno');putenv('RMA_DB_HOST');
echo "Pruebas de noviembre completas.\n";
