# Minimarket Mass - Sistema de Base de Datos y Facturación
Sistema relacional y lógica de negocio para la administración de ventas, inventario y facturación de la cadena Minimarket Mass en Arequipa (SENATI CFP Arequipa).

## Integrantes del Grupo
- ERICK SILVA AYQUIPA
- Gerald Anderson Condori Quispe
- Alexander Emmanuel Moreno Flores
- Jhon Alexandro Apaza Belizario
- Suhaily Luana Huaypar Quijhua
- Keymer Cesar Machaca Puma

## Estructura del Repositorio
El repositorio cuenta con una arquitectura modular en scripts SQL y documentación técnica:

- **`database/schema.sql`**: Script DDL que crea la base de datos `minimarket_mass` y sus **26 tablas relacionales** organizadas en 7 niveles de dependencia, con claves foráneas, restricciones de unicidad y validaciones `CHECK`.
- **`database/datos.sql`**: Script DML con datos de prueba realistas (3 tiendas en Arequipa: Cayma, Cerro Colorado y Yanahuara, personal, catálogo de productos, existencias en stock, ventas, turnos de caja y auditoría en kardex).
- **`database/consultas.sql`**: Consultas comerciales categorizadas que resuelven preguntas clave del negocio (catálogo, personal, inventario y reposición, ventas, facturación y arqueo de cajas).
- **`database/boleta.sql`**: Vista `vw_comprobante` y procedimiento almacenado `sp_boleta(serie, correlativo)` para generar la impresión completa del comprobante (cabecera, detalle y desglose de pagos).
- **`docs/informe_tecnico.md`**: Informe técnico académico completo del proyecto (análisis del negocio, 5 diagramas UML, justificación de diseño 3FN, diccionario de datos, reglas de negocio y conclusiones).
- **`docs/informe_tecnico.html`**: Versión web e imprimible del informe técnico en formato ejecutivo profesional.
- **`docs/diseno_bd.md`**: Especificación técnica completa, diccionario de datos y detalle de reglas de negocio para las 26 tablas.
- **`docs/diagrama-er.png`**: Diagrama Entidad-Relación visual de alta resolución (sin colisiones en relaciones).

---

## Diagrama Entidad-Relación (EER)

![Diagrama Entidad-Relación](./docs/diagrama-er.png)

---

## Tablas del Sistema (26 Tablas en 7 Niveles)

El diseño está optimizado y estructurado por niveles de integridad referencial:

| N° | Nivel | Tabla | Descripción |
|---|---|---|---|
| 1 | **Nivel 1** | `distrito` | Distritos de Arequipa con sedes de Tiendas Mass (Cayma, Cerro Colorado, Yanahuara). |
| 2 | **Nivel 1** | `rol` | Roles operativos del personal: ADMINISTRADOR, CAJERO y ALMACENERO. |
| 3 | **Nivel 1** | `cliente` | Registro de clientes con validación estricta de DNI (8 dígitos) y/o RUC (11 dígitos). |
| 4 | **Nivel 1** | `categoria` | Familias del catálogo (Abarrotes, Lácteos, Bebidas, Frutas y verduras, Limpieza). |
| 5 | **Nivel 1** | `marca` | Marcas comerciales, identificando si es marca propia de la cadena (S/N). |
| 6 | **Nivel 1** | `unidad_medida` | Unidades de cuantificación (unidad `und`, kilogramo `kg`, litro `lt`). |
| 7 | **Nivel 1** | `tipo_comprobante` | Comprobantes de pago autorizados: BOLETA (serie B) y FACTURA (serie F). |
| 8 | **Nivel 1** | `metodo_pago` | Medios de pago aceptados: EFECTIVO, TARJETA y BILLETERA DIGITAL SIP. |
| 9 | **Nivel 1** | `tipo_movimiento` | Catálogo de tipos de kardex con efecto de Entrada (`E`) o Salida (`S`). |
| 10 | **Nivel 2** | `tienda` | Sedes físicas de Tienda Mass en Arequipa vinculadas a su respectivo distrito. |
| 11 | **Nivel 2** | `producto` | Catálogo con precio único unificado para todas las tiendas, código de barras y venta por peso. |
| 12 | **Nivel 3** | `empleado` | Personal con DNI de acceso, tienda asignada y rol operativo. |
| 13 | **Nivel 3** | `caja` | Puntos de venta físicos (cajas registradoras POS) por tienda. |
| 14 | **Nivel 3** | `serie_comprobante` | Series fiscales por tienda (`B001`, `F001`, etc.) y control de su último correlativo emitido. |
| 15 | **Nivel 3** | `stock` | Existencias actuales y stock mínimo por tienda y producto (clave compuesta `id_tienda, id_producto`). |
| 16 | **Nivel 4** | `caja_turno` | Aperturas y cierres de sesión de caja con montos iniciales y control por cajero. |
| 17 | **Nivel 4** | `traslado_mercaderia` | Registro de transferencias de mercadería entre sedes físicas distintas. |
| 18 | **Nivel 4** | `merma` | Bajas de mercadería clasificadas por motivo (VENCIDO, DAÑADO, ROBO, OTRO). |
| 19 | **Nivel 5** | `venta` | Cabecera del comprobante fiscal (serie, correlativo, total, turno de caja y cliente). |
| 20 | **Nivel 6** | `detalle_venta` | Líneas de venta con cantidad, precio unitario histórico al momento de venta e importe calculado. |
| 21 | **Nivel 6** | `anulacion_venta` | Registro único de auditoría para anulación de comprobantes por el personal autorizado. |
| 22 | **Nivel 6** | `pago_venta` | Pagos aplicados a la venta que garantizan la liquidación total o combinada. |
| 23 | **Nivel 7** | `efectivo` | Subtipo de pago en efectivo con registro de monto recibido y vuelto entregado. |
| 24 | **Nivel 7** | `tarjeta` | Subtipo de pago bancario (Débito/Crédito), marca y resguardo de últimos 4 dígitos. |
| 25 | **Nivel 7** | `billetera_digital_sip` | Subtipo de cobro por billetera Sip mediante código QR o Tarjeta Sip con código de operación. |
| 26 | **Nivel 7** | `movimiento_stock` | Kardex permanente que audita cada variación de existencias vinculado a venta, traslado o merma. |

---

## Reglas de Negocio Implementadas

| Regla | Descripción | Mecanismo en la Base de Datos |
|---|---|---|
| **RN-01: Documentos de Cliente** | DNI debe tener 8 dígitos numéricos, RUC 11 dígitos. Debe existir al menos uno. | `CHECK (dni REGEXP '^[0-9]{8}$')`, `CHECK (ruc REGEXP '^[0-9]{11}$')` y `CHECK (dni IS NOT NULL OR ruc IS NOT NULL)`. |
| **RN-02: Serie de Comprobante** | Boletas inician con letra `B` y Facturas con letra `F`, seguidas de 3 dígitos. | `CHECK (serie REGEXP '^[BF][0-9]{3}$')` en `serie_comprobante`. |
| **RN-03: Correlativo Secuencial** | Serie y número correlativo forman una combinación única irrepetible. | `UNIQUE (id_serie, numero_correlativo)` en tabla `venta`. |
| **RN-04: Precio Centralizado** | El precio es uniforme en todas las tiendas y estrictamente positivo. | Columna `precio_venta` en `producto` con `CHECK (precio_venta > 0)`. |
| **RN-05: Existencias No Negativas** | El stock disponible en tienda nunca puede ser inferior a 0. | `CHECK (cantidad_actual >= 0)` en `stock` y `CHECK (stock_nuevo >= 0)` en `movimiento_stock`. |
| **RN-06: Traslados Intertiendas** | Un traslado no puede tener como origen y destino la misma sede física. | `CHECK (id_tienda_origen <> id_tienda_destino)` en `traslado_mercaderia`. |
| **RN-07: Turnos de Caja Coherentes** | Si el turno está abierto, fecha y monto de cierre son nulos. Si está cerrado, son obligatorios y válidos. | `CHECK` compuesto en `caja_turno`. |
| **RN-08: Origen Único de Kardex** | Cada movimiento de inventario procede de exactamente una fuente (venta, traslado o merma). | `CHECK ((id_venta IS NOT NULL) + (id_traslado IS NOT NULL) + (id_merma IS NOT NULL) = 1)` en `movimiento_stock`. |
| **RN-09: Medios de Pago Oficiales** | Tienda Mass opera exclusivamente con Efectivo, Tarjeta y Billetera Digital Sip. | Subtipos relacionales `efectivo`, `tarjeta` y `billetera_digital_sip` vinculados a `pago_venta`. |
| **RN-10: Vuelto en Efectivo** | El vuelto entregado no puede ser negativo ni igual o superior al dinero recibido. | `CHECK (vuelto >= 0 AND vuelto < monto_recibido)` en `efectivo`. |

---

## Cómo Ejecutar la Base de Datos (MySQL)

Para recrear y probar la base de datos en Laragon (puerto `3307`) o MySQL estándar (puerto `3306`), abre tu terminal o PowerShell y ejecuta los scripts en el orden indicado:

```powershell
# 1. Crear base de datos y las 26 tablas
mysql -u root -h 127.0.0.1 -P 3307 -e "source ./database/schema.sql"

# 2. Cargar datos de prueba
mysql -u root -h 127.0.0.1 -P 3307 -e "source ./database/datos.sql"

# 3. Ejecutar consultas y reportes de negocio
mysql -u root -h 127.0.0.1 -P 3307 -e "source ./database/consultas.sql"

# 4. Probar vista y emisión de boleta formateada
mysql -u root -h 127.0.0.1 -P 3307 -e "source ./database/boleta.sql"
```

---

## Aplicación PHP con POO y MVC (Sesión 4)

Primera versión de la aplicación en PHP 8.3 sin framework, organizada en Modelo, Vista y Controlador. Por ahora los datos salen de listas fijas copiadas de `database/datos.sql`; en la sesión 5 el `ProductoRepository` leerá de MySQL con PDO sin cambiar el controlador ni las vistas.

- **`models/`**: `Producto`, `Stock` (regla REPONER), `ProductoRepository` y los roles `Usuario` → `Administrador`, `Cajero`, `Almacenero`.
- **`controllers/ProductoController.php`**: valida la entrada, llama al Repository y elige la vista.
- **`views/`**: layout, catálogo, ficha de producto y página 404.
- **`public/index.php`**: único punto de entrada; también están las páginas de prueba `poo_producto.php`, `poo_roles.php` y `poo_repository.php`.
- **`docs/mvc.md`**: guía completa (rutas, reglas de negocio, reparto de ramas en el grupo y seguridad).
- **`docs/bitacora_ia.md`**: bitácora del uso de IA.
- **`docs/explicacion_archivos.md`**: qué hace cada archivo, para estudiar antes de la entrega.
- **`docs/reparto.md`**: comandos git por integrante si se pide un Pull Request por persona.

```powershell
php -S localhost:8000 -t public
# Abrir http://localhost:8000  (catálogo de Mass Cayma)
# http://localhost:8000/?ruta=productos&tienda=3      (Mass Yanahuara, la leche sale REPONER)
# http://localhost:8000/?ruta=producto&codigo=7750000000011
```

---

## Cómo Sincronizar Cambios con Git y GitHub

Si realizaste cambios directos en GitHub o tus compañeros subieron modificaciones, sigue estos pasos para mantener tu copia local actualizada:

1. **Descargar los últimos cambios desde GitHub:**
   ```bash
   git pull origin main
   ```
2. **Revisar el estado de tus archivos locales:**
   ```bash
   git status
   ```
3. **Guardar y subir nuevos cambios a GitHub:**
   ```bash
   git add .
   git commit -m "docs: actualizar README y documentacion segun nuevo schema"
   git push origin main
   ```
