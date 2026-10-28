<?php
declare(strict_types=1);

/**
 * Funciones que usan todas las vistas. Se cargan una sola vez (require_once).
 */
require_once __DIR__ . '/../../models/Cantidad.php';

// Escapa todo lo que se imprime en el HTML
function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function soles(float $monto): string
{
    return 'S/ ' . number_format($monto, 2);
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
