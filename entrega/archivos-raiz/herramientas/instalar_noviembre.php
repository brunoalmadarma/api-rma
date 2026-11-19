<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../api-rma/db_config.php';
rmaConexion('rma')->exec(file_get_contents(__DIR__.'/../sql/06_correcciones_noviembre.sql'));
echo "Catálogos de equipos, marcas y correo de clientes instalados.\n";
