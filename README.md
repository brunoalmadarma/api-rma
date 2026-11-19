# API RMA

API PHP/MySQL para casos, clientes, inventario, remisiones y estadísticas. El proyecto se instala en `rma-app/api-rma`, junto a `adm-rma`.

Las conexiones se definen en `config.local.php` (excluido de Git; usar `config.local.example.php` como referencia) o mediante `RMA_DB_HOST`, `RMA_DB_PORT`, `RMA_DB_NAME`, `RMA_DB_USER`, `RMA_DB_PASSWORD` y sus equivalentes `RMA_INV_DB_*` para inventario.

La entrega completa de noviembre y las instrucciones de instalación están en [entrega/README.md](entrega/README.md). Incluye etiquetas locales, filtrado por fecha/cliente/caso y QR, correo de clientes, reportes con agrupaciones, remisiones de envío y CRM calculado desde la base.
