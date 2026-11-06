/* =====================================================================
   MINIMARKET MASS - migracion 024: un segundo cajero en cada sede (para la caja 2)

   Cada sede de ejemplo tiene dos cajas (caja 1 y caja 2) pero un solo cajero, asi que la caja 2
   nunca se podia abrir mientras la 1 trabajaba. Se agrega un cajero mas por sede:
       Mass Cayma          -> Diego Huanca Torres    DNI 70000101
       Mass Cerro Colorado -> Elena Paredes Quispe   DNI 70000102
       Mass Yanahuara      -> Raúl Apaza Medina      DNI 70000103
   - Misma clave de prueba que los 9 empleados de login.sql, guardada solo como hash bcrypt.
     debe_cambiar_clave = 'S': al entrar por primera vez la web y la app piden cambiarla (migracion 013).
   - Su sede es empleado.id_tienda: los triggers de la migracion 021 no los dejan abrir turno ni
     vender en otra sede.
   - Idempotente: si el DNI ya existe (alguien lo registro a mano en Personal) no se inserta otra vez
     ni se cambia su clave. Los empleados que ya estaban no se tocan.
   - Solo en la base de ejemplo: cada uno entra a la sede del cajero de ejemplo de esa sede (Luis 70000002,
     Ana 70000005, Miguel 70000008). Una tienda real (quitar_datos_demo.php ya borro a esos cajeros)
     no recibe empleados con una clave conocida.
   - SET NAMES: el cliente mysql de Docker conecta en latin1; sin esto la tilde de Raúl llega mal.
   ===================================================================== */

SET NAMES utf8mb4;

INSERT INTO empleado (nombres, apellidos, dni, clave_hash, version_clave, debe_cambiar_clave, estado, id_tienda, id_rol)
SELECT n.nombres, n.apellidos, n.dni, n.clave_hash, 1, 'S', 'ACTIVO', c.id_tienda, c.id_rol
  FROM (
        SELECT 'Diego' AS nombres, 'Huanca Torres' AS apellidos, '70000101' AS dni, '70000002' AS dni_companero,
               '$2y$10$mTdLfzM6mx0Nbp.SD73ULOlGBYWWaN7XteIR1B3yRKnlyDRsuAc.6' AS clave_hash
        UNION ALL
        SELECT 'Elena', 'Paredes Quispe', '70000102', '70000005',
               '$2y$10$ivznXuqaRGqrL8bVyuDiWObyMCSCr7bw6pjuvujgM6hJltyF53TFG'
        UNION ALL
        SELECT 'Raúl', 'Apaza Medina', '70000103', '70000008',
               '$2y$10$.aS2wc8EDpaEx5D73ov3DOqOlZsGRMatl3VisV2F3hpWGMPiQLqh2'
       ) AS n
  JOIN empleado c ON c.dni = n.dni_companero COLLATE utf8mb4_unicode_ci
  JOIN rol r ON r.id_rol = c.id_rol AND r.nombre_rol = 'CAJERO'
 WHERE NOT EXISTS (SELECT 1 FROM empleado e WHERE e.dni = n.dni COLLATE utf8mb4_unicode_ci);
