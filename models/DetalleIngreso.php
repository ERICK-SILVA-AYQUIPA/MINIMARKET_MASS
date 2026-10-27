<?php
declare(strict_types=1);

require_once __DIR__ . '/Dinero.php';
require_once __DIR__ . '/Cantidad.php';

/**
 * Una fila de `detalle_ingreso`: un producto recibido, su cantidad y su costo de compra.
 */
class DetalleIngreso
{
    public function __construct(
        private int $idIngreso,
        private int $idProducto,
        private float $cantidad,          // cantidad DECIMAL(10,3)
        private float $costoUnitario,     // costo_unitario DECIMAL(10,2)
        private float $costoTotal         // costo_total DECIMAL(10,2) = ROUND(cantidad * costo_unitario, 2)
    ) {
        if ($cantidad <= 0 || $costoUnitario < 0) {
            throw new InvalidArgumentException('Cantidad o costo no validos');
        }
        // CHECK ck_detalle_ingreso_total: costo_total = ROUND(cantidad * costo_unitario, 2), en enteros
        // (con float, 10.5 x 1.95 da 20.4749... y redondearia a 20.47 en vez de 20.48)
        $esperado = Cantidad::importe(Cantidad::deDecimal(number_format($cantidad, 3, '.', '')), Dinero::centimos($costoUnitario));
        if (Dinero::centimos($costoTotal) !== $esperado) {
            throw new InvalidArgumentException('El costo total no es cantidad x costo unitario');
        }
    }

    public function getIdIngreso(): int { return $this->idIngreso; }
    public function getIdProducto(): int { return $this->idProducto; }
    public function getCantidad(): float { return $this->cantidad; }
    public function getCostoUnitario(): float { return $this->costoUnitario; }
    public function getCostoTotal(): float { return $this->costoTotal; }
}
