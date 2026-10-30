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
