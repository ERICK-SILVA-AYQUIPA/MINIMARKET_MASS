<?php
declare(strict_types=1);
/**
 * V: estado vacio de una lista: que pasa y que se puede hacer.
 * @var string $vacioTitulo
 * @var ?string $vacioTexto
 * @var ?array{0: string, 1: string} $vacioAccion  [url, texto] del boton (opcional)
 */
$vacioTexto ??= null;
$vacioAccion ??= null;
?>
<div class="vacio">
    <?= icono('vacio') ?>
    <strong><?= e($vacioTitulo) ?></strong>
    <?php if ($vacioTexto !== null): ?><p><?= e($vacioTexto) ?></p><?php endif; ?>
    <?php if ($vacioAccion !== null): ?><a class="boton secundario" href="<?= e($vacioAccion[0]) ?>"><?= e($vacioAccion[1]) ?></a><?php endif; ?>
</div>
<?php unset($vacioTitulo, $vacioTexto, $vacioAccion); ?>
