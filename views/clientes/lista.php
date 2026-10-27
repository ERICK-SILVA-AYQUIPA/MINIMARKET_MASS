<?php
declare(strict_types=1);
/**
 * V: clientes con sus compras. Se busca por el inicio del DNI o del RUC.
 * Al tocar un cliente se abre su ficha en el panel lateral (&ver=ID).
 * @var ?string $documento
 * @var array<int, array{cliente: Cliente, compras: int, total: float}> $filas
 * @var Paginacion $paginacion
 * @var string $urlPagina
 * @var ?array{cliente: Cliente, compras: array} $ficha
 */
$titulo = 'Clientes';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="cabecera">
    <div>
        <h1>Clientes</h1>
        <p class="sutil"><?= $paginacion->getTotalFilas() ?> clientes con DNI o RUC. Las ventas sin documento salen como CLIENTE VARIOS y no aparecen aquí.</p>
    </div>
</div>
<form class="filtros" method="get" action="/" role="search">
    <input type="hidden" name="ruta" value="clientes">
    <label>DNI o RUC (o sus primeros dígitos)
        <input name="documento" inputmode="numeric" pattern="[0-9]{1,11}" maxlength="11" value="<?= e($documento ?? '') ?>">
    </label>
    <button type="submit"><?= icono('buscar') ?>Buscar</button>
    <?php if ($documento !== null): ?><a class="boton secundario" href="/?ruta=clientes">Ver todos</a><?php endif; ?>
</form>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = $documento !== null ? 'Ningún cliente empieza con ' . $documento : 'Todavía no hay clientes';
    $vacioTexto = $documento !== null ? 'Revisa los dígitos o busca con menos números.' : 'Se registran solos cuando el cajero pone un DNI o RUC en una venta.';
    $vacioAccion = $documento !== null ? ['/?ruta=clientes', 'Ver todos'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Cliente</th><th scope="col">DNI</th><th scope="col">RUC</th><th scope="col" class="num">Compras</th><th scope="col" class="num">Total comprado</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php $c = $fila['cliente']; ?>
        <tr id="fila-<?= $c->getIdCliente() ?>">
            <td><a class="fila-enlace" href="<?= e($urlPagina . '&pagina=' . $paginacion->getPagina() . '&ver=' . $c->getIdCliente()) ?>">Cliente <?= $c->getIdCliente() ?></a></td>
            <td><?= e($c->getDni() ?? '-') ?></td>
            <td><?= e($c->getRuc() ?? '-') ?></td>
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
    $drawerTitulo = 'Cliente ' . $cliente->getIdCliente();
    $drawerSubtitulo = $cliente->getRuc() !== null ? 'RUC ' . $cliente->getRuc() : 'DNI ' . ($cliente->getDni() ?? '-');
    $drawerCerrar = $urlPagina . '&pagina=' . $paginacion->getPagina() . '#fila-' . $cliente->getIdCliente();
    $drawerAbierto = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    require __DIR__ . '/ficha.php';
    require __DIR__ . '/../parciales/drawer_cerrar.php';
    ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
