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

    // El jefe de tienda revisa el cuadre de las cajas de su tienda
    public function puedeVerArqueo(int $idTienda): bool
    {
        return $this->estaActivo() && $idTienda === $this->getIdTienda();
    }

    // Cualquier administrador activo mantiene el catalogo comun
    public function puedeAdministrarCatalogo(): bool
    {
        return $this->estaActivo();
    }

    // El jefe de tienda maneja el personal, los minimos y los reportes de SU tienda
    public function puedeAdministrarTienda(int $idTienda): bool
    {
        return $this->estaActivo() && $idTienda === $this->getIdTienda();
    }
}
