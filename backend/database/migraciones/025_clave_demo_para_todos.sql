/* =====================================================================
   MINIMARKET MASS - migracion 025: la misma clave conocida para los 12 empleados de ejemplo (SOLO DEMO)

   Para la exposicion: todos los empleados de ejemplo vuelven a entrar con la clave de prueba
   Mass2026! y ya no se les pide cambiarla en el primer ingreso (la web va directo al inicio y la app
   da token). Son los 9 de login.sql y los 3 cajeros de la caja 2 de la migracion 024:
       Mass Cayma          -> Rosa 70000001, Luis 70000002, Carmen 70000003, Diego 70000101
       Mass Cerro Colorado -> Jorge 70000004, Ana 70000005, Pedro 70000006, Elena 70000102
       Mass Yanahuara      -> Lucía 70000007, Miguel 70000008, Sofía 70000009, Raúl 70000103
   - En la base solo va el hash bcrypt de password_hash() (el mismo metodo de Clave::hash), nunca la clave.
   - Si la clave cambio (alguien ya la habia cambiado en Mi clave), sube version_clave y se revocan sus
     tokens de la app: las sesiones abiertas con la clave anterior se cierran, como en Personal.
   - Se borran sus intentos fallidos de login, por si alguno quedo bloqueado probando claves.
   - El estado (ACTIVO / INACTIVO) y la sede no se tocan. Los empleados que no son de ejemplo tampoco.
   - Idempotente: los hashes son fijos, asi que correrla otra vez deja todo igual y no vuelve a cerrar sesiones.
   - Solo en la base de ejemplo: si la administradora de ejemplo (Rosa, 70000001) ya no existe
     (quitar_datos_demo.php cambia su DNI) no se cambia nada. Una tienda real no debe quedar con una clave
     conocida: verificar_instalacion.php sigue marcando como FALLA a todo empleado activo con Mass2026!.
   ===================================================================== */

SET @base_de_ejemplo = (SELECT COUNT(*) FROM empleado WHERE dni = '70000001' AND id_rol = 1);

DROP TEMPORARY TABLE IF EXISTS clave_demo;

CREATE TEMPORARY TABLE clave_demo (
    dni        CHAR(8)      NOT NULL PRIMARY KEY,
    clave_hash VARCHAR(255) NOT NULL
) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Un hash distinto (con su propia sal) por empleado; todos son de Mass2026!
INSERT INTO clave_demo (dni, clave_hash) VALUES
    ('70000001', '$2y$10$0LeJK4Km1ClB.z/JlRwMUepgbahhnbJSrB2tJyEqwP3nL0J/g0Zt2'),
    ('70000002', '$2y$10$HzWaVVal9Ys3wQU8DltkWOgYbH2bAtq70YLDwJWkNdr01Jiwj.zK2'),
    ('70000003', '$2y$10$95EYMPoh/NQpyncsJowb6u.YvApG/2B7t2eoC40m5SQq3100jXjBy'),
    ('70000004', '$2y$10$tGkchDcRnoCv9a5DeX62LOVyKJ.huFWeuYy02NQOXvaQb7tcpQTlO'),
    ('70000005', '$2y$10$es4QadPeuIqxYvHIaXMniuJokGQhS7BJppVZtMk2ZuK84jk4mu9zS'),
    ('70000006', '$2y$10$psDflW3HZZDu3wMb1BzRaeZAd.xyBZ9hPhaFuzXkkb.enULd1pxMa'),
    ('70000007', '$2y$10$61NrOVUIgwStRgjq5wciRu3gsKPsCpPhfLyt7magfb8bpkKqGhbXq'),
    ('70000008', '$2y$10$E8Zaqduoa1cy57BKIaGzvuFsc.VU4BEIGnkjgUz8rB7C.1eghs.3K'),
    ('70000009', '$2y$10$M992zOanPX.9PpgJpyhZUuwGvoq9D2oH5qEhS0/zuaI0CSA805RP.'),
    ('70000101', '$2y$10$iW06hRpxJ5suUWKjTmfVwOXJUs03DiImipxTDtgfi5AcG2PsO5csa'),
    ('70000102', '$2y$10$N5fqaS7rl8mDiZD/RjtbouI8k.AJxhQrPNmMl1GSc8d9Q6Ifvu3ZS'),
    ('70000103', '$2y$10$5XYDEQ9f27HoSM819zJe1u7/OWuUiOLFqftxt.wqRZz2cL0PDiaUm');

-- Antes de cambiar el hash: a quien le cambia la clave se le cierran las sesiones de la app
UPDATE api_token t
  JOIN empleado e ON e.id_empleado = t.id_empleado
  JOIN clave_demo c ON c.dni = e.dni COLLATE utf8mb4_unicode_ci
   SET t.revocado = 'S'
 WHERE @base_de_ejemplo = 1 AND t.revocado = 'N' AND NOT (e.clave_hash <=> c.clave_hash);

DELETE i FROM intento_login i
  JOIN clave_demo c ON c.dni = i.dni COLLATE utf8mb4_unicode_ci
 WHERE @base_de_ejemplo = 1 AND i.exito = 'N';

-- version_clave sube solo si el hash cambia: la sesion web abierta con otra clave se cierra sola
-- (dos sentencias: en un UPDATE con JOIN, MySQL no asegura el orden de las asignaciones)
UPDATE empleado e
  JOIN clave_demo c ON c.dni = e.dni COLLATE utf8mb4_unicode_ci
   SET e.version_clave = e.version_clave + 1
 WHERE @base_de_ejemplo = 1 AND NOT (e.clave_hash <=> c.clave_hash);

UPDATE empleado e
  JOIN clave_demo c ON c.dni = e.dni COLLATE utf8mb4_unicode_ci
   SET e.clave_hash = c.clave_hash, e.debe_cambiar_clave = 'N'
 WHERE @base_de_ejemplo = 1;

DROP TEMPORARY TABLE clave_demo;
