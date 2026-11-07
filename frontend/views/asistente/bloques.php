<?php
declare(strict_types=1);
/**
 * V: tarjetas, tablas y graficos de barras de una respuesta del asistente (los arma Presentacion.php).
 * app.js dibuja lo mismo con las mismas clases cuando la respuesta llega sin recargar.
 * @var array<int, array<string, mixed>> $bloquesTurno
 */
?>
<div class="asistente-bloques">
    <?php foreach ($bloquesTurno as $bloque): ?>
        <?php if (($bloque['tipo'] ?? '') === 'tarjetas' && is_array($bloque['items'] ?? null)): ?>
        <div class="asistente-tarjetas">
            <?php foreach ($bloque['items'] as $item): ?>
            <div class="asistente-tarjeta tono-<?= e(in_array($item['tono'] ?? '', ['marca', 'bien', 'alerta'], true) ? (string) $item['tono'] : 'normal') ?>">
                <span class="asistente-tarjeta-etiqueta"><?= e((string) ($item['etiqueta'] ?? '')) ?></span>
                <strong class="asistente-tarjeta-valor"><?= e((string) ($item['valor'] ?? '')) ?></strong>
                <?php if (($item['nota'] ?? '') !== ''): ?><span class="asistente-tarjeta-nota"><?= e((string) $item['nota']) ?></span><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif (($bloque['tipo'] ?? '') === 'tabla' && is_array($bloque['filas'] ?? null)): ?>
        <figure class="asistente-cuadro">
            <figcaption><?= e((string) ($bloque['titulo'] ?? '')) ?></figcaption>
            <div class="asistente-tabla-caja">
                <table class="asistente-tabla">
                    <thead><tr>
                        <?php foreach ($bloque['columnas'] ?? [] as $columna): ?><th<?= !empty($columna['numero']) ? ' class="num"' : '' ?>><?= e((string) ($columna['texto'] ?? '')) ?></th><?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($bloque['filas'] as $fila): ?>
                        <tr>
                            <?php foreach (array_values(is_array($fila) ? $fila : []) as $i => $celda): ?><td<?= !empty($bloque['columnas'][$i]['numero']) ? ' class="num"' : '' ?>><?= e((string) $celda) ?></td><?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ((int) ($bloque['mas'] ?? 0) > 0): ?><p class="asistente-cuadro-mas">… y <?= (int) $bloque['mas'] ?> más</p><?php endif; ?>
        </figure>
        <?php elseif (($bloque['tipo'] ?? '') === 'barras' && is_array($bloque['items'] ?? null)): ?>
        <figure class="asistente-cuadro">
            <figcaption><?= e((string) ($bloque['titulo'] ?? '')) ?></figcaption>
            <div class="asistente-barras">
                <?php foreach ($bloque['items'] as $item): ?>
                <div class="asistente-barra<?= !empty($item['resaltar']) ? ' resaltada' : '' ?>">
                    <span class="asistente-barra-etiqueta"><?= e((string) ($item['etiqueta'] ?? '')) ?></span>
                    <span class="asistente-barra-pista"><span class="asistente-barra-relleno" style="width: <?= (int) min(100, max(0, (int) ($item['ancho'] ?? 0))) ?>%"></span></span>
                    <span class="asistente-barra-valor"><?= e((string) ($item['texto'] ?? '')) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </figure>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
