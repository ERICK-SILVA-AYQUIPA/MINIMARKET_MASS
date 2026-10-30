<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `tienda`: las tiendas de Tienda Mass en Arequipa (3 en datos.sql).
 */
class Tienda
{
    public function __construct(
        private int $idTienda,           // id_tienda SMALLINT UNSIGNED
        private string $nombreTienda,    // nombre_tienda VARCHAR(60), unico
        private string $direccion,       // direccion VARCHAR(120)
        private ?string $telefono,       // telefono VARCHAR(15) NULL (opcional)
        private string $estado,          // estado: 'ACTIVA' o 'INACTIVA'
        private int $idDistrito          // id_distrito SMALLINT UNSIGNED
    ) {
        if (!in_array($estado, ['ACTIVA', 'INACTIVA'], true)) {
            throw new InvalidArgumentException("estado debe ser 'ACTIVA' o 'INACTIVA'");
        }
    }

    public function getIdTienda(): int { return $this->idTienda; }
    public function getNombreTienda(): string { return $this->nombreTienda; }
    public function getDireccion(): string { return $this->direccion; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function getEstado(): string { return $this->estado; }
    public function getIdDistrito(): int { return $this->idDistrito; }

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVA';
    }
}
