<?php
declare(strict_types=1);
require_once __DIR__.'/db_config.php';
try {
    $pdo_inv = rmaConexion('inventario');
} catch (Throwable $e) {
    error_log('RMA database connection: '.$e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'error','message'=>'No se pudo conectar con la base de datos. Revisá la configuración local.']);
    exit;
}
