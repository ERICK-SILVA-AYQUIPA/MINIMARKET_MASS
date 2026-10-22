# Sistema Minimarket Mass
Proyecto de base de datos relacional y lógica de negocio para la administración de ventas, inventario y facturación de la cadena Minimarket Mass en Arequipa.
Desarrollado como entrega académica para el curso Backend Developer Web en SENATI CFP Arequipa.

## Integrantes del Grupo
- ERICK SILVA AYQUIPA
- Gerald Anderson Condori Quispe
- Alexander Emmanuel Moreno Flores
- Jhon Alexandro Apaza Belizario
- Suhaily Luana Huaypar Quijhua
- Keymer Cesar Machaca Puma

## Archivos del Repositorio
- `database/schema.sql`: Script DDL que crea la base de datos `minimarket_mass` y sus 29 tablas, procedimientos, funciones, triggers y vista de control.
- `database/datos.sql`: Script DML con datos iniciales de catálogos, sedes, personal, catálogo de productos, existencias y operaciones de prueba.
- `database/consultas.sql`: Consultas de verificación del modelo, reportes de ventas, arqueos de caja y pruebas de validación de reglas de negocio.
- `database/boleta.sql`: Consulta estructurada que emite de forma formateada una boleta de venta electrónica completa con desglose tributario y pagos.
- `docs/diagrama-er.png`: Diagrama Entidad-Relación visual de alta resolución con las 29 entidades normalizadas y sus relaciones de clave foránea.

## Tablas del Diseño

| N° | Tabla | Descripción |
|---|---|---|
| 1 | `distrito` | Catálogo de distritos de la provincia de Arequipa donde se ubican las tiendas de la cadena. |
| 2 | `tienda` | Sedes físicas de Tienda Mass, una por distrito (RN-01). |
| 3 | `rol` | Roles del personal: Administrador (Jefe de tienda), Cajero y Almacenero (RN-02, RN-03). |
| 4 | `usuario` | Personal colaborador asignado a una tienda con su rol y credenciales de acceso (RN-02). |
| 5 | `tipo_documento_identidad` | Catálogo de documentos admitidos: DNI (8 dígitos) o RUC (11 dígitos) (RN-04, RN-06). |
| 6 | `cliente` | Datos de clientes para emisión fiscal; opcional en boleta y obligatorio en factura (RN-04, RN-05). |
| 7 | `categoria` | Familias comerciales para clasificación del catálogo (Abarrotes, Lácteos, Bebidas, etc.). |
| 8 | `marca` | Marcas de productos, distinguiendo marcas propias de la cadena frente a las de terceros. |
| 9 | `unidad_medida` | Unidades de cuantificación para comercialización e inventario (unidad, kilogramo, litro). |
| 10 | `producto` | Catálogo general de artículos con precio único para todas las tiendas e IGV incluido (RN-07, RN-08). |
| 11 | `stock` | Control de existencias físicas disponibles y stock mínimo por tienda y producto; nunca negativo (RN-09, RN-10). |
| 12 | `tipo_movimiento` | Tipos de movimiento de stock catalogados por su efecto de entrada o salida (RN-12). |
| 13 | `movimiento_stock` | Kardex inmutable que audita cada variación de existencias con su documento de origen (RN-11, RN-12). |
| 14 | `traslado_mercaderia` | Registro de traslados de mercadería entre dos sedes físicas distintas (RN-13). |
| 15 | `detalle_traslado` | Artículos y cantidades asignadas a cada traslado de mercadería intertiendas. |
| 16 | `merma` | Registro de bajas de mercadería causadas por vencimiento, deterioro, rotura o robo (RN-14). |
| 17 | `detalle_merma` | Artículos y cantidades exactas dadas de baja en cada evento de merma. |
| 18 | `caja` | Puntos de cobro físicos (estaciones POS) habilitados en cada tienda (RN-15). |
| 19 | `caja_turno` | Registro de apertura, fondo de sencillo inicial y cierre de caja por sesión de un cajero (RN-16, RN-17). |
| 20 | `tipo_comprobante` | Comprobantes autorizados: Boleta de venta (serie B) o Factura electrónica (serie F) (RN-18). |
| 21 | `serie_comprobante` | Series por tienda y tipo de comprobante con seguimiento correlativo secuencial (RN-19). |
| 22 | `venta` | Cabecera de venta con importes (subtotal, IGV y total); estado emitida o anulada (RN-19 a RN-22). |
| 23 | `detalle_venta` | Artículos, cantidades (por unidad o kilogramo), precio unitario e importe de cada venta (RN-21, RN-22). |
| 24 | `anulacion_venta` | Auditoría de anulación de una venta con motivo formal, autorizada por el Administrador (RN-23). |
| 25 | `metodo_pago` | Medios de pago aceptados en caja: Efectivo, Tarjeta y Billetera digital Sip (RN-24, RN-25). |
| 26 | `pago_venta` | Registro de pagos aplicados a una venta para soportar pagos completos o combinados (RN-24, RN-28). |
| 27 | `efectivo` | Detalle de pagos en efectivo con registro de monto recibido y vuelto entregado (RN-27). |
| 28 | `tarjeta` | Detalle de cobros con tarjeta bancaria, resguardando datos con solo los últimos 4 dígitos. |
| 29 | `billetera_digital_sip` | Detalle de cobros mediante la billetera digital Sip por código QR o tarjeta Sip (RN-25, RN-26). |

## Reglas de Negocio

| Código | Regla | Implementación en la Base de Datos |
|---|---|---|
| **RN-01** | Una tienda por distrito | Restricción `UNIQUE` en la columna `tienda.id_distrito`. |
| **RN-02** | Asignación de colaboradores | Todo personal está vinculado obligatoriamente a una tienda (`id_tienda`) y a un rol (`id_rol`). |
| **RN-03** | Administrador como Jefe de tienda | El rol `ADMINISTRADOR` ejerce la jefatura del local, responsable exclusivo de autorizaciones de anulación. |
| **RN-04** | Factura electrónica con RUC | Toda venta con serie de factura exige asociar un cliente con documento tipo RUC (11 dígitos). |
| **RN-05** | Cliente anónimo en boleta | Las boletas de venta permiten registrar `cliente.id_cliente NULL` (público general). |
| **RN-06** | Validación de formato de documento | El catálogo `tipo_documento_identidad` y los triggers validan DNI con 8 dígitos y RUC con 11 dígitos. |
| **RN-07** | Precio centralizado único | El catálogo `producto` define un precio único de venta válido en todas las tiendas con IGV incluido. |
| **RN-08** | Desglose constante de IGV 18% | En cada venta se registra `subtotal`, `igv` y `total`, validando la ecuación fiscal `total = subtotal + igv`. |
| **RN-09** | Existencias por sede física | El inventario se segrega por tienda y producto mediante la clave compuesta de la tabla `stock(id_tienda, id_producto)`. |
| **RN-10** | Inventario nunca negativo | Ninguna salida de stock puede reducir la cantidad disponible de un artículo por debajo de cero (`cantidad >= 0`). |
| **RN-11** | Kardex inmutable | Toda entrada o salida de inventario genera un registro de auditoría permanente en `movimiento_stock`. |
| **RN-12** | Efecto neto del movimiento | Cada movimiento clasifica obligatoriamente su impacto operativo como Entrada (`E`) o Salida (`S`). |
| **RN-13** | Traslado entre sedes distintas | El origen y destino de un traslado intertiendas deben ser obligatoriamente tiendas diferentes (`origen <> destino`). |
| **RN-14** | Control estricto de mermas | Las bajas por vencimiento, deterioro o robo registran el motivo, responsable y descuentan stock. |
| **RN-15** | Estaciones de cobro físicas | Las cajas físicas pertenecen a una sede y están numeradas de forma única por tienda (`UNIQUE(id_tienda, numero)`). |
| **RN-16** | Turno de caja obligatorio | No se pueden procesar ventas sin un turno en estado `ABIERTO` asignado al cajero en sesión. |
| **RN-17** | Exclusividad de turno operativo | Una caja y un cajero no pueden mantener más de un turno en estado `ABIERTO` en simultáneo. |
| **RN-18** | Prefijo de comprobante fiscal | Las series de boletas inician obligatoriamente con `B` y las de factura inician con `F`. |
| **RN-19** | Correlativo único y secuencial | El correlativo numérico se autoincrementa por serie sin duplicidad (`UNIQUE(id_serie, correlativo)`). |
| **RN-20** | Prohibición de eliminación física | Las ventas emitidas no pueden borrarse físicamente de la base de datos (trigger `trg_venta_bd` bloquea `DELETE`). |
| **RN-21** | Descuento automático de stock | Cada artículo vendido descuenta existencias en la tienda correspondiente mediante el kardex. |
| **RN-22** | Consistencia de importes | La sumatoria de las líneas de `detalle_venta` debe coincidir exactamente con el campo `total` de cabecera. |
| **RN-23** | Autorización de anulación | Solo un usuario con rol `ADMINISTRADOR` de la tienda puede registrar la `anulacion_venta` mediante `sp_anular_venta`. |
| **RN-24** | Cobertura total de pago | La venta se liquida cuando la sumatoria de abonos en `pago_venta` cubre el importe total de la venta. |
| **RN-25** | Medios de pago autorizados | Tienda Mass no admite transferencias informales (Yape/Plin); solo acepta Efectivo, Tarjeta y Billetera Sip. |
| **RN-26** | Billetera digital Sip | Soporta transacciones mediante código QR o tarjeta Sip vinculadas a su código de operación único. |
| **RN-27** | Vuelto en efectivo exacto | En pagos en efectivo, el monto devuelto satisface estrictamente: `monto_aplicado = monto_recibido - vuelto`. |
| **RN-28** | Pagos fraccionados y mixtos | Una venta puede cancelarse mediante múltiples abonos combinando distintos métodos de pago. |

## Cómo Crear la Base de Datos

Para desplegar la base de datos en Windows utilizando PowerShell y el servicio MySQL de Laragon (puerto 3307), ejecuta los siguientes comandos en orden. Reemplaza `C:/ruta/al/repo` por la ruta absoluta de tu clon local del repositorio.

1. Crear la base de datos, tablas, procedimientos y triggers:
```powershell
mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/schema.sql"
```

2. Cargar los datos iniciales y transacciones de prueba:
```powershell
mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/datos.sql"
```

3. Ejecutar las consultas de verificación y reportes de negocio:
```powershell
mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/consultas.sql"
```

4. Consultar la emisión formateada de la boleta de venta electrónica:
```powershell
mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/boleta.sql"
```

5. Comprobar las tablas creadas en MySQL:
```powershell
mysql -u root -h 127.0.0.1 -P 3307 -e "USE minimarket_mass; SHOW TABLES;"
```

## Decisiones de Diseño

- **Traducción integral de Oracle a MySQL 8.0+:** El esquema parte del DDL original del grupo desarrollado en Oracle SQL Developer Data Modeler y fue adaptado a MySQL con motor `InnoDB` transaccional y juego de caracteres `utf8mb4_unicode_ci`.
- **Precios e IGV incluido:** El IGV del 18 % es constante y está incluido en el precio de venta al público en `producto`. La venta desglosa `subtotal`, `igv` y `total` sin requerir una tabla adicional de impuestos.
- **Medios de pago autorizados de Tienda Mass:** Solo se modelan Efectivo, Tarjeta bancaria y la billetera digital Sip propia de la cadena. No se contemplan transferencias informales (Yape/Plin) al no ser admitidas en caja.
- **Segregación de existencias e inmutabilidad:** El inventario se controla mediante `stock` con clave primaria compuesta `(id_tienda, id_producto)`. Todo cambio impacta obligatoriamente en `movimiento_stock` (kardex) mediante el procedimiento centralizado `sp_mover_stock`.
- **Inmutabilidad de ventas y auditoría:** Se prohíbe el borrado físico (`DELETE`) de ventas emitidas mediante un trigger restrictivo (`trg_venta_bd`). La anulación se gestiona exclusivamente a través de `sp_anular_venta` y queda registrada en `anulacion_venta`, revirtiendo pagos y reponiendo el inventario.
- **Especialización de pagos por subtipos:** La entidad `pago_venta` centraliza la relación con la venta, mientras que `efectivo`, `tarjeta` y `billetera_digital_sip` actúan como subtipos compartiendo el mismo `id_pago` como clave primaria y foránea simultánea.
