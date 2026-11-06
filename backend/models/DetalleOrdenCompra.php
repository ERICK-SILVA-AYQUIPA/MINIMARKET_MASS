<?php
declare(strict_types=1);

require_once __DIR__ . '/Dinero.php';
require_once __DIR__ . '/Cantidad.php';

/**
 * Una fila de `detalle_orden_compra`: un producto pedido, cuanto ya llego y su costo pactado.
 * Las cantidades van en milesimas (enteros), como en Cantidad.
 */
class DetalleOrdenCompra
{
    public function __construct(
        private int $idOrdenCompra,
        private int $idProducto,
        private int $pedida,              // cantidad_pedida en milesimas
        private int $recibida,            // cantidad_recibida en milesimas
        private int $costoUnitario,       // costo_unitario en centimos
        private int $costoTotal           // costo_total en centimos
    ) {
        if ($pedida <= 0 || $recibida < 0 || $recibida > $pedida || $costoUnitario < 0) {
            throw new InvalidArgumentException('Cantidades o costo de la orden no validos');
        }
        if (Cantidad::importe($pedida, $costoUnitario) !== $costoTotal) {
            throw new InvalidArgumentException('El costo total no es cantidad x costo unitario');
        }
    }

    public function getIdOrdenCompra(): int { return $this->idOrdenCompra; }
    public function getIdProducto(): int { return $this->idProducto; }
    public function getPedida(): int { return $this->pedida; }
    public function getRecibida(): int { return $this->recibida; }
    public function getCostoUnitario(): int { return $this->costoUnitario; }
    public function getCostoTotal(): int { return $this->costoTotal; }

    // Lo que falta que llegue, en milesimas
    public function pendiente(): int
    {
        return $this->pedida - $this->recibida;
    }
}
