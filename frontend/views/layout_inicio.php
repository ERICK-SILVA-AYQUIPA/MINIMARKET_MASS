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
 * @var ?array<int, Tienda> $tiendasParaElegir  las sucursales del selector (solo el administrador; la pone Controller::vista)
 * @var ?bool $paginaEntrada  true solo en el login: fondo oscuro
 * @var ?string $modoMenu  '' (fijo), 'iconos' o 'auto': como ve el menu lateral este usuario
 * @var ?bool $bienvenida  true en la primera pagina despues de entrar: logo con barra de carga
 */
$usuarioActual ??= null;
$csrf ??= null;
$aviso ??= null;
$rutaActual ??= 'inicio'; // la opcion del menu que se resalta (solo se compara, no se imprime)
$anchoMain ??= '';
$nombreTiendaActual ??= '';
$tiendasParaElegir ??= [];
$paginaEntrada ??= false;
$bienvenida ??= false;
// Modo del menu lateral ('' fijo, 'iconos' o 'auto'): va en <html data-menu> desde el servidor
// para que el menu no aparezca abierto y luego se pliegue.
$modoMenu ??= '';
// Sin sesion (login y errores) el fondo cambia; el login ademas va en oscuro
$claseCuerpo = $usuarioActual !== null ? '' : ($paginaEntrada ? 'acceso entrada-pagina' : 'acceso');

require_once __DIR__ . '/parciales/funciones.php';

// Menu agrupado: cada rol ve solo lo que puede usar. [ruta => [texto, icono]]
$gruposMenu = [];
$pestanasActuales = [];
$opcionActual = $rutaActual;
$rutaMarcada = $rutaActual;
if ($usuarioActual !== null) {
    $idTiendaActual = $usuarioActual->getIdTienda();
    $ventasMenu = [];
    if ($usuarioActual->puedeVender()) { $ventasMenu['venta_nueva'] = ['Nueva venta', 'venta']; }
    if ($usuarioActual->puedeManejarCaja() || $usuarioActual->puedeVerArqueo($idTiendaActual)) { $ventasMenu['caja'] = ['Caja', 'caja']; }
    $ventasMenu['ventas'] = ['Ventas', 'ventas'];
    if ($usuarioActual->puedeVender() || $usuarioActual->puedeAdministrarTienda($idTiendaActual)) { $ventasMenu['clientes'] = ['Clientes', 'clientes']; }
    if ($usuarioActual->puedeVender() || $usuarioActual->puedeAdministrarPromociones($idTiendaActual)) { $ventasMenu['promociones'] = ['Promociones', 'oferta']; }

    // Inventario en dos opciones; sus pantallas salen como pestañas arriba del contenido. [ruta => [texto, icono]]
    $productosPestanas = ['productos' => ['Catálogo', 'catalogo'], 'stock' => ['Stock', 'stock']];
    $movimientosPestanas = [];
    if ($usuarioActual->puedeVerIngresos($idTiendaActual)) {
        $productosPestanas += ['vencimientos' => ['Por vencer', 'vence'], 'reponer' => ['Reponer', 'alerta']];
        $movimientosPestanas['ingresos'] = ['Ingresos', 'ingresos'];
    }
    $movimientosPestanas += ['traslados' => ['Traslados', 'traslados'], 'mermas' => ['Mermas', 'mermas']];
    if ($usuarioActual->puedeAjustarStock($idTiendaActual)) { $movimientosPestanas['ajustes'] = ['Conteo', 'conteo']; }
    $movimientosPestanas['kardex'] = ['Kardex', 'kardex'];
    $primeraMovimientos = array_key_first($movimientosPestanas);
    $inventarioMenu = ['productos' => ['Productos', 'catalogo'], $primeraMovimientos => ['Movimientos', 'traslados']];
    // [opcion del menu => [nombre del grupo de pestañas, sus pestañas]]
    $pestanasMenu = ['productos' => ['Productos', $productosPestanas], $primeraMovimientos => ['Movimientos', $movimientosPestanas]];
    // Numeros de aviso: en la pestaña y, sumados, en la opcion Productos del menu
    $alertasInventario ??= [];
    $alertasMenu = ['productos' => array_sum($alertasInventario)];
    $avisoPestana = ['vencimientos' => 'lotes vencen en 7 días o ya vencieron', 'reponer' => 'productos llegaron a su stock mínimo'];

    $adminMenu = [];
    if ($usuarioActual->puedeAdministrarTienda($idTiendaActual)) {
        $adminMenu += ['reportes' => ['Reportes', 'reportes'], 'utilidad' => ['Utilidad', 'utilidad'], 'empleados' => ['Personal', 'personal'], 'auditoria' => ['Auditoría', 'auditoria']];
    }
    if ($usuarioActual->puedeAdministrarCatalogo()) { $adminMenu['catalogo'] = ['Categorías y marcas', 'categorias']; }
    $adminMenu['tiendas'] = ['Tiendas', 'tiendas'];

    // La ficha, el formulario y la importacion de productos marcan la pestaña Catálogo
    $rutaMarcada = in_array($rutaActual, ['producto', 'producto_form', 'catalogo_importar'], true) ? 'productos' : $rutaActual;
    foreach ($pestanasMenu as $opcionPestanas => $pestanas) {
        if (isset($pestanas[1][$rutaMarcada])) {
            $pestanasActuales = $pestanas;
            $opcionActual = $opcionPestanas;
        }
    }

    $gruposMenu = [
        ''               => ['inicio' => ['Inicio', 'inicio']],
        'Ventas'         => $ventasMenu,
        'Inventario'     => $inventarioMenu,
        'Administración' => $adminMenu,
    ];

    // Barra de abajo del celular (solo quien maneja la caja): Inicio, Caja, Vender al centro, Ventas y Menú
    $barraInferior = [];
    if (isset($ventasMenu['caja'])) {
        $barraInferior = ['inicio' => ['Inicio', 'inicio'], 'caja' => ['Caja', 'caja']]
            + (isset($ventasMenu['venta_nueva']) ? ['venta_nueva' => ['Vender', 'venta']] : [])
            + ['ventas' => ['Ventas', 'ventas']];
    }
    // Al cambiar de sucursal se vuelve a la misma opcion del menu (una ficha con id puede no ser de la otra tienda)
    $volverTienda = 'inicio';
    foreach ($gruposMenu as $opcionesGrupo) {
        if (isset($opcionesGrupo[$opcionActual])) {
            $volverTienda = $opcionActual;
        }
    }
    if ($barraInferior !== []) {
        $claseCuerpo = trim($claseCuerpo . ' con-barra-inferior');
    }
}
?>
<!DOCTYPE html>
<html lang="es"<?php if ($usuarioActual !== null): ?> data-usuario="<?= $usuarioActual->getIdEmpleado() ?>"<?php endif; ?><?php if ($modoMenu !== ''): ?> data-menu="<?= e($modoMenu) ?>"<?php endif; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title><?= e($titulo) ?> · Minimarket Mass</title>
    <link rel="preload" href="/fuentes/inter.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fuentes/jetbrains-mono.woff2" as="font" type="font/woff2" crossorigin>
    <!-- tokens.css: colores, espacios 4/8, radios, sombras, vidrio, letras y curvas. estilos.css solo los usa. -->
    <link rel="stylesheet" href="/css/tokens.css?v=1">
    <link rel="stylesheet" href="/css/estilos.css?v=21">
    <script src="/js/app.js?v=11" defer></script>
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
        <div class="lateral-cabeza">
            <a class="marca" href="/?ruta=inicio"><span class="logo" aria-hidden="true">M</span><span class="texto-menu">Minimarket Mass</span></a>
            <!-- Plegar el menu a solo iconos (lo activa app.js; sin JavaScript el menu queda fijo) -->
            <button type="button" class="menu-plegar" data-menu-plegar aria-controls="menu-lateral" hidden>
                <?= icono('plegar') ?><span class="texto-menu">Ocultar menú</span>
            </button>
        </div>
        <?php if ($tiendasParaElegir !== []): ?>
        <!-- El administrador elige la sucursal; app.js envia el formulario al cambiar (sin JavaScript, con el boton) -->
        <form class="tienda-actual tienda-elegir" method="post" action="/?ruta=tienda_elegir" data-elegir-tienda>
            <?= campoCsrf($csrf) ?>
            <input type="hidden" name="volver" value="<?= e($volverTienda) ?>">
            <label class="sutil" for="tienda-elegida">Sucursal</label>
            <select id="tienda-elegida" name="tienda">
                <?php foreach ($tiendasParaElegir as $idOpcion => $tiendaOpcion): ?>
                    <option value="<?= (int) $idOpcion ?>"<?= $idOpcion === $usuarioActual->getIdTienda() ? ' selected' : '' ?>><?= e($tiendaOpcion->getNombreTienda()) ?><?= $tiendaOpcion->estaActiva() ? '' : ' (inactiva)' ?><?= $idOpcion === $usuarioActual->getIdTiendaPropia() ? ' · la tuya' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="boton-chico" data-elegir-tienda-boton>Cambiar</button>
        </form>
        <?php else: ?>
        <div class="tienda-actual">
            <span class="sutil">Tienda</span>
            <strong><?= e($nombreTiendaActual) ?></strong>
        </div>
        <?php endif; ?>
        <nav aria-label="Menú principal">
            <?php foreach ($gruposMenu as $grupo => $opciones): ?>
                <?php if ($opciones === []) { continue; } ?>
                <?php if ($grupo !== ''): ?><div class="grupo"><?= e($grupo) ?></div><?php endif; ?>
                <?php foreach ($opciones as $rutaMenu => [$textoMenu, $iconoMenu]): ?>
                    <?php $cuentaMenu = (int) ($alertasMenu[$rutaMenu] ?? 0); ?>
                    <a href="/?ruta=<?= e($rutaMenu) ?>"<?= $rutaMenu === $opcionActual ? ' class="activo" aria-current="page"' : '' ?><?php if (isset($pestanasMenu[$rutaMenu])): ?> data-pestanas="<?= e($pestanasMenu[$rutaMenu][0]) ?>"<?php endif; ?>>
                        <svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#<?= e($iconoMenu) ?>"></use></svg><span class="texto-menu"><?= e($textoMenu) ?></span>
                        <?php if ($cuentaMenu > 0): ?><span class="menu-cuenta" title="<?= e($cuentaMenu . ' avisos: por vencer y por reponer') ?>"><?= (int) $cuentaMenu ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="usuario">
            <div class="usuario-datos">
                <strong><?= e($usuarioActual->nombreCompleto()) ?></strong>
                <?= e(ucfirst(strtolower($usuarioActual->getNombreRol()))) ?>
            </div>
            <fieldset class="modo-menu" data-modo-menu hidden>
                <legend>Menú lateral</legend>
                <label><input type="radio" name="modo-menu" value="fijo"><span>Fijo</span></label>
                <label><input type="radio" name="modo-menu" value="iconos"><span>Íconos</span></label>
                <label><input type="radio" name="modo-menu" value="auto"><span>Auto</span></label>
            </fieldset>
            <div class="acciones-usuario">
                <a href="/?ruta=mi_clave"><?= icono('clave') ?><span class="texto-menu">Mi clave</span></a>
                <form method="post" action="/?ruta=salir"><?= campoCsrf($csrf) ?><button class="enlace" type="submit"><?= icono('salir') ?><span class="texto-menu">Salir</span></button></form>
            </div>
        </div>
    </aside>
    <div class="cuerpo">
<?php else: ?>
<div class="app">
    <div class="cuerpo">
<?php endif; ?>
<main id="contenido" tabindex="-1" class="<?= e($anchoMain) ?>">
<?php if ($pestanasActuales !== []): ?>
    <nav class="pestanas-seccion" aria-label="<?= e($pestanasActuales[0]) ?>" data-pestanas="<?= e($pestanasActuales[0]) ?>">
        <span class="pestanas-rotulo" aria-hidden="true">Inventario<span>/</span><?= e($pestanasActuales[0]) ?></span>
        <div class="pestanas-lista">
            <?php foreach ($pestanasActuales[1] as $rutaPestana => [$textoPestana, $iconoPestana]): ?>
                <?php $cuentaPestana = (int) ($alertasInventario[$rutaPestana] ?? 0); ?>
                <a href="/?ruta=<?= e($rutaPestana) ?>"<?= $rutaPestana === $rutaMarcada ? ' aria-current="page"' : '' ?>>
                    <svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#<?= e($iconoPestana) ?>"></use></svg><?= e($textoPestana) ?>
                    <?php if ($cuentaPestana > 0): ?><span class="pestana-cuenta <?= e($rutaPestana) ?>" title="<?= e($cuentaPestana . ' ' . $avisoPestana[$rutaPestana]) ?>"><?= (int) $cuentaPestana ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
<?php endif; ?>
<?php if ($aviso !== null): ?>
    <div class="aviso <?= e(in_array($aviso['tipo'], ['ok', 'alerta'], true) ? $aviso['tipo'] : 'error') ?>" role="status" data-aviso><?= e($aviso['mensaje']) ?></div>
<?php endif; ?>
