<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate");

require_once __DIR__.'/../../inventario/remisiones_auth.php';
rmaPermitir([1,2]);
require_once __DIR__.'/../../db.php';
require_once __DIR__.'/evidencias_service.php';
require_once __DIR__.'/numeracion_service.php';

if (!isset($pdo)) {
    echo json_encode([
        "status" => "error",
        "message" => "Protocolo caído. No hay conexión con el núcleo de la base de datos."
    ]);
    exit;
}

require_once __DIR__.'/consulta_service.php';
$action = $_GET["action"] ?? "";
if($action==='aux_catalogos') {
 echo json_encode(['status'=>'success','equipos'=>$pdo->query('SELECT nombre FROM catalogo_equipos ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN),'marcas'=>$pdo->query('SELECT nombre FROM catalogo_marcas ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN),'predeterminado'=>['equipo'=>'Notebook','marca'=>'Lenovo']]);exit;
}
if ($action==='aux_tecnicos') {
    echo json_encode(['status'=>'success','tecnicos'=>$pdo->query('SELECT id,nombre FROM usuarios WHERE id_rol=1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === "aux_clientes") {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre, cedula FROM dosisma_rma_bd.clientes ORDER BY nombre ASC");
        $stmt->execute();

        echo json_encode([
            "status" => "success",
            "clientes" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

if ($action === "aux_tipos") {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre FROM dosisma_rma_bd.tipos_caso ORDER BY nombre ASC");
        $stmt->execute();

        echo json_encode([
            "status" => "success",
            "tipos" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

if ($action === "obtener_proximo_numero") {
    try {
        $proximo_numero = rmaProximoNumeroCaso($pdo);

        echo json_encode([
            "status" => "success",
            "proximo_numero" => $proximo_numero
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

if ($action === "guardar" && $_SERVER["REQUEST_METHOD"] === "POST") {
    rmaCsrfValidar();
    $nuevasFotos=[];
    try {
        $id_cliente = intval($_POST["id_cliente"] ?? 0);
        $id_tecnico = rmaRol()===1 ? rmaUsuarioId() : intval($_POST["id_tecnico"] ?? 0);

        // ✅ CORRECCIÓN CRÍTICA: Se lee 'id_tipo_caso' directamente del Payload
        $id_tipo_caso = intval($_POST["id_tipo_caso"] ?? $_POST["id_tipo_case"] ?? 0);

        $equipo = trim($_POST["equipo"] ?? "");
        $marca = trim($_POST["marca"] ?? "");
        $modelo = trim($_POST["modelo"] ?? "");
        $numero_serie = trim($_POST["numero_serie"] ?? "");
        $descripcion_problema = trim($_POST["descripcion_problema"] ?? "");

        $id_estado_inicial = 1; // 1: Ingresado / En Diagnóstico

        if ($id_cliente <= 0 || $id_tipo_caso <= 0 || $equipo === "" || $marca === "" || $numero_serie === "") {
            throw new Exception("Completá cliente, tipo de caso, equipo, marca y número de serie.");
        }
        $validarTecnico=$pdo->prepare('SELECT id FROM usuarios WHERE id=? AND id_rol=1');
        $validarTecnico->execute([$id_tecnico]);
        if(!$validarTecnico->fetchColumn())throw new InvalidArgumentException('Seleccioná un técnico responsable.');

        $nuevasFotos=rmaRecibirFotos($_FILES['foto_archivo']??null);
        $nombre_foto_bd=$nuevasFotos[0]??null;
        $pdo->beginTransaction();
        $fechaIngreso = rmaFechaNumeracion();
        $numero_caso_final = rmaReservarNumeroCaso($pdo, $fechaIngreso);

        $qr_placeholder = "QR_" . $numero_caso_final;

        $sql = "INSERT INTO dosisma_rma_bd.casos (
                    numero_caso, id_cliente, id_tecnico, id_tipo_caso, id_estado_actual,
                    equipo, marca, modelo, numero_serie, descripcion_problema,
                    foto_archivo, qr_archivo, fecha_ingreso
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $numero_caso_final,
            $id_cliente,
            $id_tecnico,
            $id_tipo_caso,
            $id_estado_inicial,
            $equipo,
            $marca,
            $modelo,
            $numero_serie,
            $descripcion_problema,
            $nombre_foto_bd,
            $qr_placeholder,
            $fechaIngreso->format('Y-m-d H:i:s')
        ]);

        $id_caso_insertado = $pdo->lastInsertId();
        rmaAnexarFotos($pdo,(int)$id_caso_insertado,$nuevasFotos);

        $sql_historial = "INSERT INTO dosisma_rma_bd.historial_estados (
                            id_caso, id_estado, fecha, observacion, id_usuario
                          ) VALUES (?, ?, ?, ?, ?)";
        $stmt_hist = $pdo->prepare($sql_historial);
        $stmt_hist->execute([
            $id_caso_insertado,
            $id_estado_inicial,
            $fechaIngreso->format('Y-m-d H:i:s'),
            "INGRESO INICIAL AUTOMÁTICO DESDE SISTEMA",
            $id_tecnico
        ]);

        $pdo->commit();

        $vinculos=rmaVinculosCaso($numero_caso_final);
        $qr_image_url=$vinculos['qr_url'];$url_visualizacion=$vinculos['link_secure'];

        echo json_encode([
            "status" => "success",
            "message" => "Caso registrado bajo la firma: $numero_caso_final.",
            "numero_caso" => $numero_caso_final,
            "id_caso" => (int)$id_caso_insertado,
            "qr_url" => $qr_image_url,
            "barcode_url" => $vinculos["barcode_url"],
            "link_pdf" => $url_visualizacion
        ]);

    } catch (Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        rmaRetirarFotos($nuevasFotos);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode([
    "status" => "error",
    "message" => "Acción o vector no reconocido por el sistema de control central."
]);
