/* =====================================================================
   MINIMARKET MASS - caja_apertura.sql
   Apertura, arqueo y cierre de caja por empleado.
   Ejecutar DESPUES de api_movil.sql (docker-compose lo corre como 12).
   Solo agrega: no borra ni renombra columnas, asi que lo que ya funciona sigue igual.

   Cambios al diseno:
   - caja.fondo_fijo: el sencillo que el administrador entrega en sobre a cada caja.
   - regla_caja: reglas de caja de cada tienda (tolerancia de diferencia, tope de
     efectivo en la gaveta, cierre ciego y horas maximas de un turno).
   - caja_turno guarda lo esperado y la diferencia al cerrar, la pausa del cajero
     y la revision del administrador. Estados nuevos:
        ABIERTO   -> CERRADO   (la diferencia esta dentro de la tolerancia)
        ABIERTO   -> OBSERVADO (la pasa: la revisa el administrador)
        OBSERVADO -> CERRADO   (el administrador la aprueba con un comentario)
        OBSERVADO -> RECONTEO  -> CERRADO u OBSERVADO (el cajero vuelve a contar)
   - Una caja con un solo turno ABIERTO y un cajero con un solo turno sin terminar,
     ahora tambien en la base (antes solo lo validaba CajaServicio).
   - arqueo_caja y arqueo_detalle: cada conteo de la gaveta (al abrir, sorpresa del
     administrador y al cerrar) con los billetes y monedas que se contaron.
   - Datos de ejemplo: el esperado y la diferencia de los turnos ya cerrados de
     datos.sql, y su arqueo de apertura y de cierre.
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

-- 1. Fondo fijo de cada caja
ALTER TABLE caja
    ADD COLUMN fondo_fijo DECIMAL(10,2) NOT NULL DEFAULT 100.00 AFTER numero_caja,
    ADD CONSTRAINT ck_caja_fondo CHECK (fondo_fijo >= 0);

-- 2. Reglas de caja de cada tienda (las cambia el administrador)
CREATE TABLE regla_caja (
    id_tienda     SMALLINT UNSIGNED NOT NULL,
    tolerancia    DECIMAL(10,2)     NOT NULL DEFAULT 1.00,       -- diferencia que se acepta sin revision
    tope_gaveta   DECIMAL(10,2)     NOT NULL DEFAULT 500.00,     -- pasado el tope se pide un retiro al deposito
    cierre_ciego  CHAR(1)           NOT NULL DEFAULT 'S',        -- S = el cajero no ve el esperado antes de contar
    horas_maximas TINYINT UNSIGNED  NOT NULL DEFAULT 10,         -- despues: alerta de turno olvidado
    CONSTRAINT pk_regla_caja PRIMARY KEY (id_tienda),
    CONSTRAINT ck_regla_montos CHECK (tolerancia >= 0 AND tope_gaveta > 0),
    CONSTRAINT ck_regla_ciego CHECK (cierre_ciego IN ('S','N')),
    CONSTRAINT ck_regla_horas CHECK (horas_maximas BETWEEN 1 AND 24),
    CONSTRAINT fk_regla_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda)
) ENGINE=InnoDB;

INSERT INTO regla_caja (id_tienda) SELECT id_tienda FROM tienda;

-- 3. El turno guarda el esperado, la diferencia, la pausa y la revision
ALTER TABLE caja_turno
    ADD COLUMN monto_esperado      DECIMAL(10,2)      NULL AFTER monto_cierre,      -- opcional: se llena al cerrar
    ADD COLUMN diferencia          DECIMAL(10,2)      NULL AFTER monto_esperado,    -- contado - esperado (negativo = faltante)
    ADD COLUMN en_pausa_desde      DATETIME           NULL AFTER diferencia,        -- opcional: NULL = vendiendo
    ADD COLUMN observacion         VARCHAR(200)       NULL AFTER en_pausa_desde,    -- opcional: comentario de la revision
    ADD COLUMN id_empleado_revisa  MEDIUMINT UNSIGNED NULL AFTER id_empleado,       -- opcional: administrador que reviso
    ADD COLUMN fecha_hora_revision DATETIME           NULL AFTER id_empleado_revisa,
    ADD CONSTRAINT fk_turno_revisa FOREIGN KEY (id_empleado_revisa) REFERENCES empleado (id_empleado);

ALTER TABLE caja_turno DROP CHECK ck_turno_estado;
ALTER TABLE caja_turno
    ADD CONSTRAINT ck_turno_estado CHECK (
        (estado = 'ABIERTO' AND fecha_hora_cierre IS NULL AND monto_cierre IS NULL)
        OR (estado IN ('CERRADO','OBSERVADO','RECONTEO')
            AND fecha_hora_cierre IS NOT NULL AND monto_cierre IS NOT NULL
            AND monto_cierre >= 0 AND fecha_hora_cierre >= fecha_hora_apertura
            AND en_pausa_desde IS NULL)),
    ADD CONSTRAINT ck_turno_diferencia CHECK (
        diferencia IS NULL OR (monto_esperado IS NOT NULL AND diferencia = monto_cierre - monto_esperado)),
    ADD CONSTRAINT ck_turno_revision CHECK (
        (id_empleado_revisa IS NULL) = (fecha_hora_revision IS NULL));

-- Una caja tiene a lo mas un turno ABIERTO. La columna vale NULL cuando el turno
-- no esta abierto, y un indice UNIQUE permite varios NULL.
ALTER TABLE caja_turno
    ADD COLUMN caja_abierta SMALLINT UNSIGNED
        AS (IF(estado = 'ABIERTO', id_caja, NULL)) STORED,
    ADD CONSTRAINT uq_turno_caja_abierta UNIQUE (caja_abierta);

-- Un cajero tiene a lo mas un turno sin terminar (ABIERTO, OBSERVADO o RECONTEO).
ALTER TABLE caja_turno
    ADD COLUMN cajero_pendiente MEDIUMINT UNSIGNED
        AS (IF(estado IN ('ABIERTO','OBSERVADO','RECONTEO'), id_empleado, NULL)) STORED,
    ADD CONSTRAINT uq_turno_cajero_pendiente UNIQUE (cajero_pendiente);

-- 4. Arqueo: cada conteo de la gaveta
CREATE TABLE arqueo_caja (
    id_arqueo      INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    fecha_hora     DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo           VARCHAR(9)         NOT NULL,                  -- APERTURA, PARCIAL (sorpresa) o CIERRE
    monto_contado  DECIMAL(10,2)      NOT NULL,
    monto_esperado DECIMAL(10,2)      NOT NULL,                  -- al abrir: el fondo fijo; despues: lo calculado
    observacion    VARCHAR(150)       NULL,                      -- opcional: por que no cuadra
    id_caja_turno  INT UNSIGNED       NOT NULL,
    id_empleado    MEDIUMINT UNSIGNED NOT NULL,                  -- quien conto (cajero o administrador)
    CONSTRAINT pk_arqueo PRIMARY KEY (id_arqueo),
    CONSTRAINT ck_arqueo_tipo CHECK (tipo IN ('APERTURA','PARCIAL','CIERRE')),
    CONSTRAINT ck_arqueo_montos CHECK (monto_contado >= 0 AND monto_esperado >= 0),
    CONSTRAINT fk_arqueo_turno FOREIGN KEY (id_caja_turno) REFERENCES caja_turno (id_caja_turno),
    CONSTRAINT fk_arqueo_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado),
    INDEX ix_arqueo_turno (id_caja_turno)
) ENGINE=InnoDB;

-- Cuantos billetes y monedas de cada valor se contaron. Si el cajero escribio solo
-- el total (sin desglose), su arqueo no tiene filas aqui.
CREATE TABLE arqueo_detalle (
    id_arqueo    INT UNSIGNED      NOT NULL,
    denominacion DECIMAL(5,2)      NOT NULL,
    cantidad     SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT pk_arqueo_detalle PRIMARY KEY (id_arqueo, denominacion),
    CONSTRAINT ck_arqueo_denominacion CHECK (denominacion IN
        (200.00, 100.00, 50.00, 20.00, 10.00, 5.00, 2.00, 1.00, 0.50, 0.20, 0.10)),
    CONSTRAINT ck_arqueo_cantidad CHECK (cantidad > 0),
    CONSTRAINT fk_arqueo_detalle FOREIGN KEY (id_arqueo) REFERENCES arqueo_caja (id_arqueo)
) ENGINE=InnoDB;

/* ---------------------------------------------------------------------
   Datos de ejemplo: los turnos de datos.sql con su esperado y sus arqueos.
   El esperado es la misma cuenta de Servicio::efectivoEnGaveta():
   inicial + pagos VALIDOS en efectivo - devoluciones + ingresos - retiros.
   --------------------------------------------------------------------- */
UPDATE caja_turno ct
   SET ct.monto_esperado = ct.monto_inicial
       + COALESCE((SELECT SUM(pv.monto_pagado) FROM venta v JOIN pago_venta pv ON pv.id_venta = v.id_venta
                    WHERE v.id_caja_turno = ct.id_caja_turno AND pv.estado = 'VALIDO' AND pv.id_metodo_pago = 1), 0)
       - COALESCE((SELECT SUM(d.total) FROM devolucion_venta d WHERE d.id_caja_turno = ct.id_caja_turno), 0)
       + COALESCE((SELECT SUM(IF(m.tipo = 'INGRESO', m.monto, -m.monto)) FROM movimiento_caja m
                    WHERE m.id_caja_turno = ct.id_caja_turno), 0)
 WHERE ct.estado = 'CERRADO';
UPDATE caja_turno SET diferencia = monto_cierre - monto_esperado WHERE estado = 'CERRADO';

-- Cada turno de datos.sql se abrio contando el fondo fijo exacto (S/ 100 en sobre) y los cerrados cuadraron
INSERT INTO arqueo_caja (fecha_hora, tipo, monto_contado, monto_esperado, id_caja_turno, id_empleado)
    SELECT fecha_hora, tipo, monto_contado, monto_esperado, id_caja_turno, id_empleado
      FROM (SELECT ct.fecha_hora_apertura AS fecha_hora, 'APERTURA' AS tipo, ct.monto_inicial AS monto_contado,
                   c.fondo_fijo AS monto_esperado, ct.id_caja_turno, ct.id_empleado
              FROM caja_turno ct JOIN caja c ON c.id_caja = ct.id_caja
            UNION ALL
            SELECT fecha_hora_cierre, 'CIERRE', monto_cierre, monto_esperado, id_caja_turno, id_empleado
              FROM caja_turno WHERE estado = 'CERRADO') a
     ORDER BY id_caja_turno, tipo = 'CIERRE';
-- Fondo de S/ 100: 1 billete de 50, 2 de 20 y 2 monedas de 5
INSERT INTO arqueo_detalle (id_arqueo, denominacion, cantidad)
    SELECT a.id_arqueo, d.denominacion, d.cantidad
      FROM arqueo_caja a
      JOIN (SELECT 50.00 AS denominacion, 1 AS cantidad UNION ALL SELECT 20.00, 2 UNION ALL SELECT 5.00, 2) d
     WHERE a.tipo = 'APERTURA' AND a.monto_contado = 100.00;
