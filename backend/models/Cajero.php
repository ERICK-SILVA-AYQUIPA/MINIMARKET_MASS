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

    // Abre su turno con un monto inicial y lo cierra con lo que cuenta en caja
    public function puedeManejarCaja(): bool
    {
        return $this->estaActivo();
    }

    // Devuelve parte de una venta de su tienda, con el efectivo de su turno abierto
    public function puedeDevolverVenta(int $idTiendaDeLaVenta): bool
    {
        return $this->estaActivo() && $idTiendaDeLaVenta === $this->getIdTienda();
    }

    // Al cobrar puede rebajar hasta el 10 % de la compra, con su motivo (queda en la auditoria).
    // Un descuento mayor tiene que ser una promocion del administrador.
    public const TOPE_DESCUENTO = 1000;

    public function topeDescuentoManual(): int
    {
        return $this->estaActivo() ? self::TOPE_DESCUENTO : 0;
    }
}
