/* =====================================================================
   MINIMARKET MASS - migracion 023: otros codigos de barras de un producto

   Un mismo producto puede llegar con mas de un codigo: el empaque nuevo con otro codigo, el pack
   promocional, la caja del distribuidor (ITF-14) o el codigo del proveedor. Antes el escaner solo
   reconocia producto.codigo_barras y lo demas salia "no esta en el catalogo".
   - producto_codigo: los otros codigos de cada producto. Los "ensena" quien escanea un codigo
     desconocido desde la app (Vincular a un producto que ya existe) y el administrador los ve y los
     quita en la ficha del producto.
   - codigo: unico entre si. Que no repita el codigo_barras de otro producto lo revisa ProductoServicio
     (el escaner busca en las dos tablas y en sus variantes con ceros o UPC-E: ver CodigoBarras.php).
   - fecha_registro, id_empleado_registro, id_tienda_registro: cuando, quien y desde que sucursal.
   ===================================================================== */

CREATE TABLE producto_codigo (
    id_producto_codigo   INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    id_producto          INT UNSIGNED       NOT NULL,
    codigo               VARCHAR(14)        NOT NULL,
    fecha_registro       DATETIME           NOT NULL,
    id_empleado_registro MEDIUMINT UNSIGNED NOT NULL,
    id_tienda_registro   SMALLINT UNSIGNED  NOT NULL,
    CONSTRAINT pk_producto_codigo PRIMARY KEY (id_producto_codigo),
    CONSTRAINT uq_producto_codigo_codigo UNIQUE (codigo),
    CONSTRAINT ck_producto_codigo_codigo CHECK (codigo REGEXP '^[0-9]{8,14}$'),
    CONSTRAINT fk_producto_codigo_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto),
    CONSTRAINT fk_producto_codigo_empleado FOREIGN KEY (id_empleado_registro) REFERENCES empleado (id_empleado),
    CONSTRAINT fk_producto_codigo_tienda FOREIGN KEY (id_tienda_registro) REFERENCES tienda (id_tienda),
    INDEX ix_producto_codigo_producto (id_producto),
    INDEX ix_producto_codigo_fecha (fecha_registro)
) ENGINE=InnoDB;
