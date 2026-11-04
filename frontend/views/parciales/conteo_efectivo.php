<?php
declare(strict_types=1);
/**
 * V: contar la gaveta. Por billetes y monedas (el total se suma solo con JavaScript y el servidor lo vuelve a sumar)
 * o escribiendo solo el total. El total no es required: si va vacio, el servidor suma los billetes y monedas. Lo usan la apertura, el cierre, el reconteo y el arqueo sorpresa.
 * @var string $conteoId          prefijo de los id de los campos (unico en la pagina)
 * @var string $conteoCampoTotal  nombre del campo del total: monto_inicial, monto_cierre o monto
 * @var string $conteoValor       total que sale escrito al abrir el formulario
 * @var ?float $conteoEsperado    con cuanto se compara mientras se cuenta; null = a ciegas
 * @var string $conteoRotulo      que es lo esperado: "el fondo fijo", "lo esperado"
 * @var bool $conteoExacto        true: boton "Viene exacto" que escribe lo esperado (apertura)
 * @var bool $conteoSinMarco      true: sin el borde ni el titulo (va dentro de un paso que ya lo tiene)
 * @var bool $conteoGrande        true: total grande (arqueo de cierre)
 */
$conteoValor ??= '';
$conteoEsperado ??= null;
$conteoRotulo ??= 'lo esperado';
$conteoExacto ??= false;
$conteoSinMarco ??= false;
$conteoGrande ??= false;
$grupos = [
    'Billetes' => ['200.00', '100.00', '50.00', '20.00', '10.00'],
    'Monedas'  => ['5.00', '2.00', '1.00', '0.50', '0.20', '0.10'],
];
$clases = 'conteo' . ($conteoSinMarco ? ' sin-marco' : '') . ($conteoGrande ? ' grande' : '');
?>
<fieldset class="<?= e($clases) ?>" data-conteo<?php if ($conteoEsperado !== null): ?> data-esperado="<?= e(number_format($conteoEsperado, 2, '.', '')) ?>" data-rotulo="<?= e($conteoRotulo) ?>"<?php endif; ?>>
    <legend<?= $conteoSinMarco ? ' class="visualmente-oculto"' : '' ?>>Billetes y monedas</legend>
    <?php if (!$conteoSinMarco): ?><p class="ayuda">Escribe cuántos hay de cada uno y el total se suma solo. Si prefieres, escribe solo el total.</p><?php endif; ?>
    <?php foreach ($grupos as $grupo => $denominaciones): ?>
        <div class="conteo-grupo">
            <span class="conteo-grupo-titulo"><?= e($grupo) ?></span>
            <div class="conteo-grilla">
                <?php foreach ($denominaciones as $d): ?>
                    <?php $valorDen = (float) $d; ?>
                    <label class="conteo-den <?= $valorDen >= 10 ? 'billete' : 'moneda' ?>" for="<?= e($conteoId . '-' . str_replace('.', '', $d)) ?>">
                        <span><?= e($valorDen >= 1 ? 'S/ ' . (string) (int) $valorDen : (string) (int) round($valorDen * 100) . ' cént.') ?></span>
                        <input id="<?= e($conteoId . '-' . str_replace('.', '', $d)) ?>" name="conteo[<?= e($d) ?>]" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" data-denominacion="<?= e($d) ?>">
                        <small data-subtotal></small>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="conteo-total">
        <div>
            <label for="<?= e($conteoId) ?>-total">Total contado (S/)</label>
            <input id="<?= e($conteoId) ?>-total" name="<?= e($conteoCampoTotal) ?>" inputmode="decimal" value="<?= e($conteoValor) ?>" data-total autocomplete="off" placeholder="0.00">
            <small class="conteo-modo" data-conteo-modo hidden></small>
        </div>
        <?php if ($conteoExacto && $conteoEsperado !== null): ?>
            <button type="button" class="secundario" data-conteo-exacto hidden><?= icono('ok') ?>Viene exacto</button>
        <?php endif; ?>
        <output class="conteo-resultado" data-resultado aria-live="polite"></output>
    </div>
</fieldset>
<?php unset($conteoExacto, $conteoSinMarco, $conteoGrande); ?>
