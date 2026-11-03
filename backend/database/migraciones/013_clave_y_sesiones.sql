/* =====================================================================
   MINIMARKET MASS - migracion 013: la clave cierra las sesiones viejas (issue #81)

   - version_clave: sube en 1 cada vez que cambia la clave. La sesion web guarda la version
     con la que entro; si ya no coincide, esa sesion (en otra PC) se cierra sola.
     Al cambiar la clave tambien se revocan los tokens de la app Android (api_token).
   - debe_cambiar_clave: S = la clave la puso el administrador (alta o reinicio en Personal)
     o es la clave de prueba. La web y la app no dejan hacer nada hasta cambiarla en "Mi clave".
     Los empleados que aun tienen la clave de prueba de login.sql (Mass2026!) quedan en S.
   - Indices por fecha para borrar rapido los intentos de login viejos y los tokens vencidos.
   ===================================================================== */

ALTER TABLE empleado
    ADD COLUMN version_clave      INT UNSIGNED NOT NULL DEFAULT 1   AFTER clave_hash,
    ADD COLUMN debe_cambiar_clave CHAR(1)      NOT NULL DEFAULT 'N' AFTER version_clave,
    ADD CONSTRAINT ck_empleado_debe_cambiar CHECK (debe_cambiar_clave IN ('S','N'));

-- Los hashes exactos de login.sql: si siguen iguales, el empleado nunca cambio Mass2026!
UPDATE empleado SET debe_cambiar_clave = 'S'
 WHERE clave_hash IN (
    '$2y$10$IE8mL3pk9V7jxYkBP9EN3.C9ofys8baJqrxt289P9dIaQEFK6TevO',
    '$2y$10$WRwCYpn7GBvmL/m8Mhe3v.aa56ljLxPB7w6qQlo3fqK65ZXONx7kS',
    '$2y$10$4U.5y5.qaLxkiqW3WqNMae5b4W9NwYy.LZD0YglX9U7xriTWNvaua',
    '$2y$10$/tqDVxhMXlqVf5hCoJebTOytNAtXXYuRB925Q1CMWg01Bfq7bdM3e',
    '$2y$10$W/1jF8Bi2RO/GHmJk9OGheyjy1uvQh/CK9viXBgTAuyaQw16BQkEW',
    '$2y$10$0lieQr.d.JWbTd7SmbMaDuTmdygxJJ5qWe0PkKq8i7KIIwHsor7J2',
    '$2y$10$mUf.9ZPJaPNno2k2KGf3w.59rbdrqiWR9iZNcJ1WGqgAl1wIANgRW',
    '$2y$10$iEb4kWZyjDQOpz5FqMY/P.MSQX9u9Es6zDwk5XF7HREqT35IJ6MMy',
    '$2y$10$TIgha9AvDR/eT/JzAHbyI.PufIpKh5AZv6.C6GPbfUtDbAjELpDke'
 );

ALTER TABLE intento_login ADD INDEX ix_intento_fecha (fecha_hora);
ALTER TABLE api_token ADD INDEX ix_api_token_expira (fecha_expira);
