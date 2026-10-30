/* =====================================================================
   MINIMARKET MASS - api_movil.sql
   Tablas de apoyo para la app Android (android-app/) que usa la API /api/v1.
   Ejecutar DESPUES de catalogo.sql (docker-compose lo corre como 11).

   - La app y la pagina web usan la MISMA base y las mismas tablas del negocio
     (producto, stock, venta, ingreso_mercaderia, movimiento_stock...).
     Aqui solo van dos tablas que la web no necesita:
   - api_token: la sesion de un celular. Se guarda el SHA-256 del token,
     nunca el token: si alguien copia la base, no puede entrar con ellos.
   - api_operacion: la respuesta de cada venta o ingreso hecho desde la app,
     con la clave que manda el celular (cabecera Idempotency-Key). Si la red
     se corta y la app reintenta, el servidor devuelve la misma respuesta y
     no registra la venta dos veces.
   - No son parte del negocio de la tienda: son tablas de apoyo de la aplicacion.
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

CREATE TABLE api_token (
    id_token       BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    token_hash     CHAR(64)           NOT NULL,                  -- SHA-256 en hexadecimal
    id_empleado    MEDIUMINT UNSIGNED NOT NULL,
    dispositivo    VARCHAR(60)        NULL,                      -- modelo del celular, solo para reconocerlo
    fecha_creacion DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_uso     DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_expira   DATETIME           NOT NULL,
    revocado       CHAR(1)            NOT NULL DEFAULT 'N',      -- S = cerro sesion
    CONSTRAINT pk_api_token PRIMARY KEY (id_token),
    CONSTRAINT uq_api_token_hash UNIQUE (token_hash),
    CONSTRAINT ck_api_token_revocado CHECK (revocado IN ('S','N')),
    CONSTRAINT ck_api_token_expira CHECK (fecha_expira > fecha_creacion),
    CONSTRAINT fk_api_token_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado),
    INDEX ix_api_token_empleado (id_empleado)
) ENGINE=InnoDB;

CREATE TABLE api_operacion (
    id_operacion BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    clave        VARCHAR(64)        NOT NULL,                    -- Idempotency-Key (un UUID de la app)
    id_empleado  MEDIUMINT UNSIGNED NOT NULL,
    tipo         VARCHAR(10)        NOT NULL,                    -- VENTA o INGRESO
    fecha_hora   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    respuesta    JSON               NULL,                        -- lo que se respondio la primera vez
    CONSTRAINT pk_api_operacion PRIMARY KEY (id_operacion),
    CONSTRAINT uq_api_operacion_clave UNIQUE (id_empleado, clave),
    CONSTRAINT ck_api_operacion_tipo CHECK (tipo IN ('VENTA','INGRESO')),
    CONSTRAINT fk_api_operacion_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado)
) ENGINE=InnoDB;
