<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../api-rma/db_config.php';
date_default_timezone_set('America/Asuncion');
$r=rmaConexion('rma');$v=rmaConexion('inventario');
/** Datos sintéticos coherentes; nunca contactos reales ni usuarios con acceso. */
function insertarLote(PDO $p,string $tabla,array $cols,array $rows):void {
 foreach(array_chunk($rows,100) as $chunk){
  $sql='INSERT IGNORE INTO '.$tabla.' ('.implode(',',$cols).') VALUES '.implode(',',array_fill(0,count($chunk),'('.implode(',',array_fill(0,count($cols),'?')).')'));
  $p->prepare($sql)->execute(array_merge(...$chunk));
 }
}
function mapa(PDO $p,string $tabla,string $col,string $prefix):array {
 $s=$p->prepare("SELECT $col,id FROM $tabla WHERE $col LIKE ?");$s->execute([$prefix.'%']);return $s->fetchAll(PDO::FETCH_KEY_PAIR);
}
$tecs=$r->query('SELECT id FROM usuarios WHERE id_rol=1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$tipos=$r->query('SELECT id FROM tipos_caso ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$states=$r->query('SELECT nombre,id FROM estados_caso')->fetchAll(PDO::FETCH_KEY_PAIR);
if(!$tecs||!$tipos)throw new RuntimeException('Se necesitan técnicos y tipos de caso existentes.');
$nombres=['Carlos','María','Luis','Ana','Jorge','Lucía','Diego','Sofía','Miguel','Paola','Andrés','Camila','Hugo','Patricia','Raúl','Elena','Gabriel','Laura','Víctor','Valeria'];
$apellidos=['Benítez','González','Rojas','Gómez','Villalba','Duarte','Vera','Acosta','Martínez','López','Ortiz','Sosa','Caballero','Medina','Torres'];
$zonas=['Centro','San Lorenzo','Luque','Capiatá','Lambaré','Encarnación','Villarrica','Caaguazú','Coronel Oviedo','Fernando de la Mora','Itauguá','Mariano Roque Alonso','Limpio','Villa Elisa','Ñemby'];
$rubros=['Soluciones Digitales','Distribuidora Informática','Tecnología Integral','Redes y Sistemas','Servicio Técnico','Componentes del Sur','Equipamiento Comercial','Conectividad','Suministros Informáticos','Almacenamiento','Servicios IT','Mayorista Tecnológico','Repuestos y Accesorios','Periféricos','Integración de Sistemas','Infraestructura','Impresión Profesional','Electrónica Aplicada','Informática Empresarial','Soporte Regional'];
$equipos=[['Notebook','Lenovo','ThinkPad E14'],['Notebook','HP','ProBook 450'],['Notebook','Dell','Latitude 3540'],['Monitor','Samsung','S24C310'],['Impresora','Epson','EcoTank L3250'],['PC de escritorio','Asus','ExpertCenter D5'],['Placa madre','Gigabyte','B550M DS3H'],['Tarjeta gráfica','MSI','RTX 4060 Ventus'],['Servidor','Dell','PowerEdge T350'],['Router','TP-Link','Archer AX23']];
$insumos=['SSD SATA 480 GB','SSD NVMe 1 TB','Memoria DDR4 8 GB','Memoria DDR4 16 GB','Fuente ATX 550 W','Cable HDMI 2 m','Ventilador 120 mm','Pasta térmica 4 g','Adaptador USB Ethernet','Batería de notebook','Teclado USB','Cable de alimentación','Rodillo de impresora','Conector de carga','Pantalla 15.6 pulgadas'];
$fallas=['No enciende al conectar la alimentación.','Reinicios durante el uso prolongado.','La unidad de almacenamiento no es detectada.','Pérdida intermitente de imagen.','Temperatura elevada durante carga de trabajo.','Puertos sin respuesta al conectar periféricos.','No carga la batería.','Conexión de red inestable.','Ruido mecánico durante el funcionamiento.','Rendimiento inferior al esperado.'];
$stamp='Carga operativa 2026-11';$now=new DateTimeImmutable('today');
try {
 $r->beginTransaction();
 $rows=[];for($i=0;$i<300;$i++)$rows[]=[$nombres[$i%20].' '.$apellidos[intdiv($i,20)%15],'CLI-'.str_pad((string)(710001+$i),7,'0',STR_PAD_LEFT),'','cliente'.($i+1).'@example.invalid'];
 insertarLote($r,'clientes',['nombre','cedula','celular','email'],$rows);$clientes=mapa($r,'clientes','cedula','CLI-');
 $existing=mapa($r,'proveedores','nombre','');$proveedoresGenerados=[];$rows=[];for($i=0;$i<300;$i++){$name=$rubros[$i%20].' '.$zonas[intdiv($i,20)];$proveedoresGenerados[]=$name;if(!isset($existing[$name]))$rows[]=[$name,'logistica'.($i+1).'@example.invalid'];}
 insertarLote($r,'proveedores',['nombre','contacto'],$rows);$prov=array_intersect_key(mapa($r,'proveedores','nombre',''),array_flip($proveedoresGenerados));
 $stock=mapa($r,'stock','codigo_sku','REP-');$rows=[];
 for($i=0;$i<300;$i++){$sku='REP-'.str_pad((string)($i+1),5,'0',STR_PAD_LEFT);if(!isset($stock[$sku]))$rows[]=[$sku,$insumos[$i%15].' / Serie '.(intdiv($i,15)+1),'Repuesto compatible para mantenimiento de equipos. '.$stamp,array_values($prov)[$i%count($prov)],$i%10===0?2:40+$i%30,5+$i%5];}
 insertarLote($r,'stock',['codigo_sku','nombre','descripcion','id_proveedor_habitual','cantidad','stock_minimo'],$rows);$stock=mapa($r,'stock','codigo_sku','REP-');
 $existing=mapa($r,'casos','numero_caso','RMA-2026-7');$rows=[];$history=[];
 $paths=[['ingresado'],['ingresado','en_diagnostico'],['ingresado','en_diagnostico','en_reparacion'],['ingresado','en_diagnostico','enviado_a_proveedor'],['ingresado','en_diagnostico','enviado_a_proveedor','recibido_de_proveedor'],['ingresado','en_diagnostico','en_reparacion','listo_para_entrega'],['ingresado','en_diagnostico','en_reparacion','listo_para_entrega','entregado'],['ingresado','en_diagnostico','en_reparacion','listo_para_entrega','entregado','cerrado'],['ingresado','en_diagnostico','cancelado']];
 for($i=0;$i<600;$i++){
  $num='RMA-2026-'.(710001+$i);if(isset($existing[$num]))continue;
  $path=array_values(array_filter($paths[$i%9],fn($n)=>isset($states[$n])));if(!$path)throw new RuntimeException('Faltan estados de caso.');
  $last=end($path);$closed=in_array($last,['entregado','cerrado','cancelado'],true);$start=$now->modify('-'.(15+$i%160).' days')->setTime(8+$i%9,$i%60);$end=$start->modify('+'.((count($path)-1)*2).' days');
  $external=in_array('enviado_a_proveedor',$path,true);$eq=$equipos[$i%10];$tec=(int)$tecs[$i%count($tecs)];
  $rows[]=[$num,$clientes['CLI-'.str_pad((string)(710001+$i%300),7,'0',STR_PAD_LEFT)],$tec,$tipos[$i%count($tipos)],$states[$last],$external?array_values($prov)[$i%count($prov)]:null,...$eq,'SN'.(26000001+$i),$fallas[$i%10],count($path)>2?'Se verificaron conexiones, alimentación y funcionamiento. Se registraron las pruebas del equipo.':null,$start->format('Y-m-d H:i:s'),$closed?$end->format('Y-m-d H:i:s'):null,$external?$start->modify('+4 days')->format('Y-m-d H:i:s'):null,$external?'ENV-'.(260001+$i):null];
  $history[$num]=[$path,$start,$tec];
 }
 insertarLote($r,'casos',['numero_caso','id_cliente','id_tecnico','id_tipo_caso','id_estado_actual','id_proveedor','equipo','marca','modelo','numero_serie','descripcion_problema','diagnostico_final','fecha_ingreso','fecha_cierre','fecha_envio_proveedor','referencia_proveedor'],$rows);
 $cases=mapa($r,'casos','numero_caso','RMA-2026-7');$rows=[];
 $obs=['ingresado'=>'Recepción del equipo y registro de la falla informada.','en_diagnostico'=>'Inspección visual y pruebas de alimentación y conectividad.','en_reparacion'=>'Reemplazo de componente y pruebas de estabilidad.','enviado_a_proveedor'=>'Equipo despachado al proveedor con referencia de envío.','recibido_de_proveedor'=>'Recepción del equipo y comprobación de la intervención.','listo_para_entrega'=>'Pruebas finales completas. Cliente informado para retiro.','entregado'=>'Entrega al cliente con comprobación del funcionamiento.','cerrado'=>'Servicio finalizado y documentación archivada.','cancelado'=>'Servicio cancelado antes de la reparación.'];
 foreach($history as $num=>[$path,$start,$tec])foreach($path as $j=>$name)$rows[]=[$cases[$num],$states[$name],$start->modify('+'.($j*2).' days')->format('Y-m-d H:i:s'),$obs[$name],$tec];
 insertarLote($r,'historial_estados',['id_caso','id_estado','fecha','observacion','id_usuario'],$rows);
 $q=$r->prepare('SELECT id_caso FROM caso_insumos WHERE observacion=?');$q->execute([$stamp]);$consumed=$q->fetchAll(PDO::FETCH_COLUMN);$rows=[];
 for($i=0;$i<300;$i++){$id=$cases['RMA-2026-'.(710001+$i)];if(in_array($id,$consumed))continue;$rows[]=[$id,$stock['REP-'.str_pad((string)($i+1),5,'0',STR_PAD_LEFT)],$tecs[$i%count($tecs)],1,$stamp];}
 insertarLote($r,'caso_insumos',['id_caso','id_stock','id_tecnico','cantidad','observacion'],$rows);
 $q=$r->prepare("INSERT INTO notas_remision(id_caso,numero_caso,id_caso_insumo,id_stock,codigo_sku,insumo_nombre,id_tecnico,id_usuario_creador,cantidad,observacion,estado,fecha_creacion) SELECT ci.id_caso,c.numero_caso,ci.id,ci.id_stock,s.codigo_sku,s.nombre,ci.id_tecnico,ci.id_tecnico,ci.cantidad,ci.observacion,IF(MOD(ci.id,3)=0,'CERRADA','PENDIENTE'),DATE_ADD(c.fecha_ingreso,INTERVAL 2 DAY) FROM caso_insumos ci JOIN casos c ON c.id=ci.id_caso JOIN stock s ON s.id=ci.id_stock LEFT JOIN notas_remision nr ON nr.id_caso_insumo=ci.id WHERE ci.observacion=? AND nr.id IS NULL");$q->execute([$stamp]);
 $q=$r->prepare("UPDATE notas_remision SET fecha_cierre=DATE_ADD(fecha_creacion,INTERVAL 1 DAY),id_usuario_cierre=id_usuario_creador,observacion_cierre='Movimiento verificado por depósito.' WHERE observacion=? AND estado='CERRADA' AND fecha_cierre IS NULL");$q->execute([$stamp]);
 $r->commit();echo "RMA: clientes, proveedores, casos, líneas de tiempo, insumos, consumos y remisiones cargados.\n";
 $v->beginTransaction();$existing=mapa($v,'productos','codigo','ART-');$rows=[];$new=[];
 for($i=0;$i<300;$i++){$code='ART-'.str_pad((string)($i+1),5,'0',STR_PAD_LEFT);if(isset($existing[$code]))continue;$qty=$i%10===0?2:30+$i%40;$rows[]=[$code,$insumos[$i%15].' / Serie '.(intdiv($i,15)+1),'Repuestos',$equipos[$i%10][1],$qty,1,5,$qty<=5?'CRITICO':'OK'];$new[]=$code;}
 insertarLote($v,'productos',['codigo','nombre','categoria','marca','stock_actual','stock_rma','stock_minimo','estado_alerta'],$rows);$products=mapa($v,'productos','codigo','ART-');$rows=[];
 foreach($new as $i=>$code){$qty=$i%10===0?2:30+$i%40;$rows[]=[$products[$code],$cases['RMA-2026-'.(710001+$i)],'RMA-2026-'.(710001+$i),$tecs[$i%count($tecs)],'CENTRAL_RMA',1,$qty,$qty,0,1,$tecs[$i%count($tecs)],$stamp];}
 insertarLote($v,'operaciones_rma',['producto_id','id_caso','numero_caso','id_tecnico','tipo','cantidad','stock_anterior','stock_nuevo','rma_anterior','rma_nuevo','id_usuario','motivo'],$rows);$v->commit();
 echo "Inventario: 300 productos y sus asignaciones. Carga idempotente; contactos de ejemplo sin destinatarios reales.\n";
}catch(Throwable $e){if($r->inTransaction())$r->rollBack();if($v->inTransaction())$v->rollBack();throw $e;}
