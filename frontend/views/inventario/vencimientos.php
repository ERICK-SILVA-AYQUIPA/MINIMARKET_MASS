<?php
declare(strict_types=1);
/**
 * V: productos por vencer de la tienda del usuario. Cuanto queda de cada lote es una estimacion FIFO (ver models/Vencimiento.php).
 * @var Tienda $tienda
 * @var int $dias
 * @var int[] $diasPermitidos
 * @var bool $puedeDarDeBaja  el almacenero puede registrar la merma de lo vencido
 * @var array<int, array{id_ingreso: int, recibido: string, documento: string, fecha_vencimiento: string, restante: int,
 *            costo: float, dias: int, vencido: bool, producto: ?Producto, unidad: string, valor: float}> $filas
 * @var int $vencidos
 * @var float $valorEnRiesgo
 */
$titulo = 'Por vencer';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="cabecera">
    <div>
        <h1>Por vencer en <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Lotes recibidos con fecha de vencimiento. Lo que queda de cada lote se estima vendiendo primero lo más antiguo.</p>
    </div>
    <?php if ($puedeDarDeBaja && $vencidos > 0): ?><div class="acciones"><a class="boton peligro" href="/?ruta=mermas#nueva-merma"><?= icono('mermas') ?>Dar de baja lo vencido</a></div><?php endif; ?>
</div>
<?php
$guiaClave = 'vencimientos';
$guiaTitulo = 'Cómo usar Por vencer';
$guiaPasos = [
    'Cada lote recibido con fecha de vencimiento aparece aquí. Elige 7, 15, 30 o 60 días para ver lo más próximo.',
    'Lo que queda de cada lote se calcula suponiendo que primero se vende lo más antiguo.',
    'Pon en oferta lo que vence pronto. Lo vencido se da de baja como merma para que el stock quede real.',
];
require __DIR__ . '/../parciales/guia.php';
?>

<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="vencimientos">
    <label>Vencen en los próximos
        <select name="dias">
            <?php foreach ($diasPermitidos as $opcion): ?>
                <option value="<?= (int) $opcion ?>" <?= $opcion === $dias ? 'selected' : '' ?>><?= (int) $opcion ?> días</option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="secundario" type="submit"><?= icono('buscar') ?>Ver</button>
</form>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = 'Nada por vencer';
    $vacioTexto = 'Ningún lote con stock vence en estos días. Al recibir mercadería, anota su fecha de vencimiento.';
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<section class="tarjetas" aria-label="Resumen">
    <div><span>Lotes</span><strong><?= count($filas) ?></strong><small>vencen en <?= (int) $dias ?> días o menos</small></div>
    <div><span>Ya vencidos</span><strong><?= (int) $vencidos ?></strong><small>retíralos de la góndola</small></div>
    <div><span>Valor al costo</span><strong><?= e(soles($valorEnRiesgo)) ?></strong><small>lo que se perdería</small></div>
</section>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Producto</th><th scope="col">Vence</th><th scope="col" class="num">Queda (estimado)</th><th scope="col" class="num">Valor al costo</th><th scope="col">Lote</th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
        <?php
        $alPeso = $f['producto']?->esAlPeso() === true;
        $queda = $alPeso ? Cantidad::aTexto($f['restante']) : (string) intdiv($f['restante'], 1000);
        $claseVence = $f['vencido'] ? 'error' : ($f['dias'] <= 7 ? 'reponer' : 'alerta');
        ?>
        <tr>
            <td><?= e($f['producto']?->getNombreProducto() ?? '-') ?></td>
            <td><span class="etiqueta <?= e($claseVence) ?>"><?= e(Vencimiento::describir($f['dias'])) ?></span> <span class="fecha sutil"><?= e(fecha($f['fecha_vencimiento'])) ?></span></td>
            <td class="num"><?= e($queda . ' ' . $f['unidad']) ?></td>
            <td class="num"><?= e(soles($f['valor'])) ?></td>
            <td><?= e($f['documento']) ?> <span class="sutil">· recibido <?= e(substr($f['recibido'], 0, 10)) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="sutil bloque">El sistema no sabe de qué lote sale cada venta: supone que se vende primero lo más antiguo. Si lo contado en góndola no coincide, corrige con un ajuste por conteo o una merma.</p>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
