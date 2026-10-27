<?php
declare(strict_types=1);
/**
 * V: enlaces Anterior / Siguiente de una lista paginada.
 * @var Paginacion $paginacion
 * @var string $urlPagina  la URL de la lista con sus filtros, sin &pagina=
 */
?>
<?php if ($paginacion->totalPaginas() > 1): ?>
<nav class="paginas" aria-label="Páginas">
    <?php if ($paginacion->hayAnterior()): ?>
        <a href="<?= e($urlPagina . '&pagina=' . ($paginacion->getPagina() - 1)) ?>" rel="prev">← Anterior</a>
    <?php endif; ?>
    <span>Página <?= $paginacion->getPagina() ?> de <?= $paginacion->totalPaginas() ?></span>
    <?php if ($paginacion->haySiguiente()): ?>
        <a href="<?= e($urlPagina . '&pagina=' . ($paginacion->getPagina() + 1)) ?>" rel="next">Siguiente →</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
