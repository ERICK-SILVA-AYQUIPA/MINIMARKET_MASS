<?php
declare(strict_types=1);

require_once __DIR__ . '/Usuario.php';

class Cajero extends Usuario
{
    public function getIdRol(): int { return 2; }
    public function getNombreRol(): string { return 'CAJERO'; }

    // Solo el cajero vende, con su turno de caja (consulta 25 de consultas.sql)
    public function puedeVender(): bool
    {
        return $this->estaActivo();
    }
}
