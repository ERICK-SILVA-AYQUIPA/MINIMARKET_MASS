<?php
declare(strict_types=1);
/**
 * V: guia corta de la pantalla ("Como se usa"), en pasos numerados.
 * Sale abierta la primera vez; si el empleado la cierra, public/app.js la recuerda cerrada en ese navegador.
 * Sin JavaScript se abre y se cierra igual (es un <details>).
 * @var string $guiaClave   nombre corto de la pantalla, para recordarla
 * @var string $guiaTitulo
 * @var string[] $guiaPasos
 */
?>
<details class="guia" data-guia="<?= e($guiaClave) ?>" open>
    <summary><svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#guia"></use></svg><?= e($guiaTitulo) ?></summary>
    <ol>
        <?php foreach ($guiaPasos as $paso): ?><li><?= e($paso) ?></li><?php endforeach; ?>
    </ol>
</details>
<?php unset($guiaClave, $guiaTitulo, $guiaPasos); ?>
