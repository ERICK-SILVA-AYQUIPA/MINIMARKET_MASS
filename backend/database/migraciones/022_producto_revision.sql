/* =====================================================================
   MINIMARKET MASS - migracion 022: productos recien agregados y su revision

   La pestaña "Recien agregados" (?ruta=productos_nuevos) junta los productos nuevos de cualquier origen
   (app, formulario web o CSV, columnas de la migracion 018). Un producto que registro un cajero o un
   almacenero escaneando un codigo nuevo entra al catalogo al momento, pero el administrador debe revisar
   su nombre, categoria, precio y foto. Esto anota cuando y quien lo reviso:
   - fecha_revision, id_empleado_revision: NULL = pendiente de revisar. Van juntos (los dos o ninguno).
   - Lo que registra un administrador ya nace revisado: lo cargo quien aprueba el catalogo.
   - Productos de la base inicial (origen NULL): no son nuevos, no se revisan.
   ===================================================================== */

ALTER TABLE producto
    ADD COLUMN fecha_revision       DATETIME           NULL AFTER id_tienda_registro,
    ADD COLUMN id_empleado_revision MEDIUMINT UNSIGNED NULL AFTER fecha_revision,
    ADD CONSTRAINT ck_producto_revision CHECK ((fecha_revision IS NULL) = (id_empleado_revision IS NULL)),
    ADD CONSTRAINT fk_producto_empleado_revision FOREIGN KEY (id_empleado_revision) REFERENCES empleado (id_empleado),
    ADD INDEX ix_producto_registro (fecha_registro);

-- Lo que ya habia registrado un administrador queda revisado por el mismo, con la fecha en que lo registro
UPDATE producto p
  JOIN empleado e ON e.id_empleado = p.id_empleado_registro
  JOIN rol r      ON r.id_rol = e.id_rol
   SET p.fecha_revision = p.fecha_registro,
       p.id_empleado_revision = p.id_empleado_registro
 WHERE p.origen IS NOT NULL AND p.fecha_registro IS NOT NULL AND r.nombre_rol = 'ADMINISTRADOR';
