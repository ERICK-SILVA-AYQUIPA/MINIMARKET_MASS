<?php
declare(strict_types=1);

require_once __DIR__ . '/ProveedorFacturacion.php';

/**
 * Lo que hay hoy: ningun proveedor. Las ventas quedan en el sistema y el ticket sale como
 * TICKET INTERNO, no valido como comprobante de pago ante SUNAT.
 */
final class SinFacturacionElectronica implements ProveedorFacturacion
{
    public function __construct(private string $motivo = 'No hay un proveedor de facturación electrónica configurado.')
    {
    }

    public function nombre(): string
    {
        return 'Ninguno (ticket interno)';
    }

    public function estaListo(): bool
    {
        return false;
    }

    public function motivo(): string
    {
        return $this->motivo;
    }

    public function enviar(ComprobanteParaEnviar $comprobante): RespuestaFacturacion
    {
        return new RespuestaFacturacion(false, $this->motivo);
    }
}
