<?php
declare(strict_types=1);

/**
 * Una fila de `movimiento_stock` (kardex): cada cambio de stock con un unico origen.
 */
class MovimientoStock
{
    public function __construct(
        private int $idMovimiento,        // id_movimiento BIGINT UNSIGNED
        private string $fechaHora,        // fecha_hora DATETIME
        private float $cantidad,          // cantidad DECIMAL(10,3), siempre positiva
        private float $stockNuevo,        // stock_nuevo DECIMAL(10,3): saldo despues del movimiento
        private ?string $observacion,     // observacion VARCHAR(200) NULL
        private int $idTienda,
        private int $idProducto,
        private int $idTipoMovimiento,
        private int $idEmpleado,
        private ?int $idVenta,            // origen: uno solo de los tres
        private ?int $idTraslado,
        private ?int $idMerma
    ) {
        if ($cantidad <= 0 || $stockNuevo < 0) {
            throw new InvalidArgumentException('Movimiento con cantidad o saldo invalido');
        }
        // CHECK ck_movimiento_origen: exactamente un origen
        if ((int) ($idVenta !== null) + (int) ($idTraslado !== null) + (int) ($idMerma !== null) !== 1) {
            throw new InvalidArgumentException('El movimiento debe tener un solo origen');
        }
    }

    public function getIdMovimiento(): int { return $this->idMovimiento; }
    public function getFechaHora(): string { return $this->fechaHora; }
    public function getCantidad(): float { return $this->cantidad; }
    public function getStockNuevo(): float { return $this->stockNuevo; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function getIdTienda(): int { return $this->idTienda; }
    public function getIdProducto(): int { return $this->idProducto; }
    public function getIdTipoMovimiento(): int { return $this->idTipoMovimiento; }
    public function getIdEmpleado(): int { return $this->idEmpleado; }
    public function getIdVenta(): ?int { return $this->idVenta; }
    public function getIdTraslado(): ?int { return $this->idTraslado; }
    public function getIdMerma(): ?int { return $this->idMerma; }
}
