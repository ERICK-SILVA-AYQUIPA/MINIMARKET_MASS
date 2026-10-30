<?php
declare(strict_types=1);

require_once __DIR__ . '/PagoVenta.php';

/** Pago con la fila de la tabla `efectivo`. */
class PagoEfectivo extends PagoVenta
{
    public function __construct(
        int $idPago,
        float $montoPagado,
        string $fechaHora,
        string $estado,
        int $idVenta,
        private float $montoRecibido,   // monto_recibido DECIMAL(10,2)
        private float $vuelto           // vuelto DECIMAL(10,2)
    ) {
        parent::__construct($idPago, $montoPagado, $fechaHora, $estado, $idVenta);
        // CHECK ck_efectivo_vuelto: 0 <= vuelto < monto_recibido
        if ($montoRecibido <= 0 || $vuelto < 0 || $vuelto >= $montoRecibido) {
            throw new InvalidArgumentException('El vuelto debe ser 0 o mas y menor que lo recibido');
        }
    }

    public function getIdMetodoPago(): int { return 1; }
    public function getNombreMetodo(): string { return 'EFECTIVO'; }
    public function getMontoRecibido(): float { return $this->montoRecibido; }
    public function getVuelto(): float { return $this->vuelto; }

    public function detalle(): string
    {
        return 'Recibido ' . number_format($this->montoRecibido, 2) . ' - Vuelto ' . number_format($this->vuelto, 2);
    }
}
