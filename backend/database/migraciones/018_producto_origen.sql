/* =====================================================================
   MINIMARKET MASS - migracion 018: de donde salio cada producto del catalogo y quien lo registro

   - origen: WEB (formulario del catalogo), APP (escaner de la app Android) o CSV (importacion).
     NULL = producto de la base inicial (datos.sql / catalogo.sql), sin registro de quien lo creo.
   - fecha_registro, id_empleado_registro, id_tienda_registro: cuando, quien y desde que sucursal.
     Con esto la pantalla "Desde la app" (?ruta=productos_app) lista los productos que se escanearon y crearon
     desde los celulares, sin tener que leer el texto de la auditoria.
   - Los productos que ya se habian creado se completan con su fila PRODUCTO_CREADO de la auditoria:
     el detalle empieza con el id y termina en " (app)" cuando vino del escaner. Los importados por CSV
     no dejaban esa fila: quedan en NULL.
   ===================================================================== */

ALTER TABLE producto
    ADD COLUMN origen               VARCHAR(3)         NULL AFTER id_unidad,
    ADD COLUMN fecha_registro       DATETIME           NULL AFTER origen,
    ADD COLUMN id_empleado_registro MEDIUMINT UNSIGNED NULL AFTER fecha_registro,
    ADD COLUMN id_tienda_registro   SMALLINT UNSIGNED  NULL AFTER id_empleado_registro,
    ADD CONSTRAINT ck_producto_origen CHECK (origen IS NULL OR origen IN ('WEB', 'APP', 'CSV')),
    ADD CONSTRAINT fk_producto_empleado_registro FOREIGN KEY (id_empleado_registro) REFERENCES empleado (id_empleado),
    ADD CONSTRAINT fk_producto_tienda_registro FOREIGN KEY (id_tienda_registro) REFERENCES tienda (id_tienda),
    ADD INDEX ix_producto_origen (origen, fecha_registro);

UPDATE producto p
  JOIN auditoria a ON a.accion = 'PRODUCTO_CREADO' AND a.detalle LIKE CONCAT(p.id_producto, ' %')
   SET p.origen = IF(a.detalle LIKE '% (app)', 'APP', 'WEB'),
       p.fecha_registro = a.fecha_hora,
       p.id_empleado_registro = a.id_empleado,
       p.id_tienda_registro = a.id_tienda
 WHERE p.origen IS NULL;
