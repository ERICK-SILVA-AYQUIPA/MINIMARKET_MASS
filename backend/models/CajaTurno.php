<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `caja_turno`: un cajero abre una caja con un monto inicial
 * y la cierra al terminar. Del turno salen la caja y el cajero de cada venta.
 *
 * Estados (caja_apertura.sql):
 *   ABIERTO   -> CERRADO   (al cerrar, la diferencia entra en la tolerancia de la tienda)
 *   ABIERTO   -> OBSERVADO (la pasa: la revisa el administrador)
 *   OBSERVADO -> CERRADO   (el administrador la aprueba con un comentario)
 *   OBSERVADO -> RECONTEO  -> CERRADO u OBSERVADO (el cajero vuelve a contar)
 */
class CajaTurno
{
    public function __construct(
        private int $idCajaTurno,             // id_caja_turno INT UNSIGNED
        private string $fechaHoraApertura,    // fecha_hora_apertura DATETIME
        private float $montoInicial,          // monto_inicial DECIMAL(10,2)
        private ?string $fechaHoraCierre,     // fecha_hora_cierre DATETIME NULL
        private ?float $montoCierre,          // monto_cierre DECIMAL(10,2) NULL
        private string $estado,               // estado: 'ABIERTO', 'CERRADO', 'OBSERVADO' o 'RECONTEO'
        private int $idCaja,                  // id_caja SMALLINT UNSIGNED
        private int $idEmpleado,              // id_empleado: cajero responsable
        private ?float $montoEsperado = null, // monto_esperado: lo que debia haber al cerrar
        private ?float $diferencia = null,    // diferencia = monto_cierre - monto_esperado (negativo = faltante)
        private ?string $enPausaDesde = null, // en_pausa_desde: NULL = vendiendo
        private ?string $observacion = null,  // observacion: comentario de la revision del administrador
        private ?int $idEmpleadoRevisa = null,
        private ?string $fechaHoraRevision = null
    ) {
        if ($montoInicial < 0) {
            throw new InvalidArgumentException('monto_inicial no puede ser negativo');
        }
        // CHECK ck_turno_estado: abierto sin cierre; cerrado, observado o en reconteo con cierre y sin pausa
        $abiertoValido = $estado === 'ABIERTO' && $fechaHoraCierre === null && $montoCierre === null;
        $cerradoValido = in_array($estado, ['CERRADO', 'OBSERVADO', 'RECONTEO'], true) && $fechaHoraCierre !== null && $montoCierre !== null
            && $montoCierre >= 0 && $fechaHoraCierre >= $fechaHoraApertura && $enPausaDesde === null;
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
    public function getMontoEsperado(): ?float { return $this->montoEsperado; }
    public function getDiferencia(): ?float { return $this->diferencia; }
    public function getEnPausaDesde(): ?string { return $this->enPausaDesde; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function getIdEmpleadoRevisa(): ?int { return $this->idEmpleadoRevisa; }
    public function getFechaHoraRevision(): ?string { return $this->fechaHoraRevision; }

    // El cajero solo vende con su turno abierto
    public function estaAbierto(): bool
    {
        return $this->estado === 'ABIERTO';
    }

    // Abierto pero en pausa: nadie vende con este turno hasta que el cajero lo reanude
    public function estaEnPausa(): bool
    {
        return $this->estaAbierto() && $this->enPausaDesde !== null;
    }

    // Cerrado con una diferencia que espera al administrador o un nuevo conteo del cajero
    public function estaPendiente(): bool
    {
        return in_array($this->estado, ['OBSERVADO', 'RECONTEO'], true);
    }
}
