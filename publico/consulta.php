<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

require_once __DIR__ . '/../db.php';

if (!isset($pdo)) {
    echo json_encode(["status" => "error", "message" => "Conexión a la base de datos no disponible."]);
    exit();
}

require_once __DIR__.'/../rma-core/casos/consulta_service.php';
$busqueda = rmaNormalizarBusqueda((string)($_GET['busqueda'] ?? ''));

if ($busqueda === '') {
    echo json_encode(["status" => "error", "message" => "Debe ingresar un término de búsqueda."]);
    exit();
}

try {
    $term = trim($busqueda);

    $condiciones = ['c.numero_caso = :term', 'c.numero_serie = :serie', 'cli.cedula = :documento'];
    $parametros = [':term' => $term, ':serie' => $term, ':documento' => $term];
    // La C.I. se compara como texto para conservar los ceros iniciales.
    $documento = preg_replace('/[.\s\-\/]/u', '', $term);
    if ($documento !== '' && ctype_digit($documento)) {
        $condiciones[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cli.cedula, '.', ''), '-', ''), ' ', ''), '/', ''), CHAR(9), '') = :ci_normalizada";
        $parametros[':ci_normalizada'] = $documento;
    }
    $partesNombre = [];
    foreach (preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) as $i => $palabra) {
        $clave = ':nombre_' . $i;
        $partesNombre[] = "cli.nombre LIKE $clave ESCAPE '!'";
        $parametros[$clave] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $palabra) . '%';
    }
    if ($partesNombre) $condiciones[] = '(' . implode(' AND ', $partesNombre) . ')';

    // Puede haber varios equipos de un cliente; devolverlos para elegir el RMA.
    $sql = "SELECT
                c.id,
                c.numero_caso,
                c.equipo,
                c.marca,
                c.modelo,
                c.numero_serie,
                c.descripcion_problema,
                c.diagnostico_final,
                DATE_FORMAT(c.fecha_ingreso, '%Y-%m-%d %H:%i') AS fecha_ingreso,
                DATE_FORMAT(c.fecha_cierre, '%Y-%m-%d %H:%i') AS fecha_cierre,
                e.nombre AS estado_actual,
                t.nombre AS tipo_caso,
                cli.nombre AS cliente_nombre,
                cli.cedula AS cliente_cedula
            FROM casos c
            INNER JOIN clientes cli ON c.id_cliente = cli.id
            LEFT JOIN estados_caso e ON c.id_estado_actual = e.id
            LEFT JOIN tipos_caso t ON c.id_tipo_caso = t.id
            WHERE " . implode(' OR ', $condiciones) . "
            ORDER BY c.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $coincidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$coincidencias) {
        echo json_encode(["status" => "error", "message" => "No se encontró ningún registro para: " . htmlspecialchars($term)]);
        exit();
    }

    if (count($coincidencias) > 1) {
        $campos = array_flip(['numero_caso', 'cliente_nombre', 'equipo', 'marca', 'modelo', 'fecha_ingreso', 'estado_actual']);
        $casos = array_map(static fn(array $c): array => array_intersect_key($c, $campos), $coincidencias);
        echo json_encode(['status' => 'success', 'casos' => $casos, 'total' => count($casos)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $caso = $coincidencias[0];

    // 🔑 Generar Token Seguro para la impresión oficial
    $secret_salt = "MICRO_EXPRESS_SECURE_TOKEN_2026";
    $hash = md5($caso['numero_caso'] . $secret_salt);
    $rawToken = $caso['numero_caso'] . "||" . $hash;
    $tokenImpresion = base64_encode($rawToken);

    $caso['token_impresion'] = $tokenImpresion;

    // Obtener historial del caso
    $sqlHistorial = "SELECT
                        h.id,
                        DATE_FORMAT(h.fecha, '%Y-%m-%d %H:%i') AS fecha,
                        h.observacion,
                        e.nombre AS estado
                     FROM historial_estados h
                     INNER JOIN estados_caso e ON h.id_estado = e.id
                     WHERE h.id_caso = :id_caso
                     ORDER BY h.id DESC";

    $stmtHist = $pdo->prepare($sqlHistorial);
    $stmtHist->execute([':id_caso' => $caso['id']]);
    $historial = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "caso" => $caso,
        "historial" => $historial
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error interno: " . $e->getMessage()]);
}
exit();
