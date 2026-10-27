<?php
declare(strict_types=1);
/**
 * V: pagina de un cliente (la misma ficha que se abre en el panel lateral de la lista).
 * @var Cliente $cliente
 */
$titulo = 'Cliente';
require __DIR__ . '/../layout_inicio.php';
?>
<a class="volver" href="/?ruta=clientes"><?= icono('volver') ?>Volver a clientes</a>
<div class="cabecera">
    <div>
        <h1>Cliente <?= $cliente->getIdCliente() ?></h1>
        <p class="sutil"><?= e($cliente->getRuc() !== null ? 'RUC ' . $cliente->getRuc() : 'DNI ' . ($cliente->getDni() ?? '-')) ?></p>
    </div>
</div>
<div class="panel">
    <?php require __DIR__ . '/ficha.php'; ?>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
