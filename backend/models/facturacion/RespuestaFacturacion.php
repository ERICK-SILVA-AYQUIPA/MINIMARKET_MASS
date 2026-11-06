<?php
declare(strict_types=1);

/**
 * Lo que responde el proveedor: si SUNAT (o el OSE) acepto el comprobante, su hash y el enlace al PDF.
 * Es lo que se guardaria en la tabla comprobante_electronico (docs/propuesta_sunat_y_lector.md, 2.3).
 */
final class RespuestaFacturacion
{
    public function __construct(
        public readonly bool $aceptado,
        public readonly string $mensaje,
        public readonly ?string $hash = null,
        public readonly ?string $enlacePdf = null,
        public readonly ?string $codigoQr = null
    ) {
    }
}
