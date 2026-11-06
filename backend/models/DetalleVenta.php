<?php
declare(strict_types=1);

require_once __DIR__ . '/Dinero.php';

/**
 * Una fila de la tabla `detalle_venta`: producto, cantidad, precio al vender e importe.
 * Con la migracion 020 tambien sus descuentos: lo cobrado de la linea es importe - descuento - descuentoGeneral.
 */
class DetalleVenta
{
    public function __construct(
        private int $idDetalleVenta,      // id_detalle_venta BIGINT UNSIGNED
        private float $cantidad,          // cantidad DECIMAL(10,3)
        private float $precioUnitario,    // precio_unitario DECIMAL(10,2): copia del precio al vender
        private float $importe,           // importe DECIMAL(10,2)
        private int $idVenta,             // id_venta BIGINT UNSIGNED
        private int $idProducto,          // id_producto INT UNSIGNED
        private float $descuento = 0.0,   // descuento DECIMAL(10,2): el de la promocion del producto
        private float $descuentoGeneral = 0.0, // descuento_general DECIMAL(10,2): su parte del descuento a toda la venta
        private ?int $idPromocion = null  // id_promocion INT UNSIGNED NULL
    ) {
        if ($cantidad <= 0 || $precioUnitario <= 0) {
            throw new InvalidArgumentException('cantidad y precio_unitario deben ser mayores que 0');
        }
        // CHECK ck_detalle_importe: importe = ROUND(cantidad * precio_unitario, 2)
        if (Dinero::centimos($importe) !== Dinero::centimos($cantidad * $precioUnitario)) {
            throw new InvalidArgumentException('El importe no coincide con cantidad x precio');
        }
        // CHECK ck_detalle_descuento: los descuentos no pasan del importe
        if ($descuento < 0 || $descuentoGeneral < 0 || Dinero::centimos($descuento) + Dinero::centimos($descuentoGeneral) > Dinero::centimos($importe)) {
            throw new InvalidArgumentException('Los descuentos de la línea no pueden pasar su importe');
        }
    }

    public function getIdDetalleVenta(): int { return $this->idDetalleVenta; }
    public function getCantidad(): float { return $this->cantidad; }
    public function getPrecioUnitario(): float { return $this->precioUnitario; }
    public function getImporte(): float { return $this->importe; }
    public function getIdVenta(): int { return $this->idVenta; }
    public function getIdProducto(): int { return $this->idProducto; }
    public function getDescuento(): float { return $this->descuento; }
    public function getDescuentoGeneral(): float { return $this->descuentoGeneral; }
    public function getIdPromocion(): ?int { return $this->idPromocion; }

    // Lo que el cliente pago por esta linea
    public function getNeto(): float
    {
        return (Dinero::centimos($this->importe) - Dinero::centimos($this->descuento) - Dinero::centimos($this->descuentoGeneral)) / 100;
    }
}
