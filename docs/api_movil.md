# API para la app Android (`/api/v1`)

La app del celular (`android-app/`) no se conecta a MySQL. Llama a esta API, que corre en el mismo PHP y la misma base que la web, y usa los mismos servicios (`VentaServicio`, `InventarioServicio`, `CajaServicio`). Ninguna regla de negocio está copiada: una venta por la API pasa por exactamente el mismo código que una venta en la web.

- Punto de entrada: `frontend/public/api/index.php`. Con `php -S localhost:8000 -t frontend/public` la base es `http://localhost:8000/api/v1/`.
- En producción, nginx (`try_files $uri /api/index.php`) y Apache (`FallbackResource` y `CGIPassAuth On`, para que llegue la cabecera `Authorization`) ya están configurados en `deploy/`.
- Tablas propias: `api_token` y `api_operacion` (`backend/database/api_movil.sql`, 36 tablas en total). Una base creada antes necesita correr ese archivo una vez.
- Pruebas: `php backend/tests/api.php` (levanta su propio servidor en el puerto 8097; usar con la base recién creada).

## Formato

Todo responde JSON. Los montos y cantidades van como **texto** (`"13.00"`, `"0.500"`), igual que en los formularios de la web, para no perder céntimos con números de coma flotante.

```json
{"ok": true, "datos": { ... }}
{"ok": false, "error": {"codigo": "REGLA_NEGOCIO", "mensaje": "Stock insuficiente de Leche Gloria ..."}}
```

Un `POST` lleva `Content-Type: application/json` y como máximo 256 KB. Las respuestas llevan `Cache-Control: no-store` y no se guardan en ningún intermediario.

| HTTP | `codigo` | Cuándo |
|---|---|---|
| 400 | `JSON_INVALIDO`, `DATOS_INVALIDOS` | El cuerpo no es un objeto JSON o falta la lista de líneas |
| 401 | `CREDENCIALES` | DNI o clave incorrectos (el mismo mensaje si el DNI no existe o el empleado está inactivo) |
| 401 | `NO_AUTENTICADO` | Sin token, token vencido o revocado. La app vuelve al ingreso |
| 403 | `PROHIBIDO` | El rol no puede hacer eso, o el dato es de otra tienda |
| 404 | `NO_ENCONTRADO`, `RUTA_DESCONOCIDA` | Producto, código, venta o ruta que no existe |
| 405 | `METODO_NO_PERMITIDO` | Verbo equivocado (lleva la cabecera `Allow`) |
| 409 | `PRECIO_CAMBIO` | El total que vio la app ya no es el del servidor |
| 409 | `YA_EXISTE` | Producto nuevo con un código que ya está en el catálogo (otro celular lo registró antes, o es un reintento). La app lo vuelve a buscar por código |
| 413 / 415 | `MUY_GRANDE` / `TIPO_NO_SOPORTADO` | Cuerpo de más de 256 KB, o no es JSON |
| 422 | `REGLA_NEGOCIO` | Lo rechazó una regla del sistema (stock, turno, cantidades, documento, Idempotency-Key...) con el mismo mensaje que la web |
| 429 | `BLOQUEADO` | Demasiados intentos de ingreso fallidos (el mismo bloqueo que la web) |
| 500 | `ERROR_INTERNO` | Error inesperado. El detalle solo va al log del servidor, nunca a la respuesta |
| 503 | `SIN_BASE` | No hay conexión con MySQL (sin mostrar host ni usuario) |

## Sesión

```
POST /api/v1/auth/login   {"dni": "70000002", "clave": "...", "dispositivo": "Pixel 7"}
→ {"token": "...", "expira": "2026-10-30 22:10:00", "usuario": {...perfil, permisos}}
```

El token se manda en cada pedido como `Authorization: Bearer <token>`. Vence a las 12 horas y en la base solo se guarda su SHA-256. Si el empleado pasa a inactivo, el token deja de servir al instante.

| Ruta | Qué hace |
|---|---|
| `GET v1/salud` | Sin token. Para probar la dirección del servidor desde la app |
| `POST v1/auth/logout` | Revoca el token |
| `GET v1/auth/yo` | Perfil: nombre, rol, tienda, DNI enmascarado y `permisos` (`vender`, `manejar_caja`, `registrar_ingreso`, `ver_ingresos`, `anular_venta`, `ver_inventario`, `ver_ventas`, `crear_producto`) |

Los permisos son solo para armar el menú: el servidor vuelve a revisarlos en cada ruta.

## Productos e inventario (de la tienda del usuario)

| Ruta | Qué hace |
|---|---|
| `GET v1/productos?q=leche&filtro=bajo&pagina=1` | Lista con stock. `filtro`: `todos`, `bajo` o `agotados` |
| `GET v1/productos/codigo/{código}` | El escáner. Un UPC-A de 12 dígitos también encuentra el mismo producto guardado como EAN-13 con un 0 adelante, y al revés |
| `GET v1/productos/{id}` | Ficha con los últimos 15 movimientos del kardex |
| `GET v1/movimientos?producto={id}&pagina=1` | Kardex de la tienda |
| `GET v1/catalogo` | Almacenero, administrador. Categorías, marcas y unidades (sin `kg`) para el formulario de producto nuevo |
| `POST v1/productos` | Almacenero, administrador. Registra el código escaneado que no existe (201) con `ProductoServicio::crearAlEscanear`: siempre por unidad, stock 0 en todas las tiendas y auditoría `PRODUCTO_CREADO ... (app)`. Cuerpo: `{"codigo": "7750000000999", "nombre": "...", "precio": "4.50", "categoria": 1, "marca": 3, "unidad": 1}`; con una marca nueva, `"marca": ""` y `"marca_nombre": "Gloria"` (si ya existe con otras mayúsculas, se usa esa) |

### Quién hace qué

| Acción | Cajero | Almacenero | Administrador |
|---|---|---|---|
| Vender | Sí, con su turno de caja abierto | No | No |
| Consultar precio y stock | Sí | Sí | Sí |
| Entrada de stock | No | Sí, en su tienda | Sí, en su tienda |
| Registrar un producto nuevo al escanear | No: la app le pide avisar al almacenero | Sí | Sí |
| Cambiar el precio o los datos de un producto que ya existe | No | No | Sí, en la web (Catálogo) |
| Ajuste por conteo | No | Sí | Sí |

No hay restricción por horario: las ventas ya dependen del turno de caja abierto, y cada operación queda en la auditoría con quién la hizo y a qué hora.

En la app, si el código no está registrado, el almacenero o el administrador ven el formulario ahí mismo (en Entrada de stock y en Consultar producto). La app busca el código en [Open Food Facts](https://world.openfoodfacts.org) (base pública y gratuita) para sugerir el nombre y la marca; esa consulta sale directo del celular a internet, sin el token de la tienda, y si no hay internet o no lo conoce simplemente se escribe a mano. El precio siempre lo pone el trabajador.

## Caja y ventas

| Ruta | Quién | Qué hace |
|---|---|---|
| `GET v1/caja` | Cajero | Turno abierto y cajas libres |
| `POST v1/caja/turno` `{"id_caja": 1, "monto_inicial": "100.00"}` | Cajero | Abre el turno (201) con `CajaServicio::abrirTurno` |
| `POST v1/ventas` | Cajero con turno | Registra la venta con `VentaServicio::registrar` (ver abajo) |
| `GET v1/ventas?desde=2026-10-01&hasta=2026-10-31&estado=EMITIDA&comprobante=B001-5&pagina=1` | Cajero, administrador | Historial de su tienda |
| `GET v1/ventas/{id}` | Cajero, administrador | Comprobante con líneas, pagos, anulación y `puede_anular`. Otra tienda: 403 |
| `POST v1/ventas/{id}/anular` `{"motivo": "..."}` | Administrador de la tienda | `VentaServicio::anular`: la venta queda ANULADA, el stock vuelve y se guarda quién y por qué. Nunca se borra |

```json
POST /api/v1/ventas
Idempotency-Key: 6f1c2b9e-0d4a-4b7e-9a51-2f0e8c3d1a77

{"tipo_comprobante": "B", "documento": "", "total_esperado": "13.00",
 "lineas": [{"id_producto": 1, "cantidad": "2"}],
 "pago": {"metodo": "EFECTIVO", "efectivo_recibido": "20.00"}}
```

- `tipo_comprobante`: `B` (boleta, `documento` vacío o un DNI) o `F` (factura, `documento` = RUC).
- El **precio lo pone el servidor**. `total_esperado` es lo que mostró la app: si no coincide, responde 409 `PRECIO_CAMBIO` sin guardar nada.
- `pago.metodo`: `EFECTIVO` (con `efectivo_recibido`; el vuelto lo calcula el servidor), `TARJETA` (`tarjeta_tipo`, `tarjeta_marca`, `tarjeta_ultimos4`, `tarjeta_autorizacion`) o `SIP` (`sip_modalidad`, `sip_operacion`). Tarjeta y Sip **solo registran** un cobro hecho en el POS o con el QR: la API no procesa pagos.
- Respuesta: 201 con la venta completa (serie y número, líneas, pagos, vuelto).

## Ingreso de mercadería

| Ruta | Quién | Qué hace |
|---|---|---|
| `POST v1/ingresos` | Almacenero, administrador | `InventarioServicio::registrarIngreso` |
| `GET v1/ingresos?pagina=1` | Almacenero, administrador | Últimos ingresos de la tienda |
| `GET v1/ingresos/{id}` | Almacenero, administrador | Detalle con el stock actual de cada producto. Otra tienda: 403 |

```json
{"documento": {"tipo": "FACTURA", "numero": "F001-123", "fecha": "2026-10-30",
               "ruc": "20123456789", "razon_social": "Distribuidora Sur SAC", "observacion": ""},
 "lineas": [{"id_producto": 1, "cantidad": "10", "costo": "3.20", "vence": "2027-01-31"}]}
```

`documento.tipo` es `GUIA`, `FACTURA` o `BOLETA`, y el resto de reglas (número `SERIE-NÚMERO`, RUC de 11 dígitos, fecha no futura, documento repetido) son las de la pantalla de ingreso de la web.

## Reintentos seguros (`Idempotency-Key`)

`POST v1/ventas` y `POST v1/ingresos` exigen la cabecera `Idempotency-Key` (8 a 64 letras, números o guiones; la app usa un UUID por cobro).

- La primera vez la operación se guarda en `api_operacion` **en la misma transacción** que la venta o el ingreso, y responde 201.
- Si llega otra vez la misma clave del mismo empleado (por ejemplo, se cortó la señal antes de la respuesta), no se registra nada nuevo: responde 200 con `"repetida": true` y la misma venta.
- Dos pedidos simultáneos con la misma clave dejan una sola venta (la llave única de la tabla lo asegura).
- Si la venta falla (sin stock, sin turno), la clave no queda usada y se puede corregir y reintentar.

## Sincronización con la web

No hay copia local ni sincronización aparte: la app pide los datos al servidor cada vez que abre una pantalla (y el inventario se refresca cada minuto). Una venta hecha en la web baja el stock que ve el celular, y una venta del celular aparece en la caja, el kardex y los reportes de la web, porque es la misma fila en la misma base.
