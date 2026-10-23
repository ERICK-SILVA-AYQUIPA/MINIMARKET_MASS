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
    estado            VARCHAR(10)       NOT NULL DEFAULT 'ACTIVO',
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
