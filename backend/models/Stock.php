<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `stock`: cuanto hay de un producto en una tienda.
 * La tabla producto no tiene stock; por eso va en su propia clase.
 */
class Stock
{
    public function __construct(
        private int $idTienda,           // id_tienda SMALLINT UNSIGNED
        private int $idProducto,         // id_producto INT UNSIGNED
        private float $cantidadActual,   // cantidad_actual DECIMAL(10,3), nunca negativo
        private float $stockMinimo       // stock_minimo DECIMAL(10,3)
    ) {
        if ($cantidadActual < 0 || $stockMinimo < 0) {
            throw new InvalidArgumentException('El stock nunca es negativo');
        }
    }

    public function getIdTienda(): int { return $this->idTienda; }
    public function getIdProducto(): int { return $this->idProducto; }
    public function getCantidadActual(): float { return $this->cantidadActual; }
    public function getStockMinimo(): float { return $this->stockMinimo; }

    public function hayStock(float $cantidad): bool
    {
        return $cantidad > 0 && $cantidad <= $this->cantidadActual;
    }

    // Hay que pedir mercaderia cuando lo que queda llega al minimo
    public function necesitaReponer(): bool
    {
        return $this->cantidadActual <= $this->stockMinimo;
    }

    public function estaAgotado(): bool
    {
        return $this->cantidadActual == 0.0;
    }
}
