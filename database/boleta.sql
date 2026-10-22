/* =====================================================================
   TIENDA MASS (Grupo Intercorp) - AREQUIPA
   Archivo : database/boleta.sql
   Motor   : MySQL 8.0+ (Laragon, puerto 3307)

   Requisito: ejecutar ANTES schema.sql y datos.sql
     mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/boleta.sql"

   Imprime una boleta de venta completa: cabecera, detalle, totales
   (subtotal + IGV 18 % = total) y pagos.
   Por defecto toma la primera boleta emitida. Para otra venta, cambiar
   el valor de @id_venta.
   ===================================================================== */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
USE minimarket_mass;

SET @id_venta := (SELECT MIN(v.id_venta)
                    FROM venta v
                    JOIN serie_comprobante s ON s.id_serie = v.id_serie
                    JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = s.id_tipo_comprobante
                   WHERE tc.codigo = 'BOLETA'
                     AND v.estado = 'EMITIDA');

-- 1. Cabecera de la boleta
SELECT t.nombre    AS tienda,
       t.direccion AS direccion,
       CONCAT(s.serie, '-', LPAD(v.correlativo, 8, '0')) AS boleta,
       v.fecha_hora AS fecha,
       CONCAT(u.nombres, ' ', u.apellidos) AS cajero,
       IFNULL(c.nombre_razon_social, 'Publico general') AS cliente,
       IFNULL(c.numero_documento, '-') AS documento
  FROM venta v
  JOIN serie_comprobante s ON s.id_serie = v.id_serie
  JOIN tienda t            ON t.id_tienda = s.id_tienda
  JOIN usuario u           ON u.id_usuario = v.id_usuario
  LEFT JOIN cliente c      ON c.id_cliente = v.id_cliente
 WHERE v.id_venta = @id_venta;

-- 2. Detalle de productos
SELECT p.nombre          AS producto,
       d.cantidad        AS cantidad,
       d.precio_unitario AS precio_unitario,
       d.total_linea     AS importe
  FROM detalle_venta d
  JOIN producto p ON p.id_producto = d.id_producto
 WHERE d.id_venta = @id_venta
 ORDER BY d.id_detalle_venta;

-- 3. Totales (el precio ya incluye IGV)
SELECT v.subtotal AS subtotal,
       v.igv      AS igv_18,
       v.total    AS total_a_pagar
  FROM venta v
 WHERE v.id_venta = @id_venta;

-- 4. Pagos recibidos
SELECT m.nombre AS metodo_pago,
       p.monto  AS monto_aplicado,
       e.monto_recibido,
       e.vuelto,
       sp.modalidad,
       sp.codigo_operacion,
       p.estado
  FROM pago_venta p
  JOIN metodo_pago m             ON m.id_metodo_pago = p.id_metodo_pago
  LEFT JOIN efectivo e           ON e.id_pago = p.id_pago
  LEFT JOIN billetera_digital_sip sp ON sp.id_pago = p.id_pago
 WHERE p.id_venta = @id_venta
 ORDER BY p.id_pago;