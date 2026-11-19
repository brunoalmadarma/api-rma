# Guía de aceptación — 4 de noviembre de 2026

Abrir http://localhost/rma-app/adm-rma/ y recargar con Ctrl+F5. El administrador institucional usa `administracion.rma` y conserva su contraseña.

1. **Nuevo caso:** comprobar Notebook y Lenovo seleccionados; cambiar ambos, completar cliente, técnico, falla y serie, guardar y revisar fotos, etiqueta y comprobante.
2. **Búsqueda:** buscar `RMA-2026-710008`, nombre de cliente, serie y fecha de ingreso. Usar Desde/Hasta para un mismo día. Limpiar debe restaurar el listado.
3. **Códigos:** colocar el cursor sobre el QR o las barras y comprobar el enlace. Copiar la URL del QR al buscador y comprobar que aparece el mismo caso. Con la pistola física enfocada en el buscador, escanear barras y QR y confirmar con Enter.
4. **Exportaciones:** filtrar casos, seleccionar dos filas, elegir Sólo registros seleccionados, agrupar por cliente y exportar CSV/PDF. Comprobar las dos filas, los subtotales y el total general. Repetir con todos los filtrados.
5. **Flujo externo:** registrar proveedor, referencia y fecha de envío. Abrir y descargar la remisión; revisar equipo, serie y firmas. Seleccionar varios envíos y generar el documento agrupado por proveedor.
6. **Seguimiento:** usar Línea de tiempo desde la bandeja. Revisar etapas y fechas en `RMA-2026-710008` y los ejemplos del historial.
7. **CRM:** abrir Estadísticas y Estadísticas de clientes. Comparar los totales con los listados. Comprobar gráficos mensuales y cobertura de correos.
8. **Mensajes:** agregar o editar el correo de un cliente. Elegir uno de sus casos, editar el mensaje y usar Preparar correo. Debe abrir la aplicación de correo con destinatario, asunto y texto, para revisión y envío manual. Revisar también el enlace a WhatsApp con un teléfono válido.
9. **Móvil:** reducir el ancho a 390 píxeles. Abrir Menú, desplegar categorías y navegar. Los formularios deben apilarse; las tablas permiten desplazamiento horizontal dentro de su panel.
10. **Inventario y fotos:** comprobar consumos y descuentos una sola vez, remisiones, devoluciones, bajas y persistencia de varias fotografías. Para modificaciones de prueba usar las fixtures temporales de las herramientas, no registros de trabajo.

La instalación tiene 600 casos creados con datos verosímiles, además de los originales conservados. La procedencia y las cantidades están en [ENTREGA_NOVIEMBRE.md](ENTREGA_NOVIEMBRE.md). No se enviaron mensajes ni correos durante las pruebas.

Pruebas automatizadas: `php herramientas/probar_correcciones.php preparar`, `php herramientas/probar_correcciones.php verificar`, `php herramientas/probar_noviembre.php` y `php herramientas/probar_correcciones.php limpiar`.
