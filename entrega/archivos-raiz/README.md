# MICROEXPRESS RMA — Paquete actualizado

## Correcciones del 4 de noviembre de 2026

Incluye catálogos de equipo y marca con valores iniciales, interfaz y botones en español, filtros por fecha/cliente/caso y lectura de códigos de barras o enlaces QR, etiquetas generadas localmente, visor del enlace al pasar el cursor, exportaciones CSV/PDF por filtrado o selección con agrupaciones y subtotales, remisiones de envío por proveedor, líneas de tiempo, estadísticas CRM, correo de clientes y menú adaptable a móviles.

La instalación actual incorpora 600 casos generados con información verosímil y 300 registros por catálogo operativo (clientes, proveedores, insumos, productos, consumos y remisiones). Son datos creados para este proyecto; no representan reparaciones históricas ni contactos reales. No se crearon cuentas de acceso ficticias. Los registros de demostración anteriores se archivaron con respaldo externo a la carpeta web.

Para instalar en otra copia, ejecutar `C:\xampp\php\php.exe herramientas\instalar_noviembre.php` después de las migraciones existentes. La carga opcional e idempotente está en `herramientas/precargar_operaciones.php`. Requiere tablas y técnicos existentes; conserva los datos ajenos a su carga.

La guía vigente está en [output/correcciones/GUIA_PRUEBAS_CLIENTE.md](output/correcciones/GUIA_PRUEBAS_CLIENTE.md). Las pruebas HTTP se preparan con `herramientas/probar_correcciones.php preparar`; luego ejecutar `verificar`, `herramientas/probar_noviembre.php` y finalmente `herramientas/probar_correcciones.php limpiar`.

Las conexiones usan `api-rma/config.local.php` (excluido de Git) o variables de entorno. El correo abre la aplicación de correo del usuario con destinatario y mensaje para revisión y envío. No requiere un servidor SMTP configurado.

Esta entrega conserva el proyecto original (`adm-rma`, `api-rma`, librerías, imágenes y módulos) e integra **notas de remisión por consumos de insumos**, tanto desde la estación del técnico como desde Asignaciones de Insumos.

## 1. Qué hace la nueva versión

- Cada vez que un técnico guarda un avance con un insumo seleccionado, se registra el consumo, se descuenta el stock y se crea una **nota de remisión pendiente**.
- También se crea la remisión cuando se aplica el insumo desde **Insumos → Aplicar insumos a casos RMA**.
- La remisión se guarda en **MySQL**, no en la sesión ni en el navegador; por eso cualquier usuario de **depósito (rol 5)** ve exactamente las mismas remisiones, aunque otro usuario cierre sesión o se conecte desde otra computadora.
- Depósito y administración (rol 2) tienen página **Remisiones** con listado, búsqueda, estados pendientes/cerradas, detalle, opción de impresión y cierre con responsable, fecha y observación.
- Los dashboards de depósito y administrador muestran las remisiones activas, y la barra de navegación muestra el contador.
- La pantalla consulta automáticamente novedades cada **20 segundos** (contador del menú cada 30 segundos).
- Si existe un trigger en `caso_insumos` que ya descuenta stock, el servicio NO vuelve a descontar. Si no descuenta, el servicio descuenta y escribe un movimiento en Kardex. Si descuenta cantidades inesperadas, revierte la operación.
- La creación de consumo, descuento y remisión es **atómica**: si hay un error en cualquiera de los pasos, se cancela todo con `ROLLBACK`.
- El técnico no puede suplantar el ID de otro técnico modificando el request; el servidor toma su identidad de la sesión.

## 2. Requisitos

- XAMPP para Windows, PHP 8.1+ con extensiones `pdo_mysql` habilitada, Apache y MySQL/MariaDB.
- **Las tablas y datos existentes de RMA:** `dosisma_rma_bd` (`usuarios`, `roles`, `casos`, `clientes`, `stock`, `caso_insumos`, `movimientos_stock`, `historial_estados`, `estados_caso`, etc.).
- Para los módulos antiguos de inventario, la segunda base que usa el código original: `dosisma_inventario_db` con su tabla `productos` y movimientos respectivos.
- Se debe usar un usuario MySQL con acceso de lectura/escritura a ambas bases.

**Importante:** El archivo `.rar` original no incluía un respaldo `.sql` de las bases existentes. Este paquete NO inventa ni sobrescribe los datos originales. Incluye únicamente la migración nueva de `notas_remision`. Si se instala en una computadora completamente nueva, también se necesitan respaldos de las bases originales.

## 3. Instalar en Windows (XAMPP)

1. Hacé una copia de seguridad de tu actual `C:\xampp\htdocs\rma-app` y exportá **ambas bases** desde phpMyAdmin antes de hacer cambios.
2. Extraé el ZIP de esta entrega dentro de `C:\xampp\htdocs\`, de modo que la ruta sea exactamente:

   `C:\xampp\htdocs\rma-app\adm-rma\index.php`

3. Copiá `api-rma\config.local.example.php` a `api-rma\config.local.php` y configurá ahí las credenciales **de tu entorno** para `rma` e `inventario`.

   - En XAMPP local típico el host es `127.0.0.1`, usuario `root` y contraseña vacía, si no se modificaron; verificá tus propios datos.
   - Las bases deben mantener los nombres `dosisma_rma_bd` y `dosisma_inventario_db`, ya que módulos heredados utilizan estos nombres explícitamente.
   - Nunca incluyas `config.local.php` con credenciales en un repositorio ni lo envíes a otra persona.

4. Iniciá Apache y MySQL desde el panel de XAMPP.
5. Ingresá a phpMyAdmin, seleccioná la base **`dosisma_rma_bd`**, elegí *Importar* y ejecutá `sql/01_notas_remision.sql`.

   Alternativa por terminal con PHP en el PATH:

   ```powershell
   cd C:\xampp\htdocs\rma-app
   C:\xampp\php\php.exe herramientas\instalar_remisiones.php
   ```

6. Comprobá las conexiones y las tablas:

   ```powershell
   C:\xampp\php\php.exe herramientas\verificar_instalacion.php
   ```

7. Entrá a **http://localhost/rma-app/adm-rma/**, iniciá sesión con un usuario existente y probá las operaciones de la sección siguiente.

## 4. Prueba funcional recomendada

1. Iniciá sesión como **técnico (rol 1)** y abrí *Estación de trabajo*.
2. Elegí una orden abierta con stock disponible; guardá un avance eligiendo un insumo.
3. Verificá que el sistema registre el avance y confirme una nota tipo `NR-000001`.
4. Iniciá sesión como **depósito (rol 5)**, preferentemente en otro navegador u otra computadora.
5. Abrí *Remisiones*: deberá figurar la nota en **PENDIENTE**, con caso, equipo, repuesto, cantidad y técnico.
6. Desde depósito pulsá *Ver nota* y *Cerrar remisión*. La nota debe pasar a **CERRADA** con fecha y usuario responsable.
7. Iniciá sesión como **administrador (rol 2)**: podrás ver remisiones pendientes y cerradas, y también cerrarlas.
8. Repetí el flujo usando *Insumos → Aplicar insumo a caso RMA*, para comprobar que ambos caminos crean remisiones.
9. Confirmá que el stock de un insumo se reduzca exactamente en la cantidad registrada, sin descuento duplicado.
10. Cerrá y volvé a iniciar sesión en depósito: las remisiones siguen disponibles (se guardan en MySQL).

## 5. Estructura principal de la modificación

```text
rma-app/
├── adm-rma/
│   ├── plantilla.php                        (router, roles y token CSRF)
│   ├── menu.php                             (acceso + contador de remisiones)
│   └── vistas/
│       ├── css/remisiones.css
│       ├── modulos/inventario/remisiones.php
│       ├── modulos/inventario/widgetRemisiones.php
│       └── js/inventario/{remisiones,widgetRemisiones,navRemisiones}.js
├── api-rma/
│   ├── db_config.php                        (conexiones configurable)
│   ├── config.local.example.php
│   ├── db.php / db_inventario.php
│   ├── inventario/remisiones.php            (listar, contar, detalle, cerrar)
│   ├── inventario/remisiones_auth.php       (roles y CSRF)
│   ├── inventario/remisiones_service.php    (registrar consumo y remisión)
│   ├── inventario/stock-asignaciones.php    (camino de consumo 1)
│   └── tecnico/perfil_tecnico.php           (camino de consumo 2)
├── herramientas/
│   ├── instalar_remisiones.php
│   └── verificar_instalacion.php
└── sql/01_notas_remision.sql
```

## 6. Consideraciones técnicas

- Una remisión corresponde a **un consumo de insumo**. Si se agregan tres insumos distintos, se crean tres notas independientes; esto facilita trazabilidad y verificación.
- Se respetan los datos anteriores de `caso_insumos`. Los registros antiguos **no se convierten retroactivamente** en remisiones pendientes, para evitar órdenes ficticias.
- Cerrar una remisión NO devuelve el insumo al stock: significa que depósito verificó el movimiento ya realizado.
- Los módulos de inventario del proyecto original operan también sobre una segunda base independiente. La gestión de remisiones utiliza la base RMA donde realmente se guarda `caso_insumos`; **no se sincronizan automáticamente las existencias entre esas dos bases**.
- La detección automática de triggers cubre casos en los que el trigger ajusta `stock.cantidad`. No se dispone del SQL del trigger original: comprobá mediante el verificador y una prueba en un ambiente seguro si también genera el Kardex.
- No se incluyeron contraseñas de las bases del RAR original en esta entrega. **Cambiá las contraseñas existentes**, ya que estaban en texto plano dentro del proyecto proporcionado.
- Se corrigieron algunos accesos de pantalla y nuevas operaciones sensibles, pero **no constituye una auditoría de seguridad integral de todos los endpoints heredados**.
- Algunas bibliotecas antiguas del proyecto cargan recursos externos por CDN, por lo que esos elementos visuales pueden requerir Internet. Bootstrap 3 local, ausente en el archivo recibido, se incluyó en esta entrega.
- La actualización del 28 de octubre pasó pruebas de sintaxis, navegación, exportaciones y operaciones contra las dos bases configuradas. La guía de aceptación detalla los escenarios verificados.
