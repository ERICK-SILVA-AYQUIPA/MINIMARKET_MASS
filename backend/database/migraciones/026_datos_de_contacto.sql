/* =====================================================================
   MINIMARKET MASS - migracion 026: direccion y telefono del personal; nombre, direccion y telefono del cliente

   - empleado: direccion y telefono, opcionales. El administrador los pone al registrar o editar a su personal
     (?ruta=empleado). El DNI ya existia: ahora tambien se corrige desde la misma pantalla.
   - cliente: nombre, direccion y telefono, opcionales. Un cliente nuevo se puede registrar a mano desde
     ?ruta=clientes (antes solo nacia al poner su DNI o RUC en una venta). Sigue necesitando DNI o RUC
     (ck_cliente_documento).
   - telefono: solo digitos, de 6 a 9 (fijo con o sin codigo de ciudad, o celular de 9).
   La tabla migracion_aplicada anota esta migracion: se aplica una sola vez en cada base.
   ===================================================================== */

ALTER TABLE empleado
    ADD COLUMN direccion VARCHAR(120) NULL AFTER dni,
    ADD COLUMN telefono  VARCHAR(9)   NULL AFTER direccion,
    ADD CONSTRAINT ck_empleado_telefono CHECK (telefono IS NULL OR telefono REGEXP '^[0-9]{6,9}$');

ALTER TABLE cliente
    ADD COLUMN nombre    VARCHAR(100) NULL AFTER id_cliente,
    ADD COLUMN direccion VARCHAR(120) NULL AFTER ruc,
    ADD COLUMN telefono  VARCHAR(9)   NULL AFTER direccion,
    ADD CONSTRAINT ck_cliente_telefono CHECK (telefono IS NULL OR telefono REGEXP '^[0-9]{6,9}$');
