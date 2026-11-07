<?php
declare(strict_types=1);

require_once __DIR__ . '/Herramientas.php';

/**
 * Preguntas predeterminadas de la tienda, respondidas al instante desde la base, SIN inteligencia artificial
 * (no necesitan ANTHROPIC_API_KEY ni internet): ventas de hoy, ayer y del mes, mas vendidos, por cajero,
 * medios de pago, cada tienda, stock bajo, lo que no se vende, mejores clientes y abrir Nueva venta. Y las "inteligentes":
 * que pedir y cuanto, pronostico de la semana, productos por vencer, horas punta y las alertas de la tienda juntas.
 *
 * Usa las mismas herramientas (y permisos) que Claude: si un rol no tiene la herramienta, la pregunta no se le ofrece.
 * Tambien entiende una pregunta escrita con palabras clave ("cuanto vendimos ayer", "stock bajo", "precio del arroz")
 * para que el asistente funcione sin clave; con clave, lo escrito libre lo responde Claude.
 */
final class RespuestasRapidas
{
    // clave => [texto del boton, herramienta que necesita]
    private const PREGUNTAS = [
        'alertas'          => ['¿Qué alertas hay en la tienda?', 'productos_por_reponer'],
        'ventas_hoy'       => ['¿Cuánto vendimos hoy?', 'resumen_ventas'],
        'ventas_ayer'      => ['¿Cuánto vendimos ayer?', 'resumen_ventas'],
        'ventas_mes'       => ['¿Cuánto llevamos vendido este mes?', 'resumen_ventas'],
        'mas_vendidos'     => ['Los 5 más vendidos de la semana', 'detalle_ventas'],
        'por_cajero'       => ['¿Cuánto vendió cada cajero hoy?', 'detalle_ventas'],
        'medios_pago'      => ['¿Cómo pagaron hoy los clientes?', 'detalle_ventas'],
        'tiendas_mes'      => ['¿Cuánto vendió cada tienda este mes?', 'cadena'],
        'stock_bajo'       => ['¿Qué productos tienen stock bajo?', 'productos_por_reponer'],
        'sin_venta'        => ['¿Qué productos no se venden hace 30 días?', 'productos_sin_venta'],
        'mejores_clientes' => ['¿Quiénes son los mejores clientes del mes?', 'mejores_clientes'],
        'pedido'           => ['¿Qué pido al proveedor y cuánto?', 'sugerencia_pedido'],
        'por_vencer'       => ['¿Qué productos están por vencer?', 'productos_por_vencer'],
        'pronostico'       => ['¿Cuánto venderemos esta semana?', 'pronostico_ventas'],
        'horas_punta'      => ['¿Cuáles son las horas punta?', 'horas_punta'],
        'hacer_venta'      => ['Quiero hacer una venta', 'nueva_venta'],
    ];

    // Palabras que no sirven para buscar un producto ("¿cuánto cuesta el arroz?" -> "arroz")
    private const RELLENO = ['cual', 'cuales', 'es', 'el', 'la', 'los', 'las', 'de', 'del', 'un', 'una', 'que', 'precio', 'precios',
        'cuanto', 'cuesta', 'cuestan', 'vale', 'valen', 'hay', 'tenemos', 'tienen', 'tiene', 'queda', 'quedan', 'stock', 'busca',
        'buscar', 'producto', 'productos', 'me', 'dime', 'dame', 'por', 'favor', 'y', 'a', 'en', 'cuantos', 'cuantas', 'quiero', 'saber',
        'unidades', 'todavia', 'aun', 'algo', 'sobre', 'info', 'informacion', 'con', 'para', 'mi', 'nuestra', 'tienda'];

    /** @var array<string, bool> herramientas (y permisos especiales) que tiene este usuario */
    private array $tiene = [];

    public function __construct(private Herramientas $herramientas, private DateTimeImmutable $hoy = new DateTimeImmutable('today'))
    {
        foreach ($herramientas->definiciones() as $d) {
            $nombre = (string) ($d['name'] ?? '');
            $this->tiene[$nombre] = true;
            $propiedades = $d['input_schema']['properties'] ?? [];
            if ($nombre === 'resumen_ventas' && is_array($propiedades) && isset($propiedades['alcance'])) {
                $this->tiene['cadena'] = true;
            }
            if ($nombre === 'abrir_pagina' && is_array($propiedades) && in_array('nueva_venta', $propiedades['pagina']['enum'] ?? [], true)) {
                $this->tiene['nueva_venta'] = true;
            }
        }
    }

    /** @return array<string, string> clave => texto de la pregunta, solo las que este rol puede hacer */
    public function disponibles(): array
    {
        $lista = [];
        foreach (self::PREGUNTAS as $clave => [$texto, $necesita]) {
            if (isset($this->tiene[$necesita])) {
                $lista[$clave] = $texto;
            }
        }
        return $lista;
    }

    public function texto(string $clave): ?string
    {
        return $this->disponibles()[$clave] ?? null;
    }

    /**
     * Responde una pregunta predeterminada.
     * @return array{respuesta: string, enlaces: array<int, array{texto: string, url: string}>, ok: bool}
     */
    public function responder(string $clave): array
    {
        if ($this->texto($clave) === null) {
            return $this->fin('Esa pregunta no está disponible para tu usuario.', false);
        }
        $hoy = $this->hoy->format('Y-m-d');
        $ayer = $this->hoy->modify('-1 day')->format('Y-m-d');
        $mes = $this->hoy->format('Y-m-01');
        $semana = $this->hoy->modify('-6 days')->format('Y-m-d');
        return match ($clave) {
            'ventas_hoy'       => $this->resumen('Hoy', $hoy, $hoy),
            'ventas_ayer'      => $this->resumen('Ayer', $ayer, $ayer),
            'ventas_mes'       => $this->resumen('En lo que va del mes', $mes, $hoy),
            'tiendas_mes'      => $this->tiendas($mes, $hoy),
            'mas_vendidos'     => $this->masVendidos($semana, $hoy),
            'por_cajero'       => $this->porCajero($hoy),
            'medios_pago'      => $this->mediosPago($hoy),
            'stock_bajo'       => $this->stockBajo(),
            'sin_venta'        => $this->sinVenta(),
            'mejores_clientes' => $this->clientes($mes, $hoy),
            'alertas'          => $this->alertas(),
            'pedido'           => $this->pedido(),
            'por_vencer'       => $this->porVencer(),
            'pronostico'       => $this->pronostico(),
            'horas_punta'      => $this->horasPunta(),
            'hacer_venta'      => $this->abrir('nueva_venta', 'Listo: abre Nueva venta con el botón de abajo.'),
            default            => $this->fin('No conozco esa pregunta.', false),
        };
    }

    /**
     * Lo escrito libre, sin IA: si se parece a una pregunta predeterminada la responde; si no, lo busca como producto.
     * @return array{respuesta: string, enlaces: array<int, array{texto: string, url: string}>, ok: bool}
     */
    public function interpretar(string $pregunta): array
    {
        $clave = $this->claveDe($pregunta);
        if ($clave !== null && $this->texto($clave) !== null) {
            return $this->responder($clave);
        }
        if ($clave !== null) {
            return $this->fin('Tu usuario no puede ver eso. Prueba con una de las preguntas de abajo.', false);
        }
        $palabras = array_diff(preg_split('/\s+/', $this->normalizar($pregunta)) ?: [], self::RELLENO, ['']);
        if ($palabras !== []) {
            $r = $this->buscar(implode(' ', $palabras));
            if ($r !== null) {
                return $r;
            }
        }
        return $this->fin("No entendí la pregunta. Puedo responder sobre la tienda, por ejemplo:\n- "
            . implode("\n- ", array_slice(array_values($this->disponibles()), 0, 5))
            . "\n- El precio o el stock de un producto: «precio del arroz Costeño»", false);
    }

    // A que pregunta predeterminada se parece lo escrito (null si a ninguna)
    public function claveDe(string $pregunta): ?string
    {
        $t = ' ' . $this->normalizar($pregunta) . ' ';
        $hay = static fn(string ...$palabras): bool => array_filter($palabras, fn(string $p): bool => str_contains($t, $p)) !== [];
        if ($hay('hacer una venta', 'quiero vender', 'nueva venta', 'registrar una venta', 'cobrar a')) {
            return 'hacer_venta';
        }
        if ($hay('alerta', 'aviso', 'problema', 'que hay que hacer', 'que debo hacer', 'pendiente', 'como esta la tienda')) {
            return 'alertas';
        }
        if ($hay('vence', 'vencer', 'vencid', 'vencimiento', 'caduc', 'malogr')) {
            return 'por_vencer';
        }
        if ($hay('hora punta', 'horas punta', 'hora pico', 'horas pico', 'mas gente', 'mas movid', 'mas concurr', 'turno', 'a que hora')) {
            return 'horas_punta';
        }
        if ($hay('pronostic', 'venderemos', 'vamos a vender', 'se va a vender', 'proxima semana', 'estim', 'prediccion', 'predec', 'esperamos vender')) {
            return 'pronostico';
        }
        if ($hay('pedir', ' pido ', 'pedido', 'comprar al', 'orden de compra', 'proveedor', 'cuanto repon')) {
            return 'pedido';
        }
        if ($hay('stock bajo', 'poco stock', 'reponer', 'agotad', 'se acab', 'falta stock', 'faltan', 'minimo')) {
            return 'stock_bajo';
        }
        if ($hay('no se vende', 'no se venden', 'sin venta', 'no sale', 'no salen', 'quieto', 'parado', 'estancad')) {
            return 'sin_venta';
        }
        if ($hay('pagaron', 'medio de pago', 'medios de pago', 'metodo', 'yape', 'plin', 'tarjeta', 'efectivo')) {
            return 'medios_pago';
        }
        if ($hay('cliente')) {
            return 'mejores_clientes';
        }
        if ($hay('mas vendid', 'mas se vend', 'top ', 'lo que mas', 'mejores productos', 'mas salida')) {
            return 'mas_vendidos';
        }
        if ($hay('vend', 'venta', 'factur', 'recaud', 'ganamos', 'hicimos', 'total')) {
            return match (true) {
                $hay('cajer') => 'por_cajero',
                $hay('tienda', 'sede', 'local', 'sucursal', 'cadena') => 'tiendas_mes',
                $hay('ayer') => 'ventas_ayer',
                $hay(' mes ', 'mensual') => 'ventas_mes',
                $hay('semana') => 'mas_vendidos',
                default => 'ventas_hoy',
            };
        }
        return null;
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return trim(preg_replace('/[^a-z0-9 ]+/', ' ', $texto) ?? '');
    }

    private function soles(float $monto): string
    {
        return 'S/ ' . number_format($monto, 2);
    }

    private function cantidad(float $c, string $unidad): string
    {
        return rtrim(rtrim(number_format($c, 3, '.', ''), '0'), '.') . ' ' . $unidad;
    }

    private function resumen(string $cuando, string $desde, string $hasta): array
    {
        $r = $this->herramientas->ejecutar('resumen_ventas', ['desde' => $desde, 'hasta' => $hasta]);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        if ((int) $r['comprobantes'] === 0) {
            return $this->fin($cuando . ' todavía no hay ventas en ' . $r['alcance'] . '.', true);
        }
        $texto = $cuando . ' ' . $r['alcance'] . ' vendió ' . $this->soles((float) $r['total']) . ' en ' . $r['comprobantes'] . ' comprobantes'
            . ' (ticket promedio ' . $this->soles((float) $r['ticketPromedio']) . ').';
        if ((float) $r['devuelto'] > 0) {
            $texto .= "\nDevoluciones: " . $this->soles((float) $r['devuelto']) . '; neto ' . $this->soles((float) $r['neto']) . '.';
        }
        if ((int) $r['anuladas'] > 0) {
            $texto .= "\nVentas anuladas: " . $r['anuladas'] . '.';
        }
        return $this->fin($texto, true);
    }

    private function tiendas(string $desde, string $hasta): array
    {
        $r = $this->herramientas->ejecutar('resumen_ventas', ['desde' => $desde, 'hasta' => $hasta, 'alcance' => 'cadena']);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        $lineas = array_map(fn(array $t): string => '- ' . $t['tienda'] . ': ' . $this->soles((float) $t['total']) . ' (' . $t['comprobantes'] . ' comprobantes)',
            $r['por_tienda'] ?? []);
        return $this->fin('Este mes la cadena vendió ' . $this->soles((float) $r['total']) . ($lineas === [] ? '.' : ":\n" . implode("\n", $lineas)), true);
    }

    private function masVendidos(string $desde, string $hasta): array
    {
        $r = $this->herramientas->ejecutar('detalle_ventas', ['desde' => $desde, 'hasta' => $hasta, 'tipo' => 'mas_vendidos']);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        $filas = array_slice($r['filas'], 0, 5);
        if ($filas === []) {
            return $this->fin('En los últimos 7 días no hubo ventas en ' . $r['alcance'] . '.', true);
        }
        $lineas = [];
        foreach ($filas as $i => $f) {
            $lineas[] = ($i + 1) . '. ' . $f['producto'] . ': ' . $this->cantidad((float) $f['cantidad'], (string) $f['unidad'])
                . ', ' . $this->soles((float) $f['importe']);
        }
        return $this->fin("Lo más vendido en los últimos 7 días:\n" . implode("\n", $lineas), true);
    }

    private function porCajero(string $hoy): array
    {
        $r = $this->herramientas->ejecutar('detalle_ventas', ['desde' => $hoy, 'hasta' => $hoy, 'tipo' => 'por_cajero']);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        if ($r['filas'] === []) {
            return $this->fin('Hoy ningún cajero ha vendido todavía.', true);
        }
        return $this->fin("Hoy cada cajero vendió:\n" . implode("\n", array_map(
            fn(array $f): string => '- ' . $f['cajero'] . ': ' . $this->soles((float) $f['total']) . ' (' . $f['comprobantes'] . ' comprobantes)',
            $r['filas'])), true);
    }

    private function mediosPago(string $hoy): array
    {
        $r = $this->herramientas->ejecutar('detalle_ventas', ['desde' => $hoy, 'hasta' => $hoy, 'tipo' => 'por_metodo_pago']);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        if ($r['filas'] === []) {
            return $this->fin('Hoy todavía no hay pagos.', true);
        }
        return $this->fin("Hoy los clientes pagaron así:\n" . implode("\n", array_map(
            fn(array $f): string => '- ' . $f['metodo'] . ': ' . $this->soles((float) $f['monto']) . ' (' . $f['pagos'] . ' pagos)',
            $r['filas'])), true);
    }

    private function stockBajo(): array
    {
        $r = $this->herramientas->ejecutar('productos_por_reponer', []);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        if ((int) $r['total'] === 0) {
            return $this->fin('Ningún producto está en su stock mínimo. Todo bien.', true);
        }
        $lineas = array_map(fn(array $f): string => '- ' . $f['producto'] . ': quedan ' . $this->cantidad((float) $f['cantidad'], (string) $f['unidad'])
            . ' (mínimo ' . $this->cantidad((float) $f['minimo'], (string) $f['unidad']) . ')', array_slice($r['mostrados'], 0, 10));
        $resto = (int) $r['total'] - count($lineas);
        return $this->fin($r['total'] . ' productos llegaron a su stock mínimo en ' . $r['tienda'] . ":\n" . implode("\n", $lineas)
            . ($resto > 0 ? "\n… y " . $resto . ' más.' : ''), true);
    }

    private function sinVenta(): array
    {
        $r = $this->herramientas->ejecutar('productos_sin_venta', ['dias' => 30]);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        if ((int) $r['total'] === 0) {
            return $this->fin('Todo lo que tiene stock se vendió en los últimos 30 días.', true);
        }
        $lineas = array_map(fn(array $f): string => '- ' . $f['producto'] . ': ' . $this->cantidad((float) $f['stock'], (string) $f['unidad'])
            . ' en estante, ' . ($f['ultima_venta'] === null ? 'nunca se vendió' : 'última venta ' . $this->fecha((string) $f['ultima_venta'])),
            array_slice($r['mostrados'], 0, 10));
        return $this->fin($r['total'] . ' productos con stock no se venden hace más de 30 días en ' . $r['tienda']
            . " (los de más plata parada primero):\n" . implode("\n", $lineas)
            . "\nIdea: ponlos en promoción o deja de pedirlos.", true);
    }

    private function clientes(string $desde, string $hasta): array
    {
        $r = $this->herramientas->ejecutar('mejores_clientes', ['desde' => $desde, 'hasta' => $hasta]);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        if ($r['clientes'] === []) {
            return $this->fin('Este mes todavía no hay ventas con cliente identificado (DNI o RUC).', true);
        }
        $lineas = [];
        foreach (array_slice($r['clientes'], 0, 5) as $i => $c) {
            $lineas[] = ($i + 1) . '. ' . $c['cliente'] . ': ' . $this->soles((float) $c['total']) . ' en ' . $c['compras']
                . ($c['compras'] === 1 ? ' compra' : ' compras');
        }
        return $this->fin("Los mejores clientes del mes:\n" . implode("\n", $lineas), true);
    }

    // Todo lo que pide atencion en la tienda, en un solo mensaje: lo que cada rol puede ver
    private function alertas(): array
    {
        $lineas = [];
        $r = $this->herramientas->ejecutar('productos_por_reponer', []);
        if (!isset($r['error']) && (int) $r['total'] > 0) {
            $nombres = implode(', ', array_slice(array_column($r['mostrados'], 'producto'), 0, 3));
            $lineas[] = '- Stock bajo: ' . $r['total'] . ' productos (' . $nombres . ((int) $r['total'] > 3 ? '…' : '') . ').';
        }
        if (isset($this->tiene['productos_por_vencer'])) {
            $v = $this->herramientas->ejecutar('productos_por_vencer', ['dias' => 15]);
            if (!isset($v['error']) && (int) $v['total'] > 0) {
                $lineas[] = '- Por vencer en 15 días: ' . $v['total'] . ' lotes' . ((int) $v['vencidos'] > 0 ? ' (' . $v['vencidos'] . ' ya vencidos)' : '')
                    . ', ' . $this->soles((float) $v['valor_en_riesgo']) . ' al costo.';
            }
        }
        $q = $this->herramientas->ejecutar('productos_sin_venta', ['dias' => 30]);
        if (!isset($q['error']) && (int) $q['total'] > 0) {
            $lineas[] = '- Sin venderse hace 30 días: ' . $q['total'] . ' productos con stock.';
        }
        if ($lineas === []) {
            return $this->fin('Todo en orden en ' . ($r['tienda'] ?? 'la tienda') . ': nada en stock mínimo, nada por vencer y todo se vende.', true);
        }
        $idea = isset($this->tiene['sugerencia_pedido'])
            ? 'Idea: pregunta «¿Qué pido al proveedor y cuánto?» para armar el pedido.'
            : 'Idea: avisa al almacenero para reponer lo que falta.';
        return $this->fin('Esto necesita atención en ' . ($r['tienda'] ?? 'la tienda') . ":\n" . implode("\n", $lineas) . "\n" . $idea, true);
    }

    private function pedido(): array
    {
        $r = $this->herramientas->ejecutar('sugerencia_pedido', []);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        if ((int) $r['total'] === 0) {
            return $this->fin('No hace falta pedir nada: ningún producto está en su stock mínimo.', true);
        }
        $lineas = array_map(fn(array $f): string => '- ' . $f['producto'] . ': pedir ' . $this->cantidad((float) $f['pedir'], (string) $f['unidad'])
            . ' (quedan ' . $this->cantidad((float) $f['queda'], (string) $f['unidad'])
            . ($f['alcanza_dias'] === null ? ', no se vendió en 2 semanas' : ', alcanza ' . $this->dias((int) $f['alcanza_dias'])) . ')'
            . ($f['otras_tiendas'] !== [] ? '. Le sobra a ' . $f['otras_tiendas'][0]['tienda'] . ': pide un traslado' : ''),
            array_slice($r['mostrados'], 0, 8));
        $resto = (int) $r['total'] - count($lineas);
        return $this->fin('Para que dure ' . $r['dias_que_debe_durar'] . " días, pide esto (lo que se acaba antes, primero):\n"
            . implode("\n", $lineas) . ($resto > 0 ? "\n… y " . $resto . ' más.' : '')
            . "\nIdea: con el botón de abajo se arma la orden de compra con estas cantidades.", true);
    }

    private function porVencer(): array
    {
        $r = $this->herramientas->ejecutar('productos_por_vencer', ['dias' => 30]);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        if ((int) $r['total'] === 0) {
            return $this->fin('Nada vence en los próximos 30 días en ' . $r['tienda'] . '.', true);
        }
        $lineas = array_map(fn(array $f): string => '- ' . $f['producto'] . ': ' . $this->cantidad((float) $f['queda'], (string) $f['unidad'])
            . ', ' . mb_strtolower($f['estado']) . ' (' . $this->fecha((string) $f['vence']) . ')', array_slice($r['mostrados'], 0, 8));
        $resto = (int) $r['total'] - count($lineas);
        return $this->fin($r['total'] . ' lotes vencen en 30 días o ya vencieron en ' . $r['tienda'] . ' (' . $this->soles((float) $r['valor_en_riesgo'])
            . " al costo):\n" . implode("\n", $lineas) . ($resto > 0 ? "\n… y " . $resto . ' más.' : '')
            . "\nIdea: " . ((int) $r['vencidos'] > 0 ? 'retira lo vencido (merma) y ' : '') . 'pon en promoción lo que vence primero.', true);
    }

    private function pronostico(): array
    {
        $r = $this->herramientas->ejecutar('pronostico_ventas', []);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        if (isset($r['sin_historia'])) {
            return $this->fin('Todavía no hay ventas suficientes en ' . $r['tienda'] . ' para estimar la semana.', true);
        }
        $mejor = $r['dias'][0];
        foreach ($r['dias'] as $d) {
            if ($d['estimado'] > $mejor['estimado']) {
                $mejor = $d;
            }
        }
        $lineas = array_map(fn(array $d): string => '- ' . ucfirst($d['dia']) . ' ' . $this->fecha($d['fecha']) . ': ' . $this->soles((float) $d['estimado']),
            $r['dias']);
        $tendencia = $r['tendencia'] === null ? ''
            : ($r['tendencia'] >= 0 ? "\nLas ventas vienen subiendo " : "\nLas ventas vienen bajando ") . number_format(abs((float) $r['tendencia']), 1)
              . '% en las 2 últimas semanas.';
        return $this->fin('En los próximos 7 días ' . $r['tienda'] . ' vendería unos ' . $this->soles((float) $r['total'])
            . ' (la semana pasada: ' . $this->soles((float) $r['ultimos7']) . "):\n" . implode("\n", $lineas) . $tendencia
            . "\nIdea: el " . $mejor['dia'] . ' es el día más fuerte; ten stock y caja lista. Es una estimación con las últimas '
            . $r['semanas'] . ($r['semanas'] === 1 ? ' semana.' : ' semanas.'), true);
    }

    private function horasPunta(): array
    {
        $r = $this->herramientas->ejecutar('horas_punta', []);
        if (isset($r['error'])) {
            return $this->fin((string) $r['error'], false);
        }
        if ((int) $r['comprobantes'] === 0) {
            return $this->fin('No hubo ventas en los últimos ' . $r['dias'] . ' días en ' . $r['tienda'] . ' para ver las horas punta.', true);
        }
        $horas = fn(array $filas): string => implode(', ', array_map(fn(array $h): string => $h['hora'] . ' (' . $h['comprobantes'] . ')', $filas));
        $texto = 'En los últimos ' . $r['dias'] . ' días, en ' . $r['tienda'] . ":\n- Horas punta: " . $horas($r['horas_punta']);
        if ($r['horas_tranquilas'] !== []) {
            $texto .= "\n- Horas tranquilas: " . $horas($r['horas_tranquilas']);
        }
        if ($r['dia_mas_movido'] !== null) {
            $texto .= "\n- Día más movido: " . $r['dia_mas_movido'] . ($r['dia_mas_tranquilo'] !== null ? '; el más tranquilo: ' . $r['dia_mas_tranquilo'] : '') . '.';
        }
        return $this->fin($texto . "\n(Entre paréntesis, los comprobantes.)\nIdea: pon más cajeros en las horas punta y usa las tranquilas para reponer y ordenar.", true);
    }

    private function dias(int $dias): string
    {
        return match (true) {
            $dias <= 0 => 'menos de 1 día',
            $dias === 1 => '1 día',
            default => $dias . ' días',
        };
    }

    private function buscar(string $texto): ?array
    {
        $r = $this->herramientas->ejecutar('buscar_productos', ['texto' => $texto]);
        if (isset($r['error']) || (int) ($r['encontrados'] ?? 0) === 0) {
            return null;
        }
        $lineas = [];
        foreach (array_slice($r['productos'], 0, 6) as $p) {
            $stock = implode(', ', array_map(fn(array $s): string => $s['tienda'] . ' ' . $this->cantidad((float) $s['cantidad'], (string) $p['unidad'])
                . ($s['bajo'] ? ' (bajo)' : ''), $p['stock']));
            $lineas[] = '- ' . $p['producto'] . ': ' . $this->soles((float) $p['precio']) . ($stock !== '' ? ' · stock: ' . $stock : '');
        }
        $mas = (int) $r['encontrados'] > 6 ? "\n… y " . ((int) $r['encontrados'] - 6) . ' más: escribe más palabras del nombre.' : '';
        return $this->fin(((int) $r['encontrados'] === 1 ? 'Encontré esto:' : 'Encontré ' . $r['encontrados'] . ' productos:')
            . "\n" . implode("\n", $lineas) . $mas, true);
    }

    private function abrir(string $pagina, string $texto): array
    {
        $r = $this->herramientas->ejecutar('abrir_pagina', ['pagina' => $pagina]);
        return isset($r['error']) ? $this->fin((string) $r['error'], false) : $this->fin($texto, true);
    }

    private function fecha(string $iso): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m) === 1 ? $m[3] . '/' . $m[2] . '/' . $m[1] : $iso;
    }

    /** @return array{respuesta: string, enlaces: array<int, array{texto: string, url: string}>, ok: bool} */
    private function fin(string $texto, bool $ok): array
    {
        return ['respuesta' => $texto, 'enlaces' => $this->herramientas->enlaces(), 'ok' => $ok];
    }
}
