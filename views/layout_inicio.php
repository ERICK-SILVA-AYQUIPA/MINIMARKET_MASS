<?php
declare(strict_types=1);
/**
 * @var string $titulo
 * @var ?Usuario $usuarioActual  quien entro (null en el login y en los errores sin sesion)
 * @var ?string $csrf
 * @var ?array{tipo: string, mensaje: string} $aviso
 * @var ?string $rutaActual  la ruta pedida, para resaltar el menu
 * @var ?string $anchoMain   'angosto' para formularios cortos
 * @var ?string $nombreTiendaActual  la tienda del usuario (la pone Controller::vista)
 * @var ?bool $paginaEntrada  true solo en el login: fondo oscuro
 * @var ?bool $bienvenida  true en la primera pagina despues de entrar: logo con barra de carga
 */
$usuarioActual ??= null;
$csrf ??= null;
$aviso ??= null;
$rutaActual ??= 'inicio'; // la opcion del menu que se resalta (solo se compara, no se imprime)
$anchoMain ??= '';
$nombreTiendaActual ??= '';
$paginaEntrada ??= false;
$bienvenida ??= false;
// Sin sesion (login y errores) el fondo cambia; el login ademas va en oscuro
$claseCuerpo = $usuarioActual !== null ? '' : ($paginaEntrada ? 'acceso entrada-pagina' : 'acceso');

require_once __DIR__ . '/parciales/funciones.php';

// Menu agrupado: cada rol ve solo lo que puede usar. [ruta => [texto, icono]]
$gruposMenu = [];
if ($usuarioActual !== null) {
    $idTiendaActual = $usuarioActual->getIdTienda();
    $ventasMenu = [];
    if ($usuarioActual->puedeVender()) { $ventasMenu['venta_nueva'] = ['Nueva venta', 'venta']; }
    if ($usuarioActual->puedeManejarCaja() || $usuarioActual->puedeVerArqueo($idTiendaActual)) { $ventasMenu['caja'] = ['Caja', 'caja']; }
    $ventasMenu['ventas'] = ['Ventas', 'ventas'];
    if ($usuarioActual->puedeVender() || $usuarioActual->puedeAdministrarTienda($idTiendaActual)) { $ventasMenu['clientes'] = ['Clientes', 'clientes']; }

    $inventarioMenu = ['productos' => ['Catálogo', 'catalogo'], 'stock' => ['Stock', 'stock']];
    if ($usuarioActual->puedeVerIngresos($idTiendaActual)) {
        $inventarioMenu['ingresos'] = ['Ingresos', 'ingresos'];
        $inventarioMenu['vencimientos'] = ['Por vencer', 'vence'];
    }
    $inventarioMenu += ['traslados' => ['Traslados', 'traslados'], 'mermas' => ['Mermas', 'mermas']];
    if ($usuarioActual->puedeAjustarStock($idTiendaActual)) { $inventarioMenu['ajustes'] = ['Conteo', 'conteo']; }
    $inventarioMenu['kardex'] = ['Kardex', 'kardex'];

    $adminMenu = [];
    if ($usuarioActual->puedeAdministrarTienda($idTiendaActual)) {
        $adminMenu += ['reportes' => ['Reportes', 'reportes'], 'empleados' => ['Personal', 'personal'], 'auditoria' => ['Auditoría', 'auditoria']];
    }
    if ($usuarioActual->puedeAdministrarCatalogo()) { $adminMenu['catalogo'] = ['Categorías y marcas', 'categorias']; }
    $adminMenu['tiendas'] = ['Tiendas', 'tiendas'];

    $gruposMenu = [
        ''               => ['inicio' => ['Inicio', 'inicio']],
        'Ventas'         => $ventasMenu,
        'Inventario'     => $inventarioMenu,
        'Administración' => $adminMenu,
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#16202b">
    <title><?= e($titulo) ?> · Minimarket Mass</title>
    <link rel="preload" href="/fuentes/outfit.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/estilos.css?v=7">
    <script src="/app.js?v=1" defer></script>
</head>
<body class="<?= e($claseCuerpo) ?>">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<?php if ($bienvenida && $usuarioActual !== null): ?>
<div class="entrada-splash" aria-hidden="true">
    <div class="splash-marca"><span class="logo">M</span><span>Minimarket <b>Mass</b></span></div>
    <div class="splash-lema">Bienvenido, <?= e($usuarioActual->getNombres()) ?> · <?= e($nombreTiendaActual) ?></div>
    <div class="splash-barra"><span></span></div>
</div>
<?php endif; ?>
<?php if ($usuarioActual !== null): ?>
<input type="checkbox" id="menu-casilla" class="menu-casilla" aria-controls="menu-lateral" aria-label="Abrir el menú">
<div class="barra-movil">
    <label for="menu-casilla"><?= icono('menu') ?> Menú</label>
    <a class="marca" href="/?ruta=inicio"><span class="logo" aria-hidden="true">M</span> Mass</a>
</div>
<div class="app">
    <label class="menu-fondo" for="menu-casilla" aria-hidden="true"></label>
    <aside class="lateral" id="menu-lateral">
        <a class="marca" href="/?ruta=inicio"><span class="logo" aria-hidden="true">M</span> Minimarket Mass</a>
        <div class="tienda-actual">
            <span class="sutil">Tienda</span>
            <strong><?= e($nombreTiendaActual) ?></strong>
        </div>
        <nav aria-label="Menú principal">
            <?php foreach ($gruposMenu as $grupo => $opciones): ?>
                <?php if ($opciones === []) { continue; } ?>
                <?php if ($grupo !== ''): ?><div class="grupo"><?= e($grupo) ?></div><?php endif; ?>
                <?php foreach ($opciones as $rutaMenu => [$textoMenu, $iconoMenu]): ?>
                    <a href="/?ruta=<?= e($rutaMenu) ?>"<?= $rutaMenu === $rutaActual ? ' class="activo" aria-current="page"' : '' ?>>
                        <svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#<?= e($iconoMenu) ?>"></use></svg><?= e($textoMenu) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="usuario">
            <strong><?= e($usuarioActual->nombreCompleto()) ?></strong>
            <?= e(ucfirst(strtolower($usuarioActual->getNombreRol()))) ?>
            <div class="acciones-usuario">
                <a href="/?ruta=mi_clave"><?= icono('clave') ?>Mi clave</a>
                <form method="post" action="/?ruta=salir"><?= campoCsrf($csrf) ?><button class="enlace" type="submit"><?= icono('salir') ?>Salir</button></form>
            </div>
        </div>
    </aside>
    <div class="cuerpo">
<?php else: ?>
<div class="app">
    <div class="cuerpo">
<?php endif; ?>
<main id="contenido" tabindex="-1" class="<?= e($anchoMain) ?>">
<?php if ($aviso !== null): ?>
    <div class="aviso <?= $aviso['tipo'] === 'ok' ? 'ok' : 'error' ?>" role="status" data-aviso><?= e($aviso['mensaje']) ?></div>
<?php endif; ?>
