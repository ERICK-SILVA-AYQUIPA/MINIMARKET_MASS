<?php
declare(strict_types=1);
/**
 * V: el asistente. Conversacion en burbujas, sugerencias para empezar y la caja para escribir (o dictar con el
 * microfono: app.js lo muestra si el navegador sabe reconocer voz). Sin JavaScript cada pregunta recarga la pagina.
 * @var array<int, array{rol: string, texto: string, enlaces: array<int, array{texto: string, url: string}>}> $conversacion
 * @var bool $configurado  hay ANTHROPIC_API_KEY en el .env
 * @var string[] $sugerencias
 */
require __DIR__ . '/../layout_inicio.php';
$ultimo = count($conversacion) - 1;
?>
<div class="cabecera">
    <div>
        <h1>Asistente</h1>
        <p class="sutil">Pregúntale en tus palabras por ventas, precios o stock, escribiendo o con el micrófono. Responde con los datos que tu usuario puede ver.</p>
    </div>
    <?php if ($conversacion !== []): ?>
    <div class="acciones">
        <form method="post" action="/?ruta=asistente_borrar">
            <?= campoCsrf($csrf) ?>
            <button type="submit" class="boton secundario"><?= icono('quitar') ?>Nueva conversación</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if (!$configurado): ?>
<div class="aviso alerta" role="status">
    El asistente aún no está configurado. El administrador debe poner su clave de la API de Claude en el archivo
    <code>.env</code> del servidor (<code>ANTHROPIC_API_KEY=...</code>) y volver a abrir esta página.
</div>
<?php endif; ?>

<section class="panel asistente" data-asistente>
    <div class="asistente-mensajes" data-asistente-mensajes aria-live="polite">
        <div class="asistente-burbuja asistente-ia">
            <span class="asistente-quien"><?= icono('asistente') ?>Asistente</span>
            <p>Hola, <?= e($usuarioActual->getNombres()) ?>. ¿Qué quieres saber hoy?</p>
        </div>
        <?php foreach ($conversacion as $i => $turno): ?>
        <div class="asistente-burbuja <?= $turno['rol'] === 'user' ? 'asistente-tu' : 'asistente-ia' ?>"<?= $i === $ultimo ? ' id="ultima"' : '' ?>>
            <?php if ($turno['rol'] !== 'user'): ?><span class="asistente-quien"><?= icono('asistente') ?>Asistente</span><?php endif; ?>
            <p><?= e($turno['texto']) ?></p>
            <?php if (($turno['enlaces'] ?? []) !== []): ?>
            <div class="asistente-enlaces">
                <?php foreach ($turno['enlaces'] as $enlace): ?>
                    <?php if (str_starts_with($enlace['url'], '/?ruta=')): ?><a class="boton secundario boton-chico" href="<?= e($enlace['url']) ?>"><?= e($enlace['texto']) ?></a><?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($configurado): ?>
    <div class="asistente-sugerencias" data-asistente-sugerencias>
        <?php foreach ($sugerencias as $sugerencia): ?>
        <form method="post" action="/?ruta=asistente_preguntar">
            <?= campoCsrf($csrf) ?>
            <input type="hidden" name="pregunta" value="<?= e($sugerencia) ?>">
            <button type="submit" class="asistente-chip" data-asistente-sugerencia><?= e($sugerencia) ?></button>
        </form>
        <?php endforeach; ?>
    </div>

    <form class="asistente-escribir" method="post" action="/?ruta=asistente_preguntar" data-asistente-form>
        <?= campoCsrf($csrf) ?>
        <label class="visualmente-oculto" for="asistente-pregunta">Tu pregunta</label>
        <textarea id="asistente-pregunta" name="pregunta" rows="1" maxlength="500" required
            placeholder="Ej.: ¿cuánto vendimos ayer?, ¿hay arroz Costeño?" autocomplete="off"></textarea>
        <button type="button" class="boton secundario asistente-micro" data-asistente-micro hidden aria-label="Dictar la pregunta" title="Dictar la pregunta"><?= icono('microfono') ?></button>
        <button type="submit" class="boton acento" data-asistente-enviar><?= icono('enviar') ?>Preguntar</button>
    </form>
    <p class="sutil asistente-nota">Las respuestas las arma una inteligencia artificial (Claude) con los datos del sistema: revisa los montos importantes en Reportes antes de decidir.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../layout_fin.php'; ?>
