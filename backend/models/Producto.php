<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `producto` (database/schema.sql).
 * Las propiedades tienen los mismos nombres que las columnas, en camelCase.
 */
class Producto
{
    public function __construct(
        private int $idProducto,             // id_producto INT UNSIGNED
        private string $nombreProducto,      // nombre_producto VARCHAR(100)
        private ?string $codigoBarras,       // codigo_barras VARCHAR(14) NULL (los productos al peso no tienen)
        private float $precioVenta,          // precio_venta DECIMAL(10,2), unico para todas las tiendas
        private string $seVendePorPeso,      // se_vende_por_peso CHAR(1): 'S' o 'N'
        private string $estado,              // estado VARCHAR(10): 'ACTIVO' o 'DESCONTINUADO'
        private int $idCategoria,            // id_categoria SMALLINT UNSIGNED
        private int $idMarca,                // id_marca SMALLINT UNSIGNED
        private int $idUnidad                // id_unidad TINYINT UNSIGNED
    ) {
        // Las mismas restricciones CHECK de la tabla, para no crear objetos invalidos
        if ($precioVenta <= 0) {
            throw new InvalidArgumentException('precio_venta debe ser mayor que 0');
        }
        if (!in_array($seVendePorPeso, ['S', 'N'], true)) {
            throw new InvalidArgumentException("se_vende_por_peso debe ser 'S' o 'N'");
        }
        if (!in_array($estado, ['ACTIVO', 'DESCONTINUADO'], true)) {
            throw new InvalidArgumentException("estado debe ser 'ACTIVO' o 'DESCONTINUADO'");
        }
        if ($codigoBarras !== null && preg_match('/^[0-9]{8,14}$/', $codigoBarras) !== 1) {
            throw new InvalidArgumentException('codigo_barras debe tener de 8 a 14 digitos');
        }
    }

    public function getIdProducto(): int { return $this->idProducto; }
    public function getNombreProducto(): string { return $this->nombreProducto; }
    public function getCodigoBarras(): ?string { return $this->codigoBarras; }
    public function getPrecioVenta(): float { return $this->precioVenta; }
    public function getSeVendePorPeso(): string { return $this->seVendePorPeso; }
    public function getEstado(): string { return $this->estado; }
    public function getIdCategoria(): int { return $this->idCategoria; }
    public function getIdMarca(): int { return $this->idMarca; }
    public function getIdUnidad(): int { return $this->idUnidad; }

    // ----- Reglas de negocio -----

    public function esAlPeso(): bool
    {
        return $this->seVendePorPeso === 'S';
    }

    // Un producto DESCONTINUADO sigue en el catalogo pero ya no se vende
    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    // Al peso: kilos con hasta 3 decimales (DECIMAL(10,3)). Por unidad: solo enteros.
    public function cantidadValida(float $cantidad): bool
    {
        if ($cantidad <= 0) {
            return false;
        }
        if ($this->esAlPeso()) {
            return round($cantidad, 3) == $cantidad;
        }
        return floor($cantidad) == $cantidad;
    }

    // Igual que el CHECK de detalle_venta: importe = ROUND(cantidad * precio_unitario, 2)
    public function importe(float $cantidad): float
    {
        if (!$this->cantidadValida($cantidad)) {
            throw new InvalidArgumentException('Cantidad no valida para este producto');
        }
        return round($cantidad * $this->precioVenta, 2);
    }
}
