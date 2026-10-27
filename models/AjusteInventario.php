<?php
declare(strict_types=1);

/**
 * Cabecera de `ajuste_inventario` (database/ajustes.sql): un conteo fisico en una tienda.
 * Las diferencias de cada producto estan en el kardex (AJUSTE DE ENTRADA / AJUSTE DE SALIDA).
 */
class AjusteInventario
{
    public const MOTIVOS = ['CONTEO_FISICO', 'CORRECCION'];

    public function __construct(
        private int $idAjuste,           // id_ajuste INT UNSIGNED
        private string $fechaHora,       // fecha_hora DATETIME
        private string $motivo,          // motivo VARCHAR(20)
        private ?string $observacion,    // observacion VARCHAR(200) NULL
        private int $idTienda,
        private int $idEmpleado
    ) {
        if (!in_array($motivo, self::MOTIVOS, true)) {
            throw new InvalidArgumentException('Motivo de ajuste no valido');
        }
    }

    public function getIdAjuste(): int { return $this->idAjuste; }
    public function getFechaHora(): string { return $this->fechaHora; }
    public function getMotivo(): string { return $this->motivo; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function getIdTienda(): int { return $this->idTienda; }
    public function getIdEmpleado(): int { return $this->idEmpleado; }
}
