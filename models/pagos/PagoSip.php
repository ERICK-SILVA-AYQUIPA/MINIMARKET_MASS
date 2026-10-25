<?php
declare(strict_types=1);

require_once __DIR__ . '/PagoVenta.php';

/** Pago con la fila de la tabla `billetera_digital_sip`. */
class PagoSip extends PagoVenta
{
    public function __construct(
        int $idPago,
        float $montoPagado,
        string $fechaHora,
        string $estado,
        int $idVenta,
        private string $modalidadPago,     // modalidad_pago: 'QR' o 'TARJETA_SIP'
        private string $codigoOperacion    // codigo_operacion VARCHAR(30), unico
    ) {
        parent::__construct($idPago, $montoPagado, $fechaHora, $estado, $idVenta);
        if (!in_array($modalidadPago, ['QR', 'TARJETA_SIP'], true)) {
            throw new InvalidArgumentException("modalidad_pago debe ser 'QR' o 'TARJETA_SIP'");
        }
    }

    public function getIdMetodoPago(): int { return 3; }
    public function getNombreMetodo(): string { return 'BILLETERA DIGITAL SIP'; }
    public function getModalidadPago(): string { return $this->modalidadPago; }
    public function getCodigoOperacion(): string { return $this->codigoOperacion; }

    public function detalle(): string
    {
        return 'Sip ' . $this->modalidadPago . ' - Operacion ' . $this->codigoOperacion;
    }
}
