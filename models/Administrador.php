<?php
declare(strict_types=1);

require_once __DIR__ . '/Usuario.php';

class Administrador extends Usuario
{
    public function getIdRol(): int { return 1; }
    public function getNombreRol(): string { return 'ADMINISTRADOR'; }

    // Solo el administrador anula, y solo ventas de su propia tienda (consulta 27 de consultas.sql)
    public function puedeAnularVenta(int $idTiendaDeLaVenta): bool
    {
        return $this->estaActivo() && $idTiendaDeLaVenta === $this->getIdTienda();
    }
}
