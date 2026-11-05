<?php
declare(strict_types=1);

require_once __DIR__ . '/ProveedorFacturacion.php';

/**
 * Proveedor PSE/OSE tipo Nubefact: se le manda la venta en JSON por HTTPS y el proveedor arma el XML UBL 2.1,
 * lo firma, lo envia a SUNAT y responde con el estado, el hash, el QR y el enlace al PDF.
 *
 * PREPARADO, NO CONECTADO: arma el pedido y sabe enviarlo, pero la venta todavia no lo llama
 * (falta la tabla comprobante_electronico y el envio programado: docs/facturacion_electronica.md).
 * La ruta y el token van solo en .env (NUBEFACT_URL, NUBEFACT_TOKEN), nunca en GitHub ni en la base.
 * Los nombres de los campos siguen la documentacion publica de Nubefact [revisar con la version vigente].
 */
final class NubefactProveedor implements ProveedorFacturacion
{
    /** @var callable(string, array<int, string>, string): array{codigo: int, cuerpo: string} */
    private $transporte;

    /**
     * @param ?callable(string, array<int, string>, string): array{codigo: int, cuerpo: string} $transporte
     *        quien hace el POST; las pruebas pasan uno falso para no salir a internet
     */
    public function __construct(
        private string $url,
        private string $token,
        ?callable $transporte = null,
        private int $segundosLimite = 15
    ) {
        $this->transporte = $transporte ?? $this->postConCurl(...);
    }

    public function nombre(): string
    {
        return 'Nubefact (OSE/PSE)';
    }

    public function estaListo(): bool
    {
        return str_starts_with($this->url, 'https://') && strlen($this->token) >= 20;
    }

    /**
     * El JSON que pide el proveedor. Cada linea lleva su valor sin IGV y su IGV, calculados igual que
     * DesgloseIgv, asi los totales del pedido son los mismos centimos que el ticket.
     * @return array<string, mixed>
     */
    public function armarPedido(ComprobanteParaEnviar $c): array
    {
        $tasa = DesgloseIgv::TASA_PORCENTAJE;
        $items = [];
        foreach ($c->lineas as $l) {
            $cantidad = $l['milesimas'] / 1000;
            $sinIgv = DesgloseIgv::valorSinIgv($l['importeCentimos']);
            $items[] = [
                'unidad_de_medida'          => $l['alPeso'] ? 'KGM' : 'NIU',   // catalogo 03: kilogramo o unidad
                'codigo'                    => $l['codigo'],
                'descripcion'               => $l['descripcion'],
                'cantidad'                  => $cantidad,
                'valor_unitario'            => round($l['precioCentimos'] / 100 / (1 + $tasa / 100), 10),
                'precio_unitario'           => $l['precioCentimos'] / 100,
                'descuento'                 => '',
                'subtotal'                  => $sinIgv / 100,
                'tipo_de_igv'               => 1,                               // gravado, operacion onerosa [contador]
                'igv'                       => ($l['importeCentimos'] - $sinIgv) / 100,
                'total'                     => $l['importeCentimos'] / 100,
                'anticipo_regularizacion'   => false,
            ];
        }
        $d = $c->desglose();
        return [
            'operacion'                         => 'generar_comprobante',
            'tipo_de_comprobante'               => $c->esFactura ? 1 : 2,       // 1 factura, 2 boleta
            'serie'                             => $c->serie,
            'numero'                            => $c->numero,
            'sunat_transaction'                 => 1,                           // venta interna
            'cliente_tipo_de_documento'         => $c->tipoDocumentoCliente,
            'cliente_numero_de_documento'       => $c->numeroDocumentoCliente,
            'cliente_denominacion'              => $c->nombreCliente,
            'cliente_direccion'                 => '',
            'fecha_de_emision'                  => date('d-m-Y', (int) strtotime($c->fechaEmision)),
            'moneda'                            => 1,                           // soles
            'porcentaje_de_igv'                 => (float) $tasa,
            'total_gravada'                     => $d->getGravada() / 100,
            'total_igv'                         => $d->getIgv() / 100,
            'total'                             => $d->getTotal() / 100,
            'enviar_automaticamente_a_la_sunat' => true,
            'enviar_automaticamente_al_cliente' => false,
            'formato_de_pdf'                    => 'TICKET',
            'items'                             => $items,
        ];
    }

    public function enviar(ComprobanteParaEnviar $comprobante): RespuestaFacturacion
    {
        if (!$this->estaListo()) {
            return new RespuestaFacturacion(false, 'Falta NUBEFACT_URL (https) o NUBEFACT_TOKEN en .env.');
        }
        $cuerpo = json_encode($this->armarPedido($comprobante), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $cabeceras = ['Authorization: Token token="' . $this->token . '"', 'Content-Type: application/json'];
        try {
            $respuesta = ($this->transporte)($this->url, $cabeceras, $cuerpo);
        } catch (RuntimeException $ex) {
            // Sin internet o el proveedor no responde: la venta ya esta guardada, se reintenta despues
            return new RespuestaFacturacion(false, 'No se pudo conectar con el proveedor: ' . $ex->getMessage());
        }
        $datos = json_decode($respuesta['cuerpo'], true);
        if (!is_array($datos)) {
            return new RespuestaFacturacion(false, 'El proveedor respondió HTTP ' . $respuesta['codigo'] . ' sin JSON.');
        }
        if (isset($datos['errors'])) {
            return new RespuestaFacturacion(false, 'El proveedor rechazó el comprobante: ' . (is_string($datos['errors']) ? $datos['errors'] : json_encode($datos['errors'])));
        }
        return new RespuestaFacturacion(
            ($datos['aceptada_por_sunat'] ?? false) === true,
            (string) ($datos['sunat_description'] ?? 'Recibido por el proveedor, pendiente de SUNAT.'),
            isset($datos['codigo_hash']) ? (string) $datos['codigo_hash'] : null,
            isset($datos['enlace_del_pdf']) ? (string) $datos['enlace_del_pdf'] : null,
            isset($datos['cadena_para_codigo_qr']) ? (string) $datos['cadena_para_codigo_qr'] : null
        );
    }

    /**
     * @param array<int, string> $cabeceras
     * @return array{codigo: int, cuerpo: string}
     */
    private function postConCurl(string $url, array $cabeceras, string $cuerpo): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP no tiene la extension curl');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->segundosLimite,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);
        $respuesta = curl_exec($ch);
        if (!is_string($respuesta)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error !== '' ? $error : 'sin respuesta');
        }
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['codigo' => $codigo, 'cuerpo' => $respuesta];
    }
}
