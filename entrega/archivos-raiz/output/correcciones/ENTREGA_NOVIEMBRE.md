# Entrega del 4 de noviembre de 2026

## Funciones entregadas

- Nuevo caso con catálogos precargados de equipo y marca. Notebook y Lenovo quedan seleccionados inicialmente y pueden cambiarse antes de guardar.
- Botones y mensajes de las pantallas operativas en español. Estilo compartido, vista previa de PDF y navegación adaptable a móvil con menú desplegable.
- Búsqueda de casos por cliente, número RMA, serie, equipo o fecha. Fechas admitidas: DD/MM/AAAA y AAAA-MM-DD; filtros Desde/Hasta inclusivos.
- Lectores que actúan como teclado: admite número de caso, prefijos HID y URL del QR, con Enter o búsqueda automática. Peticiones anteriores no reemplazan resultados más recientes.
- QR y Code 128 generados localmente, también en comprobantes y etiquetas. El cursor muestra el enlace del comprobante.
- Exportaciones de las tablas por todos los registros filtrados o filas seleccionadas. Agrupación por columna, subtotal de registros por grupo, suma de cantidades y total general en CSV/PDF. La exportación conserva los filtros usados y evita fórmulas ejecutables en CSV.
- Remisión de envío por uno o varios casos del flujo externo, agrupados por proveedor, con serie, equipo, referencia, fecha y firmas. Exige que el despacho esté registrado y conserva su responsable autenticado.
- Líneas de tiempo persistentes con enlaces desde cada caso y accesos a ejemplos.
- CRM con clientes registrados, cobertura de correo, recurrencia, clientes con pendientes y gráficos mensuales. Los indicadores se calculan desde la base; se retiraron métricas fijas.
- Correo electrónico en alta y edición de clientes, con validación y persistencia. Mensajes editables con enlaces a WhatsApp y a la aplicación de correo para revisar y enviar. Los contactos incompletos no habilitan envíos.
- Identidad institucional en los usuarios personalizados y eliminación del nombre personal en muestras del proyecto. El acceso administrativo pasó a `administracion.rma`, con la contraseña existente. Las atribuciones de autores de bibliotecas de terceros se conservan.
- Conexiones centralizadas mediante configuración local o variables de entorno. Las credenciales locales se excluyen del repositorio.

## Datos cargados

Se crearon 600 casos, 300 clientes, 300 proveedores, 300 insumos, 300 productos centrales, 300 consumos, 300 remisiones y 300 asignaciones de inventario. Los casos tienen marcas, modelos, series, fallas, fechas y evolución por etapas. Se conservaron los registros originales ajenos a la demostración anterior.

Son registros generados con información verosímil, según la aclaración del pedido; no son reparaciones históricas verificadas. Los documentos de cliente usan identificadores internos y los correos el dominio reservado `example.invalid`. No se inventaron destinatarios reales ni cuentas con acceso al sistema.

La carga es idempotente: volver a ejecutarla conserva los registros y existencias ya creados. Los 100 casos, 100 insumos y 100 productos de demostración anteriores se retiraron después de guardar un respaldo completo de las dos bases fuera de la carpeta web. La carga nueva no agrega marcadores de demostración a las pantallas.

Los catálogos de roles, estados y tipos conservan sus valores funcionales; no se agregaron cientos de estados ni cuentas artificiales.

## Instalación y validación

1. Actualizar ambos repositorios y copiar el contenido de `api-rma/entrega/archivos-raiz/` en la raíz de `rma-app`, conservando `api-rma/config.local.php`.
2. Ejecutar las migraciones existentes si faltan, y luego `php herramientas/instalar_noviembre.php`.
3. Para reproducir la carga en otra instalación con los mismos esquemas: `php herramientas/precargar_operaciones.php`.
4. Recargar el navegador con Ctrl+F5.
5. Pruebas con datos y sesiones temporales: `php herramientas/probar_correcciones.php preparar`, `php herramientas/probar_correcciones.php verificar`, `php herramientas/probar_noviembre.php`, `php herramientas/probar_correcciones.php limpiar`.

Se verificaron sintaxis PHP/JavaScript, permisos, CSRF, persistencia de correo, filtros y lecturas por HTTP, PDF de envío, códigos decodificados por software, exportaciones reales desde navegador, CRM, líneas de tiempo y pantallas a 1440 y 390 píxeles. Se renderizaron los PDF para comprobar su presentación. Las sesiones y datos temporales de las pruebas se retiraron al finalizar.

La validación con una pistola física queda para la aceptación en el puesto: no había un lector conectado para efectuar esa prueba. Se comprobó el contenido de ambos códigos y la entrada que entrega un lector de teclado.
