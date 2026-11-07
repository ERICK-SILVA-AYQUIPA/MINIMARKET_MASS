<?php
declare(strict_types=1);

require_once __DIR__ . '/../Dinero.php';
require_once __DIR__ . '/../exportar/DocumentoPdf.php';

/**
 * El comprobante de una venta listo para mandarlo al cliente: el texto de WhatsApp, el correo (texto, HTML y PDF)
 * y los enlaces que abren WhatsApp o Gmail con todo ya escrito. Solo arma textos: no toca la base ni la red.
 */
final class ComprobanteParaCliente
{
    public const URL_WHATSAPP = 'https://wa.me/';
    public const URL_GMAIL = 'https://mail.google.com/mail/';

    /**
     * @param array<int, array{nombre: string, cantidad: string, precio: float, importe: float, descuento: float}> $lineas
     * @param array<int, array{metodo: string, monto: float}> $pagos  solo los pagos validos
     */
    public function __construct(
        private string $empresa,        // TIENDA MASS (nombre comercial del .env)
        private string $tienda,         // nombre de la sucursal
        private string $direccion,
        private string $tipo,           // Boleta / Factura
        private string $numero,         // B001-00000012
        private string $fecha,          // 06/11/2026 17:30
        private string $cliente,        // DNI 12345678 o CLIENTE VARIOS
        private array $lineas,
        private float $descuentoVenta,  // descuento de toda la venta (no el de cada producto)
        private float $total,
        private array $pagos,
        private bool $interno,          // ticket interno: aun no vale ante SUNAT
        private string $ruc = '',       // RUC de la empresa (vacio si no esta en el .env)
        private ?float $gravada = null, // op. gravada e IGV (DesgloseIgv), si se conocen
        private ?float $igv = null,
        private ?string $enlacePdf = null // enlace publico al PDF (solo si la pagina se abre desde internet)
    ) {
    }

    public function getNumero(): string { return $this->numero; }

    // 987654321, +51 987 654 321 o 51987654321 => 987654321. Un celular peruano: 9 digitos que empiezan en 9.
    public static function celular(?string $texto): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $texto) ?? '';
        if (strlen($digitos) === 11 && str_starts_with($digitos, '51')) {
            $digitos = substr($digitos, 2);
        }
        return preg_match('/^9[0-9]{8}$/', $digitos) === 1 ? $digitos : null;
    }

    public static function correo(?string $texto): ?string
    {
        $correo = trim((string) $texto);
        return strlen($correo) <= 120 && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false ? $correo : null;
    }

    public function asunto(): string
    {
        return 'Tu ' . mb_strtolower($this->tipo) . ' ' . $this->numero . ' de ' . $this->empresa;
    }

    /**
     * El mensaje para el cliente, ordenado como el ticket de una tienda: cabecera, cada producto en su linea,
     * totales y forma de pago. Sin tablas ni bloques de codigo (en WhatsApp salen como cuadros grises):
     * solo *negrita* y _cursiva_ de WhatsApp. Para el correo va igual pero sin esas marcas.
     */
    public function texto(bool $paraWhatsApp = true): string
    {
        $b = fn(string $t): string => $paraWhatsApp ? '*' . $t . '*' : $t;
        $i = fn(string $t): string => $paraWhatsApp ? '_' . $t . '_' : $t;
        $s = fn(float $monto): string => 'S/ ' . Dinero::aTexto(Dinero::centimos($monto));
        $raya = '------------------------------';

        $filas = [$b($this->empresa)];
        if ($this->ruc !== '') {
            $filas[] = 'RUC ' . $this->ruc;
        }
        $filas[] = $this->tienda;
        if ($this->direccion !== '') {
            $filas[] = $this->direccion;
        }
        array_push($filas, $raya, $b(mb_strtoupper($this->tipo)), $b($this->numero), 'Fecha: ' . $this->fecha, 'Cliente: ' . $this->cliente, $raya);

        $descuentos = $this->descuentoVenta;
        foreach ($this->lineas as $l) {
            $filas[] = $b($l['nombre']);
            $filas[] = $l['cantidad'] . ' x ' . $s($l['precio']) . '  =  ' . $s($l['importe']);
            if ($l['descuento'] > 0) {
                $filas[] = $i('Descuento -' . $s($l['descuento']));
                $descuentos += $l['descuento'];
            }
            $filas[] = '';
        }
        array_pop($filas);
        $filas[] = $raya;
        if ($descuentos > 0) {
            $filas[] = 'Subtotal: ' . $s($this->total + $descuentos);
            $filas[] = 'Descuentos: -' . $s($descuentos);
        }
        if ($this->gravada !== null && $this->igv !== null) {
            $filas[] = 'Op. gravada: ' . $s($this->gravada);
            $filas[] = 'IGV 18%: ' . $s($this->igv);
        }
        $filas[] = $b('TOTAL: ' . $s($this->total));
        foreach ($this->pagos as $p) {
            $filas[] = 'Pago: ' . self::nombrePago($p['metodo']) . ' ' . $s($p['monto']);
        }
        $filas[] = $raya;
        if ($this->enlacePdf !== null) {
            $filas[] = 'Descarga tu comprobante en PDF:';
            $filas[] = $this->enlacePdf;
            $filas[] = '';
        }
        if ($this->interno) {
            $filas[] = $i('Ticket interno, no válido como comprobante de pago ante SUNAT.');
            $filas[] = '';
        }
        $filas[] = 'Gracias por tu compra en ' . $this->empresa . '. ¡Te esperamos pronto!';
        return implode("\n", $filas);
    }

    // EFECTIVO => Efectivo, TARJETA DE DEBITO => Tarjeta de debito (como lo escribiria una persona)
    private static function nombrePago(string $metodo): string
    {
        $texto = mb_strtolower($metodo);
        return mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }

    // Sin celular, WhatsApp pregunta a que contacto mandarlo
    public function enlaceWhatsApp(?string $celular): string
    {
        $celular = self::celular($celular);
        return self::URL_WHATSAPP . ($celular !== null ? '51' . $celular : '') . '?text=' . rawurlencode($this->texto());
    }

    // Gmail en el navegador con el correo ya escrito (cuando el servidor no tiene SMTP configurado)
    public function enlaceGmail(?string $correo): string
    {
        return self::URL_GMAIL . '?' . http_build_query([
            'view' => 'cm',
            'fs'   => '1',
            'to'   => self::correo($correo) ?? '',
            'su'   => $this->asunto(),
            'body' => $this->texto(false),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function html(): string
    {
        $e = fn(string $t): string => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        $s = fn(float $monto): string => 'S/ ' . Dinero::aTexto(Dinero::centimos($monto));
        $filas = '';
        foreach ($this->lineas as $l) {
            $filas .= '<tr><td>' . $e($l['cantidad']) . '</td><td>' . $e($l['nombre'])
                . ($l['descuento'] > 0 ? '<br><small style="color:#0a7a3d">Descuento -' . $e($s($l['descuento'])) . '</small>' : '')
                . '</td><td style="text-align:right">' . $e($s($l['precio'])) . '</td><td style="text-align:right">'
                . $e($s($l['importe'])) . '</td></tr>';
        }
        $pagos = implode('<br>', array_map(fn(array $p): string => 'Pagado con ' . $e($p['metodo']) . ': ' . $e($s($p['monto'])), $this->pagos));
        return '<!doctype html><html lang="es"><body style="font-family:Arial,sans-serif;color:#1f2d3d;max-width:560px">'
            . '<h2 style="margin:0">' . $e($this->empresa) . '</h2>'
            . '<p style="margin:4px 0 16px;color:#5a6472">' . $e($this->tienda . ($this->direccion !== '' ? ' · ' . $this->direccion : '')) . '</p>'
            . '<h3 style="margin:0">' . $e($this->tipo . ' ' . $this->numero) . '</h3>'
            . '<p style="margin:4px 0 12px">Fecha: ' . $e($this->fecha) . '<br>Cliente: ' . $e($this->cliente) . '</p>'
            . '<table cellpadding="6" style="border-collapse:collapse;width:100%;font-size:14px">'
            . '<tr style="background:#ffe14d"><th align="left">Cant.</th><th align="left">Producto</th><th align="right">P. unit.</th><th align="right">Importe</th></tr>'
            . $filas
            . ($this->descuentoVenta > 0 ? '<tr><td colspan="3">Descuento de la compra</td><td style="text-align:right">-' . $e($s($this->descuentoVenta)) . '</td></tr>' : '')
            . '<tr><th colspan="3" align="left">TOTAL</th><th align="right">' . $e($s($this->total)) . '</th></tr></table>'
            . '<p>' . $pagos . '</p>'
            . ($this->interno ? '<p style="color:#5a6472;font-size:12px">Ticket interno: no es un comprobante de pago ante SUNAT.</p>' : '')
            . '<p>¡Gracias por tu compra! Adjuntamos el comprobante en PDF.</p></body></html>';
    }

    public function nombrePdf(): string
    {
        return $this->numero . '.pdf';
    }

    public function pdf(): string
    {
        $s = fn(float $monto): string => 'S/ ' . Dinero::aTexto(Dinero::centimos($monto));
        $pdf = new DocumentoPdf($this->tipo . ' ' . $this->numero, $this->empresa);
        $pdf->titulo($this->empresa);
        if ($this->ruc !== '') {
            $pdf->texto('RUC ' . $this->ruc, 9, false);
        }
        $pdf->texto($this->tienda . ($this->direccion !== '' ? ' · ' . $this->direccion : ''));
        $pdf->subtitulo($this->tipo . ' ' . $this->numero);
        $pdf->texto('Fecha: ' . $this->fecha . '   ·   Cliente: ' . $this->cliente, 9, false);
        $filas = [];
        foreach ($this->lineas as $l) {
            $filas[] = [$l['cantidad'], $l['nombre'], $s($l['precio']), $l['descuento'] > 0 ? '-' . $s($l['descuento']) : '', $s($l['importe'])];
        }
        $pdf->tabla([['Cant.', 1, 'der'], ['Producto', 5, 'izq'], ['P. unit.', 1.5, 'der'], ['Descuento', 1.5, 'der'], ['Importe', 1.5, 'der']], $filas, 'Sin productos.');
        if ($this->descuentoVenta > 0) {
            $pdf->texto('Descuento de la compra: -' . $s($this->descuentoVenta), 10, false);
        }
        if ($this->gravada !== null && $this->igv !== null) {
            $pdf->texto('Op. gravada: ' . $s($this->gravada) . '   ·   IGV 18%: ' . $s($this->igv), 9, false);
        }
        $pdf->subtitulo('TOTAL: ' . $s($this->total));
        foreach ($this->pagos as $p) {
            $pdf->texto('Pagado con ' . $p['metodo'] . ': ' . $s($p['monto']), 9, false);
        }
        if ($this->interno) {
            $pdf->texto('Ticket interno: no es un comprobante de pago ante SUNAT.');
        }
        $pdf->texto('¡Gracias por tu compra!', 10, false);
        return $pdf->contenido();
    }
}
