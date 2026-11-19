# Entrega de noviembre de 2026

Actualizar ambos repositorios (`adm-rma` y `api-rma`). Copiar el contenido de `archivos-raiz/` a la carpeta raíz `rma-app/`, al mismo nivel que esos dos directorios. Incluye migraciones, herramientas, comprobante y las bibliotecas locales de códigos, con sus licencias.

Conservar `api-rma/config.local.php`. Para una instalación nueva copiar `config.local.example.php` y ajustar las conexiones. También se admiten las variables `RMA_DB_*` y `RMA_INV_DB_*`.

Ejecutar `php herramientas/instalar_noviembre.php` después de las migraciones anteriores. La carga opcional `php herramientas/precargar_operaciones.php` crea 600 casos y 300 registros por catálogo operativo sin duplicarlos en una segunda ejecución.

Ver `archivos-raiz/output/correcciones/ENTREGA_NOVIEMBRE.md` para alcance, procedencia de datos y pruebas, y `GUIA_PRUEBAS_CLIENTE.md` para aceptación. Las credenciales, respaldos, sesiones y adjuntos de usuarios no forman parte de esta entrega.
