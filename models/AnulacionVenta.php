<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `anulacion_venta` (como maximo una por venta).
 */
class AnulacionVenta
{
    public function __construct(
        private int $idAnulacion,     // id_anulacion INT UNSIGNED
        private string $fechaHora,    // fecha_hora DATETIME
        private string $motivo,       // motivo VARCHAR(150)
        private int $idVenta,         // id_venta BIGINT UNSIGNED, unico
        private int $idEmpleado       // id_empleado: el administrador que anulo
    ) {}

    public function getIdAnulacion(): int { return $this->idAnulacion; }
    public function getFechaHora(): string { return $this->fechaHora; }
    public function getMotivo(): string { return $this->motivo; }
    public function getIdVenta(): int { return $this->idVenta; }
    public function getIdEmpleado(): int { return $this->idEmpleado; }
}
