/* =====================================================================
   MINIMARKET MASS - login.sql
   Agrega la clave de acceso de cada empleado para el login de la aplicacion.
   Ejecutar DESPUES de schema.sql y datos.sql (docker-compose lo corre como 04).

   - Solo se guarda el hash de password_hash() de PHP (bcrypt), nunca la clave.
   - NULL = el empleado todavia no tiene clave y no puede entrar.
   - Clave de PRUEBA de los 9 empleados: Mass2026!  (cambiarla fuera del aula).
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

ALTER TABLE empleado
    ADD COLUMN clave_hash VARCHAR(255) NULL AFTER dni;   -- hash bcrypt de la clave; NULL = sin acceso

UPDATE empleado SET clave_hash = '$2y$10$IE8mL3pk9V7jxYkBP9EN3.C9ofys8baJqrxt289P9dIaQEFK6TevO' WHERE id_empleado = 1;
UPDATE empleado SET clave_hash = '$2y$10$WRwCYpn7GBvmL/m8Mhe3v.aa56ljLxPB7w6qQlo3fqK65ZXONx7kS' WHERE id_empleado = 2;
UPDATE empleado SET clave_hash = '$2y$10$4U.5y5.qaLxkiqW3WqNMae5b4W9NwYy.LZD0YglX9U7xriTWNvaua' WHERE id_empleado = 3;
UPDATE empleado SET clave_hash = '$2y$10$/tqDVxhMXlqVf5hCoJebTOytNAtXXYuRB925Q1CMWg01Bfq7bdM3e' WHERE id_empleado = 4;
UPDATE empleado SET clave_hash = '$2y$10$W/1jF8Bi2RO/GHmJk9OGheyjy1uvQh/CK9viXBgTAuyaQw16BQkEW' WHERE id_empleado = 5;
UPDATE empleado SET clave_hash = '$2y$10$0lieQr.d.JWbTd7SmbMaDuTmdygxJJ5qWe0PkKq8i7KIIwHsor7J2' WHERE id_empleado = 6;
UPDATE empleado SET clave_hash = '$2y$10$mUf.9ZPJaPNno2k2KGf3w.59rbdrqiWR9iZNcJ1WGqgAl1wIANgRW' WHERE id_empleado = 7;
UPDATE empleado SET clave_hash = '$2y$10$iEb4kWZyjDQOpz5FqMY/P.MSQX9u9Es6zDwk5XF7HREqT35IJ6MMy' WHERE id_empleado = 8;
UPDATE empleado SET clave_hash = '$2y$10$TIgha9AvDR/eT/JzAHbyI.PufIpKh5AZv6.C6GPbfUtDbAjELpDke' WHERE id_empleado = 9;
