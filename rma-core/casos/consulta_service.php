<?php
declare(strict_types=1);
/** Texto, lectores HID y enlaces de consulta o comprobante. */
function rmaNormalizarBusqueda(string $valor): string
{
    $valor=trim(preg_replace('/[\x00-\x1F\x7F]/u','',$valor)??'');
    $valor=preg_replace('/^\][A-Za-z][0-9]/','',$valor)??$valor;
    if(preg_match('~^https?://~i',$valor)) {
        parse_str((string)parse_url($valor,PHP_URL_QUERY),$q);
        if(isset($q['token'])) {
            $decoded=base64_decode((string)$q['token'],true);
            $valor=$decoded!==false?explode('||',$decoded,2)[0]:$valor;
        } else $valor=(string)($q['busqueda']??$q['numero_caso']??$q['caso']??$valor);
    }
    return mb_substr(trim($valor),0,200);
}
function rmaVinculosCaso(string $numero): array
{
    $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
    $host=$_SERVER['HTTP_HOST']??'localhost';
    $token=base64_encode($numero.'||'.md5($numero.'MICRO_EXPRESS_SECURE_TOKEN_2026'));
    $url="$scheme://$host/rma-app/comprobante-caso.php?token=".urlencode($token);
    $image='/rma-app/api-rma/rma-core/casos/etiqueta.php?numero_caso='.rawurlencode($numero);
    return ['link_secure'=>$url,'qr_url'=>$image.'&tipo=qr','barcode_url'=>$image.'&tipo=barras'];
}
function rmaFechaFiltro(string $valor): string
{
    if($valor==='')return '';
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$valor);
    if(!$d||$d->format('Y-m-d')!==$valor)throw new InvalidArgumentException('La fecha debe tener formato AAAA-MM-DD.');
    return $valor;
}
