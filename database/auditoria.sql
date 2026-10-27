/* =====================================================================
   MINIMARKET MASS - auditoria.sql
   Bitacora de acciones delicadas: quien hizo que, cuando y en que tienda.
   Ejecutar DESPUES de ajustes.sql (docker-compose lo corre como 07).

   - Ventas, traslados, mermas y ajustes ya guardan quien los hizo en su
     propia tabla; aqui van los cambios que no dejan otra huella:
     anular una venta, cambiar el catalogo o un precio, el personal y las
     claves, los turnos de caja, las tiendas, las cajas y los distritos.
   - Solo se inserta: la aplicacion nunca cambia ni borra una fila.
   - No es parte del negocio de la tienda: es una tabla de apoyo de la aplicacion.
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

CREATE TABLE auditoria (
    id_auditoria BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    fecha_hora   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accion       VARCHAR(30)        NOT NULL,                    -- VENTA_ANULADA, PRECIO_CAMBIADO...
    detalle      VARCHAR(250)       NULL,                        -- que cambio, en palabras
    id_tienda    SMALLINT UNSIGNED  NOT NULL,                    -- la tienda de quien lo hizo
    id_empleado  MEDIUMINT UNSIGNED NOT NULL,                    -- quien lo hizo
    CONSTRAINT pk_auditoria PRIMARY KEY (id_auditoria),
    CONSTRAINT fk_auditoria_tienda   FOREIGN KEY (id_tienda)   REFERENCES tienda (id_tienda),
    CONSTRAINT fk_auditoria_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado),
    INDEX ix_auditoria_tienda (id_tienda, id_auditoria)
) ENGINE=InnoDB;
