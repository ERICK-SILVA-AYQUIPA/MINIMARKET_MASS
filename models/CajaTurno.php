<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `caja_turno`: un cajero abre una caja con un monto inicial
 * y la cierra al terminar. Del turno salen la caja y el cajero de cada venta.
 */
class CajaTurno
{
    public function __construct(
        private int $idCajaTurno,             // id_caja_turno INT UNSIGNED
        private string $fechaHoraApertura,    // fecha_hora_apertura DATETIME
        private float $montoInicial,          // monto_inicial DECIMAL(10,2)
        private ?string $fechaHoraCierre,     // fecha_hora_cierre DATETIME NULL
        private ?float $montoCierre,          // monto_cierre DECIMAL(10,2) NULL
        private string $estado,               // estado: 'ABIERTO' o 'CERRADO'
        private int $idCaja,                  // id_caja SMALLINT UNSIGNED
        private int $idEmpleado               // id_empleado: cajero responsable
    ) {
        if ($montoInicial < 0) {
            throw new InvalidArgumentException('monto_inicial no puede ser negativo');
        }
        // CHECK ck_turno_estado: abierto sin cierre, cerrado con cierre
        $abiertoValido = $estado === 'ABIERTO' && $fechaHoraCierre === null && $montoCierre === null;
        $cerradoValido = $estado === 'CERRADO' && $fechaHoraCierre !== null && $montoCierre !== null
            && $montoCierre >= 0 && $fechaHoraCierre >= $fechaHoraApertura;
        if (!$abiertoValido && !$cerradoValido) {
            throw new InvalidArgumentException('Turno de caja incoherente con su estado');
        }
    }

    public function getIdCajaTurno(): int { return $this->idCajaTurno; }
    public function getFechaHoraApertura(): string { return $this->fechaHoraApertura; }
    public function getMontoInicial(): float { return $this->montoInicial; }
    public function getFechaHoraCierre(): ?string { return $this->fechaHoraCierre; }
    public function getMontoCierre(): ?float { return $this->montoCierre; }
    public function getEstado(): string { return $this->estado; }
    public function getIdCaja(): int { return $this->idCaja; }
    public function getIdEmpleado(): int { return $this->idEmpleado; }

    // El cajero solo vende con su turno abierto
    public function estaAbierto(): bool
    {
        return $this->estado === 'ABIERTO';
    }
}
