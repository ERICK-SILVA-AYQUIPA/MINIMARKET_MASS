# Sistema Minimarket Mass
Proyecto de base de datos y backend para la gestión de ventas e inventario de la tienda Mass.
Desarrollado para el curso Backend Developer Web en SENATI CFP Arequipa.

## Integrantes del Grupo
- ERICK SILVA AYQUIPA
- Gerald Anderson Condori Quispe
- Alexander Emmanuel Moreno Flores
- Jhon Alexandro Apaza Belizario
- Suhaily Luana Huaypar Quijhua
- Keymer Cesar Machaca Puma

## Archivos del Repositorio
- `README.md`: Documentación principal del repositorio.
- `Informe_Tienda_Mass.pdf`: Informe del proyecto con los diagramas UML (Casos de uso, Secuencia, Actividades, Componentes y Despliegue) y el tablero Kanban de la base de datos.
- `database/schema.sql`: Script que crea la base de datos `minimarket_mass`, sus 32 tablas y los triggers de las reglas de negocio (sin datos de ejemplo).

## Tablas del Diseño
La base de datos tiene 32 tablas agrupadas en 7 módulos, tal como figuran en el tablero Kanban del informe.

| Módulo | Tablas |
|---|---|
| Catálogo | `categoria`, `marca`, `unidad_medida`, `producto` |
| Ubicación y personal | `distrito`, `tienda`, `rol`, `empleado`, `turno_empleado` |
| Ventas | `cliente`, `venta`, `detalle_venta`, `anulacion_venta` |
| Inventario | `stock`, `tipo_movimiento`, `movimiento_stock`, `merma` |
| Caja y comprobantes | `caja`, `caja_turno`, `tipo_comprobante`, `serie_comprobante`, `comprobante` |
| Pagos | `metodo_pago`, `pago_venta`, `efectivo`, `tarjeta`, `billetera_digital_sip`, `autorizacion_pago` |
| Distribución | `centro_distribucion`, `pedido_reposicion`, `detalle_pedido`, `recepcion_mercaderia` |

Características técnicas:
- MySQL 8.4, motor InnoDB, `utf8mb4` / `utf8mb4_0900_ai_ci`.
- Claves primarias `INT UNSIGNED AUTO_INCREMENT`; cada clave foránea tiene el mismo tipo que la clave a la que apunta.
- Dinero en `DECIMAL(10,2)` y cantidades (incluidas las ventas por kilo) en `DECIMAL(10,3)`.
- `ENUM` solo en el resultado de la autorización de pago (`AUTORIZADA` / `RECHAZADA`), que es lo único con valores fijos en el diseño.
- Las tablas se crean en orden: primero las que no dependen de otras y al final las que tienen claves foráneas.
- `UNIQUE` en serie + correlativo del comprobante, código de barras del producto, DNI del empleado y documento del cliente.

### Detalle de cada tabla

PK = clave primaria, FK = clave foránea. Las columnas son la propuesta del grupo a partir de los diagramas del informe.

### Módulo Catálogo

#### `categoria`
Agrupa los productos del catalogo (abarrotes, lacteos, etc.).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| categoria_id | INT UNSIGNED | No |  | PK |
| nombre | VARCHAR(60) | No |  |  |
| activo | TINYINT(1) | No | 1 |  |

Únicos: (nombre)

#### `marca`
Fabricante o marca comercial de cada producto.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| marca_id | INT UNSIGNED | No |  | PK |
| nombre | VARCHAR(60) | No |  |  |
| activo | TINYINT(1) | No | 1 |  |

Únicos: (nombre)

#### `unidad_medida`
Como se vende el producto (unidad, kilo, litro...). permite_decimales = 1 para productos que se venden por kilo.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| unidad_medida_id | INT UNSIGNED | No |  | PK |
| codigo | VARCHAR(5) | No |  |  |
| nombre | VARCHAR(30) | No |  |  |
| permite_decimales | TINYINT(1) | No | 0 |  |

Únicos: (codigo)

#### `producto`
Articulo del catalogo; codigo_barras es el que usa consultarPrecio(codigo). un solo precio vigente para toda la cadena.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| producto_id | INT UNSIGNED | No |  | PK |
| categoria_id | INT UNSIGNED | No |  | FK → categoria |
| marca_id | INT UNSIGNED | No |  | FK → marca |
| unidad_medida_id | INT UNSIGNED | No |  | FK → unidad_medida |
| codigo_barras | VARCHAR(20) | No |  |  |
| nombre | VARCHAR(120) | No |  |  |
| precio_venta | DECIMAL(10,2) | No |  |  |
| activo | TINYINT(1) | No | 1 |  |

Únicos: (codigo_barras)

### Módulo Ubicación y personal

#### `distrito`
Ubicacion geografica de tiendas y centros de distribucion.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| distrito_id | INT UNSIGNED | No |  | PK |
| nombre | VARCHAR(60) | No |  |  |
| provincia | VARCHAR(60) | No |  |  |
| departamento | VARCHAR(60) | No |  |  |

Únicos: (departamento, provincia, nombre)

#### `tienda`
Cada local de la cadena Mass (donde corre ServTienda).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| tienda_id | INT UNSIGNED | No |  | PK |
| distrito_id | INT UNSIGNED | No |  | FK → distrito |
| codigo | VARCHAR(10) | No |  |  |
| nombre | VARCHAR(80) | No |  |  |
| direccion | VARCHAR(150) | No |  |  |
| activo | TINYINT(1) | No | 1 |  |

Únicos: (codigo)

#### `rol`
Cargo del empleado (Colaborador de tienda, Jefe de tienda).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| rol_id | INT UNSIGNED | No |  | PK |
| nombre | VARCHAR(40) | No |  |  |

Únicos: (nombre)

#### `empleado`
Personal de la tienda (colaboradores y jefe de tienda).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| empleado_id | INT UNSIGNED | No |  | PK |
| tienda_id | INT UNSIGNED | No |  | FK → tienda |
| rol_id | INT UNSIGNED | No |  | FK → rol |
| dni | CHAR(8) | No |  |  |
| nombres | VARCHAR(80) | No |  |  |
| apellidos | VARCHAR(80) | No |  |  |
| fecha_ingreso | DATE | No |  |  |
| activo | TINYINT(1) | No | 1 |  |

Únicos: (dni)

#### `turno_empleado`
Horarios del personal que programa el Jefe de tienda.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| turno_empleado_id | INT UNSIGNED | No |  | PK |
| empleado_id | INT UNSIGNED | No |  | FK → empleado |
| programado_por_id | INT UNSIGNED | No |  | FK → empleado |
| fecha | DATE | No |  |  |
| hora_inicio | TIME | No |  |  |
| hora_fin | TIME | No |  |  |

Únicos: (empleado_id, fecha, hora_inicio)

### Módulo Ventas

#### `cliente`
Comprador que pide boleta (DNI) o factura (RUC). numero_documento guarda DNI (8) o RUC (11); la venta sin datos del cliente se registra con cliente_id NULL.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| cliente_id | INT UNSIGNED | No |  | PK |
| numero_documento | VARCHAR(11) | No |  |  |
| nombre_razon_social | VARCHAR(150) | No |  |  |
| direccion | VARCHAR(150) | Sí |  |  |
| email | VARCHAR(100) | Sí |  |  |
| fecha_registro | DATETIME | No | CURRENT_TIMESTAMP |  |

Únicos: (numero_documento)

#### `venta`
Cabecera de cada compra registrada en una caja (registrarVenta / guardarVenta). registrada_central = 1 cuando ServCentral confirma registrarVentaCentral().

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| venta_id | INT UNSIGNED | No |  | PK |
| caja_turno_id | INT UNSIGNED | No |  | FK → caja_turno |
| cliente_id | INT UNSIGNED | Sí |  | FK → cliente |
| fecha_hora | DATETIME | No | CURRENT_TIMESTAMP |  |
| total | DECIMAL(10,2) | No | 0.00 |  |
| registrada_central | TINYINT(1) | No | 0 |  |
| fecha_registro_central | DATETIME | Sí |  |  |

Únicos: (venta_id, registrada_central)

#### `detalle_venta`
Productos de cada venta con el precio consultado en ese momento.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| detalle_venta_id | INT UNSIGNED | No |  | PK |
| venta_id | INT UNSIGNED | No |  | FK → venta |
| producto_id | INT UNSIGNED | No |  | FK → producto |
| cantidad | DECIMAL(10,3) | No |  |  |
| precio_unitario | DECIMAL(10,2) | No |  |  |
| subtotal | DECIMAL(10,2) | No |  |  |

Únicos: (venta_id, producto_id)

#### `anulacion_venta`
Anulacion de una venta; siempre con el Jefe de tienda que la autoriza. El trigger trg_anulacion_solo_jefe verifica que autorizado_por_id sea Jefe de tienda.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| anulacion_venta_id | INT UNSIGNED | No |  | PK |
| venta_id | INT UNSIGNED | No |  | FK → venta |
| solicitado_por_id | INT UNSIGNED | No |  | FK → empleado |
| autorizado_por_id | INT UNSIGNED | No |  | FK → empleado |
| motivo | VARCHAR(255) | No |  |  |
| fecha_hora | DATETIME | No | CURRENT_TIMESTAMP |  |

Únicos: (venta_id)

### Módulo Inventario

#### `stock`
Cantidad disponible de cada producto en cada tienda (Consultar/Sincronizar stock).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| stock_id | INT UNSIGNED | No |  | PK |
| tienda_id | INT UNSIGNED | No |  | FK → tienda |
| producto_id | INT UNSIGNED | No |  | FK → producto |
| cantidad | DECIMAL(10,3) | No | 0.000 |  |
| stock_minimo | DECIMAL(10,3) | No | 0.000 |  |
| fecha_sincronizacion | DATETIME | Sí |  |  |

Únicos: (tienda_id, producto_id)

#### `tipo_movimiento`
Clase de movimiento de stock (venta, recepcion, merma, ajuste). signo: +1 si el movimiento suma stock, -1 si lo resta.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| tipo_movimiento_id | INT UNSIGNED | No |  | PK |
| nombre | VARCHAR(40) | No |  |  |
| signo | TINYINT | No |  |  |

Únicos: (nombre)

#### `movimiento_stock`
Historial de entradas y salidas de stock por tienda (venta, recepcion, merma o ajuste del jefe).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| movimiento_stock_id | INT UNSIGNED | No |  | PK |
| tienda_id | INT UNSIGNED | No |  | FK → tienda |
| producto_id | INT UNSIGNED | No |  | FK → producto |
| tipo_movimiento_id | INT UNSIGNED | No |  | FK → tipo_movimiento |
| empleado_id | INT UNSIGNED | Sí |  | FK → empleado |
| venta_id | INT UNSIGNED | Sí |  | FK → venta |
| recepcion_mercaderia_id | INT UNSIGNED | Sí |  | FK → recepcion_mercaderia |
| merma_id | INT UNSIGNED | Sí |  | FK → merma |
| cantidad | DECIMAL(10,3) | No |  |  |
| fecha_hora | DATETIME | No | CURRENT_TIMESTAMP |  |

#### `merma`
Producto perdido o danado que se da de baja del stock.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| merma_id | INT UNSIGNED | No |  | PK |
| tienda_id | INT UNSIGNED | No |  | FK → tienda |
| producto_id | INT UNSIGNED | No |  | FK → producto |
| empleado_id | INT UNSIGNED | No |  | FK → empleado |
| cantidad | DECIMAL(10,3) | No |  |  |
| motivo | VARCHAR(150) | No |  |  |
| fecha_hora | DATETIME | No | CURRENT_TIMESTAMP |  |

### Módulo Caja y comprobantes

#### `caja`
Punto de venta (AppCaja) de una tienda, con su codigo QR para pagos Sip.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| caja_id | INT UNSIGNED | No |  | PK |
| tienda_id | INT UNSIGNED | No |  | FK → tienda |
| numero | SMALLINT UNSIGNED | No |  |  |
| codigo_qr | VARCHAR(100) | No |  |  |
| activo | TINYINT(1) | No | 1 |  |

Únicos: (tienda_id, numero); (codigo_qr)

#### `caja_turno`
Apertura y cierre de una caja por un colaborador (Cobrar en caja / Supervisar caja).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| caja_turno_id | INT UNSIGNED | No |  | PK |
| caja_id | INT UNSIGNED | No |  | FK → caja |
| empleado_id | INT UNSIGNED | No |  | FK → empleado |
| fecha_apertura | DATETIME | No | CURRENT_TIMESTAMP |  |
| monto_apertura | DECIMAL(10,2) | No | 0.00 |  |
| fecha_cierre | DATETIME | Sí |  |  |
| monto_cierre | DECIMAL(10,2) | Sí |  |  |

#### `tipo_comprobante`
Boleta o Factura (codigos SUNAT 03 y 01).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| tipo_comprobante_id | INT UNSIGNED | No |  | PK |
| codigo_sunat | CHAR(2) | No |  |  |
| nombre | VARCHAR(30) | No |  |  |

Únicos: (codigo_sunat); (nombre)

#### `serie_comprobante`
Series de boleta/factura asignadas a cada tienda (ej. B001, F001).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| serie_comprobante_id | INT UNSIGNED | No |  | PK |
| tipo_comprobante_id | INT UNSIGNED | No |  | FK → tipo_comprobante |
| tienda_id | INT UNSIGNED | No |  | FK → tienda |
| serie | CHAR(4) | No |  |  |
| ultimo_correlativo | INT UNSIGNED | No | 0 |  |
| activo | TINYINT(1) | No | 1 |  |

Únicos: (serie)

#### `comprobante`
Boleta o factura emitida; solo para ventas ya registradas en el central. venta_registrada_central siempre vale 1 y, junto con la FK compuesta, impide emitir el comprobante de una venta no registrada en ServCentral.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| comprobante_id | INT UNSIGNED | No |  | PK |
| venta_id | INT UNSIGNED | No |  | FK → venta |
| venta_registrada_central | TINYINT(1) | No | 1 | FK → venta |
| serie_comprobante_id | INT UNSIGNED | No |  | FK → serie_comprobante |
| correlativo | INT UNSIGNED | No |  |  |
| fecha_emision | DATETIME | No | CURRENT_TIMESTAMP |  |

Únicos: (serie_comprobante_id, correlativo); (venta_id)

### Módulo Pagos

#### `metodo_pago`
Medios de pago aceptados (Efectivo, Tarjeta, Billetera Sip).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| metodo_pago_id | INT UNSIGNED | No |  | PK |
| nombre | VARCHAR(40) | No |  |  |
| activo | TINYINT(1) | No | 1 |  |

Únicos: (nombre)

#### `pago_venta`
Cada pago aplicado a una venta (una venta puede combinar medios).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| pago_venta_id | INT UNSIGNED | No |  | PK |
| venta_id | INT UNSIGNED | No |  | FK → venta |
| metodo_pago_id | INT UNSIGNED | No |  | FK → metodo_pago |
| monto | DECIMAL(10,2) | No |  |  |
| fecha_hora | DATETIME | No | CURRENT_TIMESTAMP |  |

#### `efectivo`
Datos del pago en efectivo (pagarMonto / cobrarEfectivo).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| efectivo_id | INT UNSIGNED | No |  | PK |
| pago_venta_id | INT UNSIGNED | No |  | FK → pago_venta |
| monto_recibido | DECIMAL(10,2) | No |  |  |
| vuelto | DECIMAL(10,2) | No | 0.00 |  |

Únicos: (pago_venta_id)

#### `tarjeta`
Pago con tarjeta de credito o debito; solo con autorizacion AUTORIZADA.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| tarjeta_id | INT UNSIGNED | No |  | PK |
| pago_venta_id | INT UNSIGNED | No |  | FK → pago_venta |
| autorizacion_pago_id | INT UNSIGNED | No |  | FK → autorizacion_pago |
| resultado_autorizacion | ENUM('AUTORIZADA','RECHAZADA') | No | 'AUTORIZADA' | FK → autorizacion_pago |
| ultimos_digitos | CHAR(4) | No |  |  |

Únicos: (pago_venta_id); (autorizacion_pago_id)

#### `billetera_digital_sip`
Pago con la app Sip por QR, usando saldo y/o recompensas; solo con autorizacion AUTORIZADA de la pasarela.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| billetera_digital_sip_id | INT UNSIGNED | No |  | PK |
| pago_venta_id | INT UNSIGNED | No |  | FK → pago_venta |
| autorizacion_pago_id | INT UNSIGNED | No |  | FK → autorizacion_pago |
| resultado_autorizacion | ENUM('AUTORIZADA','RECHAZADA') | No | 'AUTORIZADA' | FK → autorizacion_pago |
| monto_saldo | DECIMAL(10,2) | No | 0.00 |  |
| monto_recompensas | DECIMAL(10,2) | No | 0.00 |  |

Únicos: (pago_venta_id); (autorizacion_pago_id)

#### `autorizacion_pago`
Respuesta de la PasarelaPago a autorizarTransaccion(). ENUM porque el diseno define solo dos resultados: [autorizada] / [rechazada].

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| autorizacion_pago_id | INT UNSIGNED | No |  | PK |
| codigo_transaccion | VARCHAR(40) | No |  |  |
| monto | DECIMAL(10,2) | No |  |  |
| resultado | ENUM('AUTORIZADA','RECHAZADA') | No |  |  |
| motivo_rechazo | VARCHAR(150) | Sí |  |  |
| fecha_hora | DATETIME | No | CURRENT_TIMESTAMP |  |

Únicos: (codigo_transaccion); (autorizacion_pago_id, resultado)

### Módulo Distribución

#### `centro_distribucion`
Almacen que envia pedidos a las tiendas (ServDistribucion).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| centro_distribucion_id | INT UNSIGNED | No |  | PK |
| distrito_id | INT UNSIGNED | No |  | FK → distrito |
| nombre | VARCHAR(80) | No |  |  |
| direccion | VARCHAR(150) | No |  |  |
| activo | TINYINT(1) | No | 1 |  |

Únicos: (nombre)

#### `pedido_reposicion`
Pedido de mercaderia de la tienda al centro de distribucion (Enviar pedidos / Confirmar entrega).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| pedido_reposicion_id | INT UNSIGNED | No |  | PK |
| tienda_id | INT UNSIGNED | No |  | FK → tienda |
| centro_distribucion_id | INT UNSIGNED | No |  | FK → centro_distribucion |
| empleado_id | INT UNSIGNED | No |  | FK → empleado |
| fecha_pedido | DATETIME | No | CURRENT_TIMESTAMP |  |
| fecha_envio | DATETIME | Sí |  |  |
| fecha_entrega | DATETIME | Sí |  |  |

#### `detalle_pedido`
Productos y cantidades de cada pedido de reposicion.

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| detalle_pedido_id | INT UNSIGNED | No |  | PK |
| pedido_reposicion_id | INT UNSIGNED | No |  | FK → pedido_reposicion |
| producto_id | INT UNSIGNED | No |  | FK → producto |
| cantidad_solicitada | DECIMAL(10,3) | No |  |  |
| cantidad_recibida | DECIMAL(10,3) | Sí |  |  |

Únicos: (pedido_reposicion_id, producto_id)

#### `recepcion_mercaderia`
Llegada del pedido a la tienda, verificada por un colaborador (Recibir mercancia).

| Columna | Tipo | Nulo | Default | Clave |
|---|---|---|---|---|
| recepcion_mercaderia_id | INT UNSIGNED | No |  | PK |
| pedido_reposicion_id | INT UNSIGNED | No |  | FK → pedido_reposicion |
| empleado_id | INT UNSIGNED | No |  | FK → empleado |
| fecha_hora | DATETIME | No | CURRENT_TIMESTAMP |  |
| observaciones | VARCHAR(255) | Sí |  |  |

Únicos: (pedido_reposicion_id)

## Reglas de Negocio
| Regla | Cómo la cumple la base de datos |
|---|---|
| **Cobro seguro:** no se puede procesar ni cerrar una venta si la pasarela de pago rechaza la tarjeta. | `tarjeta` y `billetera_digital_sip` solo aceptan una `autorizacion_pago` con resultado `AUTORIZADA` (clave foránea compuesta + `CHECK`). |
| **Control de anulaciones:** toda anulación exige la autorización del Jefe de tienda. | `anulacion_venta.autorizado_por_id` es obligatorio y el trigger `trg_anulacion_solo_jefe` verifica que tenga el rol `Jefe de tienda`. |
| **Emisión de comprobantes:** no se emite boleta o factura hasta que la venta esté registrada en el servidor central. | `comprobante` solo puede apuntar a una `venta` con `registrada_central = 1` (clave foránea compuesta + `CHECK`). |
| **Descuento de stock al vender.** | El trigger `trg_detalle_venta_descuenta_stock` descuenta el stock de la tienda al guardar cada producto vendido y rechaza la venta si no alcanza. |

## Cómo Crear la Base de Datos
Para implementar la base de datos en Windows utilizando PowerShell y Laragon (MySQL 8.4 en el puerto 3307), ejecuta el siguiente comando. Reemplaza `C:/ruta/al/repo` con la ruta exacta de tu repositorio local:

```powershell
mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/schema.sql"
```

Para comprobar que se creó:

```powershell
mysql -u root -h 127.0.0.1 -P 3307 -e "USE minimarket_mass; SHOW TABLES;"
```

## Supuestos y Pendientes
- El informe define los nombres de las 32 tablas (tablero Kanban); las columnas se propusieron a partir de los mensajes del diagrama de Secuencia (`consultarPrecio(codigo)`, `pagarMonto(monto)`, `elegirFormaPago(saldo | recompensas)`, etc.).
- Un solo precio de venta para toda la cadena (`producto.precio_venta`).
- `cliente.numero_documento` guarda DNI (8 dígitos) o RUC (11 dígitos); una venta sin datos del cliente usa `cliente_id` vacío.
- `venta.total` incluye el IGV; no se guarda por separado.
- El trigger de anulaciones exige que el rol se llame exactamente `Jefe de tienda`.
- La tabla `merma` queda pendiente de confirmar con el equipo, como indica el informe.
- Los datos de prueba se cargarán en un script aparte (tarjeta «Datos de prueba y pruebas» del Kanban).
