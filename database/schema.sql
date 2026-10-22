/* =====================================================================
   TIENDA MASS (Grupo Intercorp) - AREQUIPA
   Archivo : database/schema.sql
   Motor   : MySQL 8.0+ (Laragon, puerto 3307)
   Alcance : 3 tiendas en Arequipa, 29 tablas.

   Orden de ejecucion (PowerShell):
     mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/schema.sql"
     mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/datos.sql"
     mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/consultas.sql"
     mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/boleta.sql"

   ADVERTENCIA: elimina y vuelve a crear la base de datos minimarket_mass.

   Contenido:
     1) Tablas, restricciones e indices (traducidos del DDL de Oracle)
     2) Procedimientos, funcion, triggers y vista de control

   Notas de diseno:
   - El IGV (18 %) es constante y ya esta incluido en el precio del producto.
     No existe una tabla IGV: la venta guarda subtotal, igv y total.
   - Yape y Plin no se modelan: Tienda Mass no los acepta.
   - Los valores de catalogo (roles, metodos de pago, etc.) se cargan en datos.sql.
   - Oracle -> MySQL: IDENTITY = AUTO_INCREMENT, REGEXP_LIKE = REGEXP,
     los indices unicos por expresion pasan a columnas generadas con UNIQUE,
     y los triggers que cubrian INSERT OR UPDATE se separan en dos.
   ===================================================================== */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP DATABASE IF EXISTS minimarket_mass;
CREATE DATABASE minimarket_mass
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE minimarket_mass;

/* =====================================================================
   1. UBICACION Y ORGANIZACION
   ===================================================================== */

-- 1. distrito
CREATE TABLE distrito (
    id_distrito   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(60)  NOT NULL,
    CONSTRAINT pk_distrito        PRIMARY KEY (id_distrito),
    CONSTRAINT uq_distrito_nombre UNIQUE (nombre)
) ENGINE=InnoDB COMMENT='Distritos de Arequipa donde estan las tiendas';

-- 2. tienda (cada tienda esta en un distrito distinto: RN-01)
CREATE TABLE tienda (
    id_tienda     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_distrito   INT UNSIGNED NOT NULL,
    codigo        VARCHAR(10)  NOT NULL,
    nombre        VARCHAR(80)  NOT NULL,
    direccion     VARCHAR(150) NOT NULL,
    estado        VARCHAR(10)  NOT NULL DEFAULT 'ACTIVA',
    CONSTRAINT pk_tienda          PRIMARY KEY (id_tienda),
    CONSTRAINT uq_tienda_codigo   UNIQUE (codigo),
    CONSTRAINT uq_tienda_distrito UNIQUE (id_distrito),
    CONSTRAINT fk_tienda_distrito FOREIGN KEY (id_distrito) REFERENCES distrito (id_distrito),
    CONSTRAINT ck_tienda_estado   CHECK (estado IN ('ACTIVA', 'INACTIVA'))
) ENGINE=InnoDB COMMENT='Sedes de Tienda Mass (una por distrito)';

-- 3. rol (RN-02, RN-03)
CREATE TABLE rol (
    id_rol        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(20)  NOT NULL,
    CONSTRAINT pk_rol        PRIMARY KEY (id_rol),
    CONSTRAINT uq_rol_nombre UNIQUE (nombre),
    CONSTRAINT ck_rol_nombre CHECK (nombre IN ('ADMINISTRADOR', 'CAJERO', 'ALMACENERO'))
) ENGINE=InnoDB COMMENT='Roles: administrador, cajero, almacenero';

-- 4. usuario (pertenece a una sola tienda y tiene un rol: RN-02)
CREATE TABLE usuario (
    id_usuario    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_tienda     INT UNSIGNED NOT NULL,
    id_rol        INT UNSIGNED NOT NULL,
    nombres       VARCHAR(80)  NOT NULL,
    apellidos     VARCHAR(80)  NOT NULL,
    dni           CHAR(8)      NOT NULL,
    login         VARCHAR(40)  NOT NULL,
    clave_hash    VARCHAR(255) NOT NULL,
    estado        VARCHAR(10)  NOT NULL DEFAULT 'ACTIVO',
    CONSTRAINT pk_usuario        PRIMARY KEY (id_usuario),
    CONSTRAINT uq_usuario_dni    UNIQUE (dni),
    CONSTRAINT uq_usuario_login  UNIQUE (login),
    CONSTRAINT fk_usuario_tienda FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_usuario_rol    FOREIGN KEY (id_rol)    REFERENCES rol (id_rol),
    CONSTRAINT ck_usuario_dni    CHECK (dni REGEXP '^[0-9]{8}$'),
    CONSTRAINT ck_usuario_estado CHECK (estado IN ('ACTIVO', 'INACTIVO'))
) ENGINE=InnoDB COMMENT='Personal de una tienda, con un rol';

/* =====================================================================
   2. CLIENTES
   ===================================================================== */

-- 5. tipo_documento_identidad (DNI = 8 digitos, RUC = 11 digitos: RN-04, RN-06)
CREATE TABLE tipo_documento_identidad (
    id_tipo_documento INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    codigo            VARCHAR(5)       NOT NULL,
    nombre            VARCHAR(60)      NOT NULL,
    longitud          TINYINT UNSIGNED NOT NULL,
    CONSTRAINT pk_tipo_documento        PRIMARY KEY (id_tipo_documento),
    CONSTRAINT uq_tipo_documento_codigo UNIQUE (codigo),
    CONSTRAINT ck_tipo_documento_codigo CHECK (codigo IN ('DNI', 'RUC')),
    CONSTRAINT ck_tipo_documento_long   CHECK (longitud IN (8, 11))
) ENGINE=InnoDB COMMENT='DNI (8 digitos) o RUC (11 digitos)';

-- 6. cliente (opcional en boleta, obligatorio en factura: RN-04, RN-05)
CREATE TABLE cliente (
    id_cliente          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_tipo_documento   INT UNSIGNED NOT NULL,
    numero_documento    VARCHAR(11)  NOT NULL,
    nombre_razon_social VARCHAR(150) NOT NULL,
    direccion           VARCHAR(150) NULL,
    telefono            VARCHAR(15)  NULL,
    correo              VARCHAR(100) NULL,
    estado              VARCHAR(10)  NOT NULL DEFAULT 'ACTIVO',
    CONSTRAINT pk_cliente           PRIMARY KEY (id_cliente),
    CONSTRAINT uq_cliente_documento UNIQUE (id_tipo_documento, numero_documento),
    CONSTRAINT fk_cliente_tipo_doc  FOREIGN KEY (id_tipo_documento)
                                    REFERENCES tipo_documento_identidad (id_tipo_documento),
    CONSTRAINT ck_cliente_numero    CHECK (numero_documento REGEXP '^[0-9]+$'),
    CONSTRAINT ck_cliente_estado    CHECK (estado IN ('ACTIVO', 'INACTIVO'))
) ENGINE=InnoDB COMMENT='Clientes; opcional en boleta, obligatorio en factura';

/* =====================================================================
   3. CATALOGO
   ===================================================================== */

-- 7. categoria
CREATE TABLE categoria (
    id_categoria  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(60)  NOT NULL,
    descripcion   VARCHAR(150) NULL,
    CONSTRAINT pk_categoria        PRIMARY KEY (id_categoria),
    CONSTRAINT uq_categoria_nombre UNIQUE (nombre)
) ENGINE=InnoDB COMMENT='Categorias de producto';

-- 8. marca
CREATE TABLE marca (
    id_marca         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre           VARCHAR(60)  NOT NULL,
    es_marca_propia  CHAR(1)      NOT NULL DEFAULT 'N',
    CONSTRAINT pk_marca        PRIMARY KEY (id_marca),
    CONSTRAINT uq_marca_nombre UNIQUE (nombre),
    CONSTRAINT ck_marca_propia CHECK (es_marca_propia IN ('S', 'N'))
) ENGINE=InnoDB COMMENT='Marcas de producto, propias o de terceros';

-- 9. unidad_medida
CREATE TABLE unidad_medida (
    id_unidad     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo        VARCHAR(5)   NOT NULL,
    nombre        VARCHAR(40)  NOT NULL,
    CONSTRAINT pk_unidad_medida        PRIMARY KEY (id_unidad),
    CONSTRAINT uq_unidad_medida_codigo UNIQUE (codigo)
) ENGINE=InnoDB COMMENT='Unidades de medida';

-- 10. producto (un solo precio para todas las tiendas, IGV incluido: RN-07, RN-08)
CREATE TABLE producto (
    id_producto    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_categoria   INT UNSIGNED  NOT NULL,
    id_marca       INT UNSIGNED  NOT NULL,
    id_unidad      INT UNSIGNED  NOT NULL,
    codigo_barras  VARCHAR(20)   NOT NULL,
    nombre         VARCHAR(120)  NOT NULL,
    precio_venta   DECIMAL(10,2) NOT NULL,
    estado         VARCHAR(10)   NOT NULL DEFAULT 'ACTIVO',
    CONSTRAINT pk_producto          PRIMARY KEY (id_producto),
    CONSTRAINT uq_producto_barras   UNIQUE (codigo_barras),
    CONSTRAINT fk_producto_cat      FOREIGN KEY (id_categoria) REFERENCES categoria (id_categoria),
    CONSTRAINT fk_producto_marca    FOREIGN KEY (id_marca)     REFERENCES marca (id_marca),
    CONSTRAINT fk_producto_unidad   FOREIGN KEY (id_unidad)    REFERENCES unidad_medida (id_unidad),
    CONSTRAINT ck_producto_precio   CHECK (precio_venta > 0),
    CONSTRAINT ck_producto_estado   CHECK (estado IN ('ACTIVO', 'INACTIVO'))
) ENGINE=InnoDB COMMENT='Producto con precio unico (IGV incluido)';

/* =====================================================================
   4. INVENTARIO
   ===================================================================== */

-- 11. stock (puente producto-tienda; nunca negativo: RN-09, RN-10)
CREATE TABLE stock (
    id_tienda     INT UNSIGNED  NOT NULL,
    id_producto   INT UNSIGNED  NOT NULL,
    cantidad      DECIMAL(12,3) NOT NULL DEFAULT 0,
    stock_minimo  DECIMAL(12,3) NOT NULL DEFAULT 0,
    CONSTRAINT pk_stock          PRIMARY KEY (id_tienda, id_producto),
    CONSTRAINT fk_stock_tienda   FOREIGN KEY (id_tienda)   REFERENCES tienda (id_tienda),
    CONSTRAINT fk_stock_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto),
    CONSTRAINT ck_stock_cantidad CHECK (cantidad >= 0),
    CONSTRAINT ck_stock_minimo   CHECK (stock_minimo >= 0)
) ENGINE=InnoDB COMMENT='Cantidad actual por tienda y producto; nunca negativa';

-- 12. tipo_movimiento (E = entrada, S = salida: RN-12)
CREATE TABLE tipo_movimiento (
    id_tipo_movimiento INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo             VARCHAR(20)  NOT NULL,
    nombre             VARCHAR(60)  NOT NULL,
    efecto             CHAR(1)      NOT NULL,
    CONSTRAINT pk_tipo_movimiento        PRIMARY KEY (id_tipo_movimiento),
    CONSTRAINT uq_tipo_movimiento_codigo UNIQUE (codigo),
    CONSTRAINT ck_tipo_movimiento_efecto CHECK (efecto IN ('E', 'S'))
) ENGINE=InnoDB COMMENT='Tipos de movimiento de stock (entrada o salida)';

-- 13. movimiento_stock (kardex: RN-11, RN-12)
--     Las claves foraneas hacia venta, traslado_mercaderia y merma se agregan
--     al final de las tablas (esas tablas se crean mas abajo).
CREATE TABLE movimiento_stock (
    id_movimiento      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_tienda          INT UNSIGNED  NOT NULL,
    id_producto        INT UNSIGNED  NOT NULL,
    id_tipo_movimiento INT UNSIGNED  NOT NULL,
    id_usuario         INT UNSIGNED  NOT NULL,
    cantidad           DECIMAL(12,3) NOT NULL,
    stock_resultante   DECIMAL(12,3) NOT NULL,
    fecha_hora         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_venta           INT UNSIGNED  NULL,
    id_traslado        INT UNSIGNED  NULL,
    id_merma           INT UNSIGNED  NULL,
    observacion        VARCHAR(200)  NULL,
    CONSTRAINT pk_movimiento_stock PRIMARY KEY (id_movimiento),
    CONSTRAINT fk_mov_tienda       FOREIGN KEY (id_tienda)          REFERENCES tienda (id_tienda),
    CONSTRAINT fk_mov_producto     FOREIGN KEY (id_producto)        REFERENCES producto (id_producto),
    CONSTRAINT fk_mov_tipo         FOREIGN KEY (id_tipo_movimiento) REFERENCES tipo_movimiento (id_tipo_movimiento),
    CONSTRAINT fk_mov_usuario      FOREIGN KEY (id_usuario)         REFERENCES usuario (id_usuario),
    CONSTRAINT ck_mov_cantidad     CHECK (cantidad > 0),
    CONSTRAINT ck_mov_resultante   CHECK (stock_resultante >= 0),
    CONSTRAINT ck_mov_un_origen    CHECK (
        (CASE WHEN id_venta    IS NULL THEN 0 ELSE 1 END
       + CASE WHEN id_traslado IS NULL THEN 0 ELSE 1 END
       + CASE WHEN id_merma    IS NULL THEN 0 ELSE 1 END) <= 1)
) ENGINE=InnoDB COMMENT='Kardex: cada entrada o salida de stock';

-- 14. traslado_mercaderia (origen y destino distintos: RN-13)
CREATE TABLE traslado_mercaderia (
    id_traslado         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_tienda_origen    INT UNSIGNED NOT NULL,
    id_tienda_destino   INT UNSIGNED NOT NULL,
    id_usuario          INT UNSIGNED NOT NULL,
    fecha_hora          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion         VARCHAR(200) NULL,
    CONSTRAINT pk_traslado          PRIMARY KEY (id_traslado),
    CONSTRAINT fk_traslado_origen   FOREIGN KEY (id_tienda_origen)  REFERENCES tienda (id_tienda),
    CONSTRAINT fk_traslado_destino  FOREIGN KEY (id_tienda_destino) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_traslado_usuario  FOREIGN KEY (id_usuario)        REFERENCES usuario (id_usuario),
    CONSTRAINT ck_traslado_tiendas  CHECK (id_tienda_origen <> id_tienda_destino)
) ENGINE=InnoDB COMMENT='Traslado de producto entre dos tiendas';

-- 15. detalle_traslado
CREATE TABLE detalle_traslado (
    id_detalle_traslado INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_traslado         INT UNSIGNED  NOT NULL,
    id_producto         INT UNSIGNED  NOT NULL,
    cantidad            DECIMAL(12,3) NOT NULL,
    CONSTRAINT pk_detalle_traslado   PRIMARY KEY (id_detalle_traslado),
    CONSTRAINT uq_detalle_traslado   UNIQUE (id_traslado, id_producto),
    CONSTRAINT fk_dtraslado_traslado FOREIGN KEY (id_traslado) REFERENCES traslado_mercaderia (id_traslado),
    CONSTRAINT fk_dtraslado_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto),
    CONSTRAINT ck_dtraslado_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB COMMENT='Productos y cantidades de cada traslado';

-- 16. merma (RN-14)
CREATE TABLE merma (
    id_merma      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_tienda     INT UNSIGNED NOT NULL,
    id_usuario    INT UNSIGNED NOT NULL,
    fecha_hora    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo        VARCHAR(20)  NOT NULL,
    observacion   VARCHAR(200) NULL,
    CONSTRAINT pk_merma         PRIMARY KEY (id_merma),
    CONSTRAINT fk_merma_tienda  FOREIGN KEY (id_tienda)  REFERENCES tienda (id_tienda),
    CONSTRAINT fk_merma_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario),
    CONSTRAINT ck_merma_motivo  CHECK (motivo IN ('VENCIMIENTO', 'DETERIORO', 'ROBO', 'OTRO'))
) ENGINE=InnoDB COMMENT='Perdida de producto por vencimiento o deterioro';

-- 17. detalle_merma
CREATE TABLE detalle_merma (
    id_detalle_merma INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_merma         INT UNSIGNED  NOT NULL,
    id_producto      INT UNSIGNED  NOT NULL,
    cantidad         DECIMAL(12,3) NOT NULL,
    CONSTRAINT pk_detalle_merma   PRIMARY KEY (id_detalle_merma),
    CONSTRAINT uq_detalle_merma   UNIQUE (id_merma, id_producto),
    CONSTRAINT fk_dmerma_merma    FOREIGN KEY (id_merma)    REFERENCES merma (id_merma),
    CONSTRAINT fk_dmerma_producto FOREIGN KEY (id_producto) REFERENCES producto (id_producto),
    CONSTRAINT ck_dmerma_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB COMMENT='Productos y cantidades de cada merma';

/* =====================================================================
   5. CAJA
   ===================================================================== */

-- 18. caja (RN-15)
CREATE TABLE caja (
    id_caja       INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    id_tienda     INT UNSIGNED     NOT NULL,
    numero        TINYINT UNSIGNED NOT NULL,
    descripcion   VARCHAR(60)      NULL,
    estado        VARCHAR(10)      NOT NULL DEFAULT 'ACTIVA',
    CONSTRAINT pk_caja         PRIMARY KEY (id_caja),
    CONSTRAINT uq_caja_numero  UNIQUE (id_tienda, numero),
    CONSTRAINT fk_caja_tienda  FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT ck_caja_estado  CHECK (estado IN ('ACTIVA', 'INACTIVA'))
) ENGINE=InnoDB COMMENT='Punto de cobro fisico de una tienda';

-- 19. caja_turno (RN-16, RN-17)
--     RN-17: una caja no puede tener dos turnos abiertos a la vez, y un cajero
--     tampoco. Las columnas generadas valen NULL cuando el turno esta cerrado,
--     asi que el UNIQUE solo aplica a los turnos ABIERTOS.
CREATE TABLE caja_turno (
    id_caja_turno   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_caja         INT UNSIGNED  NOT NULL,
    id_usuario      INT UNSIGNED  NOT NULL,
    fecha_apertura  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto_apertura  DECIMAL(12,2) NOT NULL DEFAULT 0,
    fecha_cierre    DATETIME      NULL,
    monto_cierre    DECIMAL(12,2) NULL,
    estado          VARCHAR(10)   NOT NULL DEFAULT 'ABIERTO',
    abierto_caja    INT UNSIGNED AS (CASE WHEN estado = 'ABIERTO' THEN id_caja    END) VIRTUAL,
    abierto_usuario INT UNSIGNED AS (CASE WHEN estado = 'ABIERTO' THEN id_usuario END) VIRTUAL,
    CONSTRAINT pk_caja_turno        PRIMARY KEY (id_caja_turno),
    CONSTRAINT fk_turno_caja        FOREIGN KEY (id_caja)    REFERENCES caja (id_caja),
    CONSTRAINT fk_turno_usuario     FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario),
    CONSTRAINT ux_turno_abierto_caja    UNIQUE (abierto_caja),
    CONSTRAINT ux_turno_abierto_usuario UNIQUE (abierto_usuario),
    CONSTRAINT ck_turno_estado      CHECK (estado IN ('ABIERTO', 'CERRADO')),
    CONSTRAINT ck_turno_monto_ap    CHECK (monto_apertura >= 0),
    CONSTRAINT ck_turno_cierre      CHECK (
        (estado = 'ABIERTO' AND fecha_cierre IS NULL     AND monto_cierre IS NULL)
     OR (estado = 'CERRADO' AND fecha_cierre IS NOT NULL AND monto_cierre IS NOT NULL AND monto_cierre >= 0)),
    CONSTRAINT ck_turno_fechas      CHECK (fecha_cierre IS NULL OR fecha_cierre >= fecha_apertura)
) ENGINE=InnoDB COMMENT='Turno de caja: apertura y cierre por cajero';

/* =====================================================================
   6. VENTAS Y COMPROBANTES
   ===================================================================== */

-- 20. tipo_comprobante (RN-18)
CREATE TABLE tipo_comprobante (
    id_tipo_comprobante INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo              VARCHAR(10)  NOT NULL,
    nombre              VARCHAR(40)  NOT NULL,
    prefijo_serie       CHAR(1)      NOT NULL,
    CONSTRAINT pk_tipo_comprobante         PRIMARY KEY (id_tipo_comprobante),
    CONSTRAINT uq_tipo_comprobante_codigo  UNIQUE (codigo),
    CONSTRAINT uq_tipo_comprobante_prefijo UNIQUE (prefijo_serie),
    CONSTRAINT ck_tipo_comprobante_codigo  CHECK (codigo IN ('BOLETA', 'FACTURA')),
    CONSTRAINT ck_tipo_comprobante_prefijo CHECK (prefijo_serie IN ('B', 'F'))
) ENGINE=InnoDB COMMENT='Boleta o factura';

-- 21. serie_comprobante (serie propia por tienda y tipo; correlativo propio: RN-19)
CREATE TABLE serie_comprobante (
    id_serie            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_tienda           INT UNSIGNED NOT NULL,
    id_tipo_comprobante INT UNSIGNED NOT NULL,
    serie               VARCHAR(4)   NOT NULL,
    ultimo_correlativo  INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT pk_serie_comprobante   PRIMARY KEY (id_serie),
    CONSTRAINT uq_serie_codigo        UNIQUE (serie),
    CONSTRAINT uq_serie_tienda_tipo   UNIQUE (id_tienda, id_tipo_comprobante),
    CONSTRAINT fk_serie_tienda        FOREIGN KEY (id_tienda) REFERENCES tienda (id_tienda),
    CONSTRAINT fk_serie_tipo          FOREIGN KEY (id_tipo_comprobante)
                                      REFERENCES tipo_comprobante (id_tipo_comprobante),
    CONSTRAINT ck_serie_formato       CHECK (serie REGEXP '^[BF][0-9]{3}$'),
    CONSTRAINT ck_serie_correlativo   CHECK (ultimo_correlativo BETWEEN 0 AND 99999999)
) ENGINE=InnoDB COMMENT='Serie por tienda y tipo, con su ultimo correlativo';

-- 22. venta (RN-19 a RN-22). El precio incluye IGV: total = subtotal + igv
--     El correlativo lo asigna el trigger trg_venta_bi (por eso tiene DEFAULT 0).
CREATE TABLE venta (
    id_venta        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_serie        INT UNSIGNED  NOT NULL,
    correlativo     INT UNSIGNED  NOT NULL DEFAULT 0,
    id_caja_turno   INT UNSIGNED  NOT NULL,
    id_usuario      INT UNSIGNED  NOT NULL,
    id_cliente      INT UNSIGNED  NULL,
    fecha_hora      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal        DECIMAL(12,2) NOT NULL,
    igv             DECIMAL(12,2) NOT NULL,
    total           DECIMAL(12,2) NOT NULL,
    estado          VARCHAR(10)   NOT NULL DEFAULT 'EMITIDA',
    CONSTRAINT pk_venta             PRIMARY KEY (id_venta),
    CONSTRAINT uq_venta_comprobante UNIQUE (id_serie, correlativo),
    CONSTRAINT fk_venta_serie       FOREIGN KEY (id_serie)      REFERENCES serie_comprobante (id_serie),
    CONSTRAINT fk_venta_turno       FOREIGN KEY (id_caja_turno) REFERENCES caja_turno (id_caja_turno),
    CONSTRAINT fk_venta_usuario     FOREIGN KEY (id_usuario)    REFERENCES usuario (id_usuario),
    CONSTRAINT fk_venta_cliente     FOREIGN KEY (id_cliente)    REFERENCES cliente (id_cliente),
    CONSTRAINT ck_venta_estado      CHECK (estado IN ('EMITIDA', 'ANULADA')),
    CONSTRAINT ck_venta_importes    CHECK (subtotal >= 0 AND igv >= 0 AND total > 0),
    CONSTRAINT ck_venta_total       CHECK (total = subtotal + igv)
) ENGINE=InnoDB COMMENT='Cabecera de venta: subtotal, igv y total; estado emitida o anulada';

-- 23. detalle_venta
CREATE TABLE detalle_venta (
    id_detalle_venta INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_venta         INT UNSIGNED  NOT NULL,
    id_producto      INT UNSIGNED  NOT NULL,
    cantidad         DECIMAL(12,3) NOT NULL,
    precio_unitario  DECIMAL(10,2) NOT NULL,
    total_linea      DECIMAL(12,2) NOT NULL,
    CONSTRAINT pk_detalle_venta      PRIMARY KEY (id_detalle_venta),
    CONSTRAINT uq_detalle_venta      UNIQUE (id_venta, id_producto),
    CONSTRAINT fk_dventa_venta       FOREIGN KEY (id_venta)    REFERENCES venta (id_venta),
    CONSTRAINT fk_dventa_producto    FOREIGN KEY (id_producto) REFERENCES producto (id_producto),
    CONSTRAINT ck_dventa_cantidad    CHECK (cantidad > 0),
    CONSTRAINT ck_dventa_precio      CHECK (precio_unitario > 0),
    CONSTRAINT ck_dventa_total_linea CHECK (total_linea = ROUND(cantidad * precio_unitario, 2))
) ENGINE=InnoDB COMMENT='Productos, cantidad y precio de cada venta';

-- 24. anulacion_venta (RN-23). Una venta se anula una sola vez.
CREATE TABLE anulacion_venta (
    id_anulacion  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_venta      INT UNSIGNED NOT NULL,
    id_usuario    INT UNSIGNED NOT NULL,
    fecha_hora    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo        VARCHAR(200) NOT NULL,
    CONSTRAINT pk_anulacion_venta   PRIMARY KEY (id_anulacion),
    CONSTRAINT uq_anulacion_venta   UNIQUE (id_venta),
    CONSTRAINT fk_anulacion_venta   FOREIGN KEY (id_venta)   REFERENCES venta (id_venta),
    CONSTRAINT fk_anulacion_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
) ENGINE=InnoDB COMMENT='Registro de quien, cuando y por que se anulo una venta';

/* =====================================================================
   7. PAGOS
   ===================================================================== */

-- 25. metodo_pago (RN-24, RN-25)
CREATE TABLE metodo_pago (
    id_metodo_pago INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo         VARCHAR(10)  NOT NULL,
    nombre         VARCHAR(40)  NOT NULL,
    estado         VARCHAR(10)  NOT NULL DEFAULT 'ACTIVO',
    CONSTRAINT pk_metodo_pago        PRIMARY KEY (id_metodo_pago),
    CONSTRAINT uq_metodo_pago_codigo UNIQUE (codigo),
    CONSTRAINT ck_metodo_pago_codigo CHECK (codigo IN ('EFECTIVO', 'TARJETA', 'SIP')),
    CONSTRAINT ck_metodo_pago_estado CHECK (estado IN ('ACTIVO', 'INACTIVO'))
) ENGINE=InnoDB COMMENT='Efectivo, tarjeta, billetera digital Sip';

-- 26. pago_venta (una venta puede tener varios pagos: RN-24, RN-28)
CREATE TABLE pago_venta (
    id_pago         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    id_venta        INT UNSIGNED  NOT NULL,
    id_metodo_pago  INT UNSIGNED  NOT NULL,
    monto           DECIMAL(12,2) NOT NULL,
    fecha_hora      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado          VARCHAR(10)   NOT NULL DEFAULT 'APLICADO',
    CONSTRAINT pk_pago_venta     PRIMARY KEY (id_pago),
    CONSTRAINT fk_pago_venta     FOREIGN KEY (id_venta)       REFERENCES venta (id_venta),
    CONSTRAINT fk_pago_metodo    FOREIGN KEY (id_metodo_pago) REFERENCES metodo_pago (id_metodo_pago),
    CONSTRAINT ck_pago_monto     CHECK (monto > 0),
    CONSTRAINT ck_pago_estado    CHECK (estado IN ('APLICADO', 'ANULADO'))
) ENGINE=InnoDB COMMENT='Un registro por cada pago de una venta';

-- 27. efectivo (monto aplicado = monto recibido - vuelto: RN-27)
CREATE TABLE efectivo (
    id_pago         INT UNSIGNED  NOT NULL,
    monto_recibido  DECIMAL(12,2) NOT NULL,
    vuelto          DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT pk_efectivo          PRIMARY KEY (id_pago),
    CONSTRAINT fk_efectivo_pago     FOREIGN KEY (id_pago) REFERENCES pago_venta (id_pago),
    CONSTRAINT ck_efectivo_recibido CHECK (monto_recibido > 0),
    CONSTRAINT ck_efectivo_vuelto   CHECK (vuelto >= 0 AND vuelto < monto_recibido)
) ENGINE=InnoDB COMMENT='Detalle de pago en efectivo: monto recibido y vuelto';

-- 28. tarjeta (nunca se guarda el numero completo: solo los ultimos 4 digitos)
CREATE TABLE tarjeta (
    id_pago              INT UNSIGNED NOT NULL,
    tipo_tarjeta         VARCHAR(10)  NOT NULL,
    marca                VARCHAR(20)  NOT NULL,
    ultimos_4            CHAR(4)      NOT NULL,
    codigo_autorizacion  VARCHAR(20)  NOT NULL,
    CONSTRAINT pk_tarjeta        PRIMARY KEY (id_pago),
    CONSTRAINT fk_tarjeta_pago   FOREIGN KEY (id_pago) REFERENCES pago_venta (id_pago),
    CONSTRAINT ck_tarjeta_tipo   CHECK (tipo_tarjeta IN ('DEBITO', 'CREDITO')),
    CONSTRAINT ck_tarjeta_marca  CHECK (marca IN ('VISA', 'MASTERCARD', 'AMERICAN_EXPRESS', 'DINERS', 'OTRA')),
    CONSTRAINT ck_tarjeta_ult4   CHECK (ultimos_4 REGEXP '^[0-9]{4}$')
) ENGINE=InnoDB COMMENT='Detalle de pago con tarjeta';

-- 29. billetera_digital_sip (RN-25, RN-26)
CREATE TABLE billetera_digital_sip (
    id_pago            INT UNSIGNED NOT NULL,
    modalidad          VARCHAR(15)  NOT NULL,
    codigo_operacion   VARCHAR(30)  NOT NULL,
    CONSTRAINT pk_billetera_sip      PRIMARY KEY (id_pago),
    CONSTRAINT uq_billetera_sip_oper UNIQUE (codigo_operacion),
    CONSTRAINT fk_billetera_sip_pago FOREIGN KEY (id_pago) REFERENCES pago_venta (id_pago),
    CONSTRAINT ck_billetera_sip_mod  CHECK (modalidad IN ('QR', 'TARJETA_SIP'))
) ENGINE=InnoDB COMMENT='Detalle de pago con la billetera digital Sip';

/* =====================================================================
   CLAVES FORANEAS DIFERIDAS (movimiento_stock -> venta, traslado, merma)
   ===================================================================== */
ALTER TABLE movimiento_stock ADD CONSTRAINT fk_mov_venta
    FOREIGN KEY (id_venta) REFERENCES venta (id_venta);
ALTER TABLE movimiento_stock ADD CONSTRAINT fk_mov_traslado
    FOREIGN KEY (id_traslado) REFERENCES traslado_mercaderia (id_traslado);
ALTER TABLE movimiento_stock ADD CONSTRAINT fk_mov_merma
    FOREIGN KEY (id_merma) REFERENCES merma (id_merma);

/* =====================================================================
   INDICES sobre claves foraneas de consulta frecuente
   ===================================================================== */
CREATE INDEX ix_usuario_tienda       ON usuario (id_tienda);
CREATE INDEX ix_usuario_rol          ON usuario (id_rol);
CREATE INDEX ix_producto_categoria   ON producto (id_categoria);
CREATE INDEX ix_producto_marca       ON producto (id_marca);
CREATE INDEX ix_stock_producto       ON stock (id_producto);
CREATE INDEX ix_mov_tienda_prod_fec  ON movimiento_stock (id_tienda, id_producto, fecha_hora);
CREATE INDEX ix_mov_venta            ON movimiento_stock (id_venta);
CREATE INDEX ix_caja_turno_caja      ON caja_turno (id_caja);
CREATE INDEX ix_venta_turno          ON venta (id_caja_turno);
CREATE INDEX ix_venta_cliente        ON venta (id_cliente);
CREATE INDEX ix_venta_fecha          ON venta (fecha_hora);
CREATE INDEX ix_detalle_venta_prod   ON detalle_venta (id_producto);
CREATE INDEX ix_pago_venta_venta     ON pago_venta (id_venta);
CREATE INDEX ix_pago_venta_metodo    ON pago_venta (id_metodo_pago);

/* =====================================================================
   LOGICA DE NEGOCIO: procedimientos, funcion, triggers y vista
   ===================================================================== */
DELIMITER $$

/* ---------------------------------------------------------------------
   sp_mover_stock
   Unico punto por donde cambia el stock (RN-09 a RN-12).
   - Salida: valida que alcance el stock (RN-10) y lo descuenta.
   - Entrada: suma al stock (crea la fila si no existe).
   - Siempre deja un registro en movimiento_stock (kardex).
   --------------------------------------------------------------------- */
CREATE PROCEDURE sp_mover_stock (
    IN p_id_tienda    INT UNSIGNED,
    IN p_id_producto  INT UNSIGNED,
    IN p_codigo_mov   VARCHAR(20),
    IN p_cantidad     DECIMAL(12,3),
    IN p_id_usuario   INT UNSIGNED,
    IN p_id_venta     INT UNSIGNED,
    IN p_id_traslado  INT UNSIGNED,
    IN p_id_merma     INT UNSIGNED,
    IN p_observacion  VARCHAR(200)
)
BEGIN
    DECLARE v_id_tipo INT UNSIGNED DEFAULT NULL;
    DECLARE v_efecto  CHAR(1)      DEFAULT NULL;
    DECLARE v_actual  DECIMAL(12,3) DEFAULT 0;
    DECLARE v_nuevo   DECIMAL(12,3);

    SELECT id_tipo_movimiento, efecto
      INTO v_id_tipo, v_efecto
      FROM tipo_movimiento
     WHERE codigo = p_codigo_mov;

    IF v_id_tipo IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20011,
            MESSAGE_TEXT = 'Tipo de movimiento de stock inexistente.';
    END IF;

    IF p_cantidad IS NULL OR p_cantidad <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20012,
            MESSAGE_TEXT = 'La cantidad del movimiento debe ser mayor que cero.';
    END IF;

    -- Bloquea la fila de stock; si no existe, el disponible es 0
    SELECT cantidad
      INTO v_actual
      FROM stock
     WHERE id_tienda = p_id_tienda
       AND id_producto = p_id_producto
       FOR UPDATE;

    IF v_efecto = 'S' THEN
        IF v_actual < p_cantidad THEN
            SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20010,
                MESSAGE_TEXT = 'Stock insuficiente para realizar la salida (RN-10).';
        END IF;
        SET v_nuevo = v_actual - p_cantidad;
    ELSE
        SET v_nuevo = v_actual + p_cantidad;
    END IF;

    -- Primero el kardex (si falla, el stock no se toca), luego el stock
    INSERT INTO movimiento_stock
        (id_tienda, id_producto, id_tipo_movimiento, id_usuario, cantidad,
         stock_resultante, id_venta, id_traslado, id_merma, observacion)
    VALUES
        (p_id_tienda, p_id_producto, v_id_tipo, p_id_usuario, p_cantidad,
         v_nuevo, p_id_venta, p_id_traslado, p_id_merma, p_observacion);

    INSERT INTO stock (id_tienda, id_producto, cantidad)
    VALUES (p_id_tienda, p_id_producto, v_nuevo)
    ON DUPLICATE KEY UPDATE cantidad = v_nuevo;
END$$

/* ---------------------------------------------------------------------
   Funcion auxiliar: codigo del metodo de un pago
   --------------------------------------------------------------------- */
CREATE FUNCTION fn_codigo_metodo_pago (p_id_pago INT UNSIGNED)
RETURNS VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
READS SQL DATA
BEGIN
    DECLARE v_codigo VARCHAR(10) DEFAULT NULL;

    SELECT m.codigo
      INTO v_codigo
      FROM pago_venta p
      JOIN metodo_pago m ON m.id_metodo_pago = p.id_metodo_pago
     WHERE p.id_pago = p_id_pago;

    RETURN v_codigo;
END$$

/* ---------------------------------------------------------------------
   Validaciones reutilizables (las llaman los triggers de INSERT y UPDATE)
   --------------------------------------------------------------------- */

-- Clientes: el numero debe tener la longitud de su tipo (RN-04, RN-06)
CREATE PROCEDURE sp_valida_documento_cliente (
    IN p_id_tipo_documento INT UNSIGNED,
    IN p_numero_documento  VARCHAR(11)
)
BEGIN
    DECLARE v_longitud TINYINT UNSIGNED DEFAULT NULL;

    SELECT longitud
      INTO v_longitud
      FROM tipo_documento_identidad
     WHERE id_tipo_documento = p_id_tipo_documento;

    IF CHAR_LENGTH(p_numero_documento) <> v_longitud THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20001,
            MESSAGE_TEXT = 'El numero de documento no tiene la longitud de su tipo (DNI 8, RUC 11).';
    END IF;
END$$

-- Series: la serie empieza con la letra del tipo (B boleta, F factura)
CREATE PROCEDURE sp_valida_prefijo_serie (
    IN p_serie                VARCHAR(4),
    IN p_id_tipo_comprobante  INT UNSIGNED
)
BEGIN
    DECLARE v_prefijo CHAR(1) DEFAULT NULL;

    SELECT prefijo_serie
      INTO v_prefijo
      FROM tipo_comprobante
     WHERE id_tipo_comprobante = p_id_tipo_comprobante;

    IF SUBSTRING(p_serie, 1, 1) <> v_prefijo THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20002,
            MESSAGE_TEXT = 'La serie debe empezar con la letra del tipo de comprobante (B o F).';
    END IF;
END$$

-- Ventas: la factura exige cliente con RUC (RN-04)
CREATE PROCEDURE sp_valida_factura (
    IN p_id_serie    INT UNSIGNED,
    IN p_id_cliente  INT UNSIGNED
)
BEGIN
    DECLARE v_cod_comprobante VARCHAR(10) DEFAULT NULL;
    DECLARE v_cod_documento   VARCHAR(5)  DEFAULT NULL;

    SELECT tc.codigo
      INTO v_cod_comprobante
      FROM serie_comprobante s
      JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = s.id_tipo_comprobante
     WHERE s.id_serie = p_id_serie;

    IF v_cod_comprobante = 'FACTURA' THEN
        IF p_id_cliente IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20020,
                MESSAGE_TEXT = 'La factura exige un cliente con RUC.';
        END IF;

        SELECT td.codigo
          INTO v_cod_documento
          FROM cliente c
          JOIN tipo_documento_identidad td ON td.id_tipo_documento = c.id_tipo_documento
         WHERE c.id_cliente = p_id_cliente;

        IF v_cod_documento <> 'RUC' THEN
            SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20021,
                MESSAGE_TEXT = 'El cliente de una factura debe tener RUC (11 digitos).';
        END IF;
    END IF;
END$$

-- Pagos: cada pago tiene un solo detalle, segun su metodo (RN-26, RN-27)
CREATE PROCEDURE sp_valida_efectivo (
    IN p_id_pago         INT UNSIGNED,
    IN p_monto_recibido  DECIMAL(12,2),
    IN p_vuelto          DECIMAL(12,2)
)
BEGIN
    DECLARE v_monto DECIMAL(12,2) DEFAULT NULL;

    IF fn_codigo_metodo_pago(p_id_pago) <> 'EFECTIVO' THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20030,
            MESSAGE_TEXT = 'El pago no es en efectivo.';
    END IF;

    SELECT monto INTO v_monto FROM pago_venta WHERE id_pago = p_id_pago;

    -- RN-27: en efectivo cuenta el monto recibido menos el vuelto
    IF p_monto_recibido - p_vuelto <> v_monto THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20031,
            MESSAGE_TEXT = 'En efectivo, monto recibido - vuelto debe ser igual al monto del pago.';
    END IF;
END$$

/* ---------------------------------------------------------------------
   Triggers de clientes y series
   --------------------------------------------------------------------- */
CREATE TRIGGER trg_cliente_bi BEFORE INSERT ON cliente
FOR EACH ROW
BEGIN
    CALL sp_valida_documento_cliente(NEW.id_tipo_documento, NEW.numero_documento);
END$$

CREATE TRIGGER trg_cliente_bu BEFORE UPDATE ON cliente
FOR EACH ROW
BEGIN
    CALL sp_valida_documento_cliente(NEW.id_tipo_documento, NEW.numero_documento);
END$$

CREATE TRIGGER trg_serie_bi BEFORE INSERT ON serie_comprobante
FOR EACH ROW
BEGIN
    CALL sp_valida_prefijo_serie(NEW.serie, NEW.id_tipo_comprobante);
END$$

CREATE TRIGGER trg_serie_bu BEFORE UPDATE ON serie_comprobante
FOR EACH ROW
BEGIN
    CALL sp_valida_prefijo_serie(NEW.serie, NEW.id_tipo_comprobante);
END$$

/* ---------------------------------------------------------------------
   Turnos: solo un CAJERO de la misma tienda abre la caja (RN-16, RN-17)
   --------------------------------------------------------------------- */
CREATE TRIGGER trg_turno_bi BEFORE INSERT ON caja_turno
FOR EACH ROW
BEGIN
    DECLARE v_tienda_caja     INT UNSIGNED DEFAULT NULL;
    DECLARE v_tienda_usuario  INT UNSIGNED DEFAULT NULL;
    DECLARE v_rol_usuario     VARCHAR(20)  DEFAULT NULL;

    SELECT id_tienda
      INTO v_tienda_caja
      FROM caja
     WHERE id_caja = NEW.id_caja;

    SELECT u.id_tienda, r.nombre
      INTO v_tienda_usuario, v_rol_usuario
      FROM usuario u
      JOIN rol r ON r.id_rol = u.id_rol
     WHERE u.id_usuario = NEW.id_usuario;

    IF v_rol_usuario <> 'CAJERO' THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20003,
            MESSAGE_TEXT = 'Solo un usuario con rol CAJERO puede abrir un turno de caja.';
    END IF;

    IF v_tienda_usuario <> v_tienda_caja THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20004,
            MESSAGE_TEXT = 'El cajero debe pertenecer a la tienda de la caja.';
    END IF;
END$$

/* ---------------------------------------------------------------------
   Ventas (RN-04, RN-16, RN-19, RN-21, RN-22)
   - La factura exige cliente con RUC.
   - Solo se vende con un turno ABIERTO del mismo cajero y de la misma
     tienda de la serie.
   - El correlativo se asigna solo, por serie (no se repite).
   - No se puede cambiar serie/correlativo ni reabrir una venta anulada.
   --------------------------------------------------------------------- */
CREATE TRIGGER trg_venta_bi BEFORE INSERT ON venta
FOR EACH ROW
BEGIN
    DECLARE v_tienda_serie   INT UNSIGNED DEFAULT NULL;
    DECLARE v_estado_turno   VARCHAR(10)  DEFAULT NULL;
    DECLARE v_usuario_turno  INT UNSIGNED DEFAULT NULL;
    DECLARE v_tienda_turno   INT UNSIGNED DEFAULT NULL;
    DECLARE v_correlativo    INT UNSIGNED DEFAULT NULL;

    -- RN-04: la factura exige cliente con RUC
    CALL sp_valida_factura(NEW.id_serie, NEW.id_cliente);

    SELECT id_tienda
      INTO v_tienda_serie
      FROM serie_comprobante
     WHERE id_serie = NEW.id_serie;

    -- RN-16: turno abierto, del mismo cajero y de la misma tienda
    SELECT ct.estado, ct.id_usuario, c.id_tienda
      INTO v_estado_turno, v_usuario_turno, v_tienda_turno
      FROM caja_turno ct
      JOIN caja c ON c.id_caja = ct.id_caja
     WHERE ct.id_caja_turno = NEW.id_caja_turno;

    IF v_estado_turno <> 'ABIERTO' THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20022,
            MESSAGE_TEXT = 'No se puede vender sin un turno de caja abierto.';
    END IF;
    IF v_usuario_turno <> NEW.id_usuario THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20023,
            MESSAGE_TEXT = 'El turno de caja pertenece a otro cajero.';
    END IF;
    IF v_tienda_turno <> v_tienda_serie THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20024,
            MESSAGE_TEXT = 'La serie y la caja deben ser de la misma tienda.';
    END IF;

    -- RN-19: correlativo propio de la serie (se asigna solo)
    UPDATE serie_comprobante
       SET ultimo_correlativo = ultimo_correlativo + 1
     WHERE id_serie = NEW.id_serie;

    SELECT ultimo_correlativo
      INTO v_correlativo
      FROM serie_comprobante
     WHERE id_serie = NEW.id_serie;

    SET NEW.correlativo = v_correlativo;
END$$

CREATE TRIGGER trg_venta_bu BEFORE UPDATE ON venta
FOR EACH ROW
BEGIN
    CALL sp_valida_factura(NEW.id_serie, NEW.id_cliente);

    IF NEW.id_serie <> OLD.id_serie OR NEW.correlativo <> OLD.correlativo THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20026,
            MESSAGE_TEXT = 'No se puede cambiar la serie ni el correlativo de un comprobante.';
    END IF;
    IF OLD.estado = 'ANULADA' AND NEW.estado <> 'ANULADA' THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20027,
            MESSAGE_TEXT = 'Una venta anulada no puede volver a emitirse.';
    END IF;
END$$

-- RN-22: las ventas no se borran
CREATE TRIGGER trg_venta_bd BEFORE DELETE ON venta
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20025,
        MESSAGE_TEXT = 'Las ventas no se borran. Use sp_anular_venta.';
END$$

/* ---------------------------------------------------------------------
   Cada linea de venta descuenta el stock de la tienda y deja un
   movimiento (RN-11)
   --------------------------------------------------------------------- */
CREATE TRIGGER trg_detalle_venta_ai AFTER INSERT ON detalle_venta
FOR EACH ROW
BEGIN
    DECLARE v_id_tienda   INT UNSIGNED DEFAULT NULL;
    DECLARE v_id_usuario  INT UNSIGNED DEFAULT NULL;
    DECLARE v_estado      VARCHAR(10)  DEFAULT NULL;

    SELECT s.id_tienda, v.id_usuario, v.estado
      INTO v_id_tienda, v_id_usuario, v_estado
      FROM venta v
      JOIN serie_comprobante s ON s.id_serie = v.id_serie
     WHERE v.id_venta = NEW.id_venta;

    IF v_estado <> 'EMITIDA' THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20028,
            MESSAGE_TEXT = 'No se pueden agregar productos a una venta anulada.';
    END IF;

    CALL sp_mover_stock(v_id_tienda, NEW.id_producto, 'VENTA', NEW.cantidad,
                        v_id_usuario, NEW.id_venta, NULL, NULL, NULL);
END$$

/* ---------------------------------------------------------------------
   Traslado: descuenta del origen y suma al destino (RN-13)
   --------------------------------------------------------------------- */
CREATE TRIGGER trg_detalle_traslado_ai AFTER INSERT ON detalle_traslado
FOR EACH ROW
BEGIN
    DECLARE v_origen   INT UNSIGNED DEFAULT NULL;
    DECLARE v_destino  INT UNSIGNED DEFAULT NULL;
    DECLARE v_usuario  INT UNSIGNED DEFAULT NULL;

    SELECT id_tienda_origen, id_tienda_destino, id_usuario
      INTO v_origen, v_destino, v_usuario
      FROM traslado_mercaderia
     WHERE id_traslado = NEW.id_traslado;

    CALL sp_mover_stock(v_origen,  NEW.id_producto, 'TRASLADO_SALIDA',  NEW.cantidad,
                        v_usuario, NULL, NEW.id_traslado, NULL, NULL);
    CALL sp_mover_stock(v_destino, NEW.id_producto, 'TRASLADO_ENTRADA', NEW.cantidad,
                        v_usuario, NULL, NEW.id_traslado, NULL, NULL);
END$$

/* ---------------------------------------------------------------------
   Merma: descuenta stock con su motivo (RN-14)
   --------------------------------------------------------------------- */
CREATE TRIGGER trg_detalle_merma_ai AFTER INSERT ON detalle_merma
FOR EACH ROW
BEGIN
    DECLARE v_tienda   INT UNSIGNED DEFAULT NULL;
    DECLARE v_usuario  INT UNSIGNED DEFAULT NULL;
    DECLARE v_motivo   VARCHAR(20)  DEFAULT NULL;

    SELECT id_tienda, id_usuario, motivo
      INTO v_tienda, v_usuario, v_motivo
      FROM merma
     WHERE id_merma = NEW.id_merma;

    CALL sp_mover_stock(v_tienda, NEW.id_producto, 'MERMA', NEW.cantidad,
                        v_usuario, NULL, NULL, NEW.id_merma,
                        CONCAT('Merma por ', v_motivo));
END$$

/* ---------------------------------------------------------------------
   Pagos: cada pago tiene un solo detalle, segun su metodo (RN-26, RN-27)
   --------------------------------------------------------------------- */
CREATE TRIGGER trg_efectivo_bi BEFORE INSERT ON efectivo
FOR EACH ROW
BEGIN
    CALL sp_valida_efectivo(NEW.id_pago, NEW.monto_recibido, NEW.vuelto);
END$$

CREATE TRIGGER trg_efectivo_bu BEFORE UPDATE ON efectivo
FOR EACH ROW
BEGIN
    CALL sp_valida_efectivo(NEW.id_pago, NEW.monto_recibido, NEW.vuelto);
END$$

CREATE TRIGGER trg_tarjeta_bi BEFORE INSERT ON tarjeta
FOR EACH ROW
BEGIN
    IF fn_codigo_metodo_pago(NEW.id_pago) <> 'TARJETA' THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20032,
            MESSAGE_TEXT = 'El pago no es con tarjeta.';
    END IF;
END$$

CREATE TRIGGER trg_tarjeta_bu BEFORE UPDATE ON tarjeta
FOR EACH ROW
BEGIN
    IF fn_codigo_metodo_pago(NEW.id_pago) <> 'TARJETA' THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20032,
            MESSAGE_TEXT = 'El pago no es con tarjeta.';
    END IF;
END$$

CREATE TRIGGER trg_billetera_sip_bi BEFORE INSERT ON billetera_digital_sip
FOR EACH ROW
BEGIN
    IF fn_codigo_metodo_pago(NEW.id_pago) <> 'SIP' THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20033,
            MESSAGE_TEXT = 'El pago no es con la billetera digital Sip.';
    END IF;
END$$

CREATE TRIGGER trg_billetera_sip_bu BEFORE UPDATE ON billetera_digital_sip
FOR EACH ROW
BEGIN
    IF fn_codigo_metodo_pago(NEW.id_pago) <> 'SIP' THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20033,
            MESSAGE_TEXT = 'El pago no es con la billetera digital Sip.';
    END IF;
END$$

/* ---------------------------------------------------------------------
   sp_anular_venta (RN-22, RN-23, RN-28)
   - Registra quien, cuando y por que.
   - Marca la venta como ANULADA y sus pagos como ANULADO.
   - Devuelve el stock con un movimiento ANULACION.
   - Todo en una sola transaccion: si algo falla, no queda nada a medias.
   --------------------------------------------------------------------- */
CREATE PROCEDURE sp_anular_venta (
    IN p_id_venta    INT UNSIGNED,
    IN p_id_usuario  INT UNSIGNED,
    IN p_motivo      VARCHAR(200)
)
BEGIN
    DECLARE v_estado       VARCHAR(10)   DEFAULT NULL;
    DECLARE v_id_tienda    INT UNSIGNED  DEFAULT NULL;
    DECLARE v_fin          TINYINT       DEFAULT 0;
    DECLARE v_id_producto  INT UNSIGNED;
    DECLARE v_cantidad     DECIMAL(12,3);
    DECLARE cur_detalle CURSOR FOR
        SELECT id_producto, cantidad
          FROM detalle_venta
         WHERE id_venta = p_id_venta;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fin = 1;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT v.estado, s.id_tienda
      INTO v_estado, v_id_tienda
      FROM venta v
      JOIN serie_comprobante s ON s.id_serie = v.id_serie
     WHERE v.id_venta = p_id_venta
       FOR UPDATE;

    IF v_estado IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20041,
            MESSAGE_TEXT = 'La venta no existe.';
    END IF;

    IF v_estado = 'ANULADA' THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 20040,
            MESSAGE_TEXT = 'La venta ya esta anulada.';
    END IF;

    INSERT INTO anulacion_venta (id_venta, id_usuario, motivo)
    VALUES (p_id_venta, p_id_usuario, p_motivo);

    UPDATE venta       SET estado = 'ANULADA' WHERE id_venta = p_id_venta;
    UPDATE pago_venta  SET estado = 'ANULADO' WHERE id_venta = p_id_venta;

    SET v_fin = 0;
    OPEN cur_detalle;
    lectura: LOOP
        FETCH cur_detalle INTO v_id_producto, v_cantidad;
        IF v_fin = 1 THEN
            LEAVE lectura;
        END IF;
        CALL sp_mover_stock(v_id_tienda, v_id_producto, 'ANULACION', v_cantidad,
                            p_id_usuario, p_id_venta, NULL, NULL, 'Anulacion de venta');
    END LOOP;
    CLOSE cur_detalle;

    COMMIT;
END$$

DELIMITER ;

/* ---------------------------------------------------------------------
   Vista de control (RN-24): la suma de los pagos aplicados y la suma
   de las lineas deben ser iguales al total de la venta.
   Como los pagos se registran despues de la venta, esta regla se verifica
   al cerrar cada venta: control = 'OK' o 'DESCUADRE'.
   --------------------------------------------------------------------- */
CREATE VIEW vw_control_ventas AS
SELECT x.id_venta,
       x.comprobante,
       x.estado,
       x.total,
       x.total_pagado,
       x.total_detalle,
       CASE
           WHEN x.estado = 'ANULADA' THEN 'ANULADA'
           WHEN x.total = x.total_pagado AND x.total = x.total_detalle THEN 'OK'
           ELSE 'DESCUADRE'
       END AS control
  FROM (SELECT v.id_venta,
               CONCAT(s.serie, '-', LPAD(v.correlativo, 8, '0')) AS comprobante,
               v.estado,
               v.total,
               IFNULL((SELECT SUM(p.monto)
                         FROM pago_venta p
                        WHERE p.id_venta = v.id_venta
                          AND p.estado = 'APLICADO'), 0) AS total_pagado,
               IFNULL((SELECT SUM(d.total_linea)
                         FROM detalle_venta d
                        WHERE d.id_venta = v.id_venta), 0) AS total_detalle
          FROM venta v
          JOIN serie_comprobante s ON s.id_serie = v.id_serie) x;
