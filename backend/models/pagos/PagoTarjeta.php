<?php
declare(strict_types=1);

require_once __DIR__ . '/PagoVenta.php';

/** Pago con la fila de la tabla `tarjeta`. Nunca se guarda el numero completo. */
class PagoTarjeta extends PagoVenta
{
    public function __construct(
        int $idPago,
        float $montoPagado,
        string $fechaHora,
        string $estado,
        int $idVenta,
        private string $tipoTarjeta,          // tipo_tarjeta: 'CREDITO' o 'DEBITO'
        private string $marcaTarjeta,         // marca_tarjeta VARCHAR(20)
        private string $ultimos4Digitos,      // ultimos_4_digitos CHAR(4)
        private string $codigoAutorizacion    // codigo_autorizacion VARCHAR(10)
    ) {
        parent::__construct($idPago, $montoPagado, $fechaHora, $estado, $idVenta);
        if (!in_array($tipoTarjeta, ['CREDITO', 'DEBITO'], true)) {
            throw new InvalidArgumentException("tipo_tarjeta debe ser 'CREDITO' o 'DEBITO'");
        }
        if (preg_match('/^[0-9]{4}$/', $ultimos4Digitos) !== 1) {
            throw new InvalidArgumentException('Solo se guardan los ultimos 4 digitos');
        }
    }

    public function getIdMetodoPago(): int { return 2; }
    public function getNombreMetodo(): string { return 'TARJETA'; }
    public function getTipoTarjeta(): string { return $this->tipoTarjeta; }
    public function getMarcaTarjeta(): string { return $this->marcaTarjeta; }
    public function getUltimos4Digitos(): string { return $this->ultimos4Digitos; }
    public function getCodigoAutorizacion(): string { return $this->codigoAutorizacion; }

    public function detalle(): string
    {
        return $this->tipoTarjeta . ' ' . $this->marcaTarjeta . ' ****' . $this->ultimos4Digitos;
    }
}
