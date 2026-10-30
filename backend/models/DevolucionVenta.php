<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `devolucion_venta` (operaciones.sql): parte de una venta que el cliente devolvio.
 */
class DevolucionVenta
{
    public function __construct(
        private int $idDevolucion,    // id_devolucion INT UNSIGNED
        private string $fechaHora,    // fecha_hora DATETIME
        private string $motivo,       // motivo VARCHAR(150)
        private float $total,         // total DECIMAL(10,2): efectivo devuelto
        private int $idVenta,         // id_venta BIGINT UNSIGNED
        private int $idCajaTurno,     // id_caja_turno: de cuya gaveta salio el dinero
        private int $idEmpleado       // id_empleado: el cajero que la registro
    ) {
        if ($total <= 0) {
            throw new InvalidArgumentException('total debe ser mayor que 0');
        }
    }

    public function getIdDevolucion(): int { return $this->idDevolucion; }
    public function getFechaHora(): string { return $this->fechaHora; }
    public function getMotivo(): string { return $this->motivo; }
    public function getTotal(): float { return $this->total; }
    public function getIdVenta(): int { return $this->idVenta; }
    public function getIdCajaTurno(): int { return $this->idCajaTurno; }
    public function getIdEmpleado(): int { return $this->idEmpleado; }
}
