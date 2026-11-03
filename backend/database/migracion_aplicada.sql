/* =====================================================================
   MINIMARKET MASS - migracion_aplicada.sql
   Registro de las migraciones de backend/database/migraciones/ que ya se aplicaron a esta base.
   No es parte del negocio de la tienda: es una tabla de apoyo para actualizar sin borrar datos.

   - La crean solos backend/scripts/migrar.php, docker/mysql/98_migraciones.sh e instalar_laragon.sql.
   - checksum: SHA-256 del archivo (sin los \r de Windows). Si el archivo cambia despues
     de aplicarse, migrar.php avisa: una migracion aplicada no se edita, se agrega otra.
   ===================================================================== */

CREATE TABLE IF NOT EXISTS migracion_aplicada (
    nombre   VARCHAR(100) NOT NULL,                  -- 013_clave_y_sesiones.sql
    fecha    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checksum CHAR(64)     NOT NULL,                  -- SHA-256 en hexadecimal
    CONSTRAINT pk_migracion_aplicada PRIMARY KEY (nombre)
) ENGINE=InnoDB;
