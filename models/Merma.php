<?php
declare(strict_types=1);

/**
 * Cabecera de `merma`: perdida de mercaderia. Su tienda sale del empleado que la registra,
 * y las cantidades estan en el kardex (MERMA).
 */
class Merma
{
    public const MOTIVOS = ['VENCIDO', 'DAÑADO', 'ROBO', 'OTRO'];

    public function __construct(
        private int $idMerma,            // id_merma INT UNSIGNED
        private string $fechaHora,       // fecha_hora DATETIME
        private string $motivo,          // motivo VARCHAR(20)
        private ?string $observacion,    // observacion VARCHAR(200) NULL
        private int $idEmpleado
    ) {
        if (!in_array($motivo, self::MOTIVOS, true)) {
            throw new InvalidArgumentException('Motivo de merma no valido');
        }
    }

    public function getIdMerma(): int { return $this->idMerma; }
    public function getFechaHora(): string { return $this->fechaHora; }
    public function getMotivo(): string { return $this->motivo; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function getIdEmpleado(): int { return $this->idEmpleado; }
}
