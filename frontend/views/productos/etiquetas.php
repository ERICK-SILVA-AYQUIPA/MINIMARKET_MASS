<?php
declare(strict_types=1);
/**
 * V: hoja A4 de etiquetas con QR (3 por fila) para imprimir y pegar en el estante o en el producto.
 * Pagina suelta, sin menu, como la orden de compra. El QR lo dibuja qr() (CodigoQr, sin internet).
 * @var array<int, array{nombre: string, precio: float, codigo: string}> $etiquetas
 * @var string[] $sinCodigo  productos sin codigo (al peso): no llevan QR
 * @var string $titulo
 * @var int $copias
 * @var ?int $categoriaElegida
 * @var ?int $idElegido
 * @var array<int, string> $categorias
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas QR · <?= e($titulo) ?> · Minimarket Mass</title>
    <style>
        @page { size: A4; margin: 8mm; }
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; font-size: 13px; color: #111; background: #e9ebee; margin: 0; }
        .acciones { max-width: 194mm; margin: 16px auto; background: #fff; padding: 12px 16px; border-radius: 8px; }
        .acciones h1 { font-size: 18px; margin: 0 0 6px; }
        .acciones p { margin: 4px 0; }
        .acciones form { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; margin-top: 8px; }
        .acciones label { display: flex; flex-direction: column; gap: 2px; font-size: 12px; }
        .acciones select, .acciones input, .acciones button { font: inherit; padding: 4px 6px; }
        .hoja { max-width: 194mm; margin: 0 auto 16px; background: #fff; padding: 4mm; display: grid; grid-template-columns: repeat(3, 1fr); gap: 2mm; }
        .etiqueta { display: flex; gap: 2mm; align-items: center; border: 1px dashed #999; padding: 2mm; height: 32mm; box-sizing: border-box; break-inside: avoid; overflow: hidden; }
        .etiqueta svg { width: 27mm; height: 27mm; flex: none; }
        .etiqueta div { min-width: 0; }
        .nombre { font-weight: 600; font-size: 11px; line-height: 1.2; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; }
        .precio { font-size: 18px; font-weight: 800; margin-top: 1mm; }
        .codigo { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 9px; color: #444; }
        .aviso { color: #7a4b00; }
        @media print {
            body { background: #fff; }
            .acciones { display: none; }
            .hoja { margin: 0; padding: 0; max-width: none; }
        }
    </style>
</head>
<body>
<div class="acciones">
    <h1>Etiquetas QR · <?= e($titulo) ?></h1>
    <p>Pega cada etiqueta en el estante o en el producto. Al escanear el QR con la app, el producto se reconoce al instante
        (en Nueva venta, Entrada de stock y Consultar), aunque el empaque traiga otro código de barras.</p>
    <p>Para imprimir: <strong>Ctrl + P</strong> (en Mac, <strong>Cmd + P</strong>). Mejor en papel adhesivo y al 100 % (sin «ajustar a la página»).</p>
    <?php if ($idElegido === null): ?>
        <form method="get" action="/">
            <input type="hidden" name="ruta" value="etiquetas">
            <label>Categoría
                <select name="categoria">
                    <option value="">Todo el catálogo</option>
                    <?php foreach ($categorias as $idCategoria => $nombreCategoria): ?>
                        <option value="<?= (int) $idCategoria ?>"<?= $categoriaElegida === $idCategoria ? ' selected' : '' ?>><?= e($nombreCategoria) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Copias de cada una
                <input type="number" name="copias" min="1" max="30" value="<?= (int) $copias ?>">
            </label>
            <button type="submit">Ver etiquetas</button>
        </form>
    <?php endif; ?>
    <p><?= (int) (count($etiquetas) * $copias) ?> etiquetas<?= e($copias > 1 ? ' (' . count($etiquetas) . ' productos × ' . $copias . ')' : '') ?>.</p>
    <?php if ($sinCodigo !== []): ?>
        <p class="aviso">Sin QR (se venden al peso, no tienen código): <?= e(implode(', ', $sinCodigo)) ?>.</p>
    <?php endif; ?>
    <p><a href="/?ruta=productos<?= e($categoriaElegida !== null ? '&categoria=' . $categoriaElegida : '') ?>">← Volver al catálogo</a></p>
</div>
<?php if ($etiquetas !== []): ?>
<div class="hoja">
    <?php foreach ($etiquetas as $et): ?>
        <?php for ($i = 0; $i < $copias; $i++): ?>
            <div class="etiqueta">
                <?= qr($et['codigo'], $et['nombre']) ?>
                <div>
                    <div class="nombre"><?= e($et['nombre']) ?></div>
                    <div class="precio"><?= e(soles($et['precio'])) ?></div>
                    <div class="codigo"><?= e($et['codigo']) ?></div>
                </div>
            </div>
        <?php endfor; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>
