<?php
declare(strict_types=1);
require_once __DIR__.'/../../inventario/remisiones_auth.php';rmaPermitir([1,2]);
require_once __DIR__.'/../../db.php';
require_once __DIR__.'/../../inventario/remisiones_pdf.php';
try {
    $ids=array_values(array_unique(array_filter(array_map('intval',explode(',',(string)($_GET['ids']??$_GET['id']??''))))));
    if(!$ids||count($ids)>300)throw new InvalidArgumentException('Seleccioná entre uno y 300 casos.');
    $q=$pdo->prepare('SELECT c.*,p.nombre proveedor_nombre,p.contacto proveedor_contacto,cl.nombre cliente_nombre FROM casos c LEFT JOIN proveedores p ON p.id=c.id_proveedor JOIN clientes cl ON cl.id=c.id_cliente WHERE c.id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY p.nombre,c.numero_caso');
    $q->execute($ids);$casos=$q->fetchAll(PDO::FETCH_ASSOC);
    if(count($casos)!==count($ids))throw new InvalidArgumentException('Hay casos inexistentes en la selección.');
    foreach($casos as $c)if(!$c['id_proveedor']||!$c['fecha_envio_proveedor']||!$c['referencia_proveedor'])throw new InvalidArgumentException('Registrá proveedor, fecha y referencia de envío en todos los casos seleccionados.');
    $p=new RmaRemisionDocumento(['id'=>0,'titulo'=>'REMISIÓN DE PEDIDO DE ENVÍO / FLUJO EXTERNO','fecha_creacion'=>(new DateTimeImmutable('now',new DateTimeZone('America/Asuncion')))->format('Y-m-d H:i:s'),'encabezado'=>'MICRO EXPRESS / REMISIÓN DE ENVÍO','alcance'=>'Equipos enviados por proveedor','estado'=>count($casos).' equipos','numero_caso'=>''],true);
    $rows=[];$groups=[];
    foreach($casos as $c)$groups[$c['proveedor_nombre']][]=$c;
    foreach($groups as $proveedor=>$grupo){
        $rows[]=[$proveedor,'','','','','',''];
        foreach($grupo as $c)$rows[]=[$c['numero_caso'],$c['cliente_nombre'],$c['equipo'].' '.$c['marca'].' '.$c['modelo'],$c['numero_serie'],$c['referencia_proveedor'],date('d/m/Y',strtotime($c['fecha_envio_proveedor'])),$c['proveedor_contacto']??''];
        $rows[]=['Subtotal: '.count($grupo).' equipos','','','','','',''];
    }
    $rows[]=['TOTAL: '.count($casos).' equipos','','','','','',''];
    $p->listado(['Caso / Proveedor','Cliente','Equipo','Serie','Referencia envío','Fecha envío','Contacto proveedor'],$rows);
    if($p->y>450)$p->newPage();
    $y=$p->y+50;
    foreach([[32,240,'Entregado por / Firma'],[300,510,'Recibido por / Firma'],[570,810,'Fecha y aclaración']] as [$x,$x2,$label]){$p->line($x,$y,$x2,$y);$p->text($x,$y+8,$label,9);}
    $bytes=$p->finish();header('Content-Type: application/pdf');header('Content-Disposition: '.(isset($_GET['descargar'])?'attachment':'inline').'; filename="remision-envio.pdf"');header('Content-Length: '.strlen($bytes));echo $bytes;
}catch(Throwable $e){rmaEnviarError($e);}
