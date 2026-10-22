# Diseño de Base de Datos - Minimarket Mass

Documento técnico de especificación y diccionario de datos del sistema relacional de ventas, inventario y facturación para la cadena Minimarket Mass (Arequipa).

- **Motor de Base de Datos:** MySQL 8.0+ / 8.4 (InnoDB Engine en Laragon, puerto 3307)
- **Codificación / Collation:** `utf8mb4` / `utf8mb4_unicode_ci`
- **Total de tablas:** 29 tablas relacionales normalizadas en 3FN
- **Objetos de programación:** 6 procedimientos almacenados, 1 función, 15 triggers y 1 vista de control

---

## 1. Módulos y Entidades del Sistema

| Módulo | Tablas | Descripción |
|---|---|---|
| **1. Ubicación y organización** | `distrito`, `tienda`, `rol`, `usuario` | Gestión de sedes de Tiendas Mass en Arequipa y asignación de personal con roles funcionales. |
| **2. Clientes** | `tipo_documento_identidad`, `cliente` | Registro de clientes con validación estricta de documentos (DNI 8 dígitos, RUC 11 dígitos). |
| **3. Catálogo** | `categoria`, `marca`, `unidad_medida`, `producto` | Jerarquía comercial, marcas propias (Mass, Bell's) o de terceros, y catálogo con precio unificado. |
| **4. Inventario** | `stock`, `tipo_movimiento`, `movimiento_stock`, `traslado_mercaderia`, `detalle_traslado`, `merma`, `detalle_merma` | Existencias por tienda, kardex valorizado, traslados entre sedes y bajas por vencimiento o merma. |
| **5. Cajas** | `caja`, `caja_turno` | Puntos de venta POS y control de turnos de apertura, fondo de caja y cierre por cajero. |
| **6. Ventas** | `tipo_comprobante`, `serie_comprobante`, `venta`, `detalle_venta`, `anulacion_venta` | Facturación de boletas/facturas, cálculo de IGV, control de correlativos y anulaciones por Administrador. |
| **7. Pagos** | `metodo_pago`, `pago_venta`, `efectivo`, `tarjeta`, `billetera_digital_sip` | Pasarela multipago, detalle de efectivo con vuelto, cobros con tarjeta y billetera digital Sip. |

---

## 2. Diccionario de Datos Detallado (29 Tablas)

### Módulo 1: Ubicación y Organización

#### `distrito`
Distritos de la provincia de Arequipa donde se ubican las tiendas.
- `id_distrito` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `nombre` (VARCHAR(60), NOT NULL, UNIQUE)

#### `tienda`
Sedes físicas de Tienda Mass, una por distrito (RN-01).
- `id_tienda` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_distrito` (INT UNSIGNED, NOT NULL, FK → `distrito.id_distrito`, UNIQUE)
- `codigo` (VARCHAR(10), NOT NULL, UNIQUE)
- `nombre` (VARCHAR(80), NOT NULL)
- `direccion` (VARCHAR(150), NOT NULL)
- `estado` (VARCHAR(10), NOT NULL, DEFAULT 'ACTIVA', CHECK IN ('ACTIVA', 'INACTIVA'))

#### `rol`
Roles de colaboradores: ADMINISTRADOR (Jefe de tienda), CAJERO, ALMACENERO (RN-02, RN-03).
- `id_rol` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `nombre` (VARCHAR(20), NOT NULL, UNIQUE, CHECK IN ('ADMINISTRADOR', 'CAJERO', 'ALMACENERO'))

#### `usuario`
Personal de tienda asignado a una sede y con un rol específico (RN-02).
- `id_usuario` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_tienda` (INT UNSIGNED, NOT NULL, FK → `tienda.id_tienda`)
- `id_rol` (INT UNSIGNED, NOT NULL, FK → `rol.id_rol`)
- `nombres` (VARCHAR(80), NOT NULL)
- `apellidos` (VARCHAR(80), NOT NULL)
- `dni` (CHAR(8), NOT NULL, UNIQUE, CHECK REGEXP '^[0-9]{8}$')
- `login` (VARCHAR(40), NOT NULL, UNIQUE)
- `clave_hash` (VARCHAR(255), NOT NULL)
- `estado` (VARCHAR(10), NOT NULL, DEFAULT 'ACTIVO', CHECK IN ('ACTIVO', 'INACTIVO'))

---

### Módulo 2: Clientes

#### `tipo_documento_identidad`
Catálogo de documentos de identidad oficiales (RN-04, RN-06).
- `id_tipo_documento` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `codigo` (VARCHAR(5), NOT NULL, UNIQUE, CHECK IN ('DNI', 'RUC'))
- `nombre` (VARCHAR(60), NOT NULL)
- `longitud` (TINYINT UNSIGNED, NOT NULL, CHECK IN (8, 11))

#### `cliente`
Datos de clientes para comprobantes. Opcional en boletas, obligatorio en facturas (RN-04, RN-05).
- `id_cliente` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_tipo_documento` (INT UNSIGNED, NOT NULL, FK → `tipo_documento_identidad.id_tipo_documento`)
- `numero_documento` (VARCHAR(11), NOT NULL)
- `nombre_razon_social` (VARCHAR(150), NOT NULL)
- `direccion` (VARCHAR(150), NULL)
- `telefono` (VARCHAR(15), NULL)
- `correo` (VARCHAR(100), NULL)
- `estado` (VARCHAR(10), NOT NULL, DEFAULT 'ACTIVO', CHECK IN ('ACTIVO', 'INACTIVO'))
- UNIQUE (`id_tipo_documento`, `numero_documento`)

---

### Módulo 3: Catálogo de Productos

#### `categoria`
Clasificación de familias de productos (Abarrotes, Lácteos, Bebidas, Limpieza, etc.).
- `id_categoria` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `nombre` (VARCHAR(60), NOT NULL, UNIQUE)
- `descripcion` (VARCHAR(150), NULL)

#### `marca`
Marcas comerciales, distinguiendo si son marcas propias de la cadena (Mass, Bell's) o de terceros (S/N).
- `id_marca` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `nombre` (VARCHAR(60), NOT NULL, UNIQUE)
- `es_marca_propia` (CHAR(1), NOT NULL, DEFAULT 'N', CHECK IN ('S', 'N'))

#### `unidad_medida`
Unidades físicas de comercialización (Unidad, Kilogramo, Litro, Paquete).
- `id_unidad` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `codigo` (VARCHAR(5), NOT NULL, UNIQUE)
- `nombre` (VARCHAR(40), NOT NULL)

#### `producto`
Catálogo unificado de artículos con precio único para todas las tiendas, IGV incluido (RN-07, RN-08).
- `id_producto` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_categoria` (INT UNSIGNED, NOT NULL, FK → `categoria.id_categoria`)
- `id_marca` (INT UNSIGNED, NOT NULL, FK → `marca.id_marca`)
- `id_unidad` (INT UNSIGNED, NOT NULL, FK → `unidad_medida.id_unidad`)
- `codigo_barras` (VARCHAR(20), NOT NULL, UNIQUE)
- `nombre` (VARCHAR(120), NOT NULL)
- `precio_venta` (DECIMAL(10,2), NOT NULL, CHECK > 0)
- `estado` (VARCHAR(10), NOT NULL, DEFAULT 'ACTIVO', CHECK IN ('ACTIVO', 'INACTIVO'))

---

### Módulo 4: Inventario

#### `stock`
Existencias físicas por tienda y producto; nunca negativas (RN-09, RN-10).
- `id_tienda` (INT UNSIGNED, NOT NULL, FK → `tienda.id_tienda`)
- `id_producto` (INT UNSIGNED, NOT NULL, FK → `producto.id_producto`)
- `cantidad` (DECIMAL(12,3), NOT NULL, DEFAULT 0, CHECK >= 0)
- `stock_minimo` (DECIMAL(12,3), NOT NULL, DEFAULT 0, CHECK >= 0)
- PRIMARY KEY (`id_tienda`, `id_producto`)

#### `tipo_movimiento`
Catálogo de tipos de movimientos de inventario con su efecto neto: Entrada (E) o Salida (S) (RN-12).
- `id_tipo_movimiento` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `codigo` (VARCHAR(20), NOT NULL, UNIQUE)
- `nombre` (VARCHAR(60), NOT NULL)
- `efecto` (CHAR(1), NOT NULL, CHECK IN ('E', 'S'))

#### `movimiento_stock`
Kardex que audita cada entrada o salida de inventario con su documento origen (RN-11, RN-12).
- `id_movimiento` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_tienda` (INT UNSIGNED, NOT NULL, FK → `tienda.id_tienda`)
- `id_producto` (INT UNSIGNED, NOT NULL, FK → `producto.id_producto`)
- `id_tipo_movimiento` (INT UNSIGNED, NOT NULL, FK → `tipo_movimiento.id_tipo_movimiento`)
- `id_usuario` (INT UNSIGNED, NOT NULL, FK → `usuario.id_usuario`)
- `cantidad` (DECIMAL(12,3), NOT NULL, CHECK > 0)
- `stock_resultante` (DECIMAL(12,3), NOT NULL, CHECK >= 0)
- `fecha_hora` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- `id_venta` (INT UNSIGNED, NULL, FK → `venta.id_venta`)
- `id_traslado` (INT UNSIGNED, NULL, FK → `traslado_mercaderia.id_traslado`)
- `id_merma` (INT UNSIGNED, NULL, FK → `merma.id_merma`)
- `observacion` (VARCHAR(200), NULL)

#### `traslado_mercaderia`
Traslado físico de mercadería entre dos tiendas distintas (RN-13).
- `id_traslado` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_tienda_origen` (INT UNSIGNED, NOT NULL, FK → `tienda.id_tienda`)
- `id_tienda_destino` (INT UNSIGNED, NOT NULL, FK → `tienda.id_tienda`)
- `id_usuario` (INT UNSIGNED, NOT NULL, FK → `usuario.id_usuario`)
- `fecha_hora` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- `observacion` (VARCHAR(200), NULL)
- CHECK (`id_tienda_origen` <> `id_tienda_destino`)

#### `detalle_traslado`
Productos y cantidades trasladadas entre tiendas.
- `id_detalle_traslado` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_traslado` (INT UNSIGNED, NOT NULL, FK → `traslado_mercaderia.id_traslado`)
- `id_producto` (INT UNSIGNED, NOT NULL, FK → `producto.id_producto`)
- `cantidad` (DECIMAL(12,3), NOT NULL, CHECK > 0)
- UNIQUE (`id_traslado`, `id_producto`)

#### `merma`
Registro de bajas de mercadería por vencimiento, deterioro, rotura o robo (RN-14).
- `id_merma` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_tienda` (INT UNSIGNED, NOT NULL, FK → `tienda.id_tienda`)
- `id_usuario` (INT UNSIGNED, NOT NULL, FK → `usuario.id_usuario`)
- `fecha_hora` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- `motivo` (VARCHAR(20), NOT NULL, CHECK IN ('VENCIMIENTO', 'DETERIORO', 'ROBO', 'OTRO'))
- `observacion` (VARCHAR(200), NULL)

#### `detalle_merma`
Productos y cantidades dadas de baja en cada merma.
- `id_detalle_merma` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_merma` (INT UNSIGNED, NOT NULL, FK → `merma.id_merma`)
- `id_producto` (INT UNSIGNED, NOT NULL, FK → `producto.id_producto`)
- `cantidad` (DECIMAL(12,3), NOT NULL, CHECK > 0)
- UNIQUE (`id_merma`, `id_producto`)

---

### Módulo 5: Cajas

#### `caja`
Puntos de cobro físicos (estaciones POS) habilitados en la tienda (RN-15).
- `id_caja` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_tienda` (INT UNSIGNED, NOT NULL, FK → `tienda.id_tienda`)
- `numero` (TINYINT UNSIGNED, NOT NULL)
- `descripcion` (VARCHAR(60), NULL)
- `estado` (VARCHAR(10), NOT NULL, DEFAULT 'ACTIVA', CHECK IN ('ACTIVA', 'INACTIVA'))
- UNIQUE (`id_tienda`, `numero`)

#### `caja_turno`
Apertura, fondo inicial de sencillo y cierre de caja por sesión de un cajero (RN-16, RN-17).
- `id_caja_turno` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_caja` (INT UNSIGNED, NOT NULL, FK → `caja.id_caja`)
- `id_usuario` (INT UNSIGNED, NOT NULL, FK → `usuario.id_usuario`)
- `fecha_apertura` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- `monto_apertura` (DECIMAL(12,2), NOT NULL, DEFAULT 0, CHECK >= 0)
- `fecha_cierre` (DATETIME, NULL)
- `monto_cierre` (DECIMAL(12,2), NULL, CHECK >= 0)
- `estado` (VARCHAR(10), NOT NULL, DEFAULT 'ABIERTO', CHECK IN ('ABIERTO', 'CERRADO'))
- `abierto_caja` (INT UNSIGNED AS (CASE WHEN estado = 'ABIERTO' THEN id_caja END) VIRTUAL)
- `abierto_usuario` (INT UNSIGNED AS (CASE WHEN estado = 'ABIERTO' THEN id_usuario END) VIRTUAL)
- UNIQUE (`abierto_caja`) (RN-17: una caja solo admite un turno abierto a la vez)
- UNIQUE (`abierto_usuario`) (RN-17: un cajero solo admite un turno abierto a la vez)


---

### Módulo 6: Ventas y Comprobantes

#### `tipo_comprobante`
Definición de comprobantes: Boleta de venta (serie B) o Factura (serie F) (RN-18).
- `id_tipo_comprobante` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `codigo` (VARCHAR(10), NOT NULL, UNIQUE, CHECK IN ('BOLETA', 'FACTURA'))
- `nombre` (VARCHAR(40), NOT NULL)
- `prefijo_serie` (CHAR(1), NOT NULL, UNIQUE, CHECK IN ('B', 'F'))

#### `serie_comprobante`
Series correlativas por tienda y tipo con seguimiento del último correlativo (RN-19).
- `id_serie` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_tienda` (INT UNSIGNED, NOT NULL, FK → `tienda.id_tienda`)
- `id_tipo_comprobante` (INT UNSIGNED, NOT NULL, FK → `tipo_comprobante.id_tipo_comprobante`)
- `serie` (VARCHAR(4), NOT NULL, UNIQUE)
- `ultimo_correlativo` (INT UNSIGNED, NOT NULL, DEFAULT 0)
- UNIQUE (`id_tienda`, `id_tipo_comprobante`)

#### `venta`
Cabecera de transacción comercial: subtotal, IGV y total; estado EMITIDA o ANULADA (RN-19 a RN-22).
- `id_venta` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_serie` (INT UNSIGNED, NOT NULL, FK → `serie_comprobante.id_serie`)
- `correlativo` (INT UNSIGNED, NOT NULL, DEFAULT 0)
- `id_caja_turno` (INT UNSIGNED, NOT NULL, FK → `caja_turno.id_caja_turno`)
- `id_usuario` (INT UNSIGNED, NOT NULL, FK → `usuario.id_usuario`)
- `id_cliente` (INT UNSIGNED, NULL, FK → `cliente.id_cliente`)
- `fecha_hora` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- `subtotal` (DECIMAL(12,2), NOT NULL, CHECK >= 0)
- `igv` (DECIMAL(12,2), NOT NULL, CHECK >= 0)
- `total` (DECIMAL(12,2), NOT NULL, CHECK > 0)
- `estado` (VARCHAR(10), NOT NULL, DEFAULT 'EMITIDA', CHECK IN ('EMITIDA', 'ANULADA'))
- UNIQUE (`id_serie`, `correlativo`)
- CHECK (`total` = `subtotal` + `igv`)

#### `detalle_venta`
Productos, cantidades (unidades o kilogramos) y precios de cada venta (RN-21, RN-22).
- `id_detalle_venta` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_venta` (INT UNSIGNED, NOT NULL, FK → `venta.id_venta`)
- `id_producto` (INT UNSIGNED, NOT NULL, FK → `producto.id_producto`)
- `cantidad` (DECIMAL(12,3), NOT NULL, CHECK > 0)
- `precio_unitario` (DECIMAL(10,2), NOT NULL, CHECK > 0)
- `total_linea` (DECIMAL(12,2), NOT NULL)
- UNIQUE (`id_venta`, `id_producto`)
- CHECK (`total_linea` = ROUND(`cantidad` * `precio_unitario`, 2))

#### `anulacion_venta`
Registro de anulación de una venta, autorizada únicamente por el Administrador (RN-23).
- `id_anulacion` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_venta` (INT UNSIGNED, NOT NULL, FK → `venta.id_venta`, UNIQUE)
- `id_usuario` (INT UNSIGNED, NOT NULL, FK → `usuario.id_usuario`)
- `fecha_hora` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- `motivo` (VARCHAR(200), NOT NULL)

---

### Módulo 7: Pagos

#### `metodo_pago`
Métodos de pago autorizados: Efectivo, Tarjeta o Billetera digital Sip (RN-24, RN-25).
- `id_metodo_pago` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `codigo` (VARCHAR(10), NOT NULL, UNIQUE, CHECK IN ('EFECTIVO', 'TARJETA', 'SIP'))
- `nombre` (VARCHAR(40), NOT NULL)
- `estado` (VARCHAR(10), NOT NULL, DEFAULT 'ACTIVO', CHECK IN ('ACTIVO', 'INACTIVO'))

#### `pago_venta`
Registro de cada pago o abono aplicado a una venta (soporta pagos combinados: RN-24, RN-28).
- `id_pago` (INT UNSIGNED, AUTO_INCREMENT, PK)
- `id_venta` (INT UNSIGNED, NOT NULL, FK → `venta.id_venta`)
- `id_metodo_pago` (INT UNSIGNED, NOT NULL, FK → `metodo_pago.id_metodo_pago`)
- `monto` (DECIMAL(12,2), NOT NULL, CHECK > 0)
- `fecha_hora` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- `estado` (VARCHAR(10), NOT NULL, DEFAULT 'APLICADO', CHECK IN ('APLICADO', 'ANULADO'))

#### `efectivo`
Subtipo de pago en efectivo: monto recibido y vuelto entregado (RN-27).
- `id_pago` (INT UNSIGNED, PK, FK → `pago_venta.id_pago`)
- `monto_recibido` (DECIMAL(12,2), NOT NULL, CHECK > 0)
- `vuelto` (DECIMAL(12,2), NOT NULL, DEFAULT 0, CHECK >= 0 AND `vuelto` < `monto_recibido`)

#### `tarjeta`
Subtipo de pago con tarjeta bancaria; almacena únicamente los últimos 4 dígitos según norma PCI.
- `id_pago` (INT UNSIGNED, PK, FK → `pago_venta.id_pago`)
- `tipo_tarjeta` (VARCHAR(10), NOT NULL, CHECK IN ('DEBITO', 'CREDITO'))
- `marca` (VARCHAR(20), NOT NULL, CHECK IN ('VISA', 'MASTERCARD', 'AMERICAN_EXPRESS', 'DINERS', 'OTRA'))
- `ultimos_4` (CHAR(4), NOT NULL, CHECK REGEXP '^[0-9]{4}$')
- `codigo_autorizacion` (VARCHAR(20), NOT NULL)

#### `billetera_digital_sip`
Subtipo de pago mediante la billetera digital Sip por código QR o Tarjeta virtual Sip (RN-25, RN-26).
- `id_pago` (INT UNSIGNED, PK, FK → `pago_venta.id_pago`)
- `modalidad` (VARCHAR(15), NOT NULL, CHECK IN ('QR', 'TARJETA_SIP'))
- `codigo_operacion` (VARCHAR(30), NOT NULL, UNIQUE)

---

## 3. Objetos de Programación Almacenados

| Objeto | Tipo | Propósito |
|---|---|---|
| `sp_mover_stock` | Procedimiento | Único punto para alterar el inventario: valida existencias, actualiza `stock` y genera el registro en `movimiento_stock`. |
| `fn_codigo_metodo_pago` | Función | Retorna el código textual (`EFECTIVO`, `TARJETA`, `SIP`) a partir de un `id_pago`. |
| `sp_valida_documento_cliente` | Procedimiento | Valida la longitud exacta del documento del cliente según su tipo (DNI = 8, RUC = 11). |
| `sp_valida_prefijo_serie` | Procedimiento | Valida que las series comiencen con el prefijo oficial de su comprobante (`B` o `F`). |
| `sp_valida_factura` | Procedimiento | Comprueba que toda venta con factura esté vinculada a un cliente con documento RUC. |
| `sp_valida_efectivo` | Procedimiento | Verifica que el vuelto entregado sea exactamente igual a la diferencia: `monto_recibido - monto_pago`. |
| `sp_anular_venta` | Procedimiento | Proceso seguro de anulación: valida Administrador, registra `anulacion_venta`, anula pagos y retorna existencias. |
| `vw_control_ventas` | Vista | Audita cada venta comparando su cabecera con el detalle y los pagos aplicados (`OK`, `DESCUADRE` o `ANULADA`). |
| 15 Triggers | Triggers | Ejecución automática de las reglas de negocio sobre inserciones, modificaciones y borrados en las tablas clave. |

---

## 4. Reglas de Negocio Implementadas

1. **RN-01 (Una tienda por distrito):** Restricción de unicidad en `tienda.id_distrito`.
2. **RN-02 (Asignación de personal):** Todo usuario pertenece obligatoriamente a una tienda física y a un rol funcional.
3. **RN-03 (Administrador como Jefe de tienda):** El rol `ADMINISTRADOR` representa a la máxima autoridad operativa de la sede.
4. **RN-04 (Factura con RUC):** Las ventas emitidas con factura exigen asociar un cliente con documento RUC de 11 dígitos.
5. **RN-05 (Cliente anónimo en boleta):** Las boletas de venta permiten asociar `cliente.id_cliente NULL` (público general).
6. **RN-06 (Longitud documental estricta):** El DNI debe contener exactamente 8 dígitos y el RUC 11 dígitos numéricos.
7. **RN-07 (Precio único central):** Cada artículo tiene un único `precio_venta` para todas las tiendas, con IGV incluido.
8. **RN-08 (Desglose fiscal constante de IGV 18%):** Toda venta registra `subtotal`, `igv` y `total` respetando `total = subtotal + igv`.
9. **RN-09 (Segregación de existencias):** El inventario se controla de forma independiente por local en `stock(id_tienda, id_producto)`.
10. **RN-10 (No stock negativo):** Ninguna transacción puede reducir la cantidad disponible de un producto por debajo de cero.
11. **RN-11 (Kardex obligatorio):** Toda variación física de existencias genera un registro inmutable en `movimiento_stock`.
12. **RN-12 (Efecto de movimiento):** Cada movimiento clasifica formalmente su impacto como Entrada (`E`) o Salida (`S`).
13. **RN-13 (Traslados intertiendas):** El origen y destino de un traslado deben ser obligatoriamente tiendas distintas.
14. **RN-14 (Control de mermas):** Las bajas por vencimiento o deterioro registran el motivo, usuario responsable y descuentan stock.
15. **RN-15 (Estaciones POS):** Las cajas físicas pertenecen a una sede y están numeradas de forma única por tienda.
16. **RN-16 (Turno de caja obligatorio):** Solo un cajero de la misma tienda puede abrir turno; no se puede vender sin turno abierto.
17. **RN-17 (Exclusividad de turno):** Una caja y un cajero no pueden mantener más de un turno en estado `ABIERTO` simultáneamente.
18. **RN-18 (Prefijo de serie fiscal):** Las series de boletas inician con la letra `B` y las de factura con la letra `F`.
19. **RN-19 (Correlativo correlativo y único):** El correlativo se autoincrementa por serie y es inmutable (`UNIQUE(id_serie, correlativo)`).
20. **RN-20 (Inmutabilidad de ventas):** Está terminantemente prohibido borrar ventas emitidas mediante `DELETE` (bloqueado por trigger).
21. **RN-21 (Descuento de stock en venta):** Cada línea de `detalle_venta` rebaja existencias en la tienda correspondiente vía kardex.
22. **RN-22 (Consistencia de importes):** La sumatoria de las líneas del detalle debe ser idéntica al importe total de la venta.
23. **RN-23 (Autorización de anulación):** Solo un usuario con rol `ADMINISTRADOR` de la tienda puede registrar la anulación de una venta.
24. **RN-24 (Cobro total):** Una venta se considera pagada cuando la suma de pagos aplicados cubre la totalidad de la venta.
25. **RN-25 (Medios de pago autorizados):** Tienda Mass no acepta transferencias informales (Yape/Plin no se modelan); solo Efectivo, Tarjeta y Sip.
26. **RN-26 (Billetera Sip):** Soporta transacciones vía código QR y Tarjeta Sip con código de operación único.
27. **RN-27 (Vuelto exacto en efectivo):** El vuelto registrado debe satisfacer: `monto_aplicado = monto_recibido - vuelto`.
28. **RN-28 (Pagos combinados):** Una venta puede liquidarse mediante múltiples abonos en distintos métodos de pago.
