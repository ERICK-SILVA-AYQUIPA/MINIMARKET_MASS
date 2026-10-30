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

    // Recibe la mercaderia que llega a SU tienda, con su documento
    public function puedeRegistrarIngreso(int $idTienda): bool
    {
        return $this->estaActivo() && $idTienda === $this->getIdTienda();
    }

    // Es quien recibe la mercaderia: si llega un producto nuevo, lo registra al escanearlo
    public function puedeCrearProducto(): bool
    {
        return $this->estaActivo();
    }

    // Cuenta la mercaderia de SU tienda y corrige las diferencias
    public function puedeAjustarStock(int $idTienda): bool
    {
        return $this->estaActivo() && $idTienda === $this->getIdTienda();
    }
}
