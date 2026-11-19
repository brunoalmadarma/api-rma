<?php
// Instalador de UNA nueva tabla; no elimina ni reescribe datos de tablas existentes.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ejecutar solamente desde la consola de PHP.');
}
require_once __DIR__ . '/../api-rma/db.php';
$archivos = [__DIR__ . '/../sql/01_notas_remision.sql', __DIR__ . '/../sql/02_remisiones_estados.sql'];
$sql = '';
foreach ($archivos as $archivo) {
    if (is_file($archivo)) $sql .= "\n" . file_get_contents($archivo);
}
$sql = trim($sql);
if ($sql === '') {
    fwrite(STDERR, "No se encontraron migraciones de remisiones en sql/.\n");
    exit(1);
}
$sql = preg_replace('/^USE\s+[A-Za-z0-9_]+\s*;\s*/mi', '', $sql);
try {
    $pdo->exec($sql);
    $stmt = $pdo->query("SHOW TABLES LIKE 'notas_remision'");
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('La tabla no fue creada.');
    }
    echo "Tabla notas_remision lista.\n";
    echo "Solicitudes sin consumo de stock y estados de remisión habilitados.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error al crear la tabla: " . $e->getMessage() . "\n");
    exit(1);
}
