<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/Entorno.php';
require_once __DIR__ . '/SinFacturacionElectronica.php';
require_once __DIR__ . '/NubefactProveedor.php';

/**
 * Elige el proveedor de facturacion electronica segun .env:
 *   FACTURACION_PROVEEDOR=ninguno   (por defecto) el ticket sale como TICKET INTERNO
 *   FACTURACION_PROVEEDOR=nubefact  con NUBEFACT_URL y NUBEFACT_TOKEN de la cuenta del dueño
 *
 * Mientras el envio no este conectado a la venta ($emisionConectada = false), el ticket sigue diciendo
 * que es interno aunque haya proveedor: no se imprime como valido algo que SUNAT no recibio.
 */
final class FacturacionElectronica
{
    // Pasa a true cuando la venta guarde el comprobante PENDIENTE y el envio programado lo mande (paso 2 del plan)
    private static bool $emisionConectada = false;

    public static function desdeEntorno(): ProveedorFacturacion
    {
        return self::crear(
            strtolower(Entorno::leer('FACTURACION_PROVEEDOR', 'ninguno')),
            Entorno::leer('NUBEFACT_URL'),
            Entorno::leer('NUBEFACT_TOKEN')
        );
    }

    public static function crear(string $proveedor, string $url = '', string $token = ''): ProveedorFacturacion
    {
        return match ($proveedor) {
            'ninguno', '' => new SinFacturacionElectronica(),
            'nubefact'    => new NubefactProveedor($url, $token),
            default       => new SinFacturacionElectronica("FACTURACION_PROVEEDOR=$proveedor no es un proveedor conocido (usa ninguno o nubefact)."),
        };
    }

    // El ticket es valido ante SUNAT solo si hay proveedor listo Y la venta ya lo usa
    public static function ticketEsInterno(ProveedorFacturacion $proveedor): bool
    {
        return !(self::$emisionConectada && $proveedor->estaListo());
    }
}
