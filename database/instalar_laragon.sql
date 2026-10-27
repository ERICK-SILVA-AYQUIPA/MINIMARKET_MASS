/* ========================================================
   BASE DE DATOS COMPLETA PARA LARAGON - MINIMARKET MASS
   Ejecuta este unico archivo en HeidiSQL / phpMyAdmin.
   ======================================================== */

-- >>> INICIO DE database/schema.sql <<<
/* =====================================================================
   MINIMARKET MASS (Tienda Mass, grupo Intercorp) - 3 tiendas en Arequipa
   schema.sql: estructura de la base de datos (sin datos).

   Motor: MySQL 8.0.16 o superior (desde esa version se aplican los CHECK).
   Orden de ejecucion del repositorio: schema.sql -> datos.sql -> consultas.sql -> boleta.sql

   Convenciones:
     - Todas las tablas son InnoDB; juego de caracteres utf8mb4.
     - Llaves primarias AUTO_INCREMENT. Cada llave foranea tiene el MISMO tipo
       (incluido UNSIGNED) que la llave primaria a la que apunta.
       Excepciones por diseno: stock (llave compuesta tienda + producto) y los
       subtipos de pago (efectivo, tarjeta, billetera_digital_sip), cuya llave
       primaria es tambien la llave foranea hacia pago_venta.
     - Dinero en DECIMAL(10,2); cantidades y kilos en DECIMAL(10,3).
     - Fuente de verdad: docs/diseno_bd.md
   ===================================================================== */

DROP DATABASE IF EXISTS minimarket_mass;
CREATE DATABASE minimarket_mass CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE minimarket_mass;
SET NAMES utf8mb4;

/* ---------------------------------------------------------------------
   NIVEL 1 - Tablas independientes (no tienen claves foraneas)
   --------------------------------------------------------------------- */

-- Distritos de Arequipa donde hay tiendas (Cayma, Cerro Colorado, Yanahuara).
CREATE TABLE distrito (
    id_distrito     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_distrito VARCHAR(40)       NOT NULL,
    CONSTRAINT pk_distrito PRIMARY KEY (id_distrito),
    CONSTRAINT uq_distrito_nombre UNIQUE (nombre_distrito)
) ENGINE=InnoDB;

-- Roles de los empleados: ADMINISTRADOR, CAJERO, ALMACENERO.
CREATE TABLE rol (
    id_rol     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_rol VARCHAR(20)      NOT NULL,
    CONSTRAINT pk_rol PRIMARY KEY (id_rol),
    CONSTRAINT uq_rol_nombre UNIQUE (nombre_rol)
) ENGINE=InnoDB;

-- Clientes: solo DNI y/o RUC. Se pide uno de los dos; la factura exige RUC.
CREATE TABLE cliente (
    id_cliente INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dni        CHAR(8)      NULL,                                -- opcional
    ruc        CHAR(11)     NULL,                                -- opcional
    CONSTRAINT pk_cliente PRIMARY KEY (id_cliente),
    CONSTRAINT uq_cliente_dni UNIQUE (dni),                      -- varios NULL permitidos
    CONSTRAINT uq_cliente_ruc UNIQUE (ruc),
    CONSTRAINT ck_cliente_dni CHECK (dni IS NULL OR dni REGEXP '^[0-9]{8}$'),
    CONSTRAINT ck_cliente_ruc CHECK (ruc IS NULL OR ruc REGEXP '^[0-9]{11}$'),
    CONSTRAINT ck_cliente_documento CHECK (dni IS NOT NULL OR ruc IS NOT NULL)
) ENGINE=InnoDB;

-- Categorias de producto (abarrotes, lacteos, bebidas...).
CREATE TABLE categoria (
    id_categoria     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_categoria VARCHAR(50)       NOT NULL,
    CONSTRAINT pk_categoria PRIMARY KEY (id_categoria),
    CONSTRAINT uq_categoria_nombre UNIQUE (nombre_categoria)
) ENGINE=InnoDB;

-- Marcas de los productos.
CREATE TABLE marca (
    id_marca        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_marca    VARCHAR(50)       NOT NULL,
    es_marca_propia CHAR(1)           NOT NULL DEFAULT 'N',
    CONSTRAINT pk_marca PRIMARY KEY (id_marca),
    CONSTRAINT uq_marca_nombre UNIQUE (nombre_marca),
    CONSTRAINT ck_marca_propia CHECK (es_marca_propia IN ('S','N'))
) ENGINE=InnoDB;

-- Unidades de medida: unidad, kilogramo, litro.
CREATE TABLE unidad_medida (
    id_unidad     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    abreviatura   VARCHAR(5)       NOT NULL,
    nombre_unidad VARCHAR(30)      NOT NULL,
    CONSTRAINT pk_unidad_medida PRIMARY KEY (id_unidad),
    CONSTRAINT uq_unidad_abreviatura UNIQUE (abreviatura),
    CONSTRAINT uq_unidad_nombre UNIQUE (nombre_unidad)
) ENGINE=InnoDB;

-- Tipos de comprobante: BOLETA (serie B) y FACTURA (serie F).
CREATE TABLE tipo_comprobante (
    id_tipo_comprobante TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_comprobante  VARCHAR(20)      NOT NULL,
    letra_serie         CHAR(1)          NOT NULL,               -- B = boleta, F = factura
    CONSTRAINT pk_tipo_comprobante PRIMARY KEY (id_tipo_comprobante),
    CONSTRAINT uq_tipo_comprobante_nombre UNIQUE (nombre_comprobante),
    CONSTRAINT uq_tipo_comprobante_letra UNIQUE (letra_serie),
    CONSTRAINT ck_tipo_comprobante_letra CHECK (letra_serie IN ('B','F'))
) ENGINE=InnoDB;

-- Metodos de pago aceptados: EFECTIVO, TARJETA, BILLETERA DIGITAL SIP.
CREATE TABLE metodo_pago (
    id_metodo_pago TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_metodo  VARCHAR(30)      NOT NULL,
    CONSTRAINT pk_metodo_pago PRIMARY KEY (id_metodo_pago),
    CONSTRAINT uq_metodo_pago_nombre UNIQUE (nombre_metodo)
) ENGINE=InnoDB;

-- Tipos de movimiento del kardex y su efecto sobre el stock (E = entrada, S = salida).
CREATE TABLE tipo_movimiento (
    id_tipo_movimiento TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_movimiento  VARCHAR(30)      NOT NULL,
    efecto             CHAR(1)          NOT NULL,                -- E = entrada, S = salida
    CONSTRAINT pk_tipo_movimiento PRIMARY KEY (id_tipo_movimiento),
    CONSTRAINT uq_tipo_movimiento_nombre UNIQUE (nombre_movimiento),
    CONSTRAINT ck_tipo_movimiento_efecto CHECK (efecto IN ('E','S'))
) ENGINE=InnoDB;

/* ---------------------------------------------------------------------
   NIVEL 2 - Dependen solo de tablas del nivel 1
   --------------------------------------------------------------------- */

-- Las 3 tiendas de Tienda Mass en Arequipa.
CREATE TABLE tienda (
    id_tienda     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_tienda VARCHAR(60)       NOT NULL,
    direccion     VARCHAR(120)      NOT NULL,
    telefono      VARCHAR(15)       NULL,                        -- opcional
    estado        VARCHAR(10)       NOT NULL DEFAULT 'ACTIVA',
    id_distrito   SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT pk_tienda PRIMARY KEY (id_tienda),
    CONSTRAINT uq_tienda_nombre UNIQUE (nombre_tienda),
    CONSTRAINT ck_tienda_estado CHECK (estado IN ('ACTIVA','INACTIVA')),
    CONSTRAINT fk_tienda_distrito FOREIGN KEY (id_distrito) REFERENCES distrito (id_distrito)
) ENGINE=InnoDB;

-- Catalogo de productos. El precio es unico para todas las tiendas.
CREATE TABLE producto (
    id_producto       INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    nombre_producto   VARCHAR(100)      NOT NULL,
    codigo_barras     VARCHAR(14)       NULL,                    -- opcional (productos al peso)
    precio_venta      DECIMAL(10,2)     NOT NULL,                -- precio unico para todas las tiendas
    se_vende_por_peso CHAR(1)           NOT NULL DEFAULT 'N',
    estado            VARCHAR(13)       NOT NULL DEFAULT 'ACTIVO',   -- 'DESCONTINUADO' tiene 13 letras
    id_categoria      SMALLINT UNSIGNED NOT NULL,
    id_marca          SMALLINT UNSIGNED NOT NULL,
    id_unidad         TINYINT UNSIGNED  NOT NULL,
    CONSTRAINT pk_producto PRIMARY KEY (id_producto),
    CONSTRAINT uq_producto_codigo UNIQUE (codigo_barras),
    CONSTRAINT ck_producto_precio CHECK (precio_venta > 0),
    CONSTRAINT ck_producto_peso CHECK (se_vende_por_peso IN ('S','N')),
    CONSTRAINT ck_producto_estado CHECK (estado IN ('ACTIVO','DESCONTINUADO')),
    CONSTRAINT ck_producto_codigo CHECK (codigo_barras IS NULL OR codigo_barras REGEXP '^[0-9]{8,14}$'),
    CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria) REFERENCES categoria (id_categoria),
    CONSTRAINT fk_producto_marca FOREIGN KEY (id_marca) REFERENCES marca (id_marca),
    CONSTRAINT fk_producto_unidad FOREIGN KEY (id_unidad) REFERENCES unidad_medida (id_unidad)
) ENGINE=InnoDB;

/* ---------------------------------------------------------------------
   NIVEL 3 - Dependen de tablas de los niveles 1 y 2
   --------------------------------------------------------------------- */

-- Empleados: cada uno pertenece a una tienda, tiene un rol y entra al sistema con su DNI.
CREATE TABLE empleado (
    id_empleado MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombres     VARCHAR(60)        NOT NULL,
    apellidos   VARCHAR(60)        NOT NULL,
    dni         CHAR(8)            NOT NULL,                     -- con el DNI ingresa al sistema
    estado      VARCHAR(10)        NOT NULL DEFAULT 'ACTIVO',
    id_tienda   SMALLINT UNSIGNED  NOT NULL,
    id_rol      TINYINT UNSIGNED   NOT NULL,
    CONSTRAINT pk_empleado PRIMARY KEY (id_empleado),
    CONSTRAINT uq_empleado_dni UNIQUE (dni),
    CONSTRAINT ck_empleado_dni CHECK (dni REGEXP '^[0-9]{8}$'),
    CONSTRAINT ck_empleado_estado CHECK (estado IN ('ACTIVO','INACTIVO')),
    CONSTRAINT fk_empleado_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_empleado_rol FOREIGN KEY (id_rol) REFERENCES rol (id_rol)
) ENGINE=InnoDB;

-- Cajas registradoras de cada tienda.
CREATE TABLE caja (
    id_caja     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero_caja TINYINT UNSIGNED  NOT NULL,
    estado      VARCHAR(10)       NOT NULL DEFAULT 'ACTIVA',
    id_tienda   SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT pk_caja PRIMARY KEY (id_caja),
    CONSTRAINT uq_caja_tienda_numero UNIQUE (id_tienda, numero_caja),
    CONSTRAINT ck_caja_estado CHECK (estado IN ('ACTIVA','INACTIVA')),
    CONSTRAINT fk_caja_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda)
) ENGINE=InnoDB;

-- Series de comprobantes por tienda (B001, F001...) y el ultimo correlativo emitido.
CREATE TABLE serie_comprobante (
    id_serie            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    serie               CHAR(4)           NOT NULL,              -- B001, F001...
    ultimo_numero       INT UNSIGNED      NOT NULL DEFAULT 0,    -- ultimo correlativo emitido
    id_tienda           SMALLINT UNSIGNED NOT NULL,
    id_tipo_comprobante TINYINT UNSIGNED  NOT NULL,
    CONSTRAINT pk_serie PRIMARY KEY (id_serie),
    CONSTRAINT uq_serie_tienda UNIQUE (id_tienda, serie),
    CONSTRAINT ck_serie_formato CHECK (serie REGEXP '^[BF][0-9]{3}$'),
    CONSTRAINT ck_serie_ultimo CHECK (ultimo_numero <= 99999999),
    CONSTRAINT fk_serie_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_serie_tipo FOREIGN KEY (id_tipo_comprobante) REFERENCES tipo_comprobante (id_tipo_comprobante)
) ENGINE=InnoDB;

-- Stock de cada producto en cada tienda (llave compuesta tienda + producto).
CREATE TABLE stock (
    id_tienda       SMALLINT UNSIGNED NOT NULL,
    id_producto     INT UNSIGNED      NOT NULL,
    cantidad_actual DECIMAL(10,3)     NOT NULL DEFAULT 0,
    stock_minimo    DECIMAL(10,3)     NOT NULL DEFAULT 0,
    CONSTRAINT pk_stock PRIMARY KEY (id_tienda, id_producto),
    CONSTRAINT ck_stock_cantidad CHECK (cantidad_actual >= 0),   -- el stock nunca es negativo
    CONSTRAINT ck_stock_minimo CHECK (stock_minimo >= 0),
    CONSTRAINT fk_stock_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_stock_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto)
) ENGINE=InnoDB;

/* ---------------------------------------------------------------------
   NIVEL 4 - Turnos, traslados y mermas
   --------------------------------------------------------------------- */

-- Turno de caja: un cajero abre una caja con un monto inicial y la cierra al terminar.
CREATE TABLE caja_turno (
    id_caja_turno       INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    fecha_hora_apertura DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto_inicial       DECIMAL(10,2)      NOT NULL,
    fecha_hora_cierre   DATETIME           NULL,                 -- opcional: NULL mientras esta abierto
    monto_cierre        DECIMAL(10,2)      NULL,                 -- opcional: NULL mientras esta abierto
    estado              VARCHAR(10)        NOT NULL DEFAULT 'ABIERTO',
    id_caja             SMALLINT UNSIGNED  NOT NULL,
    id_empleado         MEDIUMINT UNSIGNED NOT NULL,             -- cajero responsable
    CONSTRAINT pk_caja_turno PRIMARY KEY (id_caja_turno),
    CONSTRAINT ck_turno_monto_inicial CHECK (monto_inicial >= 0),
    CONSTRAINT ck_turno_estado CHECK (
        (estado = 'ABIERTO' AND fecha_hora_cierre IS NULL AND monto_cierre IS NULL)
        OR (estado = 'CERRADO' AND fecha_hora_cierre IS NOT NULL AND monto_cierre IS NOT NULL
            AND monto_cierre >= 0 AND fecha_hora_cierre >= fecha_hora_apertura)),
    CONSTRAINT fk_turno_caja FOREIGN KEY (id_caja) REFERENCES caja (id_caja),
    CONSTRAINT fk_turno_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado)
) ENGINE=InnoDB;

-- Cabecera del traslado de mercaderia entre dos tiendas (origen y destino).
CREATE TABLE traslado_mercaderia (
    id_traslado       INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    fecha_hora        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion       VARCHAR(200)      NULL,                    -- opcional
    id_tienda_origen  SMALLINT UNSIGNED NOT NULL,
    id_tienda_destino SMALLINT UNSIGNED NOT NULL,
    id_empleado       MEDIUMINT UNSIGNED NOT NULL,
    CONSTRAINT pk_traslado PRIMARY KEY (id_traslado),
    CONSTRAINT ck_traslado_tiendas CHECK (id_tienda_origen <> id_tienda_destino),
    CONSTRAINT fk_traslado_origen FOREIGN KEY (id_tienda_origen) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_traslado_destino FOREIGN KEY (id_tienda_destino) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_traslado_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado)
) ENGINE=InnoDB;

-- Cabecera de la merma (perdida de mercaderia); su tienda sale del empleado que la registra.
CREATE TABLE merma (
    id_merma    INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    fecha_hora  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo      VARCHAR(20)        NOT NULL,
    observacion VARCHAR(200)       NULL,                         -- opcional
    id_empleado MEDIUMINT UNSIGNED NOT NULL,                     -- su tienda sale del empleado
    CONSTRAINT pk_merma PRIMARY KEY (id_merma),
    CONSTRAINT ck_merma_motivo CHECK (motivo IN ('VENCIDO','DAÑADO','ROBO','OTRO')),
    CONSTRAINT fk_merma_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado)
) ENGINE=InnoDB;

/* ---------------------------------------------------------------------
   NIVEL 5 - Ventas
   --------------------------------------------------------------------- */

-- Cabecera de la venta: comprobante (serie + correlativo), turno de caja y cliente opcional.
CREATE TABLE venta (
    id_venta           BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    numero_correlativo INT UNSIGNED      NOT NULL,
    fecha_hora         DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total              DECIMAL(10,2)     NOT NULL,
    estado             VARCHAR(10)       NOT NULL DEFAULT 'EMITIDA',
    id_serie           SMALLINT UNSIGNED NOT NULL,               -- de la serie se obtiene tienda y tipo
    id_caja_turno      INT UNSIGNED      NOT NULL,               -- del turno se obtiene caja y cajero
    id_cliente         INT UNSIGNED      NULL,                   -- opcional: boleta sin cliente
    CONSTRAINT pk_venta PRIMARY KEY (id_venta),
    CONSTRAINT uq_venta_serie_numero UNIQUE (id_serie, numero_correlativo),
    CONSTRAINT ck_venta_correlativo CHECK (numero_correlativo BETWEEN 1 AND 99999999),
    CONSTRAINT ck_venta_total CHECK (total > 0),
    CONSTRAINT ck_venta_estado CHECK (estado IN ('EMITIDA','ANULADA')),
    CONSTRAINT fk_venta_serie FOREIGN KEY (id_serie) REFERENCES serie_comprobante (id_serie),
    CONSTRAINT fk_venta_turno FOREIGN KEY (id_caja_turno) REFERENCES caja_turno (id_caja_turno),
    CONSTRAINT fk_venta_cliente FOREIGN KEY (id_cliente) REFERENCES cliente (id_cliente)
) ENGINE=InnoDB;

/* ---------------------------------------------------------------------
   NIVEL 6 - Detalle, anulaciones y pagos de la venta
   --------------------------------------------------------------------- */

-- Lineas de la venta: producto, cantidad, precio al momento de vender e importe.
CREATE TABLE detalle_venta (
    id_detalle_venta BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cantidad         DECIMAL(10,3)   NOT NULL,
    precio_unitario  DECIMAL(10,2)   NOT NULL,                   -- copia del precio al momento de vender
    importe          DECIMAL(10,2)   NOT NULL,
    id_venta         BIGINT UNSIGNED NOT NULL,
    id_producto      INT UNSIGNED    NOT NULL,
    CONSTRAINT pk_detalle_venta PRIMARY KEY (id_detalle_venta),
    CONSTRAINT ck_detalle_cantidad CHECK (cantidad > 0),
    CONSTRAINT ck_detalle_precio CHECK (precio_unitario > 0),
    CONSTRAINT ck_detalle_importe CHECK (importe = ROUND(cantidad * precio_unitario, 2)),
    CONSTRAINT fk_detalle_venta FOREIGN KEY (id_venta) REFERENCES venta (id_venta),
    CONSTRAINT fk_detalle_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto)
) ENGINE=InnoDB;

-- Registro de la anulacion de una venta (como maximo una por venta).
CREATE TABLE anulacion_venta (
    id_anulacion INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    fecha_hora   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo       VARCHAR(150)       NOT NULL,
    id_venta     BIGINT UNSIGNED    NOT NULL,
    id_empleado  MEDIUMINT UNSIGNED NOT NULL,
    CONSTRAINT pk_anulacion PRIMARY KEY (id_anulacion),
    CONSTRAINT uq_anulacion_venta UNIQUE (id_venta),
    CONSTRAINT fk_anulacion_venta FOREIGN KEY (id_venta) REFERENCES venta (id_venta),
    CONSTRAINT fk_anulacion_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado)
) ENGINE=InnoDB;

-- Pagos de una venta. Puede haber varios y deben sumar el total.
CREATE TABLE pago_venta (
    id_pago        BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    monto_pagado   DECIMAL(10,2)    NOT NULL,
    fecha_hora     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado         VARCHAR(10)      NOT NULL DEFAULT 'VALIDO',
    id_venta       BIGINT UNSIGNED  NOT NULL,
    id_metodo_pago TINYINT UNSIGNED NOT NULL,
    CONSTRAINT pk_pago_venta PRIMARY KEY (id_pago),
    CONSTRAINT ck_pago_monto CHECK (monto_pagado > 0),
    CONSTRAINT ck_pago_estado CHECK (estado IN ('VALIDO','ANULADO')),
    CONSTRAINT fk_pago_venta FOREIGN KEY (id_venta) REFERENCES venta (id_venta),
    CONSTRAINT fk_pago_metodo FOREIGN KEY (id_metodo_pago) REFERENCES metodo_pago (id_metodo_pago)
) ENGINE=InnoDB;

/* ---------------------------------------------------------------------
   NIVEL 7 - Subtipos de pago y kardex
   --------------------------------------------------------------------- */

-- Detalle del pago en efectivo (subtipo de pago_venta; su llave primaria es tambien foranea).
CREATE TABLE efectivo (
    id_pago        BIGINT UNSIGNED NOT NULL,
    monto_recibido DECIMAL(10,2)   NOT NULL,
    vuelto         DECIMAL(10,2)   NOT NULL DEFAULT 0,
    CONSTRAINT pk_efectivo PRIMARY KEY (id_pago),
    CONSTRAINT ck_efectivo_recibido CHECK (monto_recibido > 0),
    CONSTRAINT ck_efectivo_vuelto CHECK (vuelto >= 0 AND vuelto < monto_recibido),
    CONSTRAINT fk_efectivo_pago FOREIGN KEY (id_pago) REFERENCES pago_venta (id_pago)
) ENGINE=InnoDB;

-- Detalle del pago con tarjeta (subtipo de pago_venta). Nunca se guarda el numero completo.
CREATE TABLE tarjeta (
    id_pago             BIGINT UNSIGNED NOT NULL,
    tipo_tarjeta        VARCHAR(10)     NOT NULL,
    marca_tarjeta       VARCHAR(20)     NOT NULL,
    ultimos_4_digitos   CHAR(4)         NOT NULL,                -- nunca se guarda el numero completo
    codigo_autorizacion VARCHAR(10)     NOT NULL,
    CONSTRAINT pk_tarjeta PRIMARY KEY (id_pago),
    CONSTRAINT ck_tarjeta_tipo CHECK (tipo_tarjeta IN ('CREDITO','DEBITO')),
    CONSTRAINT ck_tarjeta_digitos CHECK (ultimos_4_digitos REGEXP '^[0-9]{4}$'),
    CONSTRAINT fk_tarjeta_pago FOREIGN KEY (id_pago) REFERENCES pago_venta (id_pago)
) ENGINE=InnoDB;

-- Detalle del pago con la billetera digital Sip (subtipo de pago_venta).
CREATE TABLE billetera_digital_sip (
    id_pago          BIGINT UNSIGNED NOT NULL,
    modalidad_pago   VARCHAR(15)     NOT NULL,
    codigo_operacion VARCHAR(30)     NOT NULL,
    CONSTRAINT pk_billetera_sip PRIMARY KEY (id_pago),
    CONSTRAINT uq_billetera_operacion UNIQUE (codigo_operacion),
    CONSTRAINT ck_billetera_modalidad CHECK (modalidad_pago IN ('QR','TARJETA_SIP')),
    CONSTRAINT fk_billetera_pago FOREIGN KEY (id_pago) REFERENCES pago_venta (id_pago)
) ENGINE=InnoDB;

-- Kardex: cada cambio de stock, con un unico origen (venta, traslado o merma).
CREATE TABLE movimiento_stock (
    id_movimiento      BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    fecha_hora         DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cantidad           DECIMAL(10,3)      NOT NULL,
    stock_nuevo        DECIMAL(10,3)      NOT NULL,              -- saldo despues del movimiento
    observacion        VARCHAR(200)       NULL,                  -- opcional
    id_tienda          SMALLINT UNSIGNED  NOT NULL,
    id_producto        INT UNSIGNED       NOT NULL,
    id_tipo_movimiento TINYINT UNSIGNED   NOT NULL,
    id_empleado        MEDIUMINT UNSIGNED NOT NULL,
    id_venta           BIGINT UNSIGNED    NULL,                  -- origen (uno solo)
    id_traslado        INT UNSIGNED       NULL,                  -- origen (uno solo)
    id_merma           INT UNSIGNED       NULL,                  -- origen (uno solo)
    CONSTRAINT pk_movimiento PRIMARY KEY (id_movimiento),
    CONSTRAINT ck_movimiento_cantidad CHECK (cantidad > 0),
    CONSTRAINT ck_movimiento_saldo CHECK (stock_nuevo >= 0),
    CONSTRAINT ck_movimiento_origen CHECK (
        (id_venta IS NOT NULL) + (id_traslado IS NOT NULL) + (id_merma IS NOT NULL) = 1),
    CONSTRAINT fk_movimiento_stock FOREIGN KEY (id_tienda, id_producto) REFERENCES stock (id_tienda, id_producto),
    CONSTRAINT fk_movimiento_tipo FOREIGN KEY (id_tipo_movimiento) REFERENCES tipo_movimiento (id_tipo_movimiento),
    CONSTRAINT fk_movimiento_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado),
    CONSTRAINT fk_movimiento_venta FOREIGN KEY (id_venta) REFERENCES venta (id_venta),
    CONSTRAINT fk_movimiento_traslado FOREIGN KEY (id_traslado) REFERENCES traslado_mercaderia (id_traslado),
    CONSTRAINT fk_movimiento_merma FOREIGN KEY (id_merma) REFERENCES merma (id_merma)
) ENGINE=InnoDB;


-- >>> INICIO DE database/datos.sql <<<
/* =====================================================================
   MINIMARKET MASS - datos.sql: datos de ejemplo (ficticios) para probar el diseno.
   Ejecutar DESPUES de schema.sql. Orden: primero las tablas independientes y luego
   las que tienen claves foraneas (el mismo orden de schema.sql).

   Los datos respetan las reglas de negocio de docs/diseno_bd.md:
     - serie + correlativo no se repiten; cada serie avanza de uno en uno y
       serie_comprobante.ultimo_numero coincide con el ultimo emitido;
     - la factura lleva un cliente con RUC;
     - los pagos validos de cada venta suman su total;
     - el stock actual = stock inicial + entradas - salidas del kardex;
     - el cajero vende solo con su turno abierto, en la tienda de su caja;
     - la venta anulada tiene su anulacion, sus pagos ANULADOS y su movimiento de devolucion.
   DNI, RUC, telefonos, codigos de barras y precios son inventados.
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

-- ===== NIVEL 1: tablas independientes =====

INSERT INTO distrito (id_distrito, nombre_distrito) VALUES
    (1, 'Cayma'),
    (2, 'Cerro Colorado'),
    (3, 'Yanahuara');

INSERT INTO rol (id_rol, nombre_rol) VALUES
    (1, 'ADMINISTRADOR'),
    (2, 'CAJERO'),
    (3, 'ALMACENERO');

-- Cliente 1: solo DNI; cliente 2: solo RUC (empresa, para factura); cliente 3: tiene ambos
INSERT INTO cliente (id_cliente, dni, ruc) VALUES
    (1, '45678912', NULL),
    (2, NULL, '20123456789'),
    (3, '71234567', '10712345678');

INSERT INTO categoria (id_categoria, nombre_categoria) VALUES
    (1, 'Abarrotes'),
    (2, 'Lacteos'),
    (3, 'Bebidas'),
    (4, 'Frutas y verduras'),
    (5, 'Limpieza');

INSERT INTO marca (id_marca, nombre_marca, es_marca_propia) VALUES
    (1, 'Gloria', 'N'),
    (2, 'Costeno', 'N'),
    (3, 'Inca Kola', 'N'),
    (4, 'Primor', 'N'),
    (5, 'Florida', 'N'),
    (6, 'Bolivar', 'N'),
    (7, 'Don Vittorio', 'N'),
    (8, 'Sin marca', 'N');

INSERT INTO unidad_medida (id_unidad, abreviatura, nombre_unidad) VALUES
    (1, 'und', 'Unidad'),
    (2, 'kg', 'Kilogramo'),
    (3, 'lt', 'Litro');

INSERT INTO tipo_comprobante (id_tipo_comprobante, nombre_comprobante, letra_serie) VALUES
    (1, 'BOLETA', 'B'),
    (2, 'FACTURA', 'F');

INSERT INTO metodo_pago (id_metodo_pago, nombre_metodo) VALUES
    (1, 'EFECTIVO'),
    (2, 'TARJETA'),
    (3, 'BILLETERA DIGITAL SIP');

INSERT INTO tipo_movimiento (id_tipo_movimiento, nombre_movimiento, efecto) VALUES
    (1, 'VENTA', 'S'),
    (2, 'ANULACION DE VENTA', 'E'),
    (3, 'TRASLADO DE SALIDA', 'S'),
    (4, 'TRASLADO DE ENTRADA', 'E'),
    (5, 'MERMA', 'S');

-- ===== NIVEL 2 =====

INSERT INTO tienda (id_tienda, nombre_tienda, direccion, telefono, estado, id_distrito) VALUES
    (1, 'Mass Cayma', 'Direccion de ejemplo, Cayma', NULL, 'ACTIVA', 1),
    (2, 'Mass Cerro Colorado', 'Direccion de ejemplo, Cerro Colorado', '054000002', 'ACTIVA', 2),
    (3, 'Mass Yanahuara', 'Direccion de ejemplo, Yanahuara', NULL, 'ACTIVA', 3);

-- Precios y codigos de barras de ejemplo (inventados)
INSERT INTO producto (id_producto, nombre_producto, codigo_barras, precio_venta, se_vende_por_peso, estado, id_categoria, id_marca, id_unidad) VALUES
    (1, 'Leche evaporada Gloria entera 400 g', '7750000000011', 4.50, 'N', 'ACTIVO', 2, 1, 1),
    (2, 'Arroz Costeno extra 1 kg', '7750000000028', 5.20, 'N', 'ACTIVO', 1, 2, 1),
    (3, 'Gaseosa Inca Kola 1.5 L', '7750000000035', 6.50, 'N', 'ACTIVO', 3, 3, 1),
    (4, 'Aceite vegetal Primor 1 L', '7750000000042', 10.50, 'N', 'ACTIVO', 1, 4, 1),
    (5, 'Atun Florida filete 170 g', '7750000000059', 5.50, 'N', 'ACTIVO', 1, 5, 1),
    (6, 'Detergente Bolivar 800 g', '7750000000066', 9.90, 'N', 'ACTIVO', 5, 6, 1),
    (7, 'Fideos spaghetti Don Vittorio 500 g', '7750000000073', 3.20, 'N', 'ACTIVO', 1, 7, 1),
    (8, 'Papa blanca (por kg)', NULL, 2.50, 'S', 'ACTIVO', 4, 8, 2),
    (9, 'Platano de seda (por kg)', NULL, 3.90, 'S', 'ACTIVO', 4, 8, 2),
    (10, 'Azucar rubia Costeno 1 kg', '7750000000080', 4.80, 'N', 'ACTIVO', 1, 2, 1),
    (11, 'Yogurt Gloria fresa 1 L', '7750000000097', 7.90, 'N', 'ACTIVO', 2, 1, 1),
    (12, 'Cebolla roja (por kg)', NULL, 3.20, 'S', 'ACTIVO', 4, 8, 2);

-- ===== NIVEL 3 =====

INSERT INTO empleado (id_empleado, nombres, apellidos, dni, estado, id_tienda, id_rol) VALUES
    (1, 'Rosa', 'Quispe Huaman', '70000001', 'ACTIVO', 1, 1),
    (2, 'Luis', 'Caceres Pinto', '70000002', 'ACTIVO', 1, 2),
    (3, 'Carmen', 'Ccori Flores', '70000003', 'ACTIVO', 1, 3),
    (4, 'Jorge', 'Mendoza Salas', '70000004', 'ACTIVO', 2, 1),
    (5, 'Ana', 'Valdivia Rojas', '70000005', 'ACTIVO', 2, 2),
    (6, 'Pedro', 'Choque Ramos', '70000006', 'ACTIVO', 2, 3),
    (7, 'Lucia', 'Zegarra Paredes', '70000007', 'ACTIVO', 3, 1),
    (8, 'Miguel', 'Nunez Cuadros', '70000008', 'ACTIVO', 3, 2),
    (9, 'Sofia', 'Linares Avila', '70000009', 'ACTIVO', 3, 3);

INSERT INTO caja (id_caja, numero_caja, estado, id_tienda) VALUES
    (1, 1, 'ACTIVA', 1),
    (2, 2, 'ACTIVA', 1),
    (3, 1, 'ACTIVA', 2),
    (4, 2, 'ACTIVA', 2),
    (5, 1, 'ACTIVA', 3),
    (6, 2, 'ACTIVA', 3);

-- ultimo_numero = ultimo correlativo emitido de cada serie (coincide con las ventas de mas abajo)
INSERT INTO serie_comprobante (id_serie, serie, ultimo_numero, id_tienda, id_tipo_comprobante) VALUES
    (1, 'B001', 4, 1, 1),
    (2, 'F001', 3, 1, 2),
    (3, 'B002', 4, 2, 1),
    (4, 'F002', 0, 2, 2),
    (5, 'B003', 2, 3, 1),
    (6, 'F003', 1, 3, 2);

-- Stock ACTUAL = stock inicial (100 und / 60 kg, con dos excepciones) mas las entradas y menos las salidas del kardex
INSERT INTO stock (id_tienda, id_producto, cantidad_actual, stock_minimo) VALUES
    (1, 1, 98.000, 20.000),
    (1, 2, 78.000, 20.000),
    (1, 3, 91.000, 20.000),
    (1, 4, 95.000, 20.000),
    (1, 5, 86.000, 20.000),
    (1, 6, 98.000, 20.000),
    (1, 7, 89.000, 20.000),
    (1, 8, 44.200, 10.000),
    (1, 9, 60.000, 10.000),
    (1, 10, 90.000, 20.000),
    (1, 11, 100.000, 20.000),
    (1, 12, 58.250, 10.000),
    (2, 1, 96.000, 20.000),
    (2, 2, 100.000, 20.000),
    (2, 3, 97.000, 20.000),
    (2, 4, 100.000, 20.000),
    (2, 5, 100.000, 20.000),
    (2, 6, 20.000, 20.000),
    (2, 7, 100.000, 20.000),
    (2, 8, 57.000, 10.000),
    (2, 9, 56.000, 10.000),
    (2, 10, 98.000, 20.000),
    (2, 11, 99.000, 20.000),
    (2, 12, 60.000, 10.000),
    (3, 1, 16.000, 20.000),
    (3, 2, 120.000, 20.000),
    (3, 3, 98.000, 20.000),
    (3, 4, 96.000, 20.000),
    (3, 5, 94.000, 20.000),
    (3, 6, 97.000, 20.000),
    (3, 7, 96.000, 20.000),
    (3, 8, 69.300, 10.000),
    (3, 9, 60.000, 10.000),
    (3, 10, 100.000, 20.000),
    (3, 11, 100.000, 20.000),
    (3, 12, 60.000, 10.000);

-- ===== NIVEL 4 =====

-- Turnos 1 a 4 cerrados (monto de cierre = inicial + efectivo cobrado); turnos 5 y 6 abiertos
INSERT INTO caja_turno (id_caja_turno, fecha_hora_apertura, monto_inicial, fecha_hora_cierre, monto_cierre, estado, id_caja, id_empleado) VALUES
    (1, '2026-10-17 08:00:00', 100.00, '2026-10-17 16:00:00', 150.70, 'CERRADO', 1, 2),
    (2, '2026-10-17 08:00:00', 100.00, '2026-10-17 16:00:00', 100.00, 'CERRADO', 3, 5),
    (3, '2026-10-18 08:00:00', 100.00, '2026-10-18 16:00:00', 100.00, 'CERRADO', 1, 2),
    (4, '2026-10-18 08:00:00', 100.00, '2026-10-18 16:00:00', 151.30, 'CERRADO', 5, 8),
    (5, '2026-10-19 08:00:00', 100.00, NULL, NULL, 'ABIERTO', 1, 2),
    (6, '2026-10-19 08:00:00', 100.00, NULL, NULL, 'ABIERTO', 3, 5);

-- Traslado de Cayma a Yanahuara registrado por el almacenero de Cayma
INSERT INTO traslado_mercaderia (id_traslado, fecha_hora, observacion, id_tienda_origen, id_tienda_destino, id_empleado) VALUES
    (1, '2026-10-18 10:00:00', 'Reposicion de abarrotes y papa', 1, 3, 3);

-- Cada merma la registra un almacenero y descuenta de su propia tienda
INSERT INTO merma (id_merma, fecha_hora, motivo, observacion, id_empleado) VALUES
    (1, '2026-10-18 12:00:00', 'VENCIDO', 'Leche evaporada fuera de fecha', 9),
    (2, '2026-10-19 09:00:00', 'DAÑADO', 'Papa golpeada en el traslado', 3);

-- ===== NIVEL 5: ventas =====

-- Boletas (B...) y facturas (F...). La venta 4 esta ANULADA. Las facturas llevan cliente con RUC.
INSERT INTO venta (id_venta, numero_correlativo, fecha_hora, total, estado, id_serie, id_caja_turno, id_cliente) VALUES
    (1, 1, '2026-10-17 09:15:00', 14.20, 'EMITIDA', 1, 1, NULL),
    (2, 1, '2026-10-17 11:40:00', 136.50, 'EMITIDA', 2, 1, 2),
    (3, 1, '2026-10-17 12:30:00', 15.75, 'EMITIDA', 3, 2, 1),
    (4, 2, '2026-10-17 14:05:00', 5.20, 'ANULADA', 1, 1, NULL),
    (5, 2, '2026-10-17 15:00:00', 17.50, 'EMITIDA', 3, 2, NULL),
    (6, 1, '2026-10-18 09:30:00', 26.50, 'EMITIDA', 5, 4, NULL),
    (7, 3, '2026-10-18 10:10:00', 19.40, 'EMITIDA', 1, 3, 3),
    (8, 1, '2026-10-18 11:45:00', 104.70, 'EMITIDA', 6, 4, 2),
    (9, 2, '2026-10-18 13:20:00', 40.50, 'EMITIDA', 2, 3, 3),
    (10, 2, '2026-10-18 14:10:00', 24.80, 'EMITIDA', 5, 4, NULL),
    (11, 4, '2026-10-19 08:45:00', 12.60, 'EMITIDA', 1, 5, NULL),
    (12, 3, '2026-10-19 09:20:00', 37.50, 'EMITIDA', 3, 6, 1),
    (13, 3, '2026-10-19 10:05:00', 99.80, 'EMITIDA', 2, 5, 2),
    (14, 4, '2026-10-19 11:30:00', 17.25, 'EMITIDA', 3, 6, NULL);

-- ===== NIVEL 6: detalle, anulaciones y pagos =====

-- precio_unitario es la copia del precio del producto al vender
INSERT INTO detalle_venta (id_detalle_venta, cantidad, precio_unitario, importe, id_venta, id_producto) VALUES
    (1, 2.000, 4.50, 9.00, 1, 1),
    (2, 1.000, 3.20, 3.20, 1, 7),
    (3, 0.800, 2.50, 2.00, 1, 8),
    (4, 6.000, 6.50, 39.00, 2, 3),
    (5, 12.000, 5.50, 66.00, 2, 5),
    (6, 3.000, 10.50, 31.50, 2, 4),
    (7, 1.000, 9.90, 9.90, 3, 6),
    (8, 1.500, 3.90, 5.85, 3, 9),
    (9, 1.000, 5.20, 5.20, 4, 2),
    (10, 2.000, 4.80, 9.60, 5, 10),
    (11, 1.000, 7.90, 7.90, 5, 11),
    (12, 3.000, 4.50, 13.50, 6, 1),
    (13, 2.000, 6.50, 13.00, 6, 3),
    (14, 1.250, 3.20, 4.00, 7, 12),
    (15, 2.000, 2.50, 5.00, 7, 8),
    (16, 2.000, 5.20, 10.40, 7, 2),
    (17, 4.000, 10.50, 42.00, 8, 4),
    (18, 3.000, 9.90, 29.70, 8, 6),
    (19, 6.000, 5.50, 33.00, 8, 5),
    (20, 2.000, 10.50, 21.00, 9, 4),
    (21, 3.000, 6.50, 19.50, 9, 3),
    (22, 4.000, 3.20, 12.80, 10, 7),
    (23, 2.000, 4.50, 9.00, 10, 1),
    (24, 1.200, 2.50, 3.00, 10, 8),
    (25, 2.000, 5.50, 11.00, 11, 5),
    (26, 0.500, 3.20, 1.60, 11, 12),
    (27, 3.000, 6.50, 19.50, 12, 3),
    (28, 4.000, 4.50, 18.00, 12, 1),
    (29, 10.000, 4.80, 48.00, 13, 10),
    (30, 10.000, 3.20, 32.00, 13, 7),
    (31, 2.000, 9.90, 19.80, 13, 6),
    (32, 2.500, 3.90, 9.75, 14, 9),
    (33, 3.000, 2.50, 7.50, 14, 8);

-- La anula un administrador de la misma tienda de la venta
INSERT INTO anulacion_venta (id_anulacion, fecha_hora, motivo, id_venta, id_empleado) VALUES
    (1, '2026-10-17 14:20:00', 'Error de producto: el cliente pidio otro articulo', 4, 1);

-- Pagos multiples: la venta 2, la 8 y la 13 se pagan con dos metodos
INSERT INTO pago_venta (id_pago, monto_pagado, fecha_hora, estado, id_venta, id_metodo_pago) VALUES
    (1, 14.20, '2026-10-17 09:15:00', 'VALIDO', 1, 1),
    (2, 100.00, '2026-10-17 11:40:00', 'VALIDO', 2, 2),
    (3, 36.50, '2026-10-17 11:40:00', 'VALIDO', 2, 1),
    (4, 15.75, '2026-10-17 12:30:00', 'VALIDO', 3, 3),
    (5, 5.20, '2026-10-17 14:05:00', 'ANULADO', 4, 1),
    (6, 17.50, '2026-10-17 15:00:00', 'VALIDO', 5, 2),
    (7, 26.50, '2026-10-18 09:30:00', 'VALIDO', 6, 1),
    (8, 19.40, '2026-10-18 10:10:00', 'VALIDO', 7, 3),
    (9, 60.00, '2026-10-18 11:45:00', 'VALIDO', 8, 2),
    (10, 44.70, '2026-10-18 11:45:00', 'VALIDO', 8, 3),
    (11, 40.50, '2026-10-18 13:20:00', 'VALIDO', 9, 2),
    (12, 24.80, '2026-10-18 14:10:00', 'VALIDO', 10, 1),
    (13, 12.60, '2026-10-19 08:45:00', 'VALIDO', 11, 1),
    (14, 37.50, '2026-10-19 09:20:00', 'VALIDO', 12, 3),
    (15, 50.00, '2026-10-19 10:05:00', 'VALIDO', 13, 1),
    (16, 49.80, '2026-10-19 10:05:00', 'VALIDO', 13, 2),
    (17, 17.25, '2026-10-19 11:30:00', 'VALIDO', 14, 1);

-- ===== NIVEL 7: subtipos de pago y kardex =====

INSERT INTO efectivo (id_pago, monto_recibido, vuelto) VALUES
    (1, 20.00, 5.80),
    (3, 40.00, 3.50),
    (5, 10.00, 4.80),
    (7, 30.00, 3.50),
    (12, 25.00, 0.20),
    (13, 20.00, 7.40),
    (15, 50.00, 0.00),
    (17, 20.00, 2.75);

-- Solo se guardan los ultimos 4 digitos
INSERT INTO tarjeta (id_pago, tipo_tarjeta, marca_tarjeta, ultimos_4_digitos, codigo_autorizacion) VALUES
    (2, 'CREDITO', 'VISA', '1234', 'A12345'),
    (6, 'DEBITO', 'MASTERCARD', '5678', 'B54321'),
    (9, 'CREDITO', 'VISA', '4321', 'C11111'),
    (11, 'CREDITO', 'MASTERCARD', '8765', 'D22222'),
    (16, 'DEBITO', 'VISA', '1111', 'E33333');

INSERT INTO billetera_digital_sip (id_pago, modalidad_pago, codigo_operacion) VALUES
    (4, 'QR', 'SIP-EJEMPLO-0001'),
    (8, 'TARJETA_SIP', 'SIP-EJEMPLO-0002'),
    (10, 'QR', 'SIP-EJEMPLO-0003'),
    (14, 'QR', 'SIP-EJEMPLO-0004');

-- Kardex en orden cronologico; stock_nuevo es el saldo despues de cada movimiento
INSERT INTO movimiento_stock (id_movimiento, fecha_hora, cantidad, stock_nuevo, observacion, id_tienda, id_producto, id_tipo_movimiento, id_empleado, id_venta, id_traslado, id_merma) VALUES
    (1, '2026-10-17 09:15:00', 2.000, 98.000, NULL, 1, 1, 1, 2, 1, NULL, NULL),
    (2, '2026-10-17 09:15:00', 1.000, 99.000, NULL, 1, 7, 1, 2, 1, NULL, NULL),
    (3, '2026-10-17 09:15:00', 0.800, 59.200, NULL, 1, 8, 1, 2, 1, NULL, NULL),
    (4, '2026-10-17 11:40:00', 6.000, 94.000, NULL, 1, 3, 1, 2, 2, NULL, NULL),
    (5, '2026-10-17 11:40:00', 12.000, 88.000, NULL, 1, 5, 1, 2, 2, NULL, NULL),
    (6, '2026-10-17 11:40:00', 3.000, 97.000, NULL, 1, 4, 1, 2, 2, NULL, NULL),
    (7, '2026-10-17 12:30:00', 1.000, 20.000, NULL, 2, 6, 1, 5, 3, NULL, NULL),
    (8, '2026-10-17 12:30:00', 1.500, 58.500, NULL, 2, 9, 1, 5, 3, NULL, NULL),
    (9, '2026-10-17 14:05:00', 1.000, 99.000, NULL, 1, 2, 1, 2, 4, NULL, NULL),
    (10, '2026-10-17 14:20:00', 1.000, 100.000, NULL, 1, 2, 2, 1, 4, NULL, NULL),
    (11, '2026-10-17 15:00:00', 2.000, 98.000, NULL, 2, 10, 1, 5, 5, NULL, NULL),
    (12, '2026-10-17 15:00:00', 1.000, 99.000, NULL, 2, 11, 1, 5, 5, NULL, NULL),
    (13, '2026-10-18 09:30:00', 3.000, 21.000, NULL, 3, 1, 1, 8, 6, NULL, NULL),
    (14, '2026-10-18 09:30:00', 2.000, 98.000, NULL, 3, 3, 1, 8, 6, NULL, NULL),
    (15, '2026-10-18 10:00:00', 20.000, 80.000, NULL, 1, 2, 3, 3, NULL, 1, NULL),
    (16, '2026-10-18 10:00:00', 20.000, 120.000, NULL, 3, 2, 4, 3, NULL, 1, NULL),
    (17, '2026-10-18 10:00:00', 10.500, 48.700, NULL, 1, 8, 3, 3, NULL, 1, NULL),
    (18, '2026-10-18 10:00:00', 10.500, 70.500, NULL, 3, 8, 4, 3, NULL, 1, NULL),
    (19, '2026-10-18 10:10:00', 1.250, 58.750, NULL, 1, 12, 1, 2, 7, NULL, NULL),
    (20, '2026-10-18 10:10:00', 2.000, 46.700, NULL, 1, 8, 1, 2, 7, NULL, NULL),
    (21, '2026-10-18 10:10:00', 2.000, 78.000, NULL, 1, 2, 1, 2, 7, NULL, NULL),
    (22, '2026-10-18 11:45:00', 4.000, 96.000, NULL, 3, 4, 1, 8, 8, NULL, NULL),
    (23, '2026-10-18 11:45:00', 3.000, 97.000, NULL, 3, 6, 1, 8, 8, NULL, NULL),
    (24, '2026-10-18 11:45:00', 6.000, 94.000, NULL, 3, 5, 1, 8, 8, NULL, NULL),
    (25, '2026-10-18 12:00:00', 3.000, 18.000, NULL, 3, 1, 5, 9, NULL, NULL, 1),
    (26, '2026-10-18 13:20:00', 2.000, 95.000, NULL, 1, 4, 1, 2, 9, NULL, NULL),
    (27, '2026-10-18 13:20:00', 3.000, 91.000, NULL, 1, 3, 1, 2, 9, NULL, NULL),
    (28, '2026-10-18 14:10:00', 4.000, 96.000, NULL, 3, 7, 1, 8, 10, NULL, NULL),
    (29, '2026-10-18 14:10:00', 2.000, 16.000, NULL, 3, 1, 1, 8, 10, NULL, NULL),
    (30, '2026-10-18 14:10:00', 1.200, 69.300, NULL, 3, 8, 1, 8, 10, NULL, NULL),
    (31, '2026-10-19 08:45:00', 2.000, 86.000, NULL, 1, 5, 1, 2, 11, NULL, NULL),
    (32, '2026-10-19 08:45:00', 0.500, 58.250, NULL, 1, 12, 1, 2, 11, NULL, NULL),
    (33, '2026-10-19 09:00:00', 2.500, 44.200, NULL, 1, 8, 5, 3, NULL, NULL, 2),
    (34, '2026-10-19 09:20:00', 3.000, 97.000, NULL, 2, 3, 1, 5, 12, NULL, NULL),
    (35, '2026-10-19 09:20:00', 4.000, 96.000, NULL, 2, 1, 1, 5, 12, NULL, NULL),
    (36, '2026-10-19 10:05:00', 10.000, 90.000, NULL, 1, 10, 1, 2, 13, NULL, NULL),
    (37, '2026-10-19 10:05:00', 10.000, 89.000, NULL, 1, 7, 1, 2, 13, NULL, NULL),
    (38, '2026-10-19 10:05:00', 2.000, 98.000, NULL, 1, 6, 1, 2, 13, NULL, NULL),
    (39, '2026-10-19 11:30:00', 2.500, 56.000, NULL, 2, 9, 1, 5, 14, NULL, NULL),
    (40, '2026-10-19 11:30:00', 3.000, 57.000, NULL, 2, 8, 1, 5, 14, NULL, NULL);


-- >>> INICIO DE database/boleta (1).sql <<<
/* =====================================================================
   MINIMARKET MASS - boleta.sql
   Obtiene la informacion de un comprobante de venta (boleta o factura) a partir de
   su SERIE y su CORRELATIVO, que juntos identifican una sola venta.
   Ejecutar DESPUES de schema.sql y datos.sql.

   Contenido:
     1. Vista vw_comprobante: una fila por linea de cada venta, con los datos de cabecera.
     2. Procedimiento sp_boleta(serie, correlativo): muestra el comprobante completo
        en tres bloques (cabecera, detalle y pagos).
     3. Ejemplos de uso.
   La boleta muestra el TOTAL de la venta; el modelo no guarda subtotales ni impuestos aparte.
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

/* ---------------------------------------------------------------------
   1. VISTA: cada linea del comprobante junto con su cabecera
   --------------------------------------------------------------------- */

CREATE OR REPLACE VIEW vw_comprobante AS
SELECT v.id_venta,
       s.serie,
       v.numero_correlativo,
       CONCAT(s.serie, '-', LPAD(v.numero_correlativo, 8, '0'))        AS comprobante,
       CONCAT(tc.nombre_comprobante, ' DE VENTA')                      AS tipo_comprobante,
       v.fecha_hora,
       v.estado,
       t.nombre_tienda                                                 AS tienda,
       t.direccion                                                     AS direccion_tienda,
       CONCAT(e.nombres, ' ', e.apellidos)                             AS cajero,
       c.numero_caja                                                   AS caja,
       CASE WHEN tc.letra_serie = 'F'  THEN CONCAT('RUC ', cl.ruc)
            WHEN cl.dni IS NOT NULL    THEN CONCAT('DNI ', cl.dni)
            WHEN cl.ruc IS NOT NULL    THEN CONCAT('RUC ', cl.ruc)
            ELSE 'CLIENTE VARIOS' END                                  AS cliente,
       ROW_NUMBER() OVER (PARTITION BY v.id_venta ORDER BY d.id_detalle_venta) AS item,
       p.nombre_producto                                               AS producto,
       u.abreviatura                                                   AS unidad,
       d.cantidad,
       d.precio_unitario,
       d.importe,
       v.total
  FROM venta v
  JOIN serie_comprobante s ON s.id_serie = v.id_serie
  JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = s.id_tipo_comprobante
  JOIN tienda t            ON t.id_tienda = s.id_tienda
  JOIN caja_turno ct       ON ct.id_caja_turno = v.id_caja_turno
  JOIN caja c              ON c.id_caja = ct.id_caja
  JOIN empleado e          ON e.id_empleado = ct.id_empleado
  LEFT JOIN cliente cl     ON cl.id_cliente = v.id_cliente
  JOIN detalle_venta d     ON d.id_venta = v.id_venta
  JOIN producto p          ON p.id_producto = d.id_producto
  JOIN unidad_medida u     ON u.id_unidad = p.id_unidad;

/* ---------------------------------------------------------------------
   2. PROCEDIMIENTO: la boleta (o factura) completa
   Devuelve tres resultados: 1) cabecera  2) detalle  3) pagos.
   --------------------------------------------------------------------- */

DROP PROCEDURE IF EXISTS sp_boleta;
DELIMITER $$

CREATE PROCEDURE sp_boleta (IN p_serie CHAR(4), IN p_correlativo INT UNSIGNED)
BEGIN
    DECLARE v_id_venta BIGINT UNSIGNED;

    -- serie + correlativo identifican una sola venta
    SELECT v.id_venta INTO v_id_venta
      FROM venta v
      JOIN serie_comprobante s ON s.id_serie = v.id_serie
     WHERE s.serie = p_serie AND v.numero_correlativo = p_correlativo;

    IF v_id_venta IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No existe un comprobante con esa serie y correlativo';
    END IF;

    -- 1) Cabecera
    SELECT DISTINCT comprobante, tipo_comprobante, fecha_hora, estado, tienda, direccion_tienda, caja, cajero, cliente, total
      FROM vw_comprobante
     WHERE id_venta = v_id_venta;

    -- 2) Detalle
    SELECT item, producto, unidad, cantidad, precio_unitario, importe
      FROM vw_comprobante
     WHERE id_venta = v_id_venta
     ORDER BY item;

    -- 3) Pagos (la venta puede pagarse con varios metodos)
    SELECT mp.nombre_metodo AS metodo_pago, pv.monto_pagado, pv.estado,
           CASE WHEN ef.id_pago IS NOT NULL THEN CONCAT('Recibido ', ef.monto_recibido, ' - Vuelto ', ef.vuelto)
                WHEN ta.id_pago IS NOT NULL THEN CONCAT(ta.tipo_tarjeta, ' ', ta.marca_tarjeta, ' ****', ta.ultimos_4_digitos)
                WHEN bs.id_pago IS NOT NULL THEN CONCAT('Sip ', bs.modalidad_pago, ' - Operacion ', bs.codigo_operacion)
           END AS detalle_del_pago
      FROM pago_venta pv
      JOIN metodo_pago mp                   ON mp.id_metodo_pago = pv.id_metodo_pago
      LEFT JOIN efectivo ef                 ON ef.id_pago = pv.id_pago
      LEFT JOIN tarjeta ta                  ON ta.id_pago = pv.id_pago
      LEFT JOIN billetera_digital_sip bs    ON bs.id_pago = pv.id_pago
     WHERE pv.id_venta = v_id_venta
     ORDER BY pv.id_pago;
END$$

DELIMITER ;

/* ---------------------------------------------------------------------
   3. EJEMPLOS DE USO
   --------------------------------------------------------------------- */

-- Boleta B001-00000001: Cayma, sin cliente, pago en efectivo
CALL sp_boleta('B001', 1);

-- Factura F001-00000001: Cayma, cliente con RUC, pago mixto (tarjeta + efectivo)
CALL sp_boleta('F001', 1);

-- Boleta B002-00000001: Cerro Colorado, cliente con DNI, pago con billetera digital Sip
CALL sp_boleta('B002', 1);

-- Sin procedimiento: las lineas de una boleta directamente desde la vista
SELECT comprobante, fecha_hora, cliente, item, producto, cantidad, precio_unitario, importe, total
  FROM vw_comprobante
 WHERE serie = 'B001' AND numero_correlativo = 1
 ORDER BY item;


-- >>> INICIO DE database/login.sql <<<
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


-- >>> INICIO DE database/seguridad.sql <<<
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


-- >>> INICIO DE database/ajustes.sql <<<
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


-- >>> INICIO DE database/auditoria.sql <<<
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


-- >>> INICIO DE database/ingresos.sql <<<
/* =====================================================================
   MINIMARKET MASS - ingresos.sql
   Ingreso de mercaderia: la compra que llega a una tienda con su documento
   (guia de remision, factura o boleta del emisor), sus cantidades y su costo.
   Ejecutar DESPUES de auditoria.sql (docker-compose lo corre como 08).

   Cambios al diseno de 26 tablas:
   - Tablas nuevas ingreso_mercaderia (cabecera) y detalle_ingreso (productos).
   - Tipo de movimiento INGRESO DE MERCADERIA (entrada).
   - movimiento_stock.id_ingreso como quinto origen posible; la regla de
     "un solo origen" (RN-08) pasa a contar los cinco.
   El emisor se guarda como texto (RUC y razon social): el grupo decidio
   no tener una tabla de proveedores.
   ===================================================================== */

USE minimarket_mass;
SET NAMES utf8mb4;

INSERT INTO tipo_movimiento (id_tipo_movimiento, nombre_movimiento, efecto) VALUES
    (8, 'INGRESO DE MERCADERIA', 'E');

-- Una entrega recibida en una tienda, con el documento que la respalda.
CREATE TABLE ingreso_mercaderia (
    id_ingreso       INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    fecha_hora       DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- cuando se registro
    tipo_documento   VARCHAR(7)         NOT NULL,
    numero_documento VARCHAR(13)        NOT NULL,                            -- SERIE-NUMERO, ej. F001-00012345
    fecha_documento  DATE               NOT NULL,                            -- fecha que dice el documento
    ruc_emisor       CHAR(11)           NOT NULL,
    razon_social     VARCHAR(100)       NOT NULL,
    total            DECIMAL(10,2)      NOT NULL,                            -- suma de los costos del detalle
    observacion      VARCHAR(200)       NULL,                                -- opcional
    id_tienda        SMALLINT UNSIGNED  NOT NULL,
    id_empleado      MEDIUMINT UNSIGNED NOT NULL,
    CONSTRAINT pk_ingreso PRIMARY KEY (id_ingreso),
    CONSTRAINT ck_ingreso_tipo CHECK (tipo_documento IN ('GUIA','FACTURA','BOLETA')),
    CONSTRAINT ck_ingreso_ruc CHECK (ruc_emisor REGEXP '^[0-9]{11}$'),
    CONSTRAINT ck_ingreso_total CHECK (total >= 0),
    -- Un mismo documento de un emisor no se registra dos veces
    CONSTRAINT uq_ingreso_documento UNIQUE (ruc_emisor, tipo_documento, numero_documento),
    CONSTRAINT fk_ingreso_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_ingreso_empleado FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado),
    INDEX ix_ingreso_tienda (id_tienda, id_ingreso)
) ENGINE=InnoDB;

-- Cada producto recibido, con su costo de compra (no cambia el precio de venta).
CREATE TABLE detalle_ingreso (
    id_ingreso     INT UNSIGNED  NOT NULL,
    id_producto    INT UNSIGNED  NOT NULL,
    cantidad       DECIMAL(10,3) NOT NULL,
    costo_unitario DECIMAL(10,2) NOT NULL,
    costo_total    DECIMAL(10,2) NOT NULL,
    CONSTRAINT pk_detalle_ingreso PRIMARY KEY (id_ingreso, id_producto),
    CONSTRAINT ck_detalle_ingreso_cantidad CHECK (cantidad > 0),
    CONSTRAINT ck_detalle_ingreso_costo CHECK (costo_unitario >= 0),
    CONSTRAINT ck_detalle_ingreso_total CHECK (costo_total = ROUND(cantidad * costo_unitario, 2)),
    CONSTRAINT fk_detalle_ingreso_cabecera FOREIGN KEY (id_ingreso) REFERENCES ingreso_mercaderia (id_ingreso),
    CONSTRAINT fk_detalle_ingreso_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto)
) ENGINE=InnoDB;

ALTER TABLE movimiento_stock
    ADD COLUMN id_ingreso INT UNSIGNED NULL AFTER id_ajuste,    -- origen (uno solo)
    ADD CONSTRAINT fk_movimiento_ingreso FOREIGN KEY (id_ingreso) REFERENCES ingreso_mercaderia (id_ingreso),
    DROP CHECK ck_movimiento_origen;

ALTER TABLE movimiento_stock
    ADD CONSTRAINT ck_movimiento_origen CHECK (
        (id_venta IS NOT NULL) + (id_traslado IS NOT NULL) + (id_merma IS NOT NULL)
        + (id_ajuste IS NOT NULL) + (id_ingreso IS NOT NULL) = 1);


