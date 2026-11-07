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
<?php if (($asistenteDisponible ?? false) && ($usuarioActual ?? null) !== null && ($rutaActual ?? '') !== 'asistente'): ?>
<?php if (!in_array($rutaActual ?? '', ['caja', 'venta_nueva'], true)): ?>
<!-- Boton del asistente abajo a la derecha (no en Caja ni en Nueva venta, donde tapario botones: ahi se abre desde el menu).
     Sin JavaScript lleva a la pantalla completa; con JavaScript abre la ventanita de abajo. -->
<a class="asistente-flotante" href="/?ruta=asistente" title="Pregúntale al asistente" data-asistente-abrir aria-controls="asistente-ventana"><?= icono('asistente') ?><span>Asistente</span></a>
<?php endif; ?>
<!-- Ventanita del asistente: app.js la abre, trae las preguntas de este rol (/?ruta=asistente_datos) y pregunta sin recargar -->
<section class="asistente-ventana" id="asistente-ventana" data-asistente-ventana hidden aria-label="Asistente de la tienda">
    <header class="asistente-ventana-cabeza">
        <span class="asistente-quien"><?= icono('asistente') ?>Asistente</span>
        <span class="asistente-ventana-acciones">
            <button type="button" class="enlace" data-asistente-nueva title="Empezar una conversación nueva">Nueva</button>
            <a class="enlace" href="/?ruta=asistente" title="Abrir en pantalla completa">Ampliar</a>
            <button type="button" class="asistente-cerrar" data-asistente-cerrar aria-label="Cerrar el asistente"><?= icono('cerrar') ?></button>
        </span>
    </header>
    <div class="asistente asistente-compacto" data-asistente>
        <div class="asistente-mensajes" data-asistente-mensajes aria-live="polite">
            <div class="asistente-burbuja asistente-ia" data-asistente-saludo>
                <span class="asistente-quien"><?= icono('asistente') ?>Asistente</span>
                <p>Hola, <?= e($usuarioActual->getNombres()) ?>. ¿Qué quieres saber de la tienda? Toca una pregunta o escríbela, también «¿cómo cierro la caja?».</p>
            </div>
        </div>
        <div class="asistente-sugerencias" data-asistente-sugerencias aria-label="Preguntas rápidas"></div>
        <form class="asistente-escribir" method="post" action="/?ruta=asistente_preguntar" data-asistente-form>
            <?= campoCsrf($csrf) ?>
            <label class="visualmente-oculto" for="asistente-ventana-pregunta">Tu pregunta</label>
            <textarea id="asistente-ventana-pregunta" name="pregunta" rows="1" maxlength="500" required placeholder="Escribe tu pregunta…" autocomplete="off"></textarea>
            <button type="button" class="boton secundario asistente-micro" data-asistente-micro hidden aria-label="Dictar la pregunta" title="Dictar la pregunta"><?= icono('microfono') ?></button>
            <button type="submit" class="boton acento" data-asistente-enviar aria-label="Preguntar"><?= icono('enviar') ?></button>
        </form>
    </div>
</section>
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
