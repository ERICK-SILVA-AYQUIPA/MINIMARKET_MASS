/* =====================================================================
   MINIMARKET MASS - migracion 019: proveedores y ordenes de compra

   - proveedor: el directorio de la cadena (RUC unico, razon social, contacto y plazo de entrega).
     Lo mantiene el administrador. Uno INACTIVO ya no recibe ordenes nuevas, pero su historia queda.
   - orden_compra y detalle_orden_compra: lo que la tienda le pide a un proveedor, con la cantidad
     y el costo pactado de cada producto. La orden no mueve stock: el stock sube cuando llega la
     mercaderia y se registra el ingreso (ingreso_mercaderia), que es el que deja el kardex.
     Estados: PENDIENTE (nada recibido), PARCIAL (llego una parte), RECIBIDA (llego todo),
     ANULADA (se cancelo sin recibir nada) y CERRADA (llego una parte y el resto ya no vendra).
   - ingreso_mercaderia.id_proveedor y .id_orden_compra: el ingreso queda unido a su proveedor y,
     si se recibio contra una orden, a esa orden. Un ingreso puede seguir sin orden (compra al paso).
   - Los emisores de los ingresos que ya existian pasan a ser proveedores y sus ingresos se enlazan.
   ===================================================================== */

CREATE TABLE proveedor (
    id_proveedor    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ruc             CHAR(11)      NOT NULL,
    razon_social    VARCHAR(100)  NOT NULL,
    contacto        VARCHAR(80)   NULL,                      -- vendedor o persona que atiende los pedidos
    telefono        VARCHAR(15)   NULL,
    correo          VARCHAR(100)  NULL,
    dias_entrega    TINYINT UNSIGNED NULL,                   -- cuantos dias tarda en llegar un pedido
    estado          VARCHAR(8)    NOT NULL DEFAULT 'ACTIVO',
    fecha_registro  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_proveedor PRIMARY KEY (id_proveedor),
    CONSTRAINT uq_proveedor_ruc UNIQUE (ruc),
    CONSTRAINT ck_proveedor_ruc CHECK (ruc REGEXP '^[0-9]{11}$'),
    CONSTRAINT ck_proveedor_estado CHECK (estado IN ('ACTIVO', 'INACTIVO')),
    CONSTRAINT ck_proveedor_dias CHECK (dias_entrega IS NULL OR dias_entrega BETWEEN 0 AND 90)
) ENGINE=InnoDB;

CREATE TABLE orden_compra (
    id_orden_compra   INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    fecha_hora        DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- cuando se emitio
    fecha_entrega     DATE               NULL,                                 -- cuando deberia llegar
    estado            VARCHAR(9)         NOT NULL DEFAULT 'PENDIENTE',
    total             DECIMAL(10,2)      NOT NULL,                             -- suma del detalle al costo pactado
    observacion       VARCHAR(200)       NULL,
    id_proveedor      INT UNSIGNED       NOT NULL,
    id_tienda         SMALLINT UNSIGNED  NOT NULL,                             -- a donde llega
    id_empleado       MEDIUMINT UNSIGNED NOT NULL,                             -- quien la emitio
    fecha_cierre      DATETIME           NULL,                                 -- ANULADA o CERRADA: cuando
    id_empleado_cierre MEDIUMINT UNSIGNED NULL,                                -- ...quien
    motivo_cierre     VARCHAR(200)       NULL,                                 -- ...y por que
    CONSTRAINT pk_orden_compra PRIMARY KEY (id_orden_compra),
    CONSTRAINT ck_orden_estado CHECK (estado IN ('PENDIENTE', 'PARCIAL', 'RECIBIDA', 'ANULADA', 'CERRADA')),
    CONSTRAINT ck_orden_total CHECK (total >= 0),
    -- Anulada o cerrada a mano: siempre con fecha, quien y motivo; las demas, sin ellos
    CONSTRAINT ck_orden_cierre CHECK (
        (estado IN ('ANULADA', 'CERRADA')) = (fecha_cierre IS NOT NULL AND id_empleado_cierre IS NOT NULL AND motivo_cierre IS NOT NULL)),
    CONSTRAINT fk_orden_proveedor FOREIGN KEY (id_proveedor) REFERENCES proveedor (id_proveedor),
    CONSTRAINT fk_orden_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_orden_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado),
    CONSTRAINT fk_orden_empleado_cierre FOREIGN KEY (id_empleado_cierre) REFERENCES empleado (id_empleado),
    INDEX ix_orden_tienda (id_tienda, estado, id_orden_compra)
) ENGINE=InnoDB;

CREATE TABLE detalle_orden_compra (
    id_orden_compra   INT UNSIGNED  NOT NULL,
    id_producto       INT UNSIGNED  NOT NULL,
    cantidad_pedida   DECIMAL(10,3) NOT NULL,
    cantidad_recibida DECIMAL(10,3) NOT NULL DEFAULT 0,      -- la suben los ingresos hechos contra la orden
    costo_unitario    DECIMAL(10,2) NOT NULL,                -- costo pactado con el proveedor
    costo_total       DECIMAL(10,2) NOT NULL,
    CONSTRAINT pk_detalle_orden PRIMARY KEY (id_orden_compra, id_producto),
    CONSTRAINT ck_detalle_orden_pedida CHECK (cantidad_pedida > 0),
    -- No se recibe mas de lo pedido contra la orden: lo que sobre va en un ingreso aparte
    CONSTRAINT ck_detalle_orden_recibida CHECK (cantidad_recibida >= 0 AND cantidad_recibida <= cantidad_pedida),
    CONSTRAINT ck_detalle_orden_costo CHECK (costo_unitario >= 0),
    CONSTRAINT ck_detalle_orden_total CHECK (costo_total = ROUND(cantidad_pedida * costo_unitario, 2)),
    CONSTRAINT fk_detalle_orden_cabecera FOREIGN KEY (id_orden_compra) REFERENCES orden_compra (id_orden_compra),
    CONSTRAINT fk_detalle_orden_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto)
) ENGINE=InnoDB;

ALTER TABLE ingreso_mercaderia
    ADD COLUMN id_proveedor    INT UNSIGNED NULL AFTER razon_social,
    ADD COLUMN id_orden_compra INT UNSIGNED NULL AFTER id_proveedor,
    ADD CONSTRAINT fk_ingreso_proveedor FOREIGN KEY (id_proveedor) REFERENCES proveedor (id_proveedor),
    ADD CONSTRAINT fk_ingreso_orden FOREIGN KEY (id_orden_compra) REFERENCES orden_compra (id_orden_compra),
    -- Un ingreso contra una orden siempre sabe de que proveedor es
    ADD CONSTRAINT ck_ingreso_orden_proveedor CHECK (id_orden_compra IS NULL OR id_proveedor IS NOT NULL),
    ADD INDEX ix_ingreso_orden (id_orden_compra);

-- Los emisores de los ingresos que ya existen pasan a ser proveedores, en el orden en que llegaron por primera vez
-- (con la razon social de su ingreso mas reciente)
INSERT INTO proveedor (ruc, razon_social, fecha_registro)
SELECT i.ruc_emisor, i.razon_social, i.fecha_hora
  FROM ingreso_mercaderia i
 WHERE i.id_ingreso = (SELECT MAX(i2.id_ingreso) FROM ingreso_mercaderia i2 WHERE i2.ruc_emisor = i.ruc_emisor)
 ORDER BY (SELECT MIN(i3.id_ingreso) FROM ingreso_mercaderia i3 WHERE i3.ruc_emisor = i.ruc_emisor);

UPDATE ingreso_mercaderia i JOIN proveedor p ON p.ruc = i.ruc_emisor SET i.id_proveedor = p.id_proveedor;
