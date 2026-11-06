/* =====================================================================
   MINIMARKET MASS - migracion 026: promociones de ejemplo (SOLO DEMO)

   Para la exposicion: la pantalla Promociones y Nueva venta ya muestran como funcionan, sin crear
   nada a mano. Una de cada tipo de la migracion 020, vigentes del 01/11/2026 al 31/12/2026:
       Semana del lácteo         PORCENTAJE  Leche evaporada Gloria entera 400 g -15 %   toda la cadena
       Inca Kola 2 x S/ 11.90    PACK        Gaseosa Inca Kola 1.5 L, 2 por S/ 11.90      toda la cadena
       Papa a S/ 1.99 el kg      PRECIO      Papa blanca (por kg) a S/ 1.99 el kg         solo Mass Cayma
       5 % desde S/ 100          VENTA       5 % a toda la compra desde S/ 100.00         toda la cadena
   - Las crea la administradora de ejemplo (Rosa, 70000001). Si ya no existe (quitar_datos_demo.php
     cambia su DNI) o los productos no son los de ejemplo, no se agrega nada.
   - Idempotente: cada una se agrega solo si no hay otra con ese nombre.
   - Para quitarlas en una tienda real: Promociones -> Pausar (las ventas hechas guardan su descuento).
   ===================================================================== */

SET @rosa = (SELECT id_empleado FROM empleado WHERE dni = '70000001' AND id_rol = 1);

INSERT INTO promocion (nombre, tipo, id_producto, id_tienda, porcentaje, precio, cantidad_pack, monto_minimo,
                       fecha_inicio, fecha_fin, estado, id_empleado)
SELECT d.nombre, d.tipo, p.id_producto, d.id_tienda, d.porcentaje, d.precio, d.cantidad_pack, d.monto_minimo,
       '2026-11-01', '2026-12-31', 'ACTIVA', @rosa
  FROM (SELECT 'Semana del lácteo' AS nombre, 'PORCENTAJE' AS tipo, 'Leche evaporada Gloria entera 400 g' AS producto,
               NULL AS id_tienda, 15.00 AS porcentaje, NULL AS precio, NULL AS cantidad_pack, NULL AS monto_minimo
        UNION ALL SELECT 'Inca Kola 2 x S/ 11.90', 'PACK', 'Gaseosa Inca Kola 1.5 L', NULL, NULL, 11.90, 2, NULL
        UNION ALL SELECT 'Papa a S/ 1.99 el kg', 'PRECIO', 'Papa blanca (por kg)', 1, NULL, 1.99, NULL, NULL) d
  JOIN producto p ON p.nombre_producto = d.producto AND p.estado = 'ACTIVO' AND p.precio_venta * COALESCE(d.cantidad_pack, 1) > COALESCE(d.precio, 0)
 WHERE @rosa IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM promocion x WHERE x.nombre = d.nombre);

INSERT INTO promocion (nombre, tipo, id_producto, id_tienda, porcentaje, precio, cantidad_pack, monto_minimo,
                       fecha_inicio, fecha_fin, estado, id_empleado)
SELECT '5 % desde S/ 100', 'VENTA', NULL, NULL, 5.00, NULL, NULL, 100.00, '2026-11-01', '2026-12-31', 'ACTIVA', @rosa
  FROM DUAL
 WHERE @rosa IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM promocion x WHERE x.nombre = '5 % desde S/ 100');
