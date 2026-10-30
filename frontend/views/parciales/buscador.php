<?php
declare(strict_types=1);
/**
 * V: buscador en vivo sobre una tabla. Viene oculto: public/app.js lo muestra y filtra las filas mientras se escribe.
 * Sin JavaScript no aparece y la tabla se ve completa.
 * @var string $buscadorTabla   id del <tbody> que filtra
 * @var string $buscadorTexto   lo que dice el campo vacio
 */
?>
<div class="buscador" data-buscador hidden>
    <?= icono('buscar') ?>
    <input type="search" data-filtrar="<?= e($buscadorTabla) ?>" placeholder="<?= e($buscadorTexto) ?>" aria-label="<?= e($buscadorTexto) ?>" autocomplete="off">
    <kbd title="Atajo de teclado">/</kbd>
</div>
<?php unset($buscadorTabla, $buscadorTexto); ?>
