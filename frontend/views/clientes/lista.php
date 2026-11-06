<?php
declare(strict_types=1);
/**
 * V: clientes con sus compras. Se busca por el inicio del DNI o del RUC, o por parte del nombre.
 * Al tocar un cliente se abre su ficha en el panel lateral (&ver=ID).
 * "Registrar cliente" abre el formulario de alta en otro panel (#nuevo-cliente).
 * @var ?string $documento
 * @var array<int, array{cliente: Cliente, compras: int, total: float}> $filas
 * @var Paginacion $paginacion
 * @var string $urlPagina
 * @var ?array{cliente: Cliente, compras: array} $ficha
 * @var array $viejo      lo escrito en el alta, si hubo un error
 * @var ?string $error    error del alta
 */
$titulo = 'Clientes';
require __DIR__ . '/../layout_inicio.php';
$viejo ??= [];
$error ??= null;
$viejoTexto = static fn(string $campo): string => is_string($viejo[$campo] ?? null) ? $viejo[$campo] : '';
?>
<div class="cabecera">
    <div>
        <h1>Clientes</h1>
        <p class="sutil"><?= $paginacion->getTotalFilas() ?> clientes con DNI o RUC. Las ventas sin documento salen como CLIENTE VARIOS y no aparecen aquí.</p>
    </div>
    <div class="acciones"><a class="boton acento" href="#nuevo-cliente"><?= icono('mas') ?>Registrar cliente</a></div>
</div>
<form class="filtros" method="get" action="/" role="search">
    <input type="hidden" name="ruta" value="clientes">
    <label>Nombre, DNI o RUC (o sus primeros dígitos)
        <input name="documento" maxlength="60" value="<?= e($documento ?? '') ?>">
    </label>
    <button type="submit"><?= icono('buscar') ?>Buscar</button>
    <?php if ($documento !== null): ?><a class="boton secundario" href="/?ruta=clientes">Ver todos</a><?php endif; ?>
</form>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = $documento !== null ? 'Ningún cliente coincide con ' . $documento : 'Todavía no hay clientes';
    $vacioTexto = $documento !== null ? 'Revisa lo escrito o busca con menos letras o números.' : 'Regístralos con el botón Registrar cliente, o se registran solos cuando el cajero pone un DNI o RUC en una venta.';
    $vacioAccion = $documento !== null ? ['/?ruta=clientes', 'Ver todos'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Cliente</th><th scope="col">DNI</th><th scope="col">RUC</th><th scope="col">Teléfono</th><th scope="col" class="num">Compras</th><th scope="col" class="num">Total comprado</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php $c = $fila['cliente']; ?>
        <tr id="fila-<?= $c->getIdCliente() ?>">
            <td><a class="fila-enlace" href="<?= e($urlPagina . '&pagina=' . $paginacion->getPagina() . '&ver=' . $c->getIdCliente()) ?>"><?= e($c->nombreParaMostrar()) ?></a></td>
            <td><?= e($c->getDni() ?? '-') ?></td>
            <td><?= e($c->getRuc() ?? '-') ?></td>
            <td><?= e($c->getTelefono() ?? '-') ?></td>
            <td class="num"><?= (int) $fila['compras'] ?></td>
            <td class="num"><?= e(soles($fila['total'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../parciales/paginacion.php'; ?>
<?php endif; ?>

<?php if ($ficha !== null): ?>
    <?php
    extract($ficha);
    $drawerId = 'ficha-cliente';
    $drawerTitulo = $cliente->nombreParaMostrar();
    $drawerSubtitulo = $cliente->getRuc() !== null ? 'RUC ' . $cliente->getRuc() : 'DNI ' . ($cliente->getDni() ?? '-');
    $drawerCerrar = $urlPagina . '&pagina=' . $paginacion->getPagina() . '#fila-' . $cliente->getIdCliente();
    $drawerAbierto = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    require __DIR__ . '/ficha.php';
    require __DIR__ . '/../parciales/drawer_cerrar.php';
    ?>
<?php endif; ?>
<?php
$drawerId = 'nuevo-cliente';
$drawerTitulo = 'Registrar cliente';
$drawerSubtitulo = 'Con su DNI (o RUC, si pide factura) ya se le puede vender a su nombre';
$drawerCerrar = '/?ruta=clientes';
$drawerAbierto = $error !== null; // si hubo un error, vuelve abierto con lo escrito
require __DIR__ . '/../parciales/drawer_abrir.php';
?>
<form class="formulario" method="post" action="/?ruta=cliente_crear" autocomplete="off">
    <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= campoCsrf($csrf) ?>
    <label for="nuevo-nombre">Nombre <span class="ayuda">nombres y apellidos, o razón social</span></label>
    <input id="nuevo-nombre" name="nombre" maxlength="100" required value="<?= e($viejoTexto('nombre')) ?>">
    <label for="nuevo-dni">DNI <span class="ayuda">8 dígitos</span></label>
    <input id="nuevo-dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" value="<?= e($viejoTexto('dni')) ?>">
    <label for="nuevo-ruc">RUC <span class="ayuda">opcional, 11 dígitos: solo si pide factura</span></label>
    <input id="nuevo-ruc" name="ruc" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" value="<?= e($viejoTexto('ruc')) ?>">
    <label for="nuevo-direccion">Dirección <span class="ayuda">opcional</span></label>
    <input id="nuevo-direccion" name="direccion" maxlength="120" value="<?= e($viejoTexto('direccion')) ?>">
    <label for="nuevo-telefono">Teléfono <span class="ayuda">opcional; celular de 9 dígitos o fijo de 6 a 9</span></label>
    <input id="nuevo-telefono" name="telefono" type="tel" inputmode="numeric" maxlength="11" value="<?= e($viejoTexto('telefono')) ?>">
    <div class="botones-formulario">
        <button class="acento" type="submit">Registrar</button>
        <small>Hace falta el DNI o el RUC.</small>
    </div>
</form>
<?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
