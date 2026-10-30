/* =====================================================================
   MINIMARKET MASS - ajustes.sql
   Ajuste de inventario por conteo fisico: cuando lo contado en la tienda
   no coincide con el sistema, se corrige el stock y queda en el kardex.
   Ejecutar DESPUES de seguridad.sql (docker-compose lo corre como 06).

   Cambios al diseno de 26 tablas:
   - Tabla nueva ajuste_inventario (cabecera del conteo).
   - Dos tipos de movimiento: AJUSTE DE ENTRADA (sobraba) y AJUSTE DE SALIDA (faltaba).
   - movimiento_stock.id_ajuste como cuarto origen posible; la regla de
     "un solo origen" (RN-08) pasa a contar los cuatro.
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

INSERT INTO tipo_movimiento (id_tipo_movimiento, nombre_movimiento, efecto) VALUES
    (6, 'AJUSTE DE ENTRADA', 'E'),
    (7, 'AJUSTE DE SALIDA', 'S');

-- Un conteo fisico (o una correccion) en una tienda, hecho por un empleado.
CREATE TABLE ajuste_inventario (
    id_ajuste   INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    fecha_hora  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo      VARCHAR(20)        NOT NULL,
    observacion VARCHAR(200)       NULL,                         -- opcional
    id_tienda   SMALLINT UNSIGNED  NOT NULL,
    id_empleado MEDIUMINT UNSIGNED NOT NULL,
    CONSTRAINT pk_ajuste PRIMARY KEY (id_ajuste),
    CONSTRAINT ck_ajuste_motivo CHECK (motivo IN ('CONTEO_FISICO','CORRECCION')),
    CONSTRAINT fk_ajuste_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_ajuste_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado)
) ENGINE=InnoDB;

ALTER TABLE movimiento_stock
    ADD COLUMN id_ajuste INT UNSIGNED NULL AFTER id_merma,      -- origen (uno solo)
    ADD CONSTRAINT fk_movimiento_ajuste FOREIGN KEY (id_ajuste) REFERENCES ajuste_inventario (id_ajuste),
    DROP CHECK ck_movimiento_origen;

ALTER TABLE movimiento_stock
    ADD CONSTRAINT ck_movimiento_origen CHECK (
        (id_venta IS NOT NULL) + (id_traslado IS NOT NULL) + (id_merma IS NOT NULL) + (id_ajuste IS NOT NULL) = 1);
