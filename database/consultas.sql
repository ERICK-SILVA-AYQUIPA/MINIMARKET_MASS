/* =====================================================================
   TIENDA MASS (Grupo Intercorp) - AREQUIPA
   Archivo : database/consultas.sql
   Motor   : MySQL 8.0+ (Laragon, puerto 3307)

   Requisito: ejecutar ANTES schema.sql y datos.sql
     mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/consultas.sql"

   Contenido:
     D) Consultas de verificacion (D.1 a D.6)
     E) Consultas de reporte (E.1 a E.3)
     F) Pruebas de reglas de negocio (cada una debe dar error; estan
        comentadas: descomentar de a una)
   ===================================================================== */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
USE minimarket_mass;

/* =====================================================================
   D) CONSULTAS DE VERIFICACION
   ===================================================================== */

-- D.1 Control de ventas: total = pagos = detalle (RN-24). Esperado: OK y ANULADA
SELECT * FROM vw_control_ventas ORDER BY id_venta;

-- D.2 Comprobantes emitidos con su tienda, cliente y estado (RN-19, RN-22)
SELECT CONCAT(s.serie, '-', LPAD(v.correlativo, 8, '0')) AS comprobante,
       t.nombre   AS tienda,
       IFNULL(c.nombre_razon_social, 'Publico general') AS cliente,
       v.subtotal, v.igv, v.total, v.estado
  FROM venta v
  JOIN serie_comprobante s ON s.id_serie = v.id_serie
  JOIN tienda t            ON t.id_tienda = s.id_tienda
  LEFT JOIN cliente c      ON c.id_cliente = v.id_cliente
 ORDER BY v.id_venta;

-- D.3 Pagos por venta con su metodo (RN-24, RN-26)
SELECT CONCAT(s.serie, '-', LPAD(v.correlativo, 8, '0')) AS comprobante,
       m.nombre AS metodo, p.monto, p.estado
  FROM pago_venta p
  JOIN venta v             ON v.id_venta = p.id_venta
  JOIN serie_comprobante s ON s.id_serie = v.id_serie
  JOIN metodo_pago m       ON m.id_metodo_pago = p.id_metodo_pago
 ORDER BY p.id_pago;

-- D.4 Stock por tienda (RN-09)
SELECT t.nombre AS tienda, pr.nombre AS producto, st.cantidad
  FROM stock st
  JOIN tienda t    ON t.id_tienda = st.id_tienda
  JOIN producto pr ON pr.id_producto = st.id_producto
 ORDER BY t.nombre, pr.nombre;

-- D.5 Kardex de la leche en Cayma (RN-11, RN-12)
SELECT m.fecha_hora, tm.nombre AS movimiento, m.cantidad, m.stock_resultante, m.observacion
  FROM movimiento_stock m
  JOIN tipo_movimiento tm ON tm.id_tipo_movimiento = m.id_tipo_movimiento
  JOIN tienda t           ON t.id_tienda = m.id_tienda
  JOIN producto pr        ON pr.id_producto = m.id_producto
 WHERE t.codigo = 'MASS-CAY'
   AND pr.codigo_barras = '9990000000001'
 ORDER BY m.id_movimiento;

-- D.6 Turnos de caja (RN-17)
SELECT t.nombre AS tienda, ca.numero AS caja, u.login AS cajero,
       ct.fecha_apertura, ct.monto_apertura, ct.fecha_cierre, ct.monto_cierre, ct.estado
  FROM caja_turno ct
  JOIN caja ca    ON ca.id_caja = ct.id_caja
  JOIN tienda t   ON t.id_tienda = ca.id_tienda
  JOIN usuario u  ON u.id_usuario = ct.id_usuario;

/* =====================================================================
   E) CONSULTAS DE REPORTE
   ===================================================================== */

-- E.1 Ventas emitidas por tienda (no cuenta las anuladas)
SELECT t.nombre AS tienda,
       COUNT(*)      AS ventas,
       SUM(v.subtotal) AS subtotal,
       SUM(v.igv)      AS igv,
       SUM(v.total)    AS total_vendido
  FROM venta v
  JOIN serie_comprobante s ON s.id_serie = v.id_serie
  JOIN tienda t            ON t.id_tienda = s.id_tienda
 WHERE v.estado = 'EMITIDA'
 GROUP BY t.id_tienda, t.nombre
 ORDER BY t.nombre;

-- E.2 Total cobrado por metodo de pago (solo pagos aplicados)
SELECT m.nombre AS metodo,
       COUNT(*)    AS pagos,
       SUM(p.monto) AS total_cobrado
  FROM pago_venta p
  JOIN metodo_pago m ON m.id_metodo_pago = p.id_metodo_pago
 WHERE p.estado = 'APLICADO'
 GROUP BY m.id_metodo_pago, m.nombre
 ORDER BY total_cobrado DESC;

-- E.3 Productos mas vendidos (solo ventas emitidas)
SELECT pr.nombre AS producto,
       SUM(d.cantidad)    AS unidades,
       SUM(d.total_linea) AS importe
  FROM detalle_venta d
  JOIN venta v     ON v.id_venta = d.id_venta
  JOIN producto pr ON pr.id_producto = d.id_producto
 WHERE v.estado = 'EMITIDA'
 GROUP BY pr.id_producto, pr.nombre
 ORDER BY unidades DESC, pr.nombre;

/* =====================================================================
   F) PRUEBAS DE REGLAS (descomentar de a una; cada una debe dar error)
   ===================================================================== */

-- RN-04: factura sin cliente (error 20020)
-- INSERT INTO venta (id_serie, id_caja_turno, id_usuario, id_cliente, subtotal, igv, total)
-- VALUES ((SELECT id_serie FROM serie_comprobante WHERE serie = 'F001'),
--         1, (SELECT id_usuario FROM usuario WHERE login = 'cajero.cay'), NULL, 8.47, 1.53, 10.00);

-- RN-16: vender con el turno cerrado (error 20022)
-- INSERT INTO venta (id_serie, id_caja_turno, id_usuario, id_cliente, subtotal, igv, total)
-- VALUES ((SELECT id_serie FROM serie_comprobante WHERE serie = 'B001'),
--         1, (SELECT id_usuario FROM usuario WHERE login = 'cajero.cay'), NULL, 8.47, 1.53, 10.00);

-- RN-10: stock negativo (error 20010)
-- CALL sp_mover_stock((SELECT id_tienda FROM tienda WHERE codigo = 'MASS-CAY'),
--                     (SELECT id_producto FROM producto WHERE codigo_barras = '9990000000001'),
--                     'MERMA', 99999,
--                     (SELECT id_usuario FROM usuario WHERE login = 'almacenero.cay'),
--                     NULL, NULL, NULL, NULL);

-- RN-13: traslado con el mismo origen y destino (falla ck_traslado_tiendas)
-- INSERT INTO traslado_mercaderia (id_tienda_origen, id_tienda_destino, id_usuario)
-- VALUES ((SELECT id_tienda FROM tienda WHERE codigo = 'MASS-CAY'),
--         (SELECT id_tienda FROM tienda WHERE codigo = 'MASS-CAY'),
--         (SELECT id_usuario FROM usuario WHERE login = 'almacenero.cay'));

-- RN-22: borrar una venta (error 20025)
-- DELETE FROM venta;