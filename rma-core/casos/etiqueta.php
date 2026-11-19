<?php
declare(strict_types=1);
require_once __DIR__.'/../../inventario/remisiones_auth.php';rmaPermitir([1,2]);session_write_close();
require_once __DIR__.'/consulta_service.php';require_once __DIR__.'/../../db.php';
try {
    $numero=rmaNormalizarBusqueda((string)($_GET['numero_caso']??''));
    $s=$pdo->prepare('SELECT numero_caso FROM casos WHERE numero_caso=?');$s->execute([$numero]);
    if(!$s->fetchColumn())throw new InvalidArgumentException('Caso inexistente.');
    require_once __DIR__.'/imagenes_service.php';
    $bytes=rmaImagenEtiqueta($numero,(string)($_GET['tipo']??'qr'));header('Content-Type: image/png');header('X-Content-Type-Options: nosniff');echo $bytes;
}catch(Throwable $e){rmaEnviarError($e);}
