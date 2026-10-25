<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `caja`: cajas registradoras de cada tienda.
 */
class Caja
{
    public function __construct(
        private int $idCaja,          // id_caja SMALLINT UNSIGNED
        private int $numeroCaja,      // numero_caja TINYINT UNSIGNED, unico por tienda
        private string $estado,       // estado: 'ACTIVA' o 'INACTIVA'
        private int $idTienda         // id_tienda SMALLINT UNSIGNED
    ) {
        if (!in_array($estado, ['ACTIVA', 'INACTIVA'], true)) {
            throw new InvalidArgumentException("estado debe ser 'ACTIVA' o 'INACTIVA'");
        }
    }

    public function getIdCaja(): int { return $this->idCaja; }
    public function getNumeroCaja(): int { return $this->numeroCaja; }
    public function getEstado(): string { return $this->estado; }
    public function getIdTienda(): int { return $this->idTienda; }
}
