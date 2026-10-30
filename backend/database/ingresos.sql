/* =====================================================================
   MINIMARKET MASS - ingresos.sql
   Ingreso de mercaderia: la compra que llega a una tienda con su documento
   (guia de remision, factura o boleta del emisor), sus cantidades y su costo.
   Ejecutar DESPUES de auditoria.sql (docker-compose lo corre como 08).

   Cambios al diseno de 26 tablas:
   - Tablas nuevas ingreso_mercaderia (cabecera) y detalle_ingreso (productos).
   - Tipo de movimiento INGRESO DE MERCADERIA (entrada).
   - movimiento_stock.id_ingreso como quinto origen posible; la regla de
     "un solo origen" (RN-08) pasa a contar los cinco.
   El emisor se guarda como texto (RUC y razon social): el grupo decidio
   no tener una tabla de proveedores.
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

INSERT INTO tipo_movimiento (id_tipo_movimiento, nombre_movimiento, efecto) VALUES
    (8, 'INGRESO DE MERCADERIA', 'E');

-- Una entrega recibida en una tienda, con el documento que la respalda.
CREATE TABLE ingreso_mercaderia (
    id_ingreso       INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    fecha_hora       DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- cuando se registro
    tipo_documento   VARCHAR(7)         NOT NULL,
    numero_documento VARCHAR(13)        NOT NULL,                            -- SERIE-NUMERO, ej. F001-00012345
    fecha_documento  DATE               NOT NULL,                            -- fecha que dice el documento
    ruc_emisor       CHAR(11)           NOT NULL,
    razon_social     VARCHAR(100)       NOT NULL,
    total            DECIMAL(10,2)      NOT NULL,                            -- suma de los costos del detalle
    observacion      VARCHAR(200)       NULL,                                -- opcional
    id_tienda        SMALLINT UNSIGNED  NOT NULL,
    id_empleado      MEDIUMINT UNSIGNED NOT NULL,
    CONSTRAINT pk_ingreso PRIMARY KEY (id_ingreso),
    CONSTRAINT ck_ingreso_tipo CHECK (tipo_documento IN ('GUIA','FACTURA','BOLETA')),
    CONSTRAINT ck_ingreso_ruc CHECK (ruc_emisor REGEXP '^[0-9]{11}$'),
    CONSTRAINT ck_ingreso_total CHECK (total >= 0),
    -- Un mismo documento de un emisor no se registra dos veces
    CONSTRAINT uq_ingreso_documento UNIQUE (ruc_emisor, tipo_documento, numero_documento),
    CONSTRAINT fk_ingreso_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_ingreso_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado),
    INDEX ix_ingreso_tienda (id_tienda, id_ingreso)
) ENGINE=InnoDB;

-- Cada producto recibido, con su costo de compra (no cambia el precio de venta).
CREATE TABLE detalle_ingreso (
    id_ingreso     INT UNSIGNED  NOT NULL,
    id_producto    INT UNSIGNED  NOT NULL,
    cantidad       DECIMAL(10,3) NOT NULL,
    costo_unitario DECIMAL(10,2) NOT NULL,
    costo_total    DECIMAL(10,2) NOT NULL,
    CONSTRAINT pk_detalle_ingreso PRIMARY KEY (id_ingreso, id_producto),
    CONSTRAINT ck_detalle_ingreso_cantidad CHECK (cantidad > 0),
    CONSTRAINT ck_detalle_ingreso_costo CHECK (costo_unitario >= 0),
    CONSTRAINT ck_detalle_ingreso_total CHECK (costo_total = ROUND(cantidad * costo_unitario, 2)),
    CONSTRAINT fk_detalle_ingreso_cabecera FOREIGN KEY (id_ingreso) REFERENCES ingreso_mercaderia (id_ingreso),
    CONSTRAINT fk_detalle_ingreso_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto)
) ENGINE=InnoDB;

ALTER TABLE movimiento_stock
    ADD COLUMN id_ingreso INT UNSIGNED NULL AFTER id_ajuste,    -- origen (uno solo)
    ADD CONSTRAINT fk_movimiento_ingreso FOREIGN KEY (id_ingreso) REFERENCES ingreso_mercaderia (id_ingreso),
    DROP CHECK ck_movimiento_origen;

ALTER TABLE movimiento_stock
    ADD CONSTRAINT ck_movimiento_origen CHECK (
        (id_venta IS NOT NULL) + (id_traslado IS NOT NULL) + (id_merma IS NOT NULL)
        + (id_ajuste IS NOT NULL) + (id_ingreso IS NOT NULL) = 1);
