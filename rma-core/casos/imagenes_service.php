<?php
declare(strict_types=1);
require_once __DIR__.'/consulta_service.php';
function rmaImagenEtiqueta(string $numero,string $tipo): string
{
    if($tipo==='qr') {
        require_once __DIR__.'/../../../phpqrcode/qrlib.php';
        ob_start();
        try{QRcode::png(rmaVinculosCaso($numero)['link_secure'],false,QR_ECLEVEL_M,6,4);return (string)ob_get_contents();}
        finally{ob_end_clean();}
    }
    spl_autoload_register(static function(string $class):void {
        $prefix='Picqer\\Barcode\\';
        if(str_starts_with($class,$prefix))require_once __DIR__.'/../../../barcode/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    });
    $g=new Picqer\Barcode\BarcodeGeneratorPNG();
    return $g->getBarcode($numero,$g::TYPE_CODE_128,3,65);
}
