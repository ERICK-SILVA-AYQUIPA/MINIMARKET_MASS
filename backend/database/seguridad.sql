/* =====================================================================
   MINIMARKET MASS - seguridad.sql
   Registro de intentos de ingreso, para frenar a quien prueba claves al azar.
   Ejecutar DESPUES de login.sql (docker-compose lo corre como 05).

   - Con 5 claves erradas para un mismo DNI en 15 minutos, ese DNI espera.
   - Con 20 intentos fallidos desde una misma IP en 15 minutos, esa IP espera.
   - No es parte del negocio de la tienda: es una tabla de apoyo de la aplicacion.
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

CREATE TABLE intento_login (
    id_intento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dni        CHAR(8)         NULL,                             -- NULL si lo escrito no era un DNI
    ip         VARCHAR(45)     NOT NULL,                         -- IPv4 o IPv6
    fecha_hora DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    exito      CHAR(1)         NOT NULL,                         -- S = entro, N = clave errada
    CONSTRAINT pk_intento_login PRIMARY KEY (id_intento),
    CONSTRAINT ck_intento_exito CHECK (exito IN ('S','N')),
    INDEX ix_intento_dni (dni, fecha_hora),
    INDEX ix_intento_ip (ip, fecha_hora)
) ENGINE=InnoDB;
