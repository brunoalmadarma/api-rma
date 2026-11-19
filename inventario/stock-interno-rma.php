<?php
declare(strict_types=1);
require_once __DIR__ . '/remisiones_auth.php';
rmaPermitir([1, 2, 5]);
require_once __DIR__ . '/../db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Esta consulta requiere GET.']);
    exit;
}

try {
    $inv = rmaConexion('inventario');
    $rma = rmaConexion('rma');
    // Consultas separadas: ambas conexiones pueden estar en servidores distintos.
    $usuarios = $rma->query('SELECT id, nombre FROM usuarios')->fetchAll(PDO::FETCH_KEY_PAIR);
    $casos = [];
    foreach ($rma->query('SELECT c.id, c.numero_caso, c.id_tecnico, cl.nombre AS cliente_nombre
        FROM casos c LEFT JOIN clientes cl ON cl.id = c.id_cliente') as $caso) {
        $casos[(int) $caso['id']] = $caso;
    }

    $saldos = $inv->query("SELECT producto_id, id_caso,
        SUM(CASE WHEN tipo='CENTRAL_RMA' THEN cantidad ELSE 0 END) AS entregadas,
        SUM(CASE WHEN tipo='RMA_CENTRAL' THEN cantidad ELSE 0 END) AS devueltas,
        SUM(CASE WHEN tipo='BAJA_RMA' THEN cantidad ELSE 0 END) AS bajas,
        SUM(CASE WHEN tipo='CENTRAL_RMA' THEN cantidad ELSE -cantidad END) AS saldo,
        MAX(fecha) AS ultimo_movimiento
        FROM operaciones_rma
        WHERE tipo IN ('CENTRAL_RMA','RMA_CENTRAL','BAJA_RMA') AND id_caso IS NOT NULL
        GROUP BY producto_id, id_caso")->fetchAll();
    $asignadas = [];
    foreach ($saldos as $saldo) {
        $id = (int) $saldo['producto_id'];
        $asignadas[$id] = ($asignadas[$id] ?? 0) + (int) $saldo['saldo'];
    }

    $productos = [];
    $stock = [];
    foreach ($inv->query('SELECT id, codigo, nombre, stock_actual, stock_rma
        FROM productos ORDER BY nombre, codigo') as $p) {
        $id = (int) $p['id'];
        $productos[$id] = $p;
        $enRma = (int) $p['stock_rma'];
        $conCaso = $asignadas[$id] ?? 0;
        // stock_rma es el saldo físico vigente; no sumar otra vez los traslados.
        if ($enRma === 0 && $conCaso === 0) continue;
        $stock[] = [
            'id' => $id, 'codigo' => $p['codigo'], 'nombre' => $p['nombre'],
            'deposito_central' => (int) $p['stock_actual'] - $enRma,
            'deposito_rma' => $enRma, 'asignado_casos' => $conCaso,
            'sin_caso' => $enRma - $conCaso,
        ];
    }

    $asignaciones = [];
    foreach ($saldos as $saldo) {
        if ((int) $saldo['saldo'] === 0) continue;
        $p = $productos[(int) $saldo['producto_id']];
        $c = $casos[(int) $saldo['id_caso']] ?? [];
        $asignaciones[] = [
            'producto_id' => (int) $p['id'], 'codigo' => $p['codigo'], 'nombre' => $p['nombre'],
            'id_caso' => (int) $saldo['id_caso'], 'numero_caso' => $c['numero_caso'] ?? 'Caso no disponible',
            'cliente_nombre' => $c['cliente_nombre'] ?? '',
            'tecnico_nombre' => $usuarios[$c['id_tecnico'] ?? 0] ?? 'Sin técnico asignado',
            'entregadas' => (int) $saldo['entregadas'], 'devueltas' => (int) $saldo['devueltas'],
            'bajas' => (int) $saldo['bajas'], 'saldo' => (int) $saldo['saldo'],
            'ultimo_movimiento' => $saldo['ultimo_movimiento'],
        ];
    }

    $movimientos = [];
    foreach ($inv->query("SELECT id, producto_id, id_caso, numero_caso, tipo, cantidad,
        stock_anterior, stock_nuevo, rma_anterior, rma_nuevo, id_usuario, motivo, fecha
        FROM operaciones_rma WHERE tipo IN ('CENTRAL_RMA','RMA_CENTRAL','BAJA_RMA')
        ORDER BY fecha DESC, id DESC") as $m) {
        $p = $productos[(int) $m['producto_id']];
        $c = $casos[(int) $m['id_caso']] ?? [];
        $movimientos[] = [
            'registro' => 'C-' . $m['id'], 'fecha' => $m['fecha'], 'tipo' => $m['tipo'],
            'codigo' => $p['codigo'], 'nombre' => $p['nombre'], 'cantidad' => (int) $m['cantidad'],
            'central_antes' => (int) $m['stock_anterior'] - (int) $m['rma_anterior'],
            'central_despues' => (int) $m['stock_nuevo'] - (int) $m['rma_nuevo'],
            'rma_antes' => (int) $m['rma_anterior'], 'rma_despues' => (int) $m['rma_nuevo'],
            'numero_caso' => $c['numero_caso'] ?? $m['numero_caso'],
            'responsable' => $usuarios[$m['id_usuario']] ?? 'Usuario #' . $m['id_usuario'],
            'motivo' => $m['motivo'], 'historico' => false,
        ];
    }
    // Los movimientos anteriores no guardaban el saldo por ubicación ni siempre el caso.
    // Se muestran como histórico, sin inventar saldos ni duplicar las operaciones nuevas.
    foreach ($inv->query("SELECT id, producto_id, tipo_movimiento, cantidad, usuario_id,
        referencia, observacion, created_at FROM movimientos_inventario
        WHERE tipo_movimiento IN ('ENTREGA_TECNICO','INGRESO_RMA','SALIDA_RMA')
        AND LOCATE('[CENTRAL_RMA #', COALESCE(observacion,'')) <> 1
        AND LOCATE('[RMA_CENTRAL #', COALESCE(observacion,'')) <> 1
        AND LOCATE('[BAJA_RMA #', COALESCE(observacion,'')) <> 1") as $m) {
        $p = $productos[(int) $m['producto_id']];
        $movimientos[] = [
            'registro' => 'H-' . $m['id'], 'fecha' => $m['created_at'], 'tipo' => $m['tipo_movimiento'],
            'codigo' => $p['codigo'], 'nombre' => $p['nombre'], 'cantidad' => (int) $m['cantidad'],
            'central_antes' => null, 'central_despues' => null, 'rma_antes' => null, 'rma_despues' => null,
            'numero_caso' => $m['referencia'],
            'responsable' => $usuarios[$m['usuario_id']] ?? 'Usuario #' . $m['usuario_id'],
            'motivo' => $m['observacion'], 'historico' => true,
        ];
    }
    usort($movimientos, static fn(array $a, array $b): int => strcmp($b['fecha'], $a['fecha']));
    echo json_encode(['status' => 'success', 'stock' => $stock,
        'asignaciones' => $asignaciones, 'movimientos' => $movimientos], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    rmaEnviarError($e);
}
