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
        private ?int $idVenta,            // origen: uno solo
        private ?int $idTraslado,
        private ?int $idMerma,
        private ?int $idAjuste = null,    // ajustes.sql: conteo fisico
        private ?int $idIngreso = null    // ingresos.sql: compra recibida
    ) {
        if ($cantidad <= 0 || $stockNuevo < 0) {
            throw new InvalidArgumentException('Movimiento con cantidad o saldo invalido');
        }
        // CHECK ck_movimiento_origen: exactamente un origen
        if ((int) ($idVenta !== null) + (int) ($idTraslado !== null) + (int) ($idMerma !== null) + (int) ($idAjuste !== null)
            + (int) ($idIngreso !== null) !== 1) {
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
    public function getIdAjuste(): ?int { return $this->idAjuste; }
    public function getIdIngreso(): ?int { return $this->idIngreso; }

    // De donde salio el movimiento, para el kardex y su CSV
    public function origen(): string
    {
        return match (true) {
            $this->idVenta !== null    => 'Venta ' . $this->idVenta,
            $this->idTraslado !== null => 'Traslado ' . $this->idTraslado,
            $this->idMerma !== null    => 'Merma ' . $this->idMerma,
            $this->idAjuste !== null   => 'Ajuste ' . $this->idAjuste,
            default                    => 'Ingreso ' . $this->idIngreso,
        };
    }
}
