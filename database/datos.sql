/* =====================================================================
   TIENDA MASS (Grupo Intercorp) - AREQUIPA
   Archivo : database/datos.sql
   Motor   : MySQL 8.0+ (Laragon, puerto 3307)

   Requisito: ejecutar ANTES schema.sql
     mysql -u root -h 127.0.0.1 -P 3307 -e "source C:/ruta/al/repo/database/datos.sql"

   Contenido:
     B) Datos iniciales (catalogos, tiendas, usuarios, productos, stock)
     C) Operaciones de prueba: ventas, pagos, anulacion, traslado, merma,
        cierre de caja

   Todos los datos son EJEMPLOS ficticios (nombres, DNI, RUC, precios y
   direcciones) para probar el modelo.
   ===================================================================== */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
USE minimarket_mass;

/* =====================================================================
   B) DATOS INICIALES
   ===================================================================== */

-- Distritos y tiendas (RN-01)
INSERT INTO distrito (nombre) VALUES ('Cayma');
INSERT INTO distrito (nombre) VALUES ('Cerro Colorado');
INSERT INTO distrito (nombre) VALUES ('Yanahuara');

INSERT INTO tienda (id_distrito, codigo, nombre, direccion)
VALUES ((SELECT id_distrito FROM distrito WHERE nombre = 'Cayma'),
        'MASS-CAY', 'Tienda Mass Cayma', 'Direccion de ejemplo - Cayma');
INSERT INTO tienda (id_distrito, codigo, nombre, direccion)
VALUES ((SELECT id_distrito FROM distrito WHERE nombre = 'Cerro Colorado'),
        'MASS-CCO', 'Tienda Mass Cerro Colorado', 'Direccion de ejemplo - Cerro Colorado');
INSERT INTO tienda (id_distrito, codigo, nombre, direccion)
VALUES ((SELECT id_distrito FROM distrito WHERE nombre = 'Yanahuara'),
        'MASS-YAN', 'Tienda Mass Yanahuara', 'Direccion de ejemplo - Yanahuara');

-- Roles (RN-02, RN-03)
INSERT INTO rol (nombre) VALUES ('ADMINISTRADOR');
INSERT INTO rol (nombre) VALUES ('CAJERO');
INSERT INTO rol (nombre) VALUES ('ALMACENERO');

-- Tipos de documento (RN-04, RN-06)
INSERT INTO tipo_documento_identidad (codigo, nombre, longitud) VALUES ('DNI', 'Documento Nacional de Identidad', 8);
INSERT INTO tipo_documento_identidad (codigo, nombre, longitud) VALUES ('RUC', 'Registro Unico de Contribuyentes', 11);

-- Tipos de comprobante (RN-18)
INSERT INTO tipo_comprobante (codigo, nombre, prefijo_serie) VALUES ('BOLETA',  'Boleta de venta', 'B');
INSERT INTO tipo_comprobante (codigo, nombre, prefijo_serie) VALUES ('FACTURA', 'Factura',         'F');

-- Metodos de pago (RN-24, RN-25)
INSERT INTO metodo_pago (codigo, nombre) VALUES ('EFECTIVO', 'Efectivo');
INSERT INTO metodo_pago (codigo, nombre) VALUES ('TARJETA',  'Tarjeta de debito o credito');
INSERT INTO metodo_pago (codigo, nombre) VALUES ('SIP',      'Billetera digital Sip');

-- Tipos de movimiento de stock (RN-12). E = entrada, S = salida
INSERT INTO tipo_movimiento (codigo, nombre, efecto) VALUES ('VENTA',            'Salida por venta',      'S');
INSERT INTO tipo_movimiento (codigo, nombre, efecto) VALUES ('ANULACION',        'Entrada por anulacion', 'E');
INSERT INTO tipo_movimiento (codigo, nombre, efecto) VALUES ('TRASLADO_SALIDA',  'Salida por traslado',   'S');
INSERT INTO tipo_movimiento (codigo, nombre, efecto) VALUES ('TRASLADO_ENTRADA', 'Entrada por traslado',  'E');
INSERT INTO tipo_movimiento (codigo, nombre, efecto) VALUES ('MERMA',            'Salida por merma',      'S');
INSERT INTO tipo_movimiento (codigo, nombre, efecto) VALUES ('AJUSTE_ENTRADA',   'Entrada por ajuste',    'E');
INSERT INTO tipo_movimiento (codigo, nombre, efecto) VALUES ('AJUSTE_SALIDA',    'Salida por ajuste',     'S');

-- Unidades de medida
INSERT INTO unidad_medida (codigo, nombre) VALUES ('UND', 'Unidad');
INSERT INTO unidad_medida (codigo, nombre) VALUES ('KG',  'Kilogramo');
INSERT INTO unidad_medida (codigo, nombre) VALUES ('LT',  'Litro');
INSERT INTO unidad_medida (codigo, nombre) VALUES ('PQT', 'Paquete');

-- Categorias
INSERT INTO categoria (nombre, descripcion) VALUES ('Abarrotes',  'Productos de despensa');
INSERT INTO categoria (nombre, descripcion) VALUES ('Bebidas',    'Gaseosas, aguas y jugos');
INSERT INTO categoria (nombre, descripcion) VALUES ('Lacteos',    'Leches y derivados');
INSERT INTO categoria (nombre, descripcion) VALUES ('Limpieza',   'Articulos de limpieza del hogar');
INSERT INTO categoria (nombre, descripcion) VALUES ('Panaderia',  'Pan y derivados');
INSERT INTO categoria (nombre, descripcion) VALUES ('Congelados', 'Productos congelados');

-- Marcas de ejemplo
INSERT INTO marca (nombre, es_marca_propia) VALUES ('Gloria',                 'N');
INSERT INTO marca (nombre, es_marca_propia) VALUES ('Coca-Cola',              'N');
INSERT INTO marca (nombre, es_marca_propia) VALUES ('Alicorp',                'N');
INSERT INTO marca (nombre, es_marca_propia) VALUES ('Marca Propia (ejemplo)', 'S');

-- Productos de ejemplo (precio con IGV incluido: RN-07)
INSERT INTO producto (id_categoria, id_marca, id_unidad, codigo_barras, nombre, precio_venta)
VALUES ((SELECT id_categoria FROM categoria WHERE nombre = 'Lacteos'),
        (SELECT id_marca FROM marca WHERE nombre = 'Gloria'),
        (SELECT id_unidad FROM unidad_medida WHERE codigo = 'UND'),
        '9990000000001', 'Leche evaporada 400 g', 4.50);
INSERT INTO producto (id_categoria, id_marca, id_unidad, codigo_barras, nombre, precio_venta)
VALUES ((SELECT id_categoria FROM categoria WHERE nombre = 'Bebidas'),
        (SELECT id_marca FROM marca WHERE nombre = 'Coca-Cola'),
        (SELECT id_unidad FROM unidad_medida WHERE codigo = 'UND'),
        '9990000000002', 'Gaseosa 1.5 L', 7.90);
INSERT INTO producto (id_categoria, id_marca, id_unidad, codigo_barras, nombre, precio_venta)
VALUES ((SELECT id_categoria FROM categoria WHERE nombre = 'Abarrotes'),
        (SELECT id_marca FROM marca WHERE nombre = 'Alicorp'),
        (SELECT id_unidad FROM unidad_medida WHERE codigo = 'UND'),
        '9990000000003', 'Aceite vegetal 1 L', 10.90);
INSERT INTO producto (id_categoria, id_marca, id_unidad, codigo_barras, nombre, precio_venta)
VALUES ((SELECT id_categoria FROM categoria WHERE nombre = 'Abarrotes'),
        (SELECT id_marca FROM marca WHERE nombre = 'Marca Propia (ejemplo)'),
        (SELECT id_unidad FROM unidad_medida WHERE codigo = 'UND'),
        '9990000000004', 'Arroz extra 5 kg', 21.50);
INSERT INTO producto (id_categoria, id_marca, id_unidad, codigo_barras, nombre, precio_venta)
VALUES ((SELECT id_categoria FROM categoria WHERE nombre = 'Limpieza'),
        (SELECT id_marca FROM marca WHERE nombre = 'Marca Propia (ejemplo)'),
        (SELECT id_unidad FROM unidad_medida WHERE codigo = 'UND'),
        '9990000000005', 'Detergente 800 g', 12.90);
INSERT INTO producto (id_categoria, id_marca, id_unidad, codigo_barras, nombre, precio_venta)
VALUES ((SELECT id_categoria FROM categoria WHERE nombre = 'Panaderia'),
        (SELECT id_marca FROM marca WHERE nombre = 'Marca Propia (ejemplo)'),
        (SELECT id_unidad FROM unidad_medida WHERE codigo = 'UND'),
        '9990000000006', 'Pan de molde', 6.50);
INSERT INTO producto (id_categoria, id_marca, id_unidad, codigo_barras, nombre, precio_venta)
VALUES ((SELECT id_categoria FROM categoria WHERE nombre = 'Congelados'),
        (SELECT id_marca FROM marca WHERE nombre = 'Marca Propia (ejemplo)'),
        (SELECT id_unidad FROM unidad_medida WHERE codigo = 'KG'),
        '9990000000007', 'Pollo congelado (por kg)', 11.90);

-- Usuarios de ejemplo: un administrador, un cajero y un almacenero por tienda (RN-02)
-- Los DNI y las claves son ficticios (la clave es solo un texto de ejemplo, no un hash real).
INSERT INTO usuario (id_tienda, id_rol, nombres, apellidos, dni, login, clave_hash)
SELECT t.id_tienda,
       r.id_rol,
       CONCAT(UPPER(SUBSTRING(r.nombre, 1, 1)), LOWER(SUBSTRING(r.nombre, 2))),
       CONCAT('Demo ', SUBSTRING(t.codigo, 6)),
       CONCAT('7000', LPAD(ROW_NUMBER() OVER (ORDER BY t.id_tienda, r.id_rol), 4, '0')),
       CONCAT(LOWER(r.nombre), '.', LOWER(SUBSTRING(t.codigo, 6))),
       'HASH_DE_EJEMPLO'
  FROM tienda t
 CROSS JOIN rol r;

-- Una caja por tienda (RN-15)
INSERT INTO caja (id_tienda, numero, descripcion)
SELECT id_tienda, 1, 'Caja 1' FROM tienda;

-- Series por tienda y tipo: B001..B003 y F001..F003 (RN-19)
INSERT INTO serie_comprobante (id_tienda, id_tipo_comprobante, serie)
SELECT t.id_tienda,
       tc.id_tipo_comprobante,
       CONCAT(tc.prefijo_serie,
              LPAD(ROW_NUMBER() OVER (PARTITION BY tc.id_tipo_comprobante
                                      ORDER BY t.id_tienda), 3, '0'))
  FROM tienda t
 CROSS JOIN tipo_comprobante tc;

-- Clientes de ejemplo (DNI de 8 digitos y RUC de 11 digitos ficticios)
INSERT INTO cliente (id_tipo_documento, numero_documento, nombre_razon_social)
VALUES ((SELECT id_tipo_documento FROM tipo_documento_identidad WHERE codigo = 'DNI'),
        '45678912', 'Cliente Persona de Ejemplo');
INSERT INTO cliente (id_tipo_documento, numero_documento, nombre_razon_social, direccion)
VALUES ((SELECT id_tipo_documento FROM tipo_documento_identidad WHERE codigo = 'RUC'),
        '20000000001', 'Empresa de Ejemplo S.A.C.', 'Direccion de ejemplo - Arequipa');

-- Stock inicial: 100 unidades de cada producto en cada tienda, cargado como
-- AJUSTE_ENTRADA por el almacenero (asi queda en el kardex).
DELIMITER $$
CREATE PROCEDURE sp_tmp_stock_inicial ()
BEGIN
    DECLARE v_fin     TINYINT DEFAULT 0;
    DECLARE v_tienda  INT UNSIGNED;
    DECLARE v_usuario INT UNSIGNED;
    DECLARE v_prod    INT UNSIGNED;
    DECLARE cur CURSOR FOR
        SELECT u.id_tienda, u.id_usuario, p.id_producto
          FROM usuario u
          JOIN rol r ON r.id_rol = u.id_rol
         CROSS JOIN producto p
         WHERE r.nombre = 'ALMACENERO'
         ORDER BY u.id_tienda, p.id_producto;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_fin = 1;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    OPEN cur;
    carga: LOOP
        FETCH cur INTO v_tienda, v_usuario, v_prod;
        IF v_fin = 1 THEN
            LEAVE carga;
        END IF;
        CALL sp_mover_stock(v_tienda, v_prod, 'AJUSTE_ENTRADA', 100, v_usuario,
                            NULL, NULL, NULL, 'Stock inicial');
    END LOOP;
    CLOSE cur;
    COMMIT;
END$$
DELIMITER ;

CALL sp_tmp_stock_inicial();
DROP PROCEDURE sp_tmp_stock_inicial;

/* =====================================================================
   C) OPERACIONES DE PRUEBA
   ===================================================================== */

/* ---------------------------------------------------------------------
   C.1 Venta 1: boleta sin cliente, pagada con efectivo y Sip (RN-04, RN-24)
       2 leches (4.50) + 1 gaseosa (7.90) = 16.90
       Efectivo: recibe 20.00, vuelto 10.00 -> aplica 10.00
       Sip (QR): 6.90
   --------------------------------------------------------------------- */
SET @usuario := (SELECT id_usuario FROM usuario WHERE login = 'cajero.cay');
SET @caja    := (SELECT c.id_caja
                   FROM caja c
                   JOIN tienda t ON t.id_tienda = c.id_tienda
                  WHERE t.codigo = 'MASS-CAY' AND c.numero = 1);
SET @serie_b := (SELECT s.id_serie
                   FROM serie_comprobante s
                   JOIN tienda t ON t.id_tienda = s.id_tienda
                   JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = s.id_tipo_comprobante
                  WHERE t.codigo = 'MASS-CAY' AND tc.codigo = 'BOLETA');
SET @leche   := (SELECT id_producto FROM producto WHERE codigo_barras = '9990000000001');
SET @p_leche := (SELECT precio_venta FROM producto WHERE codigo_barras = '9990000000001');
SET @gaseosa := (SELECT id_producto FROM producto WHERE codigo_barras = '9990000000002');
SET @p_gaseosa := (SELECT precio_venta FROM producto WHERE codigo_barras = '9990000000002');

START TRANSACTION;

-- Apertura de turno (RN-16)
INSERT INTO caja_turno (id_caja, id_usuario, monto_apertura)
VALUES (@caja, @usuario, 100.00);
SET @turno := LAST_INSERT_ID();

-- El precio ya incluye IGV (18 %): subtotal = total / 1.18; igv = total - subtotal
SET @total    := 2 * @p_leche + 1 * @p_gaseosa;
SET @subtotal := ROUND(@total / 1.18, 2);

INSERT INTO venta (id_serie, id_caja_turno, id_usuario, id_cliente, subtotal, igv, total)
VALUES (@serie_b, @turno, @usuario, NULL, @subtotal, @total - @subtotal, @total);
SET @venta1 := LAST_INSERT_ID();

INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario, total_linea)
VALUES (@venta1, @leche, 2, @p_leche, ROUND(2 * @p_leche, 2));
INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario, total_linea)
VALUES (@venta1, @gaseosa, 1, @p_gaseosa, ROUND(1 * @p_gaseosa, 2));

-- Pago 1: efectivo (aplica 10.00; recibe 20.00 y devuelve 10.00)
INSERT INTO pago_venta (id_venta, id_metodo_pago, monto)
VALUES (@venta1, (SELECT id_metodo_pago FROM metodo_pago WHERE codigo = 'EFECTIVO'), 10.00);
SET @pago := LAST_INSERT_ID();
INSERT INTO efectivo (id_pago, monto_recibido, vuelto) VALUES (@pago, 20.00, 10.00);

-- Pago 2: billetera digital Sip por el saldo
INSERT INTO pago_venta (id_venta, id_metodo_pago, monto)
VALUES (@venta1, (SELECT id_metodo_pago FROM metodo_pago WHERE codigo = 'SIP'), @total - 10.00);
SET @pago := LAST_INSERT_ID();
INSERT INTO billetera_digital_sip (id_pago, modalidad, codigo_operacion)
VALUES (@pago, 'QR', 'SIP-EJEMPLO-0001');

COMMIT;

/* ---------------------------------------------------------------------
   C.2 Venta 2: factura a cliente con RUC, pagada con tarjeta; luego se
       anula (RN-04, RN-23, RN-28)
       1 arroz (21.50) + 2.5 kg de pollo (11.90) = 51.25
   --------------------------------------------------------------------- */
SET @serie_f   := (SELECT s.id_serie
                     FROM serie_comprobante s
                     JOIN tienda t ON t.id_tienda = s.id_tienda
                     JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = s.id_tipo_comprobante
                    WHERE t.codigo = 'MASS-CAY' AND tc.codigo = 'FACTURA');
SET @cliente   := (SELECT id_cliente FROM cliente WHERE numero_documento = '20000000001');
SET @arroz     := (SELECT id_producto FROM producto WHERE codigo_barras = '9990000000004');
SET @p_arroz   := (SELECT precio_venta FROM producto WHERE codigo_barras = '9990000000004');
SET @pollo     := (SELECT id_producto FROM producto WHERE codigo_barras = '9990000000007');
SET @p_pollo   := (SELECT precio_venta FROM producto WHERE codigo_barras = '9990000000007');

START TRANSACTION;

SET @total    := ROUND(1 * @p_arroz + 2.5 * @p_pollo, 2);
SET @subtotal := ROUND(@total / 1.18, 2);

INSERT INTO venta (id_serie, id_caja_turno, id_usuario, id_cliente, subtotal, igv, total)
VALUES (@serie_f, @turno, @usuario, @cliente, @subtotal, @total - @subtotal, @total);
SET @venta2 := LAST_INSERT_ID();

INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario, total_linea)
VALUES (@venta2, @arroz, 1, @p_arroz, ROUND(1 * @p_arroz, 2));
INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario, total_linea)
VALUES (@venta2, @pollo, 2.5, @p_pollo, ROUND(2.5 * @p_pollo, 2));

INSERT INTO pago_venta (id_venta, id_metodo_pago, monto)
VALUES (@venta2, (SELECT id_metodo_pago FROM metodo_pago WHERE codigo = 'TARJETA'), @total);
SET @pago := LAST_INSERT_ID();
INSERT INTO tarjeta (id_pago, tipo_tarjeta, marca, ultimos_4, codigo_autorizacion)
VALUES (@pago, 'CREDITO', 'VISA', '1234', 'AUT-EJEMPLO-0001');

COMMIT;

-- Anulacion: devuelve el stock y anula los pagos (hace su propio COMMIT)
CALL sp_anular_venta(@venta2, @usuario, 'Prueba: error en los datos del cliente');

/* ---------------------------------------------------------------------
   C.3 Traslado de Yanahuara a Cayma: 10 leches (RN-13)
   --------------------------------------------------------------------- */
SET @origen   := (SELECT id_tienda FROM tienda WHERE codigo = 'MASS-YAN');
SET @destino  := (SELECT id_tienda FROM tienda WHERE codigo = 'MASS-CAY');
SET @alm_yan  := (SELECT id_usuario FROM usuario WHERE login = 'almacenero.yan');

START TRANSACTION;

INSERT INTO traslado_mercaderia (id_tienda_origen, id_tienda_destino, id_usuario, observacion)
VALUES (@origen, @destino, @alm_yan, 'Reposicion de ejemplo');
SET @traslado := LAST_INSERT_ID();

INSERT INTO detalle_traslado (id_traslado, id_producto, cantidad)
VALUES (@traslado, @leche, 10);

COMMIT;

/* ---------------------------------------------------------------------
   C.4 Merma en Cerro Colorado: 2 panes de molde vencidos (RN-14)
   --------------------------------------------------------------------- */
SET @tienda_cco := (SELECT id_tienda FROM tienda WHERE codigo = 'MASS-CCO');
SET @alm_cco    := (SELECT id_usuario FROM usuario WHERE login = 'almacenero.cco');
SET @pan        := (SELECT id_producto FROM producto WHERE codigo_barras = '9990000000006');

START TRANSACTION;

INSERT INTO merma (id_tienda, id_usuario, motivo, observacion)
VALUES (@tienda_cco, @alm_cco, 'VENCIMIENTO', 'Merma de ejemplo');
SET @merma := LAST_INSERT_ID();

INSERT INTO detalle_merma (id_merma, id_producto, cantidad)
VALUES (@merma, @pan, 2);

COMMIT;

/* ---------------------------------------------------------------------
   C.5 Cierre del turno de caja de Cayma (RN-17)
       Monto de cierre de ejemplo: apertura 100.00 + efectivo aplicado 10.00
   --------------------------------------------------------------------- */
UPDATE caja_turno
   SET fecha_cierre = NOW(),
       monto_cierre = 110.00,
       estado       = 'CERRADO'
 WHERE estado = 'ABIERTO'
   AND id_usuario = @usuario;