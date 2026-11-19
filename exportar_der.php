<?php
require_once __DIR__ . '/db_config.php';

$tipos = ['rma', 'inventario'];
$salida = "-- DUMP DE ESTRUCTURA PARA DER - " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tipos as $tipo) {
    try {
        $cfg = rmaConfig($tipo);
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $salida .= "-- ==========================================\n";
        $salida .= "-- BASE DE DATOS: {$cfg['database']} ($tipo)\n";
        $salida .= "-- ==========================================\n\n";

        $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tablas as $tabla) {
            $stmt = $pdo->query("SHOW CREATE TABLE `$tabla`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $createSql = $row['Create Table'] ?? array_values($row)[1];
            $salida .= $createSql . ";\n\n";
        }

        echo "[OK] Extraída estructura de: {$cfg['database']} (" . count($tablas) . " tablas)\n";
    } catch (Throwable $e) {
        echo "[ERROR] En '$tipo': " . $e->getMessage() . "\n";
    }
}

$archivoDestino = __DIR__ . '/estructura_actual_der.sql';
file_put_contents($archivoDestino, $salida);
echo "\nListo. Archivo guardado en: $archivoDestino\n";