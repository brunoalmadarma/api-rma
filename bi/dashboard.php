<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

require_once __DIR__.'/../inventario/remisiones_auth.php';rmaPermitir([1,2]);
require_once __DIR__.'/../db.php';

if (!isset($pdo)) {
    echo json_encode([
        "status" => "error",
        "message" => "No fue posible conectar con la base de datos."
    ]);
    exit;
}

$action = $_GET["action"] ?? "";

if ($action === "cargar_metricas") {
    try {
        // 1. KPIs Generales
        $stmtTotal = $pdo->prepare("SELECT COUNT(*) AS total FROM dosisma_rma_bd.casos");
        $stmtTotal->execute();
        $totalCasos = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        $stmtPend = $pdo->prepare("SELECT COUNT(*) AS total FROM dosisma_rma_bd.casos WHERE fecha_cierre IS NULL");
        $stmtPend->execute();
        $enProceso = $stmtPend->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        $stmtCerradosMes = $pdo->prepare("SELECT COUNT(*) AS total FROM dosisma_rma_bd.casos WHERE fecha_cierre IS NOT NULL AND MONTH(fecha_cierre) = MONTH(CURRENT_DATE()) AND YEAR(fecha_cierre) = YEAR(CURRENT_DATE())");
        $stmtCerradosMes->execute();
        $resueltosMes = $stmtCerradosMes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // SLA: Promedio Días de Estancia
        $stmtSLA = $pdo->prepare("SELECT AVG(DATEDIFF(IFNULL(fecha_cierre, NOW()), fecha_ingreso)) AS promedio_dias FROM dosisma_rma_bd.casos");
        $stmtSLA->execute();
        $diasPromedio = $stmtSLA->fetch(PDO::FETCH_ASSOC)['promedio_dias'] ?? 0;

        $tasaEfic = $totalCasos > 0 ? round((($totalCasos - $enProceso) / $totalCasos) * 100, 1) : 100;

        // 2. Tipos de Caso (Mini Donut)
        $sqlTipos = "SELECT t.nombre, COUNT(c.id) AS total 
                     FROM dosisma_rma_bd.tipos_caso t
                     LEFT JOIN dosisma_rma_bd.casos c ON t.id = c.id_tipo_caso
                     GROUP BY t.id, t.nombre";
        $stmtTipos = $pdo->prepare($sqlTipos);
        $stmtTipos->execute();
        $tiposData = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

        $totalTiposSum = array_sum(array_column($tiposData, 'total'));
        foreach ($tiposData as &$td) {
            $td['pct'] = $totalTiposSum > 0 ? round(($td['total'] / $totalTiposSum) * 100) : 0;
        }

        // 3. Top Fallas por Modelo
        $sqlTopFallas = "SELECT CONCAT(marca, ' ', modelo) AS modelo, COUNT(*) AS total 
                         FROM dosisma_rma_bd.casos 
                         GROUP BY marca, modelo 
                         ORDER BY total DESC 
                         LIMIT 5";
        $stmtTopFallas = $pdo->prepare($sqlTopFallas);
        $stmtTopFallas->execute();
        $topFallas = $stmtTopFallas->fetchAll(PDO::FETCH_ASSOC);

        // 4. Últimos Casos
        $sqlUltimos = "SELECT c.numero_caso, cl.nombre AS cliente_nombre, e.nombre AS estado_nombre,
                              DATEDIFF(NOW(), c.fecha_ingreso) AS dias_estancia
                       FROM dosisma_rma_bd.casos c
                       LEFT JOIN dosisma_rma_bd.clientes cl ON c.id_cliente = cl.id
                       LEFT JOIN dosisma_rma_bd.estados_caso e ON c.id_estado_actual = e.id
                       ORDER BY c.id DESC LIMIT 5";
        $stmtUltimos = $pdo->prepare($sqlUltimos);
        $stmtUltimos->execute();
        $ultimosCasos = $stmtUltimos->fetchAll(PDO::FETCH_ASSOC);

        // 5. Alertas del Sistema (Stock Mínimo + Casos Retrasados)
        $alertas = [];

        // Stock Crítico
        $stmtAlertStock = $pdo->prepare("SELECT nombre, cantidad FROM dosisma_rma_bd.stock WHERE cantidad <= stock_minimo LIMIT 3");
        $stmtAlertStock->execute();
        while ($st = $stmtAlertStock->fetch(PDO::FETCH_ASSOC)) {
            $alertas[] = [
                "tipo_border" => "b-left-red",
                "tipo_bullet" => "dot-red",
                "mensaje" => "Stock crítico: <span class='text-dark-bold'>" . htmlspecialchars($st['nombre']) . "</span> — " . $st['cantidad'] . " unidades restantes."
            ];
        }

        // RMA estancado
        $stmtAlertSLA = $pdo->prepare("SELECT numero_caso, DATEDIFF(NOW(), fecha_ingreso) AS dias FROM dosisma_rma_bd.casos WHERE fecha_cierre IS NULL AND DATEDIFF(NOW(), fecha_ingreso) > 7 LIMIT 2");
        $stmtAlertSLA->execute();
        while ($cas = $stmtAlertSLA->fetch(PDO::FETCH_ASSOC)) {
            $alertas[] = [
                "tipo_border" => "b-left-yellow",
                "tipo_bullet" => "dot-yellow",
                "mensaje" => "<span class='t-cyan'>" . $cas['numero_caso'] . "</span> supera los <span class='text-yellow font-weight-bold'>" . $cas['dias'] . " días</span> en taller sin resolución."
            ];
        }

        // 6. Fallas por Marca (Gráfico Bar)
        $sqlMarcas = "SELECT marca, COUNT(*) AS total_rma FROM dosisma_rma_bd.casos GROUP BY marca ORDER BY total_rma DESC LIMIT 5";
        $stmtMarcas = $pdo->prepare($sqlMarcas);
        $stmtMarcas->execute();
        $marcasData = $stmtMarcas->fetchAll(PDO::FETCH_ASSOC);

        // 7. Costo total de Insumos gastados
        $stmtCostoIns = $pdo->prepare("SELECT SUM(costo_aplicado * cantidad) AS total_costo FROM dosisma_rma_bd.caso_insumos");
        $stmtCostoIns->execute();
        $costoInsumos = $stmtCostoIns->fetch(PDO::FETCH_ASSOC)['total_costo'] ?? 0;

        // Marca con mayor incidencia
        $marcaMayor = count($marcasData) > 0 ? $marcasData[0]['marca'] : 'N/A';

        // 8. Consola Live Auditoría (Historial reciente)
        $sqlLogs = "SELECT h.fecha, c.numero_caso, cl.nombre AS cliente, e.nombre AS estado
                    FROM dosisma_rma_bd.historial_estados h
                    INNER JOIN dosisma_rma_bd.casos c ON h.id_caso = c.id
                    LEFT JOIN dosisma_rma_bd.clientes cl ON c.id_cliente = cl.id
                    INNER JOIN dosisma_rma_bd.estados_caso e ON h.id_estado = e.id
                    ORDER BY h.id DESC LIMIT 4";
        $stmtLogs = $pdo->prepare($sqlLogs);
        $stmtLogs->execute();
        $rawLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        $consolaLive = [];
        foreach ($rawLogs as $l) {
            $hora = date("H:i", strtotime($l['fecha']));
            $consolaLive[] = [
                "hora" => $hora,
                "accion" => "CAMBIO_ESTADO",
                "color_accion" => "green-accent",
                "numero_caso" => $l['numero_caso'],
                "cliente" => $l['cliente'],
                "detalle" => "Pasa a estado: " . $l['estado']
            ];
        }

        $clientesCrm=$pdo->query("SELECT COUNT(*) total_clientes,SUM(email IS NOT NULL AND email<>'') con_correo FROM clientes")->fetch(PDO::FETCH_ASSOC);
        $clientesCrm['recurrentes']=(int)$pdo->query('SELECT COUNT(*) FROM (SELECT id_cliente FROM casos GROUP BY id_cliente HAVING COUNT(*)>1) x')->fetchColumn();
        $clientesCrm['sin_resolver']=(int)$pdo->query('SELECT COUNT(DISTINCT id_cliente) FROM casos WHERE fecha_cierre IS NULL')->fetchColumn();
        $mensual=$pdo->query("SELECT DATE_FORMAT(fecha_ingreso,'%Y-%m') mes,COUNT(*) ingresos,SUM(fecha_cierre IS NOT NULL) resueltos FROM casos WHERE fecha_ingreso>=DATE_SUB(CURRENT_DATE(),INTERVAL 12 MONTH) GROUP BY mes ORDER BY mes")->fetchAll(PDO::FETCH_ASSOC);
        $actual=(int)$pdo->query("SELECT COUNT(*) FROM casos WHERE fecha_ingreso>=DATE_FORMAT(CURRENT_DATE(),'%Y-%m-01')")->fetchColumn();
        $anterior=(int)$pdo->query("SELECT COUNT(*) FROM casos WHERE fecha_ingreso>=DATE_FORMAT(DATE_SUB(CURRENT_DATE(),INTERVAL 1 MONTH),'%Y-%m-01') AND fecha_ingreso<DATE_FORMAT(CURRENT_DATE(),'%Y-%m-01')")->fetchColumn();
        $tendencia=$anterior?round(($actual-$anterior)*100/$anterior,1):0;
        $sla=(float)$pdo->query('SELECT COALESCE(100*AVG(DATEDIFF(fecha_cierre,fecha_ingreso)<=7),0) FROM casos WHERE fecha_cierre IS NOT NULL')->fetchColumn();
        $conDiagnostico=$totalCasos?(float)$pdo->query("SELECT 100*SUM(diagnostico_final IS NOT NULL AND diagnostico_final<>'')/COUNT(*) FROM casos")->fetchColumn():0;
        $conTecnico=$totalCasos?(float)$pdo->query('SELECT 100*SUM(id_tecnico IS NOT NULL)/COUNT(*) FROM casos')->fetchColumn():0;
        echo json_encode([
            "status" => "success",
            "crm"=>$clientesCrm,"mensual"=>$mensual,
            "kpis" => [
                "total_casos" => $totalCasos,
                "tendencia_pct" => $tendencia,
                "en_proceso" => $enProceso,
                "resueltos_mes" => $resueltosMes,
                "tasa_eficiencia" => $tasaEfic,
                "dias_promedio" => $diasPromedio
            ],
            "tipos_caso" => $tiposData,
            "radar_perf" => [$tasaEfic,$sla,round($conDiagnostico,1),round($conTecnico,1),$clientesCrm["total_clientes"]?round(100*$clientesCrm["con_correo"]/$clientesCrm["total_clientes"],1):0],
            "top_fallas" => $topFallas,
            "ultimos_casos" => $ultimosCasos,
            "alertas" => $alertas,
            "fallas_marcas" => $marcasData,
            "estadisticas" => [
                "total_casos" => $totalCasos,
                "cerrados" => ($totalCasos - $enProceso),
                "pct_cerrados" => $tasaEfic,
                "marca_mayor" => $marcaMayor,
                "tasa_efectividad" => $tasaEfic,
                "costo_insumos" => $costoInsumos
            ],
            "consola_live" => $consolaLive
        ]);

    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
            "message" => "Error al procesar métricas BI: " . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode([
    "status" => "error",
    "message" => "Acción no válida."
]);