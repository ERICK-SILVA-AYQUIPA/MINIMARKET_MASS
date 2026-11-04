</main>
<footer class="pie">Minimarket Mass · Arequipa · Sistema de ventas e inventario multitienda</footer>
    </div>
</div>
<?php $barraInferior ??= []; ?>
<?php if ($barraInferior !== []): ?>
<!-- Barra de abajo del celular (estilos.css la oculta en la computadora). Vender va al centro, en amarillo. -->
<nav class="barra-inferior" aria-label="Accesos rápidos">
    <?php foreach ($barraInferior as $rutaBarra => [$textoBarra, $iconoBarra]): ?>
        <a href="/?ruta=<?= e($rutaBarra) ?>" class="<?= $rutaBarra === 'venta_nueva' ? 'barra-inferior-vender' : '' ?>"<?= $rutaBarra === ($opcionActual ?? '') ? ' aria-current="page"' : '' ?>>
            <span class="barra-inferior-icono"><svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#<?= e($iconoBarra) ?>"></use></svg></span><?= e($textoBarra) ?>
        </a>
    <?php endforeach; ?>
    <label for="menu-casilla"><span class="barra-inferior-icono"><svg class="icono" aria-hidden="true" focusable="false"><use href="/iconos.svg#menu"></use></svg></span>Menú</label>
</nav>
<?php endif; ?>
</body>
</html>
