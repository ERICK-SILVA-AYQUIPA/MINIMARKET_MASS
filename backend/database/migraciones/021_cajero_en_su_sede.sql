/* =====================================================================
   MINIMARKET MASS - migracion 021: cada cajero trabaja solo en su sede

   Cada empleado ya tiene su sede: empleado.id_tienda es NOT NULL con FK a tienda (schema.sql), asi que
   no hay cajero sin sede y no hay datos que completar. Con los datos de ejemplo:
       Luis Cáceres  -> Mass Cayma    Ana Valdivia -> Mass Cerro Colorado    Miguel Núñez -> Mass Yanahuara
   Lo que faltaba es que la BASE (no solo el PHP) no deje operar en otra sede. Tres triggers:
   - caja_turno: el turno solo se abre en una caja de la sede del cajero.
   - venta:      la serie del comprobante es de la misma sede que la caja del turno.
   - empleado:   no se cambia de sede a un empleado con un turno de caja sin cerrar
                 (seguiria vendiendo en la sede anterior). Con todos sus turnos cerrados, si se puede.
   Se compara con <=> (igual que acepta NULL) para que un dato que falta no deje pasar la fila.
   Los triggers solo revisan lo nuevo: el historial no se toca. Si alguno salta, la aplicacion ya
   estaba fallando antes (CajaServicio::abrirTurno y VentaServicio::registrar revisan lo mismo).
   ===================================================================== */

DELIMITER $$

CREATE TRIGGER trg_caja_turno_sede BEFORE INSERT ON caja_turno
FOR EACH ROW
BEGIN
    IF NOT ((SELECT id_tienda FROM empleado WHERE id_empleado = NEW.id_empleado)
            <=> (SELECT id_tienda FROM caja WHERE id_caja = NEW.id_caja)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cajero solo abre turnos en una caja de su sede';
    END IF;
END$$

CREATE TRIGGER trg_caja_turno_sede_cambio BEFORE UPDATE ON caja_turno
FOR EACH ROW
BEGIN
    IF (NEW.id_caja <> OLD.id_caja OR NEW.id_empleado <> OLD.id_empleado)
       AND NOT ((SELECT id_tienda FROM empleado WHERE id_empleado = NEW.id_empleado)
                <=> (SELECT id_tienda FROM caja WHERE id_caja = NEW.id_caja)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cajero solo abre turnos en una caja de su sede';
    END IF;
END$$

CREATE TRIGGER trg_venta_sede BEFORE INSERT ON venta
FOR EACH ROW
BEGIN
    IF NOT ((SELECT id_tienda FROM serie_comprobante WHERE id_serie = NEW.id_serie)
            <=> (SELECT c.id_tienda FROM caja_turno ct JOIN caja c ON c.id_caja = ct.id_caja
                  WHERE ct.id_caja_turno = NEW.id_caja_turno)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La venta usa una serie de otra sede';
    END IF;
END$$

CREATE TRIGGER trg_empleado_sede BEFORE UPDATE ON empleado
FOR EACH ROW
BEGIN
    IF NEW.id_tienda <> OLD.id_tienda AND EXISTS (
        SELECT 1 FROM caja_turno
         WHERE id_empleado = OLD.id_empleado AND estado IN ('ABIERTO', 'OBSERVADO', 'RECONTEO')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El empleado tiene un turno de caja sin cerrar: no se cambia de sede';
    END IF;
END$$

DELIMITER ;
