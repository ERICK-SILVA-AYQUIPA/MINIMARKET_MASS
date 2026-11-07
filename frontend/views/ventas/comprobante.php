<?php
declare(strict_types=1);
/**
 * V: boleta o factura completa: el cuerpo (parciales de detalle_venta.php), la revision, las devoluciones y la anulacion.
 * @var Venta $venta
 * @var SerieComprobante $serie
 * @var Tienda $tienda
 * @var ?AnulacionVenta $anulacion
 * @var ?Usuario $anuladaPor
 * @var Usuario[] $puedenAnular
 * @var bool $puedeAnular  el usuario actual puede anular esta venta
 * @var bool $efectivoDeTurnoCerrado  tiene efectivo de un turno ya cerrado: no se anula (descuadraria el arqueo)
 * @var bool $detalleCuadra
 * @var bool $pagosCuadran
 * @var array<int, array{detalle: DetalleVenta, producto: ?Producto, unidad: string, disponible: int}> $lineas
 * @var array<int, array{devolucion: DevolucionVenta, lineas: array}> $devoluciones
 * @var array<int, string> $nombres  id_producto => nombre
 * @var array<int, bool> $alPesoPorId  id_producto => se vende por kg
 * @var float $totalDevuelto
 * @var bool $aTiempo  la venta tiene menos de VentaServicio::DIAS_DEVOLUCION dias
 * @var bool $puedeDevolver  el usuario actual (cajero con turno) puede devolver parte
 * @var ComprobanteParaCliente $envio  el comprobante en texto para WhatsApp, Gmail y correo
 * @var ?string $celularCliente  el telefono de la ficha del cliente (si tiene)
 * @var bool $correoDesdeServidor  hay SMTP en el .env: el correo sale desde el sistema con el PDF adjunto
 */
$numero = $serie->formatear($venta->getNumeroCorrelativo());
$titulo = $numero;
require __DIR__ . '/../layout_inicio.php';
?>
<a class="volver" href="/?ruta=ventas&amp;tienda=<?= $tienda->getIdTienda() ?>"><?= icono('volver') ?>Ventas de <?= e($tienda->getNombreTienda()) ?></a>
<div class="cabecera">
    <div>
        <h1><?= e($serie->nombreComprobante()) ?> <?= e($numero) ?></h1>
        <p class="sutil">Total <?= e(soles($venta->getTotal())) ?> · <?= e(fecha($venta->getFechaHora())) ?></p>
    </div>
    <div class="acciones">
        <a class="boton" href="/?ruta=comprobante_imprimir&amp;serie=<?= e($serie->getSerie()) ?>&amp;numero=<?= $venta->getNumeroCorrelativo() ?>&amp;auto=1"><?= icono('imprimir') ?>Imprimir en ticketera</a>
        <a class="boton secundario" href="/?ruta=comprobante_imprimir&amp;serie=<?= e($serie->getSerie()) ?>&amp;numero=<?= $venta->getNumeroCorrelativo() ?>"><?= icono('ver') ?>Ver ticket / PDF</a>
    </div>
</div>

<?php if ($venta->estaAnulada()): ?>
    <div class="aviso alerta">Este comprobante está ANULADO: su stock volvió al kardex y sus pagos no cuentan.</div>
<?php endif; ?>

<div class="rejilla-principal">
<div class="comprobante">
    <?php require __DIR__ . '/detalle_venta.php'; ?>
</div>

<div>
    <?php if (!$venta->estaAnulada()): ?>
    <section class="panel enviar-comprobante" id="enviar">
        <h2><?= icono('enviar') ?>Enviar al cliente</h2>
        <form class="formulario" method="post" action="/?ruta=comprobante_correo" data-enviar-comprobante
            data-texto="<?= e($envio->texto()) ?>" data-texto-correo="<?= e($envio->texto(false)) ?>" data-asunto="<?= e($envio->asunto()) ?>"
            data-url-whatsapp="<?= e(ComprobanteParaCliente::URL_WHATSAPP) ?>" data-url-gmail="<?= e(ComprobanteParaCliente::URL_GMAIL) ?>">
            <?= campoCsrf($csrf) ?>
            <input type="hidden" name="serie" value="<?= e($serie->getSerie()) ?>">
            <input type="hidden" name="numero" value="<?= $venta->getNumeroCorrelativo() ?>">
            <label for="envio_celular">Celular (WhatsApp)</label>
            <input id="envio_celular" name="celular" type="tel" inputmode="tel" maxlength="15" placeholder="987654321"
                value="<?= e(ComprobanteParaCliente::celular($celularCliente) ?? '') ?>" data-envio-celular>
            <label for="envio_correo">Correo (Gmail u otro)</label>
            <input id="envio_correo" name="correo" type="email" maxlength="120" placeholder="cliente@gmail.com" autocomplete="off" data-envio-correo>
            <div class="botones-formulario">
                <a class="boton whatsapp" href="<?= e($envio->enlaceWhatsApp($celularCliente)) ?>" target="_blank" rel="noopener" data-envio-whatsapp><?= icono('enviar') ?>WhatsApp</a>
                <?php if ($correoDesdeServidor): ?>
                    <button class="secundario" type="submit" name="canal" value="correo" data-envio-correo-boton>Enviar correo</button>
                <?php else: ?>
                    <a class="boton secundario" href="<?= e($envio->enlaceGmail(null)) ?>" target="_blank" rel="noopener" data-envio-gmail>Abrir en Gmail</a>
                <?php endif; ?>
                <button class="secundario" type="<?= $correoDesdeServidor ? 'submit' : 'button' ?>" name="canal" value="ambos" data-envio-ambos hidden>Los dos</button>
            </div>
            <p class="ayuda" data-envio-ayuda>
                <?php if ($correoDesdeServidor): ?>El correo sale desde el sistema con el comprobante en PDF.<?php else: ?>Gmail se abre con el comprobante ya escrito: solo toca Enviar.<?php endif; ?>
                Sin celular, WhatsApp te deja elegir el contacto.
            </p>
        </form>
    </section>
    <?php endif; ?>

    <section class="panel">
        <h2>Revisión</h2>
        <ul class="lista-revision">
            <li><span class="etiqueta <?= $detalleCuadra ? 'ok' : 'error' ?>"><?= $detalleCuadra ? 'Sí' : 'No' ?></span> Importes del detalle, menos sus descuentos, suman el total</li>
            <li><span class="etiqueta <?= $pagosCuadran ? 'ok' : 'error' ?>"><?= $pagosCuadran ? 'Sí' : 'No' ?></span> <?= $venta->estaAnulada() ? 'Todos los pagos están anulados' : 'Pagos válidos suman el total' ?></li>
        </ul>
    </section>

    <?php if ($devoluciones !== [] || $puedeDevolver): ?>
    <section class="panel" id="devoluciones">
        <h2>Devoluciones</h2>
        <?php foreach ($devoluciones as $dev): ?>
            <?php $d = $dev['devolucion']; ?>
            <div class="devolucion">
                <p><strong><?= e(soles($d->getTotal())) ?></strong> devueltos el <span class="fecha"><?= e(fecha($d->getFechaHora())) ?></span> · <?= e($d->getMotivo()) ?></p>
                <ul>
                    <?php foreach ($dev['lineas'] as $l): ?>
                        <?php $cantidadDevuelta = number_format($l['cantidad'], ($alPesoPorId[$l['id_producto']] ?? true) ? 3 : 0, '.', ''); ?>
                        <li><?= e($nombres[$l['id_producto']] ?? '-') ?>: <?= e($cantidadDevuelta) ?> × <?= e(soles($l['precio'])) ?><?= Dinero::centimos($l['importe']) < Dinero::centimos($l['cantidad'] * $l['precio']) ? ' con descuento' : '' ?> = <?= e(soles($l['importe'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <?php if ($devoluciones !== []): ?>
            <p class="sutil">Total devuelto: <?= e(soles($totalDevuelto)) ?>. Neto de la venta: <?= e(soles(round($venta->getTotal() - $totalDevuelto, 2))) ?>.</p>
        <?php endif; ?>

        <?php if ($puedeDevolver): ?>
            <form class="formulario" method="post" action="/?ruta=venta_devolver" data-confirmar="¿Registrar la devolución? El producto vuelve al stock y entregas el efectivo de tu gaveta." data-confirmar-boton="Sí, devolver">
                <?= campoCsrf($csrf) ?>
                <input type="hidden" name="serie" value="<?= e($serie->getSerie()) ?>">
                <input type="hidden" name="numero" value="<?= $venta->getNumeroCorrelativo() ?>">
                <p class="ayuda">Escribe cuánto devuelve de cada producto; deja vacío lo que se queda. Se paga lo que el cliente pagó (con sus descuentos), en efectivo.</p>
                <?php foreach ($lineas as $linea): ?>
                    <?php
                    if ($linea['disponible'] <= 0) {
                        continue;
                    }
                    $idProducto = $linea['detalle']->getIdProducto();
                    $alPeso = $linea['producto']?->esAlPeso() === true;
                    $maximo = Cantidad::aTexto($linea['disponible']);
                    $etiquetaDevolver = ($nombres[$idProducto] ?? '-') . ' (hasta ' . ($alPeso ? $maximo : (string) intdiv($linea['disponible'], 1000)) . ' ' . $linea['unidad'] . ')';
                    ?>
                    <input type="hidden" name="producto[]" value="<?= (int) $idProducto ?>">
                    <label for="devolver-<?= (int) $idProducto ?>"><?= e($etiquetaDevolver) ?></label>
                    <input id="devolver-<?= (int) $idProducto ?>" name="cantidad[]" inputmode="<?= $alPeso ? 'decimal' : 'numeric' ?>" placeholder="0">
                <?php endforeach; ?>
                <label for="motivo_devolucion">Motivo</label>
                <input id="motivo_devolucion" name="motivo" maxlength="150" required placeholder="Ej.: producto en mal estado">
                <div class="botones-formulario"><button class="secundario" type="submit"><?= icono('volver') ?>Registrar devolución</button></div>
            </form>
        <?php endif; ?>
    </section>
    <?php elseif (!$venta->estaAnulada() && !$aTiempo): ?>
    <p class="sutil bloque">Pasaron más de <?= (int) VentaServicio::DIAS_DEVOLUCION ?> días: esta venta ya no acepta devoluciones.</p>
    <?php endif; ?>

    <section class="panel">
    <?php if ($anulacion !== null): ?>
        <h2>Anulación</h2>
        <dl class="datos">
            <dt>Fecha</dt><dd><?= e(fecha($anulacion->getFechaHora())) ?></dd>
            <dt>Motivo</dt><dd><?= e($anulacion->getMotivo()) ?></dd>
            <dt>Anulada por</dt><dd><?= e($anuladaPor?->nombreCompleto() ?? '-') ?> (<?= e($anuladaPor?->getNombreRol() ?? '-') ?>)</dd>
        </dl>
    <?php else: ?>
        <h2>¿Quién puede anularla?</h2>
        <p>Solo un ADMINISTRADOR activo de <?= e($tienda->getNombreTienda()) ?>:
            <?= e(implode(', ', array_map(fn(Usuario $u): string => $u->nombreCompleto(), $puedenAnular)) ?: 'nadie') ?></p>
        <?php if ($devoluciones !== []): ?>
            <p class="sutil">Tiene devoluciones: ya no se puede anular.</p>
        <?php elseif ($efectivoDeTurnoCerrado): ?>
            <p class="sutil">Se cobró en efectivo en un turno que ya cerró su caja: anularla descuadraría ese arqueo. Para devolver el dinero, registra una devolución desde una caja abierta.</p>
        <?php elseif ($puedeAnular): ?>
            <form class="formulario" method="post" action="/?ruta=venta_anular" data-confirmar="¿Anular esta venta? Devuelve el stock al kardex y anula sus pagos. No se puede deshacer." data-confirmar-boton="Sí, anular">
                <?= campoCsrf($csrf) ?>
                <input type="hidden" name="serie" value="<?= e($serie->getSerie()) ?>">
                <input type="hidden" name="numero" value="<?= $venta->getNumeroCorrelativo() ?>">
                <label for="motivo">Motivo de la anulación</label>
                <input id="motivo" name="motivo" maxlength="150" required>
                <div class="botones-formulario">
                    <button class="peligro" type="submit">Anular venta</button>
                    <small>Devuelve el stock al kardex y anula sus pagos. No se puede deshacer.</small>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    </section>
</div>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
