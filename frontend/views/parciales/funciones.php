<?php
declare(strict_types=1);

/**
 * Funciones que usan todas las vistas. Se cargan una sola vez (require_once).
 */
require_once __DIR__ . '/../../../backend/models/Cantidad.php';
require_once __DIR__ . '/../../../backend/models/CodigoQr.php';

// Escapa todo lo que se imprime en el HTML
function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

// El QR de una etiqueta como <svg>: solo trae rectangulos y el titulo pasa por htmlspecialchars (CodigoQr::svg)
function qr(string $texto, string $titulo = ''): string
{
    return CodigoQr::svg($texto, $titulo);
}

function soles(float $monto): string
{
    return 'S/ ' . number_format($monto, 2);
}

// Fecha de MySQL -> como se lee en Peru: "2026-10-19 08:00:00" -> "19/10/2026 08:00"; "2026-10-19" -> "19/10/2026"
function fecha(?string $valor): string
{
    if ($valor === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}:\d{2}))?/', $valor, $m) !== 1) {
        return $valor ?? '—';
    }
    return $m[3] . '/' . $m[2] . '/' . $m[1] . (isset($m[4]) ? ' ' . $m[4] : '');
}

// Fecha de MySQL -> cuanto paso, para listas de lo reciente: "hace 5 min", "hace 3 h", "ayer 14:20", "hace 4 días";
// mas de una semana: la fecha. $ahora se pasa en las pruebas.
function hace(?string $valor, ?DateTimeImmutable $ahora = null): string
{
    if ($valor === null || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $valor) !== 1) {
        return fecha($valor);
    }
    $ahora ??= new DateTimeImmutable();
    $momento = new DateTimeImmutable($valor);
    $segundos = $ahora->getTimestamp() - $momento->getTimestamp();
    $dias = (int) $momento->setTime(0, 0)->diff($ahora->setTime(0, 0))->format('%r%a');
    return match (true) {
        $segundos < 0      => fecha($valor),
        $segundos < 60     => 'hace un momento',
        $segundos < 3600   => 'hace ' . intdiv($segundos, 60) . ' min',
        $dias === 0        => 'hace ' . intdiv($segundos, 3600) . ' h',
        $dias === 1        => 'ayer ' . $momento->format('H:i'),
        $dias < 7          => 'hace ' . $dias . ' días',
        default            => fecha($valor),
    };
}

// Milesimas -> texto: "1.250" si se vende por kg, "12" si es por unidad
function cantidad(int $milesimas, bool $alPeso): string
{
    return $alPeso ? Cantidad::aTexto($milesimas) : (string) intdiv($milesimas, 1000);
}

// Campo oculto con el token CSRF: va en TODOS los formularios POST
function campoCsrf(?string $csrf): string
{
    return '<input type="hidden" name="csrf" value="' . e((string) $csrf) . '">';
}

// Icono del archivo public/iconos.svg (decorativo: los lectores de pantalla lo saltan)
function icono(string $nombre): string
{
    return '<svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#' . e($nombre) . '"></use></svg>';
}
