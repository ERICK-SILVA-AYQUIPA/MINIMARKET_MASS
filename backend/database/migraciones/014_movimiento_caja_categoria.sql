/* =====================================================================
   MINIMARKET MASS - migracion 014: cada movimiento de efectivo de la caja con su categoria

   - categoria: para que el cierre y el administrador vean de un vistazo a donde fue (o de donde vino)
     el efectivo que no es una venta. Un retiro es DEPOSITO (retiro de seguridad o deposito al banco),
     PROVEEDOR, GASTO u OTRO; un ingreso es SENCILLO, APORTE u OTRO.
     Las devoluciones a un cliente NO son un movimiento: van por devolucion_venta (no se cuentan dos veces).
   - comprobante: numero de la boleta, factura o recibo que respalda el movimiento (opcional).
   - Los movimientos que ya existian quedan en OTRO: no se adivina su categoria.
   ===================================================================== */

ALTER TABLE movimiento_caja
    ADD COLUMN categoria   VARCHAR(10) NOT NULL DEFAULT 'OTRO' AFTER tipo,
    ADD COLUMN comprobante VARCHAR(20) NULL                    AFTER motivo,
    ADD CONSTRAINT ck_movimiento_caja_categoria CHECK (
        (tipo = 'RETIRO'  AND categoria IN ('DEPOSITO', 'PROVEEDOR', 'GASTO', 'OTRO'))
     OR (tipo = 'INGRESO' AND categoria IN ('SENCILLO', 'APORTE', 'OTRO'))
    );
