<?php
declare(strict_types=1);

require_once __DIR__ . '/Dinero.php';

/**
 * Una fila de la tabla `detalle_venta`: producto, cantidad, precio al vender e importe.
 */
class DetalleVenta
{
    public function __construct(
        private int $idDetalleVenta,      // id_detalle_venta BIGINT UNSIGNED
        private float $cantidad,          // cantidad DECIMAL(10,3)
        private float $precioUnitario,    // precio_unitario DECIMAL(10,2): copia del precio al vender
        private float $importe,           // importe DECIMAL(10,2)
        private int $idVenta,             // id_venta BIGINT UNSIGNED
        private int $idProducto           // id_producto INT UNSIGNED
    ) {
        if ($cantidad <= 0 || $precioUnitario <= 0) {
            throw new InvalidArgumentException('cantidad y precio_unitario deben ser mayores que 0');
        }
        // CHECK ck_detalle_importe: importe = ROUND(cantidad * precio_unitario, 2)
        if (Dinero::centimos($importe) !== Dinero::centimos($cantidad * $precioUnitario)) {
            throw new InvalidArgumentException('El importe no coincide con cantidad x precio');
        }
    }

    public function getIdDetalleVenta(): int { return $this->idDetalleVenta; }
    public function getCantidad(): float { return $this->cantidad; }
    public function getPrecioUnitario(): float { return $this->precioUnitario; }
    public function getImporte(): float { return $this->importe; }
    public function getIdVenta(): int { return $this->idVenta; }
    public function getIdProducto(): int { return $this->idProducto; }
}
