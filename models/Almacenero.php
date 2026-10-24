<?php
declare(strict_types=1);

require_once __DIR__ . '/Usuario.php';

class Almacenero extends Usuario
{
    public function getIdRol(): int { return 3; }
    public function getNombreRol(): string { return 'ALMACENERO'; }

    // El almacenero registra traslados y mermas (comentarios de datos.sql)
    public function puedeMoverMercaderia(): bool
    {
        return $this->estaActivo();
    }
}
