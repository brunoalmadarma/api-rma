<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este verificador se ejecuta solo por consola.');
}
require_once __DIR__ . '/../api-rma/db_config.php';
foreach (['rma', 'inventario'] as $tipo) {
    try {
        $pdo = rmaConexion($tipo);
        echo strtoupper($tipo) . ": conexión OK\n";
        $tablas = $tipo === 'rma' ? ['usuarios', 'casos', 'stock', 'caso_insumos', 'movimientos_stock', 'notas_remision', 'historial_estados', 'estados_caso'] : ['productos'];
        foreach ($tablas as $tabla) {
            $check = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($tabla))->fetchColumn();
            echo '  ' . $tabla . ': ' . ($check ? 'OK' : 'FALTA') . "\n";
        }
        if ($tipo === 'rma') {
            $stmt = $pdo->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'caso_insumos'");
            $triggers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo '  Triggers caso_insumos: ' . ($triggers ? implode(', ', $triggers) : 'sin trigger; se aplicará descuento por código') . "\n";
            $stmt = $pdo->query('SELECT id, nombre FROM roles ORDER BY id');
            echo '  Perfiles disponibles: ';
            echo implode(', ', array_map(fn($r) => $r['id'] . '=' . $r['nombre'], $stmt->fetchAll(PDO::FETCH_ASSOC))) . "\n";
        }
    } catch (Throwable $e) {
        echo strtoupper($tipo) . ': ERROR: ' . $e->getMessage() . "\n";
    }
}
