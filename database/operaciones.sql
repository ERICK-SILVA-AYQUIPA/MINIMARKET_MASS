/* =====================================================================
   MINIMARKET MASS - operaciones.sql
   Devoluciones parciales, retiros de caja y fechas de vencimiento.
   Ejecutar DESPUES de ingresos.sql (docker-compose lo corre como 09).

   Cambios al diseno:
   - devolucion_venta y detalle_devolucion: el cliente devuelve parte de lo
     que compro; el cajero le devuelve el efectivo desde su turno y el
     producto vuelve al stock (tipo DEVOLUCION DE VENTA, entrada).
   - movimiento_stock.id_devolucion como sexto origen posible; la regla de
     "un solo origen" (RN-08) pasa a contar los seis.
   - movimiento_caja: retiros de efectivo de la gaveta (al deposito, pagos
     menores) e ingresos de sencillo durante un turno.
   - detalle_ingreso.fecha_vencimiento: la fecha que trae el lote recibido.
   - Datos de ejemplo: 3 ingresos de Mass Cayma con fechas de vencimiento
     cercanas a HOY, para ver la pantalla "Por vencer".
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

INSERT INTO tipo_movimiento (id_tipo_movimiento, nombre_movimiento, efecto) VALUES
    (9, 'DEVOLUCION DE VENTA', 'E');

-- Una devolucion de parte (o todo) de una venta EMITIDA. El dinero sale de la gaveta del turno.
CREATE TABLE devolucion_venta (
    id_devolucion INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    fecha_hora    DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo        VARCHAR(150)       NOT NULL,
    total         DECIMAL(10,2)      NOT NULL,                     -- efectivo devuelto al cliente
    id_venta      BIGINT UNSIGNED    NOT NULL,
    id_caja_turno INT UNSIGNED       NOT NULL,                     -- turno de cuya gaveta salio el dinero
    id_empleado   MEDIUMINT UNSIGNED NOT NULL,                     -- el cajero que la registro
    CONSTRAINT pk_devolucion PRIMARY KEY (id_devolucion),
    CONSTRAINT ck_devolucion_total CHECK (total > 0),
    CONSTRAINT fk_devolucion_venta FOREIGN KEY (id_venta) REFERENCES venta (id_venta),
    CONSTRAINT fk_devolucion_turno FOREIGN KEY (id_caja_turno) REFERENCES caja_turno (id_caja_turno),
    CONSTRAINT fk_devolucion_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado),
    INDEX ix_devolucion_venta (id_venta)
) ENGINE=InnoDB;

-- Cada producto devuelto, al mismo precio al que se vendio.
CREATE TABLE detalle_devolucion (
    id_devolucion   INT UNSIGNED  NOT NULL,
    id_producto     INT UNSIGNED  NOT NULL,
    cantidad        DECIMAL(10,3) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,                        -- copia del precio de la venta
    importe         DECIMAL(10,2) NOT NULL,
    CONSTRAINT pk_detalle_devolucion PRIMARY KEY (id_devolucion, id_producto),
    CONSTRAINT ck_detalle_devolucion_cantidad CHECK (cantidad > 0),
    CONSTRAINT ck_detalle_devolucion_precio CHECK (precio_unitario > 0),
    CONSTRAINT ck_detalle_devolucion_importe CHECK (importe = ROUND(cantidad * precio_unitario, 2)),
    CONSTRAINT fk_detalle_devolucion_cabecera FOREIGN KEY (id_devolucion) REFERENCES devolucion_venta (id_devolucion),
    CONSTRAINT fk_detalle_devolucion_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto)
) ENGINE=InnoDB;

ALTER TABLE movimiento_stock
    ADD COLUMN id_devolucion INT UNSIGNED NULL AFTER id_ingreso,   -- origen (uno solo)
    ADD CONSTRAINT fk_movimiento_devolucion FOREIGN KEY (id_devolucion) REFERENCES devolucion_venta (id_devolucion),
    DROP CHECK ck_movimiento_origen;

ALTER TABLE movimiento_stock
    ADD CONSTRAINT ck_movimiento_origen CHECK (
        (id_venta IS NOT NULL) + (id_traslado IS NOT NULL) + (id_merma IS NOT NULL)
        + (id_ajuste IS NOT NULL) + (id_ingreso IS NOT NULL) + (id_devolucion IS NOT NULL) = 1);

-- Efectivo que sale (RETIRO) o entra (INGRESO de sencillo) de la gaveta durante un turno.
CREATE TABLE movimiento_caja (
    id_movimiento_caja INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    fecha_hora         DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo               VARCHAR(7)         NOT NULL,
    monto              DECIMAL(10,2)      NOT NULL,
    motivo             VARCHAR(150)       NOT NULL,
    id_caja_turno      INT UNSIGNED       NOT NULL,
    id_empleado        MEDIUMINT UNSIGNED NOT NULL,                -- el cajero del turno
    CONSTRAINT pk_movimiento_caja PRIMARY KEY (id_movimiento_caja),
    CONSTRAINT ck_movimiento_caja_tipo CHECK (tipo IN ('RETIRO','INGRESO')),
    CONSTRAINT ck_movimiento_caja_monto CHECK (monto > 0),
    CONSTRAINT fk_movimiento_caja_turno FOREIGN KEY (id_caja_turno) REFERENCES caja_turno (id_caja_turno),
    CONSTRAINT fk_movimiento_caja_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado),
    INDEX ix_movimiento_caja_turno (id_caja_turno)
) ENGINE=InnoDB;

-- Fecha de vencimiento del lote recibido (opcional: no todo producto vence)
ALTER TABLE detalle_ingreso
    ADD COLUMN fecha_vencimiento DATE NULL AFTER costo_total;

/* ---------------------------------------------------------------------
   Datos de ejemplo: 3 ingresos de Mass Cayma registrados por Carmen (almacenero),
   con fechas calculadas desde HOY para que siempre haya algo "por vencer".
   Cada ingreso sube el stock y deja su entrada en el kardex, igual que la aplicacion.
   --------------------------------------------------------------------- */

INSERT INTO ingreso_mercaderia (id_ingreso, fecha_hora, tipo_documento, numero_documento, fecha_documento, ruc_emisor,
                                razon_social, total, observacion, id_tienda, id_empleado) VALUES
    (1, NOW() - INTERVAL 20 DAY, 'GUIA', 'T001-00004521', CURDATE() - INTERVAL 20 DAY, '20456789123',
        'Distribuidora Arequipa Sur S.A.C.', 40.80, NULL, 1, 3),
    (2, NOW() - INTERVAL 10 DAY, 'FACTURA', 'F001-00000877', CURDATE() - INTERVAL 10 DAY, '20456789123',
        'Distribuidora Arequipa Sur S.A.C.', 304.80, 'Pedido semanal', 1, 3),
    (3, NOW() - INTERVAL 3 DAY, 'BOLETA', 'B002-00000315', CURDATE() - INTERVAL 3 DAY, '20601234567',
        'Frutas del Valle de Majes E.I.R.L.', 120.80, NULL, 1, 3);

INSERT INTO detalle_ingreso (id_ingreso, id_producto, cantidad, costo_unitario, costo_total, fecha_vencimiento) VALUES
    (1, 1, 12.000, 3.40, 40.80, CURDATE() - INTERVAL 1 DAY),       -- leches: vencieron ayer
    (2, 1, 48.000, 3.40, 163.20, CURDATE() + INTERVAL 120 DAY),
    (2, 11, 24.000, 5.90, 141.60, CURDATE() + INTERVAL 4 DAY),     -- yogurt: vence en 4 dias
    (3, 9, 20.000, 2.50, 50.00, CURDATE() + INTERVAL 2 DAY),       -- platano: vence en 2 dias
    (3, 11, 12.000, 5.90, 70.80, CURDATE() + INTERVAL 25 DAY);

-- Stock y kardex en el mismo orden que los ingresos (el saldo de cada movimiento es el stock despues)
UPDATE stock SET cantidad_actual = cantidad_actual + 12 WHERE id_tienda = 1 AND id_producto = 1;
INSERT INTO movimiento_stock (fecha_hora, cantidad, stock_nuevo, observacion, id_tienda, id_producto, id_tipo_movimiento, id_empleado, id_ingreso)
    SELECT NOW() - INTERVAL 20 DAY, 12.000, cantidad_actual, 'GUIA T001-00004521', 1, 1, 8, 3, 1 FROM stock WHERE id_tienda = 1 AND id_producto = 1;

UPDATE stock SET cantidad_actual = cantidad_actual + 48 WHERE id_tienda = 1 AND id_producto = 1;
INSERT INTO movimiento_stock (fecha_hora, cantidad, stock_nuevo, observacion, id_tienda, id_producto, id_tipo_movimiento, id_empleado, id_ingreso)
    SELECT NOW() - INTERVAL 10 DAY, 48.000, cantidad_actual, 'FACTURA F001-00000877', 1, 1, 8, 3, 2 FROM stock WHERE id_tienda = 1 AND id_producto = 1;
UPDATE stock SET cantidad_actual = cantidad_actual + 24 WHERE id_tienda = 1 AND id_producto = 11;
INSERT INTO movimiento_stock (fecha_hora, cantidad, stock_nuevo, observacion, id_tienda, id_producto, id_tipo_movimiento, id_empleado, id_ingreso)
    SELECT NOW() - INTERVAL 10 DAY, 24.000, cantidad_actual, 'FACTURA F001-00000877', 1, 11, 8, 3, 2 FROM stock WHERE id_tienda = 1 AND id_producto = 11;

UPDATE stock SET cantidad_actual = cantidad_actual + 20 WHERE id_tienda = 1 AND id_producto = 9;
INSERT INTO movimiento_stock (fecha_hora, cantidad, stock_nuevo, observacion, id_tienda, id_producto, id_tipo_movimiento, id_empleado, id_ingreso)
    SELECT NOW() - INTERVAL 3 DAY, 20.000, cantidad_actual, 'BOLETA B002-00000315', 1, 9, 8, 3, 3 FROM stock WHERE id_tienda = 1 AND id_producto = 9;
UPDATE stock SET cantidad_actual = cantidad_actual + 12 WHERE id_tienda = 1 AND id_producto = 11;
INSERT INTO movimiento_stock (fecha_hora, cantidad, stock_nuevo, observacion, id_tienda, id_producto, id_tipo_movimiento, id_empleado, id_ingreso)
    SELECT NOW() - INTERVAL 3 DAY, 12.000, cantidad_actual, 'BOLETA B002-00000315', 1, 11, 8, 3, 3 FROM stock WHERE id_tienda = 1 AND id_producto = 11;
