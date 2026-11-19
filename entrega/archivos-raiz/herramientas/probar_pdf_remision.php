<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/../api-rma/inventario/remisiones_pdf.php';
$dir=__DIR__.'/../tmp/pdfs';
if (!is_dir($dir)) mkdir($dir,0777,true);
file_put_contents($dir.'/remision-prueba.pdf', rmaRemisionPdf([
    'id'=>999,'estado'=>'PENDIENTE','fecha_creacion'=>'2026-10-21 18:30:00',
    'numero_caso'=>'RMA-PRUEBA','equipo'=>'Equipo de prueba','cliente_nombre'=>'Prueba técnica',
    'creador_nombre'=>'Bruno Almada','insumo_nombre'=>'Memoria RAM DDR4 de 8 GB',
    'cantidad'=>2,'observacion'=>str_repeat('Revisión técnica: verificación de conexión, diagnóstico y sustitución del módulo. ',20),
    'observacion_cierre'=>'','fecha_cierre'=>null
]));
echo "PDF de prueba generado.\n";
$out=__DIR__.'/../output/pdf';
if(!is_dir($out)) mkdir($out,0777,true);
$ejemplo=[
    'id'=>'EJEMPLO','estado'=>'ACTIVA','fecha_creacion'=>'2026-10-21 18:30:00',
    'numero_caso'=>'RMA-EJEMPLO','equipo'=>'Notebook','marca'=>'ASUS','modelo'=>'Vivobook 15',
    'numero_serie'=>'SERIE-DE-EJEMPLO','cliente_nombre'=>'Cliente de ejemplo',
    'cliente_cedula'=>'1234567-0','cliente_celular'=>'0981 000 000',
    'creador_nombre'=>'Bruno Almada','insumo_nombre'=>'Memoria RAM DDR4 de 8 GB - SODIMM',
    'cantidad'=>2,'observacion'=>'Reemplazo del módulo de memoria. Verificar compatibilidad con el equipo.',
    'cierre_nombre'=>'Responsable de depósito','observacion_cierre'=>'Solicitud aceptada por depósito. Pendiente de entrega.'
];
file_put_contents($out.'/remision-ejemplo.pdf',rmaRemisionPdf($ejemplo));
$ejemplo['creador_nombre']=str_repeat('W',100);
$ejemplo['insumo_nombre']=str_repeat('á',255);
$ejemplo['observacion']=str_repeat('Diagnóstico y revisión técnica. ',60);
file_put_contents($dir.'/remision-textos-largos.pdf',rmaRemisionPdf($ejemplo));
echo "Muestra y prueba de textos largos generadas.\n";
