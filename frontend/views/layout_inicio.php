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
 * @var ?string $modoMenu  '' (fijo), 'iconos' o 'auto': como ve el menu lateral este usuario
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
// Modo del menu lateral ('' fijo, 'iconos' o 'auto'): va en <html data-menu> desde el servidor
// para que el menu no aparezca abierto y luego se pliegue.
$modoMenu ??= '';
// Sin sesion (login y errores) el fondo cambia; el login ademas va en oscuro
$claseCuerpo = $usuarioActual !== null ? '' : ($paginaEntrada ? 'acceso entrada-pagina' : 'acceso');

require_once __DIR__ . '/parciales/funciones.php';

// Menu corto: pocas secciones; cada seccion junta sus pantallas en pestanas.
// Cada rol ve solo lo que puede usar. [seccion => [texto, icono, [ruta => [texto, icono, palabras para el buscador]]]]
$secciones = [];
$seccionActual = null;
$accionesBuscador = [];
if ($usuarioActual !== null) {
    $idTiendaActual = $usuarioActual->getIdTienda();
    $ventasRutas = ['ventas' => ['Ventas', 'ventas', 'boletas comprobantes historial anular devolver']];
    if ($usuarioActual->puedeManejarCaja() || $usuarioActual->puedeVerArqueo($idTiendaActual)) { $ventasRutas['caja'] = ['Caja', 'caja', 'turno abrir cerrar arqueo efectivo gaveta']; }
    if ($usuarioActual->puedeVender() || $usuarioActual->puedeAdministrarTienda($idTiendaActual)) { $ventasRutas['clientes'] = ['Clientes', 'clientes', 'dni ruc']; }

    $productosRutas = ['productos' => ['Catálogo', 'catalogo', 'precios productos codigo'], 'stock' => ['Stock', 'stock', 'existencias cuanto hay']];
    $movimientosRutas = [];
    if ($usuarioActual->puedeVerIngresos($idTiendaActual)) {
        $productosRutas['vencimientos'] = ['Por vencer', 'vence', 'vencimiento caduca vencidos lote'];
        $productosRutas['reponer'] = ['Reponer', 'alerta', 'falta pedir bajo minimo agotado'];
        $movimientosRutas['ingresos'] = ['Ingresos', 'ingresos', 'compras proveedor llego mercaderia entrada'];
    }
    $movimientosRutas += ['traslados' => ['Traslados', 'traslados', 'enviar otra tienda mover'], 'mermas' => ['Mermas', 'mermas', 'perdida vencido roto danado robo baja']];
    if ($usuarioActual->puedeAjustarStock($idTiendaActual)) { $movimientosRutas['ajustes'] = ['Conteo', 'conteo', 'inventario contar ajuste fisico']; }
    $movimientosRutas['kardex'] = ['Kardex', 'kardex', 'historial movimientos entradas salidas'];

    $reportesRutas = [];
    $ajustesRutas = [];
    if ($usuarioActual->puedeAdministrarTienda($idTiendaActual)) {
        $reportesRutas = ['reportes' => ['Reportes', 'reportes', 'ventas por dia grafico'], 'utilidad' => ['Utilidad', 'utilidad', 'ganancia margen'], 'auditoria' => ['Auditoría', 'auditoria', 'quien hizo registro historial']];
        $ajustesRutas['empleados'] = ['Personal', 'personal', 'empleados usuarios cajeros claves'];
    }
    $ajustesRutas['tiendas'] = ['Tiendas', 'tiendas', 'locales sedes cajas direccion'];
    if ($usuarioActual->puedeAdministrarCatalogo()) { $ajustesRutas['catalogo'] = ['Categorías y marcas', 'categorias', 'marcas']; }

    $secciones = [
        'inicio'      => ['Inicio', 'inicio', ['inicio' => ['Inicio', 'inicio', 'panel resumen']]],
        'ventas'      => ['Ventas', 'ventas', $ventasRutas],
        'productos'   => ['Productos', 'catalogo', $productosRutas],
        'movimientos' => ['Movimientos', 'traslados', $movimientosRutas],
        'reportes'    => ['Reportes', 'reportes', $reportesRutas],
        'ajustes'     => ['Ajustes', 'personal', $ajustesRutas],
    ];
    $secciones = array_filter($secciones, static fn(array $s): bool => $s[2] !== []);

    // Las pantallas de detalle se marcan en la seccion de su lista
    $rutaMenu = [
        'producto' => 'productos', 'producto_form' => 'productos', 'catalogo_importar' => 'productos',
        'comprobante' => 'ventas', 'venta_nueva' => 'ventas', 'cliente' => 'clientes',
        'empleado' => 'empleados', 'tienda_editar' => 'tiendas', 'tienda_nueva' => 'tiendas',
    ][$rutaActual] ?? $rutaActual;
    foreach ($secciones as $idSeccion => [, , $rutasSeccion]) {
        if (isset($rutasSeccion[$rutaMenu])) {
            $seccionActual = $idSeccion;
        }
    }

    // Lo que se puede hacer directo desde el buscador (Ctrl K)
    if ($usuarioActual->puedeVender()) { $accionesBuscador['/?ruta=venta_nueva'] = ['Nueva venta', 'venta', 'vender cobrar boleta']; }
    if ($usuarioActual->puedeRegistrarIngreso($idTiendaActual)) { $accionesBuscador['/?ruta=ingresos#nuevo-ingreso'] = ['Registrar ingreso', 'ingresos', 'llego mercaderia compra proveedor']; }
    if ($usuarioActual->puedeMoverMercaderia()) {
        $accionesBuscador['/?ruta=mermas#nueva-merma'] = ['Registrar merma', 'mermas', 'perdida vencido roto danado robo'];
        $accionesBuscador['/?ruta=traslados#nuevo-traslado'] = ['Enviar a otra tienda', 'traslados', 'traslado mover'];
    }
    if (isset($ajustesRutas['empleados'])) { $accionesBuscador['/?ruta=empleados#nuevo-empleado'] = ['Nuevo empleado', 'personal', 'usuario cajero']; }
    $accionesBuscador['/?ruta=mi_clave'] = ['Cambiar mi clave', 'clave', 'contrasena password'];
}
?>
<!DOCTYPE html>
<html lang="es"<?php if ($usuarioActual !== null): ?> data-usuario="<?= $usuarioActual->getIdEmpleado() ?>"<?php endif; ?><?php if ($modoMenu !== ''): ?> data-menu="<?= e($modoMenu) ?>"<?php endif; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#16202b">
    <title><?= e($titulo) ?> · Minimarket Mass</title>
    <link rel="preload" href="/fuentes/outfit.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/css/estilos.css?v=11">
    <script src="/js/app.js?v=4" defer></script>
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
        <div class="tienda-actual">
            <span class="sutil">Tienda</span>
            <strong><?= e($nombreTiendaActual) ?></strong>
        </div>
        <nav aria-label="Menú principal">
            <?php foreach ($secciones as $idSeccion => [$textoSeccion, $iconoSeccion, $rutasSeccion]): ?>
                <?php
                // Una seccion con una sola pantalla toma el nombre y el icono de esa pantalla (p. ej. "Tiendas")
                $primeraRuta = array_key_first($rutasSeccion);
                if (count($rutasSeccion) === 1) { [$textoSeccion, $iconoSeccion] = $rutasSeccion[$primeraRuta]; }
                ?>
                <a href="/?ruta=<?= e($primeraRuta) ?>"<?= $idSeccion === $seccionActual ? ' class="activo" aria-current="page"' : '' ?>>
                    <svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#<?= e($iconoSeccion) ?>"></use></svg><span class="texto-menu"><?= e($textoSeccion) ?></span>
                </a>
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
    <!-- Barra de abajo en el celular: las secciones a un toque; "Más" abre el menú completo -->
    <nav class="barra-abajo" aria-label="Secciones">
        <?php foreach (array_slice($secciones, 0, 4, true) as $idSeccion => [$textoSeccion, $iconoSeccion, $rutasSeccion]): ?>
            <a href="/?ruta=<?= e(array_key_first($rutasSeccion)) ?>"<?= $idSeccion === $seccionActual ? ' aria-current="page"' : '' ?>><svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#<?= e($iconoSeccion) ?>"></use></svg><span><?= e($textoSeccion) ?></span></a>
        <?php endforeach; ?>
        <label for="menu-casilla"><?= icono('menu') ?><span>Más</span></label>
    </nav>
    <dialog class="buscador-global" id="buscador-global" aria-label="Buscar o ir a">
        <div class="buscador-campo">
            <?= icono('buscar') ?>
            <input type="search" placeholder="Escribe: merma, kardex, caja…" aria-label="Buscar una pantalla o acción" autocomplete="off" aria-controls="buscador-opciones">
            <kbd>Esc</kbd>
        </div>
        <ul id="buscador-opciones">
            <li class="buscador-grupo">Hacer</li>
            <?php foreach ($accionesBuscador as $enlace => [$textoAccion, $iconoAccion, $palabras]): ?>
                <li><a href="<?= e($enlace) ?>" data-palabras="<?= e($palabras) ?>"><span class="buscador-icono"><svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#<?= e($iconoAccion) ?>"></use></svg></span><b><?= e($textoAccion) ?></b></a></li>
            <?php endforeach; ?>
            <li class="buscador-grupo">Ir a</li>
            <?php foreach ($secciones as [$textoSeccion, , $rutasSeccion]): ?>
                <?php foreach ($rutasSeccion as $rutaOpcion => [$textoOpcion, $iconoOpcion, $palabras]): ?>
                    <li><a href="/?ruta=<?= e($rutaOpcion) ?>" data-palabras="<?= e($textoSeccion . ' ' . $palabras) ?>"><span class="buscador-icono"><svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#<?= e($iconoOpcion) ?>"></use></svg></span><b><?= e($textoOpcion) ?></b><?php if ($textoSeccion !== $textoOpcion): ?><small><?= e($textoSeccion) ?></small><?php endif; ?></a></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
        <p class="buscador-vacio" hidden>No encontré nada. Prueba con otra palabra.</p>
        <div class="buscador-ayuda"><span><kbd>↑</kbd> <kbd>↓</kbd> elegir</span><span><kbd>Enter</kbd> ir</span><span><kbd>Esc</kbd> cerrar</span></div>
    </dialog>
    <div class="cuerpo">
        <div class="arriba">
            <!-- Buscador de todo el sistema (Ctrl K). Lo activa app.js; sin JavaScript queda el menu. -->
            <button type="button" class="buscar-global" data-buscador-global aria-haspopup="dialog" hidden>
                <?= icono('buscar') ?><span>Buscar o ir a…</span><kbd>Ctrl K</kbd>
            </button>
            <?php if ($usuarioActual->puedeVender()): ?>
                <a class="boton-vender" href="/?ruta=venta_nueva"<?= $rutaActual === 'venta_nueva' ? ' aria-current="page"' : '' ?>><?= icono('venta') ?>Vender</a>
            <?php endif; ?>
        </div>
<?php else: ?>
<div class="app">
    <div class="cuerpo">
<?php endif; ?>
<main id="contenido" tabindex="-1" class="<?= e($anchoMain) ?>">
<?php if ($seccionActual !== null && count($secciones[$seccionActual][2]) > 1): ?>
    <nav class="pestanas-seccion" aria-label="<?= e($secciones[$seccionActual][0]) ?>">
        <?php foreach ($secciones[$seccionActual][2] as $rutaPestana => [$textoPestana]): ?>
            <a href="/?ruta=<?= e($rutaPestana) ?>"<?= $rutaPestana === $rutaMenu ? ' aria-current="page"' : '' ?>><?= e($textoPestana) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
<?php if ($aviso !== null): ?>
    <div class="aviso <?= $aviso['tipo'] === 'ok' ? 'ok' : 'error' ?>" role="status" data-aviso><?= e($aviso['mensaje']) ?></div>
<?php endif; ?>
