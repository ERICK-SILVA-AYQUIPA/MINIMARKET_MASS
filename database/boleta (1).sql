/* =====================================================================
   MINIMARKET MASS - boleta.sql
   Obtiene la informacion de un comprobante de venta (boleta o factura) a partir de
   su SERIE y su CORRELATIVO, que juntos identifican una sola venta.
   Ejecutar DESPUES de schema.sql y datos.sql.

   Contenido:
     1. Vista vw_comprobante: una fila por linea de cada venta, con los datos de cabecera.
     2. Procedimiento sp_boleta(serie, correlativo): muestra el comprobante completo
        en tres bloques (cabecera, detalle y pagos).
     3. Ejemplos de uso.
   La boleta muestra el TOTAL de la venta; el modelo no guarda subtotales ni impuestos aparte.
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

/* ---------------------------------------------------------------------
   1. VISTA: cada linea del comprobante junto con su cabecera
   --------------------------------------------------------------------- */

CREATE OR REPLACE VIEW vw_comprobante AS
SELECT v.id_venta,
       s.serie,
       v.numero_correlativo,
       CONCAT(s.serie, '-', LPAD(v.numero_correlativo, 8, '0'))        AS comprobante,
       CONCAT(tc.nombre_comprobante, ' DE VENTA')                      AS tipo_comprobante,
       v.fecha_hora,
       v.estado,
       t.nombre_tienda                                                 AS tienda,
       t.direccion                                                     AS direccion_tienda,
       CONCAT(e.nombres, ' ', e.apellidos)                             AS cajero,
       c.numero_caja                                                   AS caja,
       CASE WHEN tc.letra_serie = 'F'  THEN CONCAT('RUC ', cl.ruc)
            WHEN cl.dni IS NOT NULL    THEN CONCAT('DNI ', cl.dni)
            WHEN cl.ruc IS NOT NULL    THEN CONCAT('RUC ', cl.ruc)
            ELSE 'CLIENTE VARIOS' END                                  AS cliente,
       ROW_NUMBER() OVER (PARTITION BY v.id_venta ORDER BY d.id_detalle_venta) AS item,
       p.nombre_producto                                               AS producto,
       u.abreviatura                                                   AS unidad,
       d.cantidad,
       d.precio_unitario,
       d.importe,
       v.total
  FROM venta v
  JOIN serie_comprobante s ON s.id_serie = v.id_serie
  JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = s.id_tipo_comprobante
  JOIN tienda t            ON t.id_tienda = s.id_tienda
  JOIN caja_turno ct       ON ct.id_caja_turno = v.id_caja_turno
  JOIN caja c              ON c.id_caja = ct.id_caja
  JOIN empleado e          ON e.id_empleado = ct.id_empleado
  LEFT JOIN cliente cl     ON cl.id_cliente = v.id_cliente
  JOIN detalle_venta d     ON d.id_venta = v.id_venta
  JOIN producto p          ON p.id_producto = d.id_producto
  JOIN unidad_medida u     ON u.id_unidad = p.id_unidad;

/* ---------------------------------------------------------------------
   2. PROCEDIMIENTO: la boleta (o factura) completa
   Devuelve tres resultados: 1) cabecera  2) detalle  3) pagos.
   --------------------------------------------------------------------- */

DROP PROCEDURE IF EXISTS sp_boleta;
DELIMITER $$

CREATE PROCEDURE sp_boleta (IN p_serie CHAR(4), IN p_correlativo INT UNSIGNED)
BEGIN
    DECLARE v_id_venta BIGINT UNSIGNED;

    -- serie + correlativo identifican una sola venta
    SELECT v.id_venta INTO v_id_venta
      FROM venta v
      JOIN serie_comprobante s ON s.id_serie = v.id_serie
     WHERE s.serie = p_serie AND v.numero_correlativo = p_correlativo;

    IF v_id_venta IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No existe un comprobante con esa serie y correlativo';
    END IF;

    -- 1) Cabecera
    SELECT DISTINCT comprobante, tipo_comprobante, fecha_hora, estado, tienda, direccion_tienda, caja, cajero, cliente, total
      FROM vw_comprobante
     WHERE id_venta = v_id_venta;

    -- 2) Detalle
    SELECT item, producto, unidad, cantidad, precio_unitario, importe
      FROM vw_comprobante
     WHERE id_venta = v_id_venta
     ORDER BY item;

    -- 3) Pagos (la venta puede pagarse con varios metodos)
    SELECT mp.nombre_metodo AS metodo_pago, pv.monto_pagado, pv.estado,
           CASE WHEN ef.id_pago IS NOT NULL THEN CONCAT('Recibido ', ef.monto_recibido, ' - Vuelto ', ef.vuelto)
                WHEN ta.id_pago IS NOT NULL THEN CONCAT(ta.tipo_tarjeta, ' ', ta.marca_tarjeta, ' ****', ta.ultimos_4_digitos)
                WHEN bs.id_pago IS NOT NULL THEN CONCAT('Sip ', bs.modalidad_pago, ' - Operacion ', bs.codigo_operacion)
           END AS detalle_del_pago
      FROM pago_venta pv
      JOIN metodo_pago mp                   ON mp.id_metodo_pago = pv.id_metodo_pago
      LEFT JOIN efectivo ef                 ON ef.id_pago = pv.id_pago
      LEFT JOIN tarjeta ta                  ON ta.id_pago = pv.id_pago
      LEFT JOIN billetera_digital_sip bs    ON bs.id_pago = pv.id_pago
     WHERE pv.id_venta = v_id_venta
     ORDER BY pv.id_pago;
END$$

DELIMITER ;

/* ---------------------------------------------------------------------
   3. EJEMPLOS DE USO
   --------------------------------------------------------------------- */

-- Boleta B001-00000001: Cayma, sin cliente, pago en efectivo
CALL sp_boleta('B001', 1);

-- Factura F001-00000001: Cayma, cliente con RUC, pago mixto (tarjeta + efectivo)
CALL sp_boleta('F001', 1);

-- Boleta B002-00000001: Cerro Colorado, cliente con DNI, pago con billetera digital Sip
CALL sp_boleta('B002', 1);

-- Sin procedimiento: las lineas de una boleta directamente desde la vista
SELECT comprobante, fecha_hora, cliente, item, producto, cantidad, precio_unitario, importe, total
  FROM vw_comprobante
 WHERE serie = 'B001' AND numero_correlativo = 1
 ORDER BY item;
