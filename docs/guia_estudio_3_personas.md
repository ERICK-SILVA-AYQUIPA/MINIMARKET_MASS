# Guía de estudio en 3 personas

El proyecto se reparte en **tres tajadas verticales**: cada persona estudia un tema completo, desde la tabla de MySQL hasta la pantalla de la web y la del celular. Así cada uno puede explicar "su" parte de punta a punta, y entre los tres cubren todo `main`.

| | Tema | Pregunta que debe poder responder |
|---|---|---|
| **Persona A** | Cimientos: entrada, seguridad, personal, tiendas y base de datos | ¿Cómo llega un pedido al sistema, quién lo hace y por qué se le deja pasar? |
| **Persona B** | Vender: caja, ventas, cobro, comprobantes y reportes | ¿Qué pasa desde que el cajero abre su caja hasta que la cierra cuadrada? |
| **Persona C** | Mercadería: productos, inventario, compras y escáner | ¿Cómo entra, se mueve y se controla cada producto en las 3 tiendas? |

Cada bloque tiene unas 11 000 líneas (PHP, vistas, SQL, pruebas y Kotlin). La lista de qué hace cada archivo está en [`explicacion_archivos.md`](explicacion_archivos.md): úsala al lado de esta guía.

> Cambia "Persona A, B y C" por los nombres reales cuando estén decididos.

---

## Día 1: el tronco común (los tres juntos)

Antes de separarse, los tres leen esto juntos. Sin esta base nadie entiende su bloque.

1. **Levantar el proyecto** con [`instalacion.md`](instalacion.md) o [`equipo/docker.md`](equipo/docker.md). Entrar con cada rol (administrador, cajero, almacenero) y dar una vuelta por las pantallas.
2. **El patrón MVC**: [`mvc.md`](mvc.md) y el "Recorrido de un pedido" al inicio de [`explicacion_archivos.md`](explicacion_archivos.md).
3. **Las 5 piezas que usan todos** (cada una la estudia a fondo una persona, pero todos deben saber para qué sirve):

| Pieza | Para qué sirve | La explica a fondo |
|---|---|---|
| `frontend/public/index.php` | Único punto de entrada: sesión, CSRF, `match ($ruta)` y controlador | A |
| `backend/controllers/Controller.php` | Clase padre de los controladores: `vista()`, `redirigir()`, `prohibido()`, leer la tienda | A |
| `backend/models/servicios/Servicio.php` | Clase padre de los servicios: `enTransaccion()`, `moverStock()`, `auditar()` | A (`moverStock` lo explica C) |
| `backend/models/Dinero.php` | Los montos van en **céntimos enteros**, nunca en `float` | B |
| `backend/models/Cantidad.php` | Las cantidades van en **milésimas enteras** (productos al peso) | C |

4. **Las capas, en una frase cada una**: el *controlador* lee y valida lo que llega y revisa el permiso; el *servicio* vuelve a revisar el permiso y escribe en la base dentro de una transacción; el *Repository* solo lee; la *vista* solo dibuja y escapa todo con `e()`.

5. **Correr las pruebas** que no necesitan base, para ver que todo está bien:

```bash
php backend/tests/unitarios.php     # 193 correctas
php backend/tests/seguridad.php     # 19 correctas
```

---

## Persona A · Cimientos: entrada, seguridad, personal, tiendas y base de datos

### Orden de lectura

| Paso | Qué leer | Archivos |
|---|---|---|
| 1 | El diseño de la base | `backend/database/schema.sql`, `datos.sql`, [`diseno_bd.md`](diseno_bd.md) (niveles 1 a 7 y el diagrama ER) |
| 2 | Cómo crece la base sin borrarla | `backend/config/Migraciones.php`, `backend/database/migraciones/` (013 claves y sesiones, 015 tildes), `backend/scripts/migrar.php`, `generar_instalador.php` |
| 3 | La puerta de entrada | `frontend/public/index.php`, `backend/config/Database.php`, `Entorno.php`, `Aplicacion.php` |
| 4 | Login, sesión y claves | `backend/config/Sesion.php`, `AuthController.php`, `models/Clave.php`, `IntentoLoginRepository.php`, vistas `auth/` |
| 5 | Roles con herencia | `models/Usuario.php` (abstracta), `Administrador.php`, `Cajero.php`, `Almacenero.php`, `EmpleadoRepository.php` |
| 6 | Clases padre | `controllers/Controller.php`, `models/servicios/Servicio.php`, `ReglaNegocioException.php`, `Paginacion.php` |
| 7 | Personal, tiendas y auditoría | `EmpleadoController/Servicio`, `TiendaController/Servicio/Repository`, `Tienda.php`, `AuditoriaController/Repository`, vistas `empleados/`, `tiendas/`, `auditoria/` |
| 8 | Panel de inicio | `PanelController.php`, `PanelRepository.php`, vistas `panel/`, `layout_inicio.php`, `layout_fin.php`, `parciales/` (funciones, paginación, selector de tienda, buscador, guía, vacío), `errores/` |
| 9 | La API: solo la puerta | `frontend/public/api/index.php`, `controllers/api/ApiController.php`, `AuthApiController.php`, `models/TokenApiRepository.php` |
| 10 | App Android: la base | `MainActivity.kt`, `MassApp.kt`, `data/` (Repositorio, Configuracion, Preferencias, Ajustes, `api/MassApi.kt`, `api/Dtos.kt`), `ui/login/`, `ui/inicio/`, `ui/tema/`, `ui/comun/BarraInferior.kt`, `Componentes.kt` |
| 11 | Respaldos y despliegue | `backend/scripts/respaldo.sh`, `restaurar.sh`, `verificar_instalacion.php`, `docker-compose.yml`, `docker/`, `deploy/`, [`despliegue.md`](despliegue.md) |
| 12 | Pruebas de su bloque | `backend/tests/seguridad.php`, `backend/tests/web.php`, `backend/tests/restauracion.sh`, `.github/workflows/ci.yml` |

En `frontend/public/js/app.js` le tocan: sucursal del administrador, drawers, menú de celular, menú lateral plegable, guías, avisos (toast), conexión con el servidor, validación de formularios, confirmación propia, envío sin doble clic, teclado y cierre de sesión por inactividad.

### Qué debe entender

- Por qué hay **un solo** `index.php` y qué hace antes de llamar a un controlador: cabeceras de seguridad, sesión, revisar que el empleado siga activo y que su clave no haya cambiado, CSRF en cada POST, 405 si se hace POST a una ruta de lectura.
- **Herencia y polimorfismo** con los roles: `Usuario` es abstracta y cada rol responde distinto a `puedeVender()`, `puedeAnularVenta()`, `puedeElegirTienda()`... El código nunca pregunta "¿es cajero?", le pregunta al objeto qué puede hacer.
- **Doble revisión de permisos**: el controlador responde 403 y el servicio vuelve a revisar, por si alguien manda el formulario a mano.
- `enTransaccion()`: todo o nada, y por qué se une a una transacción que ya está abierta (la API la abre antes).
- Las defensas de [`seguridad.md`](seguridad.md): consultas preparadas, `htmlspecialchars` con `e()`, CSP sin scripts en línea, bloqueo tras 5 claves erradas, sesión de 30 minutos, auditoría dentro de la misma transacción.
- La diferencia entre la sesión de la web (cookie + CSRF) y la de la app (token `Bearer` de 12 horas, guardado como SHA-256).
- Cómo una migración se aplica una sola vez (`huella` del archivo) y cómo se arma `instalar_laragon.sql`.

### Práctica

1. Entra con una clave equivocada 5 veces y busca en la base la fila de `intento_login` que te bloquea.
2. Crea un empleado desde **Personal** y sigue el pedido: `index.php` → `EmpleadoController` → `EmpleadoServicio` → `auditoria`.
3. Agrega un `error_log()` temporal en `index.php` y mira en la terminal qué ruta llega en cada clic.
4. Haz un respaldo con `backend/scripts/respaldo.sh`, borra la base y restáurala.

### Preguntas de repaso

1. ¿Qué pasa si alguien manda `?ruta[]=x` en la URL? ¿Qué línea lo evita?
2. ¿Por qué un POST sin token CSRF da 403 y no 404?
3. Si el administrador reinicia la clave de un cajero que está vendiendo, ¿qué pasa con su sesión en la web y con su token en la app?
4. ¿Por qué `Usuario` es `abstract`? ¿Qué método obliga a escribir a cada rol?
5. ¿Cómo hace el administrador para trabajar en otra tienda sin cambiar su `id_tienda` en la base? (`enTienda()`)
6. ¿Qué hace `Servicio::enTransaccion()` si ya hay una transacción abierta? ¿Quién la abre?
7. ¿Qué tablas están en el nivel 1 del diseño y por qué se crean primero?
8. ¿Por qué el mensaje de error de MySQL nunca llega al navegador?
9. ¿Qué revisa `backend/tests/seguridad.php` en cada push?
10. En la app, ¿dónde se guarda la dirección del servidor y qué pasa cuando la API responde 401?

### Cómo se conecta con B y C

- B y C escriben sus servicios **heredando** de `Servicio` y sus controladores heredando de `Controller`: si A cambia esas clases, cambia todo.
- Los permisos que piden B (`puedeVender`, `puedeManejarCaja`) y C (`puedeRegistrarIngreso`, `puedeAdministrarCatalogo`) están en los roles que estudia A.
- La pantalla de inicio de la app (A) abre la venta (B) y el escáner, la entrada y el inventario (C).

---

## Persona B · Vender: caja, ventas, cobro, comprobantes y reportes

### Orden de lectura

| Paso | Qué leer | Archivos |
|---|---|---|
| 1 | Dinero sin errores de redondeo | `models/Dinero.php` y sus casos en `backend/tests/unitarios.php` |
| 2 | Las tablas de la venta | `backend/database/boleta.sql` (vista `vw_comprobante`, `sp_boleta`), `caja_apertura.sql`, `operaciones.sql`, migraciones 014 (categoría de movimientos), 020 (descuentos y promociones), 021 (cada cajero en su sede) |
| 3 | La caja | `models/Caja.php`, `CajaTurno.php`, `MovimientoCaja.php`, `CajaRepository.php`, `servicios/CajaServicio.php`, `CajaController.php` |
| 4 | Vistas de caja | `views/caja/panel.php`, `cierre.php`, parciales `cuadre_caja.php`, `conteo_efectivo.php`, `drawer_abrir.php`, `drawer_cerrar.php` |
| 5 | La venta | `models/Venta.php`, `DetalleVenta.php`, `SerieComprobante.php`, `VentaRepository.php`, `servicios/VentaServicio.php`, `VentaController.php` |
| 6 | Cómo se paga | `models/pagos/PagoVenta.php` (abstracta), `PagoEfectivo.php`, `PagoTarjeta.php`, `PagoSip.php` |
| 7 | Descuentos y promociones | `models/Descuentos.php`, `Promocion.php`, `PromocionRepository.php`, `servicios/PromocionServicio.php`, `PromocionController.php`, `views/promociones/` |
| 8 | Anular y devolver | `AnulacionVenta.php`, `DevolucionVenta.php`, `VentaServicio::anular()` y `devolver()` |
| 9 | Comprobante y ticket | `views/ventas/` (nueva, lista, comprobante, ticket, detalle), `DesgloseIgv.php`, `Emisor.php`, `AnchoTicket.php`, `frontend/public/js/ticket.js`, `parciales/lineas_producto.php` |
| 10 | Facturación electrónica | `models/facturacion/` (interfaz `ProveedorFacturacion`, `NubefactProveedor`, `SinFacturacionElectronica`), [`facturacion_electronica.md`](facturacion_electronica.md) |
| 11 | Clientes | `Cliente.php`, `ClienteRepository.php`, `ClienteServicio.php`, `ClienteController.php`, `views/clientes/` |
| 12 | Reportes | `ReporteController.php`, `ReporteRepository.php`, `models/exportar/DocumentoPdf.php`, `LibroExcel.php`, `views/reportes/` |
| 13 | La venta por la API y en la app | `controllers/api/VentaApiController.php`, `servicios/OperacionMovilServicio.php`; en Android: `ui/venta/PantallaVenta.kt`, `VentaViewModel.kt`, `ui/historial/PantallaHistorial.kt`, `dominio/Carrito.kt`, `Dinero.kt`, `Atajos.kt`, `Sugerencia.kt` y sus pruebas en `src/test/` |
| 14 | Pruebas de su bloque | `backend/tests/unitarios.php`; en `integracion.php` las secciones Ventas, Anulación, Caja, Devoluciones, Retiros e ingresos de caja, Clientes, Descuentos y promociones, Cada cajero en su sede; `tests/e2e/` (Playwright: apertura, venta, movimientos, arqueo y cierre) |

En `frontend/public/js/app.js` le tocan: dinero y cantidades en enteros, la pantalla de venta (total, pagos, carrito, cuadrícula, lector de código, borrador de venta), todas las secciones de **Caja** (movimientos, contar billetes, apertura, "Ver estimado", cierre automático, comprobante de cierre) y la de nueva promoción.

### Qué debe entender

- Por qué `0.1 + 0.2 != 0.3` y cómo `Dinero` lo evita con céntimos enteros, en PHP, en `app.js` y en Kotlin.
- Los **9 pasos de `VentaServicio::registrar()`**: turno abierto y sin pausa → cliente → líneas y descuentos → pagos → correlativo con `FOR UPDATE` → cabecera, detalle y salida de stock → pagos → avanzar la serie → descuento general. Todo en una transacción.
- Por qué el correlativo se bloquea con `SELECT ... FOR UPDATE`: dos cajeros a la vez no pueden sacar el mismo número.
- El ciclo del turno: abrir con conteo → vender → movimientos de efectivo → cerrar. **Cierre ciego**: el cajero cuenta sin ver el esperado; si la diferencia entra en la tolerancia queda CERRADO y si no, OBSERVADO para el administrador.
- `efectivoEnGaveta()`: monto inicial + efectivo cobrado − devoluciones + ingresos − retiros.
- Polimorfismo en los pagos (`PagoVenta` abstracta) y en la facturación (interfaz `ProveedorFacturacion`: se puede cambiar de proveedor sin tocar `VentaServicio`).
- Las reglas de descuento: la mejor promoción por línea, un solo descuento general y el reparto céntimo por céntimo entre las líneas.
- **Idempotencia**: por qué la app manda `Idempotency-Key` y la web `clave_operacion`, y cómo `OperacionMovilServicio::unaVez()` evita cobrar dos veces tras un corte de red.

### Práctica

1. Como cajero: abre caja, haz una boleta con pago mixto (tarjeta + efectivo), un retiro y cierra con S/ 1 de diferencia. Mira el estado del turno.
2. Como administrador: anula esa boleta y busca la fila en `anulacion_venta`, los movimientos de stock que la devuelven y la `auditoria`.
3. Llama a `CALL sp_boleta('B001', 1);` en MySQL y compáralo con `?ruta=comprobante&serie=B001&numero=1`.
4. Corre `npm run e2e:docker` y mira las capturas de `tests/e2e/*-snapshots/`.

### Preguntas de repaso

1. ¿Qué devuelve `Dinero::deTexto('12.5')` y por qué no se usa `floatval()`?
2. ¿Qué pasa si dos cajeros de la misma tienda registran una boleta en el mismo instante?
3. ¿Por qué no se puede vender con la caja en pausa ni en una caja de otra sede? ¿Dónde se revisa en PHP y dónde en la base (migración 021)?
4. ¿Cuándo un turno queda OBSERVADO y quién lo resuelve?
5. Una factura exige RUC: ¿en qué método se revisa?
6. Si hay dos promociones para el mismo producto, ¿cuál se aplica? ¿Se acumulan?
7. ¿Qué diferencia hay entre anular una venta y devolverla?
8. ¿Qué responde la API si el celular reintenta una venta con la misma `Idempotency-Key`? ¿Y si el precio cambió (409 `PRECIO_CAMBIO`)?
9. ¿Por qué `Carrito` en Kotlin es inmutable?
10. ¿Por qué el sistema guarda el total pero calcula el IGV aparte (`DesgloseIgv`)?

### Cómo se conecta con A y C

- Toda venta llama a `moverStock()` (C) con `TipoMovimiento::VENTA`: la venta es una **salida** del kardex que estudia C.
- Los precios y el stock que ve el cajero vienen de `ProductoRepository` y `CatalogoRepository` (C).
- Quién puede vender, anular o revisar un cierre lo deciden los roles de A; la auditoría de anulaciones y descuentos manuales usa `Servicio::auditar()` (A).

---

## Persona C · Mercadería: productos, inventario, compras y escáner

### Orden de lectura

| Paso | Qué leer | Archivos |
|---|---|---|
| 1 | Cantidades al peso | `models/Cantidad.php`, `UnidadMedida.php`, `Producto.php`, `Stock.php` (regla REPONER) |
| 2 | Las tablas de la mercadería | `backend/database/catalogo.sql`, `ingresos.sql`, `ajustes.sql`, `api_movil.sql`, migraciones 018 (origen del producto), 019 (proveedores y órdenes de compra), 022 (revisión de productos nuevos) |
| 3 | El catálogo | `Categoria.php`, `Marca.php`, `ProductoRepository.php`, `CatalogoRepository.php`, `servicios/ProductoServicio.php`, `ProductoController.php` |
| 4 | Vistas de productos | `views/productos/` (lista, ficha, formulario, stock, catálogo, nuevos, importar, detalle), `parciales/foto_producto.php` |
| 5 | Fotos y CSV | `FotoProducto.php` (revisa la imagen y la redimensiona con GD), `CatalogoCsv.php`, `backend/database/catalogo_inicial.csv` |
| 6 | El kardex | `Servicio::moverStock()`, `MovimientoStock.php`, `TipoMovimiento.php`, `InventarioRepository.php` |
| 7 | Movimientos de inventario | `servicios/InventarioServicio.php`, `InventarioController.php`, `IngresoMercaderia.php`, `DetalleIngreso.php`, `IngresoRepository.php`, `TrasladoMercaderia.php`, `Merma.php`, `AjusteInventario.php`, `Vencimiento.php`, `Reposicion.php`, `views/inventario/` |
| 8 | Compras | `Proveedor.php`, `OrdenCompra.php`, `DetalleOrdenCompra.php`, `CompraRepository.php`, `servicios/CompraServicio.php`, `CompraController.php`, `views/compras/` |
| 9 | Productos e ingresos por la API | `controllers/api/ProductoApiController.php`, `IngresoApiController.php`, `models/ConsultaMovilRepository.php`, [`api_movil.md`](api_movil.md) |
| 10 | App Android: escáner e inventario | `ui/escaner/Escaner.kt`, `PantallaConsulta.kt`, `ui/entrada/PantallaEntrada.kt`, `ui/inventario/PantallaInventario.kt`, `ui/comun/DialogoProductoNuevo.kt`, `dominio/CodigoLeido.kt` y `CodigoLeidoTest.kt` |
| 11 | Pruebas de su bloque | `backend/tests/api.php`; en `integracion.php` las secciones Traslado y merma, Ingreso de mercadería, Por vencer, Catálogo y stock mínimo, Importar catálogo en CSV, Fotos de producto, Conteo físico, Topes DECIMAL, Proveedores y órdenes de compra |

En `frontend/public/js/app.js` le tocan: buscador en vivo y filtro por categoría, foto del producto que se sube sola, y pestañas de Productos y Movimientos.

### Qué debe entender

- **Al peso vs. por unidad**: `se_vende_por_peso = 'S'` admite 3 decimales y no tiene código de barras; por unidad, solo enteros. Por eso las cantidades van en milésimas.
- `moverStock()` es el **único** lugar que cambia el stock: bloquea la fila con `FOR UPDATE`, nunca deja el stock negativo y siempre escribe una fila en `movimiento_stock` con su origen (venta, traslado, merma, ajuste, ingreso o devolución).
- El **kardex** se arma solo con esas filas: cada movimiento guarda la cantidad y el `stock_nuevo`.
- Ingreso (sube stock con documento y costo), traslado (sale de una tienda y entra a otra en la misma transacción), merma (sale con motivo), ajuste por conteo físico (sube o baja hasta lo contado).
- **REPONER** cuando `cantidad_actual <= stock_minimo`, y cómo llega eso al panel de inicio y a la orden de compra.
- Productos **recién agregados**: un almacenero o la app crea un producto al escanear un código que no existe (`crearAlEscanear`); queda pendiente hasta que el administrador lo revisa en lote.
- El catálogo que se sube en CSV (`CatalogoCsv`, con "solo revisar" antes de guardar), el que se descarga protegido contra fórmulas de Excel (`Controller::enviarCsv()` antepone un apóstrofo a `= + - @`) y la foto revisada con `getimagesizefromstring` antes de guardarla.
- En la app: cómo `CodigoLeido` distingue un código de producto (con dígito de control válido) de cualquier otra cosa que lea la cámara.

### Práctica

1. Como almacenero: registra un ingreso de 10 unidades y mira el kardex de ese producto en `?ruta=kardex&tienda=1`.
2. Traslada 3 unidades a otra tienda y busca las **dos** filas de `movimiento_stock` que salen.
3. Importa `catalogo_inicial.csv` con "solo revisar" y luego de verdad; compara los resultados.
4. Pide `GET /api/v1/productos/codigo/7750000000011` con un token y compara la respuesta con la ficha de la web.

### Preguntas de repaso

1. ¿Por qué la cantidad `0.500` kg se guarda como `500` en PHP?
2. ¿Qué pasa si se intenta sacar más stock del que hay? ¿En qué línea se corta?
3. ¿Por qué `moverStock()` exige exactamente un origen (`id_venta`, `id_traslado`...)?
4. Un producto llega por primera vez a una tienda: ¿qué hace `moverStock()` si no existe su fila en `stock`?
5. ¿Qué diferencia hay entre una merma y un ajuste por conteo?
6. ¿Cuándo un producto sale REPONER y dónde se ve?
7. ¿Qué pasa con un producto creado desde el celular antes de que el administrador lo revise?
8. ¿Qué revisa `FotoProducto::guardar()` antes de aceptar una imagen? ¿Por qué no basta con mirar la extensión?
9. ¿Por qué la API responde 409 `YA_EXISTE` al crear un producto y qué hace la app con eso?
10. ¿Cómo valida `CodigoLeido.digitoControlValido()` un código EAN?

### Cómo se conecta con A y B

- `moverStock()` vive en `Servicio` (A), pero quien lo explica es C: lo usan las ventas y devoluciones de B y todos los movimientos de C.
- Cada venta de B baja el stock que C controla, y aparece en el kardex como salida por venta.
- Quién puede registrar ingresos, ajustar stock o administrar el catálogo lo deciden los roles de A.

---

## Cierre: exposición cruzada

Cuando cada uno termine su bloque, una sesión de una hora:

1. **Cada persona explica su bloque en 15 minutos** a las otras dos, con el sistema abierto. Las otras dos le hacen 3 preguntas de su lista.
2. **Los tres siguen juntos una venta desde el celular** hasta la base, y cada uno explica su tramo:
   - **A**: la app manda `POST /api/v1/ventas` con el token; `api/index.php` lo valida y llega al controlador.
   - **B**: `VentaApiController` → `OperacionMovilServicio::unaVez()` → `VentaServicio::registrar()`: turno, descuentos, pagos y correlativo.
   - **C**: cada línea pasa por `moverStock()`: baja el stock y escribe el kardex; si queda en el mínimo, el producto sale REPONER.
   - **A** de nuevo: el commit, y por qué un error a mitad deja la base como estaba.
3. Cada uno anota lo que aprendió y para qué usó la IA en `docs/bitacora_ia.md` (como en [`reparto.md`](reparto.md)).

## Resumen del reparto

| | A · Cimientos | B · Vender | C · Mercadería |
|---|---|---|---|
| Controladores | Controller, Auth, Empleado, Tienda, Auditoria, Panel | Caja, Venta, Cliente, Promocion, Reporte | Producto, Inventario, Compra |
| Servicios | Servicio (base), Empleado, Tienda | Venta, Caja, Cliente, Promocion, OperacionMovil | Producto, Inventario, Compra |
| API | `api/index.php`, ApiController, AuthApi | VentaApi | ProductoApi, IngresoApi |
| App Android | Base, login, inicio, tema y componentes | Venta, historial, carrito | Escáner, consulta, entrada, inventario |
| Base de datos | schema, datos, migraciones 013 y 015, respaldos | boleta, caja_apertura, operaciones, migraciones 014, 020 y 021 | catálogo, ingresos, ajustes, api_movil, migraciones 018, 019 y 022 |
| Pruebas | seguridad, web, restauración, CI | unitarios, integración (ventas y caja), e2e | api, integración (inventario y compras) |
| Docs | seguridad, diseno_bd, despliegue, instalacion | facturacion_electronica, manual_usuario | api_movil, propuesta_sunat_y_lector, plan_sin_internet |
