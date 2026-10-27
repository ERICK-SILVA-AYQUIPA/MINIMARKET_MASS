<?php
declare(strict_types=1);
/**
 * @var string $titulo
 * @var ?Usuario $usuarioActual  quien entro (null en el login y en los errores sin sesion)
 * @var ?string $csrf
 * @var ?array{tipo: string, mensaje: string} $aviso
 * @var ?string $rutaActual  la ruta pedida, para resaltar el menu
 */
$usuarioActual ??= null;
$csrf ??= null;
$aviso ??= null;
$rutaActual ??= 'inicio'; // la opcion del menu que se resalta (solo se compara, no se imprime)

require_once __DIR__ . '/parciales/funciones.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Minimarket Mass</title>
    <style>
        :root { --marca: #ffd200; --tinta: #1f2933; --oscuro: #2d3a4a; --suave: #52606d; --borde: #e3e6ea;
                --fondo: #f5f6f8; --verde: #1d6b36; --rojo: #a12622; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; margin: 0; color: var(--tinta); background: var(--fondo);
               line-height: 1.45; min-height: 100vh; display: flex; flex-direction: column; }
        a { color: #1a4f8b; }
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
            outline: 3px solid #1a73e8; outline-offset: 2px; }
        header { background: var(--marca); padding: 10px 20px; display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center;
                 box-shadow: 0 1px 3px rgba(0,0,0,.15); }
        header a { color: var(--tinta); text-decoration: none; }
        header .marca { font-weight: 800; font-size: 1.15em; }
        header nav { display: flex; flex-wrap: wrap; gap: 2px; }
        header nav a { padding: 4px 9px; border-radius: 4px; }
        header nav a:hover { background: rgba(0,0,0,.08); }
        header nav a.activo { background: var(--oscuro); color: #fff; }
        header .usuario { margin-left: auto; font-size: .9em; }
        header form { display: inline; }
        main { width: 100%; max-width: 1100px; margin: 0 auto; padding: 16px; flex: 1; }
        h1 { margin: 8px 0 4px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 8px 10px; border-bottom: 1px solid var(--borde); text-align: left; vertical-align: top; }
        th { background: var(--oscuro); color: #fff; font-weight: 600; }
        tr:nth-child(even) td { background: #fafbfc; }
        tr:hover td { background: #fff8d6; }
        td.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .etiqueta { padding: 2px 8px; border-radius: 10px; font-size: .85em; white-space: nowrap; }
        .ok { background: #d9f2e1; color: var(--verde); }
        .reponer, .error { background: #fde2e1; color: var(--rojo); }
        .anulada { background: var(--borde); color: var(--suave); }
        .texto-ok { color: var(--verde); }
        .texto-error { color: var(--rojo); }
        .sutil { color: var(--suave); margin-top: 0; }
        .aviso { padding: 10px 14px; border-radius: 6px; margin-bottom: 12px; }
        nav.tiendas a { margin-right: 12px; }
        .tabla { overflow-x: auto; }
        .comprobante, .panel { background: #fff; padding: 16px; margin-bottom: 16px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
        .comprobante { max-width: 640px; }
        .comprobante h2, .panel h2 { margin-top: 0; font-size: 1.15em; }
        .panel.destacado { border-left: 5px solid var(--marca); }
        .rejilla { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(320px, 100%), 1fr)); gap: 16px; }
        form.formulario label { display: block; margin: 8px 0 2px; font-weight: 600; }
        input, select, textarea { padding: 6px 8px; max-width: 100%; border: 1px solid #c3c9d0; border-radius: 4px; font: inherit; }
        fieldset { border: 1px solid var(--borde); margin: 12px 0; border-radius: 6px; }
        button, a.boton { display: inline-block; padding: 8px 14px; background: var(--oscuro); color: #fff; border: 0; border-radius: 4px;
                          cursor: pointer; font: inherit; text-decoration: none; }
        button:hover, a.boton:hover { filter: brightness(1.2); }
        button.peligro { background: var(--rojo); }
        button.enlace { background: none; color: var(--tinta); padding: 0; text-decoration: underline; }
        nav.paginas { margin: 12px 0; }
        nav.paginas a, nav.paginas span { margin-right: 10px; }
        form.filtros { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: end; margin: 12px 0; }
        form.filtros label { display: flex; flex-direction: column; font-size: .9em; }
        .tarjetas { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin: 12px 0 16px; }
        .tarjetas > div { background: #fff; padding: 12px 16px; border-radius: 8px; border-top: 4px solid var(--marca);
                          box-shadow: 0 1px 2px rgba(0,0,0,.06); }
        .tarjetas span { color: var(--suave); font-size: .9em; }
        .tarjetas strong { display: block; font-size: 1.5em; }
        .tarjetas small { color: var(--suave); }
        .grafico { display: flex; align-items: flex-end; gap: 4px; height: 180px; padding-top: 8px; border-bottom: 1px solid var(--borde); }
        .grafico .barra { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; }
        .grafico .barra span { display: block; width: 100%; min-height: 2px; background: var(--oscuro); border-radius: 3px 3px 0 0; }
        .grafico .barra:hover span { background: #e0b800; }
        .grafico .barra small { font-size: .7em; color: var(--suave); margin-top: 2px; }
        footer { text-align: center; color: var(--suave); font-size: .85em; padding: 16px; border-top: 1px solid var(--borde); }
        @media (max-width: 640px) {
            header { padding: 8px 12px; }
            header .usuario { margin-left: 0; width: 100%; }
            main { padding: 12px; }
            th, td { padding: 6px; }
        }
        @media print {
            header, footer, nav, form, .boton { display: none !important; }
            body { background: #fff; }
            .panel, .comprobante { box-shadow: none; }
        }
    </style>
</head>
<body>
<header>
    <a class="marca" href="/?ruta=inicio">Minimarket Mass</a>
    <?php if ($usuarioActual !== null): ?>
        <?php
        $idTiendaActual = $usuarioActual->getIdTienda();
        $menu = ['inicio' => 'Inicio', 'productos' => 'Catálogo', 'stock' => 'Stock', 'ventas' => 'Ventas'];
        if ($usuarioActual->puedeVender()) { $menu['venta_nueva'] = 'Nueva venta'; }
        if ($usuarioActual->puedeManejarCaja() || $usuarioActual->puedeVerArqueo($idTiendaActual)) { $menu['caja'] = 'Caja'; }
        if ($usuarioActual->puedeVerIngresos($idTiendaActual)) { $menu['ingresos'] = 'Ingresos'; }
        $menu += ['traslados' => 'Traslados', 'mermas' => 'Mermas'];
        if ($usuarioActual->puedeAjustarStock($idTiendaActual)) { $menu['ajustes'] = 'Conteo'; }
        $menu += ['kardex' => 'Kardex', 'tiendas' => 'Tiendas'];
        if ($usuarioActual->puedeVender() || $usuarioActual->puedeAdministrarTienda($idTiendaActual)) { $menu['clientes'] = 'Clientes'; }
        if ($usuarioActual->puedeAdministrarTienda($idTiendaActual)) { $menu += ['reportes' => 'Reportes', 'empleados' => 'Personal', 'auditoria' => 'Auditoría']; }
        if ($usuarioActual->puedeAdministrarCatalogo()) { $menu['catalogo'] = 'Categorías y marcas'; }
        ?>
        <nav aria-label="Menú principal">
            <?php foreach ($menu as $rutaMenu => $textoMenu): ?>
                <a href="/?ruta=<?= e($rutaMenu) ?>"<?= $rutaMenu === $rutaActual ? ' class="activo" aria-current="page"' : '' ?>><?= e($textoMenu) ?></a>
            <?php endforeach; ?>
        </nav>
        <span class="usuario">
            <?= e($usuarioActual->nombreCompleto()) ?> · <?= e($usuarioActual->getNombreRol()) ?>
            · <a href="/?ruta=mi_clave">Mi clave</a>
            · <form method="post" action="/?ruta=salir"><?= campoCsrf($csrf) ?><button class="enlace" type="submit">Salir</button></form>
        </span>
    <?php endif; ?>
</header>
<main>
<?php if ($aviso !== null): ?>
    <div class="aviso <?= $aviso['tipo'] === 'ok' ? 'ok' : 'error' ?>"><?= e($aviso['mensaje']) ?></div>
<?php endif; ?>
