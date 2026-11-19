<?php
declare(strict_types=1);

function rmaFechaNumeracion(?DateTimeImmutable $fecha = null): DateTimeImmutable
{
    $zona = new DateTimeZone('America/Asuncion');
    return $fecha ? $fecha->setTimezone($zona) : new DateTimeImmutable('now', $zona);
}

function rmaFormatearNumeroCaso(int $anio, int $correlativo): string
{
    if ($anio < 1 || $anio > 9999 || $correlativo < 1) {
        throw new InvalidArgumentException('Año o correlativo de caso inválido.');
    }
    return 'RMA-' . str_pad((string) $anio, 4, '0', STR_PAD_LEFT) . '-'
        . str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT);
}

function rmaMayorCorrelativoRegistrado(PDO $pdo, int $anio): int
{
    $prefijo = 'RMA-' . str_pad((string) $anio, 4, '0', STR_PAD_LEFT) . '-';
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(numero_caso, 10) AS UNSIGNED)), 0)
        FROM casos WHERE numero_caso LIKE ? AND numero_caso REGEXP ?');
    $stmt->execute([$prefijo . '%', '^' . $prefijo . '[0-9]+$']);
    return (int) $stmt->fetchColumn();
}

/** Vista previa: no reserva ni consume un correlativo. El definitivo se asigna al guardar. */
function rmaProximoNumeroCaso(PDO $pdo, ?DateTimeImmutable $fecha = null): string
{
    $anio = (int) rmaFechaNumeracion($fecha)->format('Y');
    $stmt = $pdo->prepare('SELECT ultimo FROM correlativos_casos WHERE anio = ?');
    $stmt->execute([$anio]);
    $ultimo = max((int) $stmt->fetchColumn(), rmaMayorCorrelativoRegistrado($pdo, $anio));
    if ($ultimo === PHP_INT_MAX) throw new OverflowException('Se agotó el correlativo de este año.');
    return rmaFormatearNumeroCaso($anio, $ultimo + 1);
}

/** Reserva y alta del caso deben confirmar o revertir en la misma transacción. */
function rmaReservarNumeroCaso(PDO $pdo, ?DateTimeImmutable $fecha = null): string
{
    if (!$pdo->inTransaction()) throw new LogicException('La numeración requiere una transacción.');
    $anio = (int) rmaFechaNumeracion($fecha)->format('Y');
    // El INSERT también serializa la primera reserva cuando aún no existe el año.
    $stmt = $pdo->prepare('INSERT INTO correlativos_casos (anio, ultimo) VALUES (?, 0)
        ON DUPLICATE KEY UPDATE anio = VALUES(anio)');
    $stmt->execute([$anio]);
    $stmt = $pdo->prepare('SELECT ultimo FROM correlativos_casos WHERE anio = ? FOR UPDATE');
    $stmt->execute([$anio]);
    $ultimo = max((int) $stmt->fetchColumn(), rmaMayorCorrelativoRegistrado($pdo, $anio));
    if ($ultimo === PHP_INT_MAX) throw new OverflowException('Se agotó el correlativo de este año.');
    $siguiente = $ultimo + 1;
    $stmt = $pdo->prepare('UPDATE correlativos_casos SET ultimo = ? WHERE anio = ?');
    $stmt->execute([$siguiente, $anio]);
    return rmaFormatearNumeroCaso($anio, $siguiente);
}
