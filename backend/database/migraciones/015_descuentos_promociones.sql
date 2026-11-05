/* =====================================================================
   MINIMARKET MASS - migracion 015: descuentos y promociones

   - promocion: la crea el ADMINISTRADOR (de su tienda o de toda la cadena) y se aplica sola al vender.
       PORCENTAJE  un producto con X % de descuento                    (ej.: Leche Gloria -15 %)
       PRECIO      un producto a precio de oferta, por unidad o por kg  (ej.: Papa amarilla a S/ 2.90 el kg)
       PACK        N unidades de un producto por un precio fijo         (ej.: 3 gaseosas por S/ 10.00, 3x2)
       VENTA       X % a toda la compra desde un monto minimo           (ej.: 5 % en compras desde S/ 100)
     Rige de fecha_inicio a fecha_fin (inclusive) mientras este ACTIVA. id_tienda NULL = las tiendas de la cadena.
   - detalle_venta: cada linea guarda el descuento de su promocion (descuento + id_promocion) y su parte del
     descuento de toda la venta (descuento_general). El importe sigue siendo cantidad x precio de lista:
     lo cobrado de la linea es importe - descuento - descuento_general, y venta.total es la suma de eso.
   - descuento_venta: el descuento a toda la venta (una promocion VENTA o uno MANUAL del cajero con su motivo).
   - detalle_devolucion: se devuelve lo que el cliente pago (con descuento), que puede ser menos que
     cantidad x precio de lista.
   ===================================================================== */

CREATE TABLE promocion (
    id_promocion   INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(60)        NOT NULL,
    tipo           VARCHAR(10)        NOT NULL,
    id_producto    INT UNSIGNED       NULL,                     -- NULL solo en VENTA
    id_tienda      SMALLINT UNSIGNED  NULL,                     -- NULL = toda la cadena
    porcentaje     DECIMAL(5,2)       NULL,                     -- PORCENTAJE y VENTA
    precio         DECIMAL(10,2)      NULL,                     -- PRECIO: por unidad o kg; PACK: precio del pack
    cantidad_pack  SMALLINT UNSIGNED  NULL,                     -- PACK: unidades del pack
    monto_minimo   DECIMAL(10,2)      NULL,                     -- VENTA: compra minima
    fecha_inicio   DATE               NOT NULL,
    fecha_fin      DATE               NOT NULL,
    estado         VARCHAR(10)        NOT NULL DEFAULT 'ACTIVA',
    id_empleado    MEDIUMINT UNSIGNED NOT NULL,                 -- el administrador que la creo
    fecha_registro DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_promocion PRIMARY KEY (id_promocion),
    CONSTRAINT ck_promocion_estado CHECK (estado IN ('ACTIVA', 'PAUSADA')),
    CONSTRAINT ck_promocion_fechas CHECK (fecha_fin >= fecha_inicio),
    -- Cada tipo usa solo sus columnas (IS NOT NULL explicito: un CHECK con NULL se daria por cumplido)
    CONSTRAINT ck_promocion_tipo CHECK (
        (tipo = 'PORCENTAJE' AND id_producto IS NOT NULL AND porcentaje IS NOT NULL AND porcentaje > 0 AND porcentaje <= 90
            AND precio IS NULL AND cantidad_pack IS NULL AND monto_minimo IS NULL)
     OR (tipo = 'PRECIO' AND id_producto IS NOT NULL AND precio IS NOT NULL AND precio > 0
            AND porcentaje IS NULL AND cantidad_pack IS NULL AND monto_minimo IS NULL)
     OR (tipo = 'PACK' AND id_producto IS NOT NULL AND precio IS NOT NULL AND precio > 0
            AND cantidad_pack IS NOT NULL AND cantidad_pack BETWEEN 2 AND 99 AND porcentaje IS NULL AND monto_minimo IS NULL)
     OR (tipo = 'VENTA' AND id_producto IS NULL AND porcentaje IS NOT NULL AND porcentaje > 0 AND porcentaje <= 90
            AND monto_minimo IS NOT NULL AND monto_minimo >= 0 AND precio IS NULL AND cantidad_pack IS NULL)
    ),
    CONSTRAINT fk_promocion_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto),
    CONSTRAINT fk_promocion_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_promocion_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado),
    INDEX ix_promocion_vigencia (estado, fecha_inicio, fecha_fin)
) ENGINE=InnoDB;

ALTER TABLE detalle_venta
    ADD COLUMN descuento         DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER importe,
    ADD COLUMN descuento_general DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER descuento,
    ADD COLUMN id_promocion      INT UNSIGNED  NULL               AFTER descuento_general,
    ADD CONSTRAINT ck_detalle_descuento CHECK (descuento >= 0 AND descuento_general >= 0 AND descuento + descuento_general <= importe),
    ADD CONSTRAINT ck_detalle_promocion CHECK ((descuento > 0) = (id_promocion IS NOT NULL)),
    ADD CONSTRAINT fk_detalle_promocion FOREIGN KEY (id_promocion) REFERENCES promocion (id_promocion);

CREATE TABLE descuento_venta (
    id_venta     BIGINT UNSIGNED    NOT NULL,
    origen       VARCHAR(10)        NOT NULL,                   -- PROMOCION (tipo VENTA) o MANUAL (el cajero)
    id_promocion INT UNSIGNED       NULL,
    porcentaje   DECIMAL(5,2)       NULL,                       -- NULL: el cajero dio un monto en soles
    monto        DECIMAL(10,2)      NOT NULL,
    motivo       VARCHAR(150)       NULL,
    id_empleado  MEDIUMINT UNSIGNED NOT NULL,                   -- quien lo dio (el cajero de la venta)
    CONSTRAINT pk_descuento_venta PRIMARY KEY (id_venta),
    CONSTRAINT ck_descuento_venta_monto CHECK (monto > 0),
    CONSTRAINT ck_descuento_venta_origen CHECK (
        (origen = 'PROMOCION' AND id_promocion IS NOT NULL AND porcentaje IS NOT NULL AND motivo IS NULL)
     OR (origen = 'MANUAL' AND id_promocion IS NULL AND motivo IS NOT NULL)
    ),
    CONSTRAINT fk_descuento_venta FOREIGN KEY (id_venta) REFERENCES venta (id_venta),
    CONSTRAINT fk_descuento_venta_promocion FOREIGN KEY (id_promocion) REFERENCES promocion (id_promocion),
    CONSTRAINT fk_descuento_venta_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado)
) ENGINE=InnoDB;

-- Lo devuelto de una linea con descuento es menos que cantidad x precio de lista
ALTER TABLE detalle_devolucion
    DROP CHECK ck_detalle_devolucion_importe,
    ADD CONSTRAINT ck_detalle_devolucion_importe CHECK (importe > 0 AND importe <= ROUND(cantidad * precio_unitario, 2));
