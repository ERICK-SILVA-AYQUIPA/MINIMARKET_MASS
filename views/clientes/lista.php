<?php
declare(strict_types=1);
/**
 * V: clientes con sus compras. Se busca por el inicio del DNI o del RUC.
 * @var ?string $documento
 * @var array<int, array{cliente: Cliente, compras: int, total: float}> $filas
 * @var Paginacion $paginacion
 * @var string $urlPagina
 */
$titulo = 'Clientes';
require __DIR__ . '/../layout_inicio.php';
?>
<h1>Clientes</h1>
<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="clientes">
    <label>DNI o RUC (o sus primeros dígitos)
        <input name="documento" inputmode="numeric" pattern="[0-9]{1,11}" maxlength="11" value="<?= e($documento ?? '') ?>">
    </label>
    <button type="submit">Buscar</button>
    <a href="/?ruta=clientes">Ver todos</a>
</form>

<div class="tabla">
<table>
    <tr><th>#</th><th>DNI</th><th>RUC</th><th>Compras</th><th>Total comprado</th></tr>
    <?php foreach ($filas as $fila): ?>
        <?php $c = $fila['cliente']; ?>
        <tr>
            <td><a href="/?ruta=cliente&amp;id=<?= $c->getIdCliente() ?>"><?= $c->getIdCliente() ?></a></td>
            <td><?= e($c->getDni() ?? '-') ?></td>
            <td><?= e($c->getRuc() ?? '-') ?></td>
            <td class="num"><?= $fila['compras'] ?></td>
            <td class="num"><?= e(soles($fila['total'])) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/../parciales/paginacion.php'; ?>
<p><?= $paginacion->getTotalFilas() ?> clientes. Las ventas sin documento salen como CLIENTE VARIOS y no aparecen aquí.</p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
