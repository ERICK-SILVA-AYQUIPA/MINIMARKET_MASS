</main>
<?php
require_once __DIR__ . '/../../backend/config/Aplicacion.php';
$soportePie = Aplicacion::soporte();
?>
<!-- Version y a quien llamar: salen de VERSION y del .env (SOPORTE_*), ver backend/config/Aplicacion.php -->
<footer class="pie">
    <span>Minimarket Mass <span class="pie-version">v<?= e(Aplicacion::version()) ?></span></span>
    <span>Soporte: <?= e($soportePie['nombre']) ?><?php if ($soportePie['telefono'] !== null): ?> · <a href="<?= e(Aplicacion::enlaceTelefono($soportePie['telefono'])) ?>"><?= e($soportePie['telefono']) ?></a><?php endif; ?><?php if ($soportePie['correo'] !== null): ?> · <a href="mailto:<?= e($soportePie['correo']) ?>"><?= e($soportePie['correo']) ?></a><?php endif; ?></span>
    <?php if (($usuarioActual ?? null) !== null): ?><a href="/?ruta=ayuda">Ayuda</a><?php endif; ?>
</footer>
    </div>
</div>
<?php if (($asistenteDisponible ?? false) && ($rutaActual ?? '') !== 'asistente'): ?>
<!-- El asistente desde cualquier pantalla (solo con ANTHROPIC_API_KEY en el .env) -->
<a class="asistente-flotante" href="/?ruta=asistente" title="Pregúntale al asistente"><?= icono('asistente') ?><span>Asistente</span></a>
<?php endif; ?>
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
