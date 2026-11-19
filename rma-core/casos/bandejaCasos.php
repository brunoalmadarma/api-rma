<?php
declare(strict_types=1);
require_once __DIR__.'/../../inventario/remisiones_auth.php';rmaPermitir([1,2]);
require_once __DIR__.'/../../db.php';require_once __DIR__.'/consulta_service.php';
try {
 if(($_GET['action']??'')!=='listar_bandeja')throw new InvalidArgumentException('Acción no reconocida.');
 $buscar=rmaNormalizarBusqueda((string)($_GET['buscar']??''));
 $desde=rmaFechaFiltro((string)($_GET['desde']??''));$hasta=rmaFechaFiltro((string)($_GET['hasta']??''));
 if($desde&&$hasta&&$desde>$hasta)throw new InvalidArgumentException('La fecha inicial supera a la final.');
 $where=[];$args=[];
 if($buscar!=='') {
  $where[]="(c.numero_caso LIKE ? OR cl.nombre LIKE ? OR c.numero_serie LIKE ? OR c.equipo LIKE ? OR c.marca LIKE ? OR DATE_FORMAT(c.fecha_ingreso,'%d/%m/%Y') LIKE ? OR DATE_FORMAT(c.fecha_ingreso,'%Y-%m-%d') LIKE ?)";
  $args=array_fill(0,7,'%'.$buscar.'%');
 }
 if($desde){$where[]='c.fecha_ingreso>=?';$args[]=$desde.' 00:00:00';}
 if($hasta){$where[]='c.fecha_ingreso<DATE_ADD(?,INTERVAL 1 DAY)';$args[]=$hasta;}
 $sql='SELECT c.id,c.numero_caso,c.equipo,c.marca,c.numero_serie,c.fecha_ingreso,c.id_estado_actual,cl.nombre cliente_nombre,ec.nombre estado_nombre FROM casos c JOIN clientes cl ON cl.id=c.id_cliente JOIN estados_caso ec ON ec.id=c.id_estado_actual';
 if($where)$sql.=' WHERE '.implode(' AND ',$where);
 $s=$pdo->prepare($sql.' ORDER BY c.fecha_ingreso DESC,c.id DESC');$s->execute($args);$casos=$s->fetchAll(PDO::FETCH_ASSOC);
 foreach($casos as &$c)$c+=rmaVinculosCaso($c['numero_caso']);unset($c);
 echo json_encode(['status'=>'success','casos'=>$casos,'busqueda'=>$buscar],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){rmaEnviarError($e);}
