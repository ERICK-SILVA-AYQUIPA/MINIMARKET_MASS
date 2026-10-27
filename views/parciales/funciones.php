<?php
declare(strict_types=1);

/**
 * Funciones que usan todas las vistas. Se cargan una sola vez (require_once).
 */
// Escapa todo lo que se imprime en el HTML
function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function soles(float $monto): string
{
    return 'S/ ' . number_format($monto, 2);
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
