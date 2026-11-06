# Reparto del código para estudiar entre 3

Todo el código del sistema repartido entre **Erick**, **Alexander** y **Luana** para que cada uno lo entienda y lo pueda explicar.
Cada archivo tiene un solo dueño, y cada parte trae su orden de lectura, las preguntas que hay que saber contestar y una prueba para hacer en la PC.

| Persona | Tema | Archivos | Líneas aprox. |
|---|---|---|---|
| **Erick** | Núcleo y seguridad: entrada, login, roles, sedes, base de datos, instalación, pruebas | 94 | 13 100 |
| **Alexander** | Productos, inventario, compras y la app Android | 88 | 13 500 |
| **Luana** | Ventas, caja, clientes, promociones y reportes | 80 | 12 300 |

No se cuentan las imágenes, las capturas de las pruebas E2E ni `backend/database/instalar_laragon.sql`, que se arma solo con
`php backend/scripts/generar_instalador.php` a partir de los demás `.sql`. `frontend/public/js/app.js` se reparte por bloques (ver cada parte).

## Día 1 · Lo que estudian los 3 juntos

Antes de su parte, los tres leen el recorrido de un pedido, porque todo el sistema funciona igual:

1. El navegador pide `/?ruta=venta_nueva`. Entra siempre por `frontend/public/index.php` (la app Android entra por `frontend/public/api/index.php`).
2. `index.php` pone las cabeceras de seguridad, revisa la sesión (sin login lleva a `?ruta=login`) y en un POST exige el token CSRF.
3. Según `ruta`, llama a un método del controlador (`backend/controllers/`). El controlador revisa el permiso del rol y lee los datos del formulario.
4. Para **leer**, el controlador pide los datos a un *Repository* (`backend/models/*Repository.php`), que devuelve objetos sin HTML.
5. Para **escribir**, llama a un *Servicio* (`backend/models/servicios/`). Todo servicio hereda de `Servicio.php` y trabaja dentro de `enTransaccion()`: o se guarda todo, o no se guarda nada.
6. El controlador carga la vista (`frontend/views/...`), que solo dibuja con los datos que recibió y escapa todo con `e()`.
7. El dinero va siempre en céntimos (`Dinero.php`) y las cantidades en milésimas (`Cantidad.php`): nunca se suman decimales.

Archivos para leer juntos (solo la idea, el detalle es del dueño): `frontend/public/index.php`, `backend/controllers/Controller.php`,
`backend/models/servicios/Servicio.php`, `backend/models/Usuario.php`, `backend/database/schema.sql`, `docs/mvc.md` y `docs/diseno_bd.md`.

Para levantarlo: `docs/guia_laragon.md` (Laragon) o `docs/equipo/docker.md` (Docker). Usuarios de prueba y claves: `docs/instalacion.md`.

## Calendario sugerido

| Día | Qué se hace |
|---|---|
| 1 | Los 3 juntos: recorrido de un pedido y levantar el sistema en cada PC |
| 2 y 3 | Cada uno lee su parte en el orden indicado y anota dudas en `docs/bitacora_ia.md` |
| 4 | Cada uno hace la prueba práctica de su parte y contesta sus preguntas sin mirar el código |
| 5 | Exposición cruzada: cada uno explica su parte a los otros dos en 15 minutos, y los otros le hacen 3 preguntas |

## Erick · Núcleo y seguridad

**Por qué es tuyo:** todo pedido pasa por tu código antes de llegar al de los otros dos. Si alguien pregunta "¿cómo sabe el sistema quién eres y qué puedes hacer?", contestas tú.

**Orden de lectura**

1. `frontend/public/index.php` completo: cabeceras, `?ruta=salud`, sesión, CSRF y la tabla de rutas.
2. `backend/config/` (`Database`, `Entorno`, `Sesion`, `Aplicacion`) y `backend/controllers/Controller.php`.
3. Login: `AuthController.php`, `Clave.php`, `IntentoLoginRepository.php`, vistas `auth/`.
4. Roles con herencia: `Usuario.php` y sus hijos `Administrador`, `Cajero`, `Almacenero` (y la práctica `poo_roles.php`).
5. Sedes y personal: `TiendaController`, `TiendaServicio`, `EmpleadoController`, `EmpleadoServicio`, migración `021_cajero_en_su_sede.sql`.
6. Auditoría: `AuditoriaController`, `AuditoriaRepository`, `auditoria.sql`.
7. Base de datos e instalación: `schema.sql`, `datos.sql`, `Migraciones.php`, `scripts/migrar.php`, `generar_instalador.php`, respaldos.
8. API de la app: `frontend/public/api/index.php`, `ApiController`, `AuthApiController`, `TokenApiRepository`.
9. Diseño: `layout_inicio.php`, `tokens.css`, `estilos.css`.
10. Pruebas: cómo están armadas `unitarios.php`, `seguridad.php`, `web.php` y la carpeta `tests/e2e/`.

**De `app.js` te toca:** líneas 1 a 470 (sucursal, dinero, paneles, menú, buscador, avisos, conexión con el servidor),
1275 a 1524 (validación, confirmación, envío, teclado, pestañas) y 1787 al final (cierre de sesión por inactividad).

**Preguntas que debes poder contestar**

- ¿Qué es el token CSRF, dónde se crea y qué pasa si un POST llega sin él?
- ¿Cómo se guarda la clave en la base? ¿Por qué no se puede volver a leer? ¿Qué claves rechaza `Clave.php`?
- ¿Cuántos intentos fallidos bloquean el login y por cuánto tiempo? (pista: `IntentoLoginRepository`: 5 por DNI, 20 por IP, 15 minutos)
- ¿Qué cambia entre `Administrador`, `Cajero` y `Almacenero`? ¿Por qué `Usuario` es una clase abstracta?
- Si un cajero pone `&tienda=2` en la URL, ¿qué responde el sistema y qué impide la base aunque el PHP fallara? (migración 021)
- ¿Qué hace `migrar.php` y cómo sabe qué migraciones ya se aplicaron?
- ¿A los cuántos minutos sin uso se cierra la sesión y cómo avisa la pantalla antes?

**Prueba práctica:** entra como cajero, cambia a mano `&tienda=` en la URL de Ventas y mira el 403. Después equivócate 5 veces de clave con un DNI y mira el bloqueo.
Por último corre `php backend/tests/seguridad.php` y explica qué revisa cada línea que sale.

<details>
<summary>Lista completa de archivos de Erick</summary>

**Configuración**

- `backend/config/Aplicacion.php` (71)
- `backend/config/Database.php` (40)
- `backend/config/Entorno.php` (46)
- `backend/config/Migraciones.php` (238)
- `backend/config/Sesion.php` (152)

**Controladores**

- `backend/controllers/AuditoriaController.php` (35)
- `backend/controllers/AuthController.php` (129)
- `backend/controllers/Controller.php` (197)
- `backend/controllers/EmpleadoController.php` (135)
- `backend/controllers/TiendaController.php` (201)
- `backend/controllers/api/ApiController.php` (110)
- `backend/controllers/api/AuthApiController.php` (107)

**Base de datos**

- `backend/database/api_movil.sql` (49)
- `backend/database/auditoria.sql` (28)
- `backend/database/datos.sql` (335)
- `backend/database/login.sql` (25)
- `backend/database/migracion_aplicada.sql` (16)
- `backend/database/migraciones/013_clave_y_sesiones.sql` (33)
- `backend/database/migraciones/015_tildes_en_datos.sql` (79)
- `backend/database/migraciones/021_cajero_en_su_sede.sql` (58)
- `backend/database/schema.sql` (402)
- `backend/database/seguridad.sql` (24)

**Modelos y repositorios**

- `backend/models/Administrador.php` (72)
- `backend/models/Almacenero.php` (28)
- `backend/models/AuditoriaRepository.php` (58)
- `backend/models/Cajero.php` (37)
- `backend/models/Clave.php` (60)
- `backend/models/EmpleadoRepository.php` (94)
- `backend/models/IntentoLoginRepository.php` (50)
- `backend/models/Paginacion.php` (41)
- `backend/models/ReglaNegocioException.php` (10)
- `backend/models/Tienda.php` (39)
- `backend/models/TiendaRepository.php` (87)
- `backend/models/TokenApiRepository.php` (66)
- `backend/models/Usuario.php` (185)

**Servicios (escriben en la base, con transacción)**

- `backend/models/servicios/EmpleadoServicio.php` (212)
- `backend/models/servicios/OperacionMovilServicio.php` (88)
- `backend/models/servicios/Servicio.php` (286)
- `backend/models/servicios/TiendaServicio.php` (226)

**Scripts (instalar, migrar, respaldar)**

- `backend/scripts/generar_instalador.php` (29)
- `backend/scripts/migrar.php` (75)
- `backend/scripts/mysql_local.ps1` (65)
- `backend/scripts/mysql_local.sh` (65)
- `backend/scripts/programar_respaldo.ps1` (26)
- `backend/scripts/programar_respaldo.sh` (22)
- `backend/scripts/quitar_datos_demo.php` (98)
- `backend/scripts/respaldo.ps1` (117)
- `backend/scripts/respaldo.sh` (122)
- `backend/scripts/restaurar.ps1` (100)
- `backend/scripts/restaurar.sh` (113)
- `backend/scripts/verificar_instalacion.php` (154)

**Pruebas**

- `backend/tests/api.php` (548)
- `backend/tests/integracion.php` (1052)
- `backend/tests/restauracion.sh` (108)
- `backend/tests/seguridad.php` (279)
- `backend/tests/unitarios.php` (496)
- `backend/tests/web.php` (737)
- `tests/e2e/01-login.spec.ts` (34)
- `tests/e2e/06-celular.spec.ts` (131)
- `tests/e2e/comun.ts` (45)
- `tests/e2e/correr_en_docker.sh` (39)
- `tests/e2e/preparar.php` (25)
- `tests/e2e/preparar.ts` (6)

**Docker y servidor**

- `deploy/apache-mass.conf` (44)
- `deploy/nginx-mass.conf` (57)
- `docker/mysql/98_migraciones.sh` (15)
- `docker/mysql/99_version.sh` (5)
- `docker/mysql/revisar_version.sh` (16)
- `docker/web/Dockerfile` (24)

**Puntos de entrada y prácticas POO**

- `frontend/public/api/index.php` (195)
- `frontend/public/index.php` (448)
- `frontend/public/poo_roles.php` (42)

**Estilos y JavaScript**

- `frontend/public/css/estilos.css` (1578)
- `frontend/public/css/tokens.css` (131)

**Pantallas (vistas)**

- `frontend/views/auditoria/lista.php` (93)
- `frontend/views/auth/login.php` (44)
- `frontend/views/auth/mi_clave.php` (32)
- `frontend/views/ayuda/ayuda.php` (76)
- `frontend/views/empleados/formulario.php` (69)
- `frontend/views/empleados/lista.php` (73)
- `frontend/views/errores/403.php` (14)
- `frontend/views/errores/404.php` (14)
- `frontend/views/errores/500.php` (15)
- `frontend/views/layout_fin.php` (27)
- `frontend/views/layout_inicio.php` (226)
- `frontend/views/parciales/funciones.php` (67)
- `frontend/views/parciales/guia.php` (18)
- `frontend/views/parciales/paginacion.php` (19)
- `frontend/views/parciales/selector_tienda.php` (25)
- `frontend/views/parciales/vacio.php` (18)
- `frontend/views/tiendas/formulario.php` (106)
- `frontend/views/tiendas/lista.php` (68)
- `frontend/views/tiendas/nueva.php` (62)


</details>

## Alexander · Productos, inventario, compras y la app Android

**Por qué es tuyo:** todo lo que mueve stock pasa por tu código: crear productos, recibir mercadería, trasladar, mermas, conteo y el celular con escáner.

**Orden de lectura**

1. Modelo base: `Producto.php`, `Stock.php`, `Cantidad.php`, `Categoria`, `Marca`, `UnidadMedida` (y las prácticas `poo_producto.php` y `poo_repository.php`).
2. Catálogo: `ProductoController.php`, `ProductoRepository.php`, `ProductoServicio.php`, vistas `productos/`.
3. Recién agregados y fotos: `productos/nuevos.php`, migraciones `018_producto_origen.sql` y `022_producto_revision.sql`, `FotoProducto.php`.
4. Subir el catálogo por CSV: `CatalogoCsv.php`, `CatalogoRepository.php`, `productos/importar.php`, `catalogo.sql`.
5. Movimientos de stock: `InventarioController.php`, `InventarioServicio.php`, `InventarioRepository.php`, `MovimientoStock.php`, `TipoMovimiento.php`, vistas `inventario/`.
6. Compras: `CompraController`, `CompraServicio`, `CompraRepository`, `OrdenCompra`, `Proveedor`, migración `019`, vistas `compras/`.
7. Reponer y vencimientos: `Reposicion.php`, `Vencimiento.php`, `panel/reponer.php`.
8. API del celular para productos e ingresos: `ProductoApiController`, `IngresoApiController`, `ConsultaMovilRepository`.
9. App Android: `MainActivity.kt`, `data/` (cómo habla con la API), `dominio/`, y las pantallas de inicio, login, escáner, consulta, entrada e inventario.

**Preguntas que debes poder contestar**

- ¿Por qué la cantidad se guarda en milésimas? ¿Cómo se vende 0.750 kg de arroz?
- ¿Qué es el kárdex y qué fila se escribe en `movimiento_stock` en un ingreso, un traslado y una merma?
- ¿Qué hace `FOR UPDATE` en `Servicio.php` y qué pasaría sin él si dos personas mueven el mismo stock a la vez?
- ¿Qué pasa si el escáner lee un código que no existe? ¿Dónde aparece ese producto después y quién lo revisa?
- ¿Cómo valida el CSV antes de guardar y qué pasa si una fila está mal?
- ¿Cómo se entera la app Android de quién es el usuario (token) y qué pasa si se corta el internet?

**Prueba práctica:** como almacenero registra un ingreso de 10 unidades y un traslado de 3 a otra tienda; luego abre el kárdex de las dos tiendas y explica cada fila.
Abre el proyecto `android-app` en Android Studio, corre las pruebas (`CodigoLeidoTest`, `SugerenciaTest`) y explica qué revisan.

**Ojo:** `backend/models/facturacion/` es de Luana aunque tenga "Proveedor" en el nombre (`ProveedorFacturacion` es el proveedor de boletas electrónicas, no de mercadería).

<details>
<summary>Lista completa de archivos de Alexander</summary>

**App Android**

- `android-app/app/src/main/java/pe/mass/minimarket/MainActivity.kt` (234)
- `android-app/app/src/main/java/pe/mass/minimarket/MassApp.kt` (20)
- `android-app/app/src/main/java/pe/mass/minimarket/data/Ajustes.kt` (36)
- `android-app/app/src/main/java/pe/mass/minimarket/data/Configuracion.kt` (92)
- `android-app/app/src/main/java/pe/mass/minimarket/data/Preferencias.kt` (65)
- `android-app/app/src/main/java/pe/mass/minimarket/data/Repositorio.kt` (240)
- `android-app/app/src/main/java/pe/mass/minimarket/data/api/Dtos.kt` (321)
- `android-app/app/src/main/java/pe/mass/minimarket/data/api/MassApi.kt` (89)
- `android-app/app/src/main/java/pe/mass/minimarket/dominio/CodigoLeido.kt` (58)
- `android-app/app/src/main/java/pe/mass/minimarket/dominio/Dinero.kt` (74)
- `android-app/app/src/main/java/pe/mass/minimarket/dominio/Sugerencia.kt` (44)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/comun/BarraInferior.kt` (127)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/comun/Componentes.kt` (558)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/comun/DialogoProductoNuevo.kt` (231)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/entrada/PantallaEntrada.kt` (502)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/escaner/Escaner.kt` (552)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/escaner/PantallaConsulta.kt` (283)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/inicio/PantallaInicio.kt` (603)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/inventario/PantallaInventario.kt` (367)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/login/PantallaLogin.kt` (244)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/tema/Tema.kt` (191)

**App Android · pruebas**

- `android-app/app/src/test/java/pe/mass/minimarket/CodigoLeidoTest.kt` (50)
- `android-app/app/src/test/java/pe/mass/minimarket/SugerenciaTest.kt` (39)

**Controladores**

- `backend/controllers/CompraController.php` (266)
- `backend/controllers/InventarioController.php` (398)
- `backend/controllers/ProductoController.php` (602)
- `backend/controllers/api/IngresoApiController.php` (84)
- `backend/controllers/api/ProductoApiController.php` (121)

**Base de datos**

- `backend/database/ajustes.sql` (42)
- `backend/database/catalogo.sql` (925)
- `backend/database/ingresos.sql` (69)
- `backend/database/migraciones/018_producto_origen.sql` (30)
- `backend/database/migraciones/019_proveedores_ordenes_compra.sql` (93)
- `backend/database/migraciones/022_producto_revision.sql` (26)
- `backend/database/operaciones.sql` (134)

**Modelos y repositorios**

- `backend/models/AjusteInventario.php` (31)
- `backend/models/Cantidad.php` (42)
- `backend/models/CatalogoCsv.php` (60)
- `backend/models/CatalogoRepository.php` (80)
- `backend/models/Categoria.php` (14)
- `backend/models/CompraRepository.php` (232)
- `backend/models/ConsultaMovilRepository.php` (396)
- `backend/models/DetalleIngreso.php` (37)
- `backend/models/DetalleOrdenCompra.php` (41)
- `backend/models/FotoProducto.php` (143)
- `backend/models/IngresoMercaderia.php` (55)
- `backend/models/IngresoRepository.php` (104)
- `backend/models/InventarioRepository.php` (166)
- `backend/models/Marca.php` (20)
- `backend/models/Merma.php` (29)
- `backend/models/MovimientoStock.php` (64)
- `backend/models/OrdenCompra.php` (91)
- `backend/models/Producto.php` (79)
- `backend/models/ProductoRepository.php` (219)
- `backend/models/Proveedor.php` (50)
- `backend/models/Reposicion.php` (36)
- `backend/models/Stock.php` (41)
- `backend/models/TipoMovimiento.php` (31)
- `backend/models/TrasladoMercaderia.php` (29)
- `backend/models/UnidadMedida.php` (16)
- `backend/models/Vencimiento.php` (77)

**Servicios (escriben en la base, con transacción)**

- `backend/models/servicios/CompraServicio.php` (230)
- `backend/models/servicios/InventarioServicio.php` (401)
- `backend/models/servicios/ProductoServicio.php` (501)

**Puntos de entrada y prácticas POO**

- `frontend/public/poo_producto.php` (37)
- `frontend/public/poo_repository.php` (37)

**Pantallas (vistas)**

- `frontend/views/compras/imprimir.php` (104)
- `frontend/views/compras/ordenes.php` (237)
- `frontend/views/compras/proveedores.php` (142)
- `frontend/views/inventario/ajustes.php` (116)
- `frontend/views/inventario/ingresos.php` (218)
- `frontend/views/inventario/kardex.php` (125)
- `frontend/views/inventario/mermas.php` (105)
- `frontend/views/inventario/traslados.php` (108)
- `frontend/views/inventario/vencimientos.php` (84)
- `frontend/views/panel/reponer.php` (100)
- `frontend/views/parciales/buscador.php` (15)
- `frontend/views/parciales/foto_producto.php` (18)
- `frontend/views/parciales/lineas_producto.php` (67)
- `frontend/views/productos/catalogo.php` (54)
- `frontend/views/productos/detalle.php` (21)
- `frontend/views/productos/ficha.php` (100)
- `frontend/views/productos/formulario.php` (80)
- `frontend/views/productos/importar.php` (85)
- `frontend/views/productos/lista.php` (206)
- `frontend/views/productos/nuevos.php` (268)
- `frontend/views/productos/stock.php` (58)

**Pruebas**

- `tests/e2e/04-movimientos.spec.ts` (33)


</details>

## Luana · Ventas, caja, clientes, promociones y reportes

**Por qué es tuyo:** todo lo que mueve dinero pasa por tu código: la venta, el pago, la boleta, la caja del cajero y los reportes del administrador.

**Orden de lectura**

1. Dinero e impuestos: `Dinero.php`, `DesgloseIgv.php` (y la práctica `poo_ventas.php`).
2. La venta: `VentaController.php` → `VentaServicio::registrar()` → `VentaRepository.php`; modelos `Venta`, `DetalleVenta`, `SerieComprobante`.
3. Los pagos con herencia: `pagos/PagoVenta.php` y sus hijos `PagoEfectivo`, `PagoTarjeta`, `PagoSip`.
4. Comprobante y ticket: `Emisor.php`, `AnchoTicket.php`, vistas `ventas/comprobante.php` y `ventas/ticket.php`, `js/ticket.js`.
5. Anular y devolver: `VentaServicio::anular()` y `devolver()`, `AnulacionVenta`, `DevolucionVenta`.
6. Caja: `CajaController.php`, `CajaServicio.php` (abrir, cerrar, recontar, pausar, revisar), `CajaTurno`, `MovimientoCaja`, vistas `caja/` y los parciales de conteo y cuadre.
7. Clientes: `ClienteController`, `ClienteServicio`, `ClienteRepository`, vistas `clientes/`.
8. Promociones y descuentos: `Promocion.php`, `Descuentos.php`, `PromocionServicio`, migración `020`, vista `promociones/lista.php`.
9. Panel y reportes: `PanelController`, `PanelRepository`, `ReporteController`, `ReporteRepository`, `exportar/DocumentoPdf.php` y `LibroExcel.php`.
10. Facturación electrónica: `facturacion/` y `docs/facturacion_electronica.md`.
11. Venta desde el celular: `VentaApiController.php` y en Android `PantallaVenta.kt`, `VentaViewModel.kt`, `Carrito.kt`, `PantallaHistorial.kt`.

**De `app.js` te toca:** líneas 471 a 1274 (pantalla de venta: total, pagos y avisos en vivo) y 1525 a 1786 (caja: movimientos, conteo de billetes, apertura, cierre automático y nueva promoción).

**Preguntas que debes poder contestar**

- ¿Cómo se calcula el IGV de una boleta y por qué se trabaja en céntimos?
- ¿Qué pasa en la base si la venta falla a la mitad (por ejemplo, no hay stock del último producto)?
- ¿Cómo se paga una venta con dos medios (efectivo y tarjeta) y cómo se calcula el vuelto?
- ¿Qué es el arqueo a ciegas, cuándo lo usa una tienda y por qué en ese caso el cajero no ve el monto esperado al cerrar?
- ¿Qué diferencia hay entre anular y devolver una venta? ¿Quién puede hacer cada una?
- ¿Cómo se elige qué promoción se aplica si hay dos para el mismo producto?
- ¿Cómo se arma el PDF del reporte sin una librería externa?

**Prueba práctica:** como cajero abre la caja, haz una venta pagada con efectivo y tarjeta, imprime el ticket y cierra la caja contando billetes.
Luego como administrador revisa el cierre y descarga el reporte de ventas en PDF y en Excel. Explica de qué tabla sale cada número.

<details>
<summary>Lista completa de archivos de Luana</summary>

**App Android**

- `android-app/app/src/main/java/pe/mass/minimarket/dominio/Atajos.kt` (29)
- `android-app/app/src/main/java/pe/mass/minimarket/dominio/Carrito.kt` (71)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/historial/PantallaHistorial.kt` (358)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/venta/PantallaVenta.kt` (680)
- `android-app/app/src/main/java/pe/mass/minimarket/ui/venta/VentaViewModel.kt` (277)

**App Android · pruebas**

- `android-app/app/src/test/java/pe/mass/minimarket/AtajosTest.kt` (55)
- `android-app/app/src/test/java/pe/mass/minimarket/CarritoTest.kt` (115)

**Controladores**

- `backend/controllers/CajaController.php` (324)
- `backend/controllers/ClienteController.php` (106)
- `backend/controllers/PanelController.php` (185)
- `backend/controllers/PromocionController.php` (92)
- `backend/controllers/ReporteController.php` (346)
- `backend/controllers/VentaController.php` (435)
- `backend/controllers/api/VentaApiController.php` (240)

**Base de datos**

- `backend/database/boleta.sql` (124)
- `backend/database/caja_apertura.sql` (146)
- `backend/database/consultas.sql` (317)
- `backend/database/migraciones/014_movimiento_caja_categoria.sql` (18)
- `backend/database/migraciones/020_descuentos_promociones.sql` (83)

**Modelos y repositorios**

- `backend/models/AnchoTicket.php` (43)
- `backend/models/AnulacionVenta.php` (22)
- `backend/models/Caja.php` (43)
- `backend/models/CajaRepository.php` (231)
- `backend/models/CajaTurno.php` (94)
- `backend/models/Cliente.php` (47)
- `backend/models/ClienteRepository.php` (67)
- `backend/models/Descuentos.php` (176)
- `backend/models/DesgloseIgv.php` (52)
- `backend/models/DetalleVenta.php` (51)
- `backend/models/DevolucionVenta.php` (30)
- `backend/models/Dinero.php` (32)
- `backend/models/Emisor.php` (61)
- `backend/models/MovimientoCaja.php` (67)
- `backend/models/PanelRepository.php` (149)
- `backend/models/Promocion.php` (159)
- `backend/models/PromocionRepository.php` (111)
- `backend/models/ReporteRepository.php` (254)
- `backend/models/SerieComprobante.php` (53)
- `backend/models/Venta.php` (84)
- `backend/models/VentaRepository.php` (329)
- `backend/models/exportar/DocumentoPdf.php` (267)
- `backend/models/exportar/LibroExcel.php` (189)
- `backend/models/facturacion/ComprobanteParaEnviar.php` (35)
- `backend/models/facturacion/FacturacionElectronica.php` (44)
- `backend/models/facturacion/NubefactProveedor.php` (151)
- `backend/models/facturacion/ProveedorFacturacion.php` (22)
- `backend/models/facturacion/RespuestaFacturacion.php` (18)
- `backend/models/facturacion/SinFacturacionElectronica.php` (35)
- `backend/models/pagos/PagoEfectivo.php` (34)
- `backend/models/pagos/PagoSip.php` (33)
- `backend/models/pagos/PagoTarjeta.php` (40)
- `backend/models/pagos/PagoVenta.php` (42)

**Servicios (escriben en la base, con transacción)**

- `backend/models/servicios/CajaServicio.php` (440)
- `backend/models/servicios/ClienteServicio.php` (50)
- `backend/models/servicios/PromocionServicio.php` (155)
- `backend/models/servicios/VentaServicio.php` (586)

**Estilos y JavaScript**

- `frontend/public/js/ticket.js` (61)

**Puntos de entrada y prácticas POO**

- `frontend/public/poo_ventas.php` (57)

**Pantallas (vistas)**

- `frontend/views/caja/cierre.php` (129)
- `frontend/views/caja/panel.php` (734)
- `frontend/views/clientes/detalle.php` (20)
- `frontend/views/clientes/ficha.php` (72)
- `frontend/views/clientes/lista.php` (73)
- `frontend/views/panel/inicio.php` (194)
- `frontend/views/parciales/conteo_efectivo.php` (57)
- `frontend/views/parciales/cuadre_caja.php` (64)
- `frontend/views/parciales/drawer_abrir.php` (30)
- `frontend/views/parciales/drawer_cerrar.php` (8)
- `frontend/views/promociones/lista.php` (173)
- `frontend/views/reportes/utilidad.php` (113)
- `frontend/views/reportes/ventas.php` (221)
- `frontend/views/ventas/comprobante.php` (139)
- `frontend/views/ventas/detalle_venta.php` (94)
- `frontend/views/ventas/lista.php` (104)
- `frontend/views/ventas/nueva.php` (282)
- `frontend/views/ventas/ticket.php` (154)

**Pruebas**

- `tests/e2e/02-apertura-caja.spec.ts` (26)
- `tests/e2e/03-venta.spec.ts` (58)
- `tests/e2e/05-arqueo-cierre.spec.ts` (37)


</details>

## Pruebas grandes que tocan a los 3

`backend/tests/integracion.php`, `web.php` y `api.php` las cuida Erick, pero cada bloque prueba la parte de alguien:
cada uno busca en esos archivos las líneas `revisar('...')` de su tema y debe poder decir qué comprueban.
Para correrlas hace falta la base levantada (ver `docs/equipo/docker.md`).

## Si alguien no puede

Si falta una persona, su parte se divide así: la de Erick pasa a Alexander (núcleo) y Luana (pruebas);
la de Alexander pasa a Erick (app Android) y Luana (inventario); la de Luana pasa a Alexander (caja) y Erick (reportes).
