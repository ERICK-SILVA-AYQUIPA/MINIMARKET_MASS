<?php
declare(strict_types=1);

require_once __DIR__ . '/../DesgloseIgv.php';
require_once __DIR__ . '/ComprobanteParaEnviar.php';
require_once __DIR__ . '/RespuestaFacturacion.php';

/**
 * Un proveedor de facturacion electronica (OSE/PSE) que firma el comprobante y lo envia a SUNAT.
 * Se elige en .env con FACTURACION_PROVEEDOR (FacturacionElectronica::desdeEntorno).
 * Cambiar de proveedor es escribir otra clase con esta interfaz: la venta no cambia.
 */
interface ProveedorFacturacion
{
    // Nombre para mostrar en pantallas y en la revision de la instalacion
    public function nombre(): string;

    // true solo si tiene sus datos completos y puede enviar
    public function estaListo(): bool;

    public function enviar(ComprobanteParaEnviar $comprobante): RespuestaFacturacion;
}
