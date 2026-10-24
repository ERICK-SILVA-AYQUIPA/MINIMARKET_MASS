<?php
declare(strict_types=1);
/** @var string $titulo */

// Escapa todo lo que se imprime en el HTML
function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function soles(float $monto): string
{
    return 'S/ ' . number_format($monto, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Minimarket Mass</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #1f2933; background: #f5f6f8; }
        header { background: #ffd200; padding: 12px 20px; }
        header a { color: #1f2933; font-weight: 700; text-decoration: none; }
        main { max-width: 960px; margin: 0 auto; padding: 16px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #e3e6ea; text-align: left; }
        th { background: #2d3a4a; color: #fff; }
        td.num { text-align: right; white-space: nowrap; }
        .etiqueta { padding: 2px 8px; border-radius: 10px; font-size: .85em; }
        .ok { background: #d9f2e1; color: #1d6b36; }
        .reponer { background: #fde2e1; color: #a12622; }
        nav.tiendas a { margin-right: 12px; }
        .tabla { overflow-x: auto; }
    </style>
</head>
<body>
<header><a href="/?ruta=productos">Minimarket Mass</a></header>
<main>
