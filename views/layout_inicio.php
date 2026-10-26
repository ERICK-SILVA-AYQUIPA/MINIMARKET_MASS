<?php
declare(strict_types=1);
/**
 * @var string $titulo
 * @var ?Usuario $usuarioActual  quien entro (null en el login y en los errores sin sesion)
 * @var ?string $csrf
 * @var ?array{tipo: string, mensaje: string} $aviso
 */
$usuarioActual ??= null;
$csrf ??= null;
$aviso ??= null;

if (!function_exists('e')) {
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
        header { background: #ffd200; padding: 12px 20px; display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; }
        header a { color: #1f2933; font-weight: 700; text-decoration: none; }
        header nav a { font-weight: 400; margin-right: 12px; }
        header .usuario { margin-left: auto; font-size: .9em; }
        header form { display: inline; }
        main { max-width: 1000px; margin: 0 auto; padding: 16px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #e3e6ea; text-align: left; vertical-align: top; }
        th { background: #2d3a4a; color: #fff; }
        td.num { text-align: right; white-space: nowrap; }
        .etiqueta { padding: 2px 8px; border-radius: 10px; font-size: .85em; }
        .ok { background: #d9f2e1; color: #1d6b36; }
        .reponer, .error { background: #fde2e1; color: #a12622; }
        .anulada { background: #e3e6ea; color: #52606d; }
        .aviso { padding: 10px 14px; border-radius: 6px; margin-bottom: 12px; }
        nav.tiendas a { margin-right: 12px; }
        .tabla { overflow-x: auto; }
        .comprobante, .panel { background: #fff; padding: 16px; margin-bottom: 16px; }
        .comprobante { max-width: 640px; }
        .comprobante h2, .panel h2 { margin-top: 0; }
        form.formulario label { display: block; margin: 8px 0 2px; font-weight: 600; }
        form.formulario input, form.formulario select, form.formulario textarea { padding: 6px; max-width: 100%; }
        fieldset { border: 1px solid #e3e6ea; margin: 12px 0; }
        button { padding: 8px 14px; background: #2d3a4a; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
        button.peligro { background: #a12622; }
        button.enlace { background: none; color: #1f2933; padding: 0; text-decoration: underline; }
        nav.paginas { margin: 12px 0; }
        nav.paginas a, nav.paginas span { margin-right: 10px; }
        form.filtros { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: end; margin: 12px 0; }
        form.filtros label { display: flex; flex-direction: column; font-size: .9em; }
        .tarjetas { display: flex; flex-wrap: wrap; gap: 12px; margin: 12px 0; }
        .tarjetas div { background: #fff; padding: 12px 16px; min-width: 150px; }
        .tarjetas strong { display: block; font-size: 1.4em; }
    </style>
</head>
<body>
<header>
    <a href="/?ruta=productos">Minimarket Mass</a>
    <?php if ($usuarioActual !== null): ?>
        <nav>
            <a href="/?ruta=productos">Catálogo</a>
            <a href="/?ruta=stock">Stock</a>
            <a href="/?ruta=ventas">Ventas</a>
            <?php if ($usuarioActual->puedeVender()): ?><a href="/?ruta=venta_nueva"><strong>Nueva venta</strong></a><?php endif; ?>
            <?php if ($usuarioActual->puedeManejarCaja() || $usuarioActual->puedeVerArqueo($usuarioActual->getIdTienda())): ?><a href="/?ruta=caja">Caja</a><?php endif; ?>
            <a href="/?ruta=traslados">Traslados</a>
            <a href="/?ruta=mermas">Mermas</a>
            <a href="/?ruta=kardex">Kardex</a>
            <a href="/?ruta=tiendas">Tiendas</a>
            <?php if ($usuarioActual->puedeVender() || $usuarioActual->puedeAdministrarTienda($usuarioActual->getIdTienda())): ?><a href="/?ruta=clientes">Clientes</a><?php endif; ?>
            <?php if ($usuarioActual->puedeAdministrarTienda($usuarioActual->getIdTienda())): ?>
                <a href="/?ruta=reportes">Reportes</a>
                <a href="/?ruta=empleados">Personal</a>
            <?php endif; ?>
            <?php if ($usuarioActual->puedeAdministrarCatalogo()): ?><a href="/?ruta=catalogo">Categorías y marcas</a><?php endif; ?>
        </nav>
        <span class="usuario">
            <?= e($usuarioActual->nombreCompleto()) ?> · <?= e($usuarioActual->getNombreRol()) ?>
            · <a href="/?ruta=mi_clave">Mi clave</a>
            <form method="post" action="/?ruta=salir"><?= campoCsrf($csrf) ?><button class="enlace" type="submit">Salir</button></form>
        </span>
    <?php endif; ?>
</header>
<main>
<?php if ($aviso !== null): ?>
    <div class="aviso <?= $aviso['tipo'] === 'ok' ? 'ok' : 'error' ?>"><?= e($aviso['mensaje']) ?></div>
<?php endif; ?>
