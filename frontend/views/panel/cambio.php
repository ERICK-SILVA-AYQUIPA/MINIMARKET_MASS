<?php
declare(strict_types=1);
/**
 * V: el pie de un indicador del inicio con su cambio contra el periodo anterior (▲ verde sube, ▼ rojo baja).
 * @var ?float $variacion  en %, null si antes no hubo nada con que comparar
 * @var string $contra  ayer, el mes pasado...
 */
?>
<?php if ($variacion === null): ?>
<small class="kpi-pie">Sin ventas <?= e($contra) ?> para comparar</small>
<?php else: ?>
<small class="kpi-pie"><span class="delta <?= $variacion >= 0 ? 'sube' : 'baja' ?>"><?= $variacion >= 0 ? '▲' : '▼' ?> <?= e(number_format(abs($variacion), 1)) ?>%</span> vs <?= e($contra) ?></small>
<?php endif; ?>
