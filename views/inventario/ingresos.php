<?php
declare(strict_types=1);
/**
 * V: ingresos de mercaderia de la tienda del usuario. El almacenero registra en un panel lateral
 * (#nuevo-ingreso) y cada ingreso abre su detalle en otro (#ing-ID).
 * @var Tienda $tienda
 * @var array<int, Producto> $productos
 * @var array<int, string> $nombresEmpleado
 * @var string[] $tiposDocumento
 * @var bool $puedeRegistrar
 * @var string $hoy
 * @var array<int, array{ingreso: IngresoMercaderia, detalle: DetalleIngreso[]}> $filas
 * @var Paginacion $paginacion
 * @var string $urlPagina
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Ingresos de mercadería';
require __DIR__ . '/../layout_inicio.php';
$valorViejo = fn(string $clave, string $porDefecto = ''): string => is_string($viejo[$clave] ?? null) ? $viejo[$clave] : $porDefecto;
?>
<div class="cabecera">
    <div>
        <h1>Ingresos de mercadería</h1>
        <p class="sutil"><?= e($tienda->getNombreTienda()) ?> · <?= $paginacion->getTotalFilas() ?> ingresos registrados</p>
    </div>
    <div class="acciones">
        <a class="boton secundario" href="/?ruta=ingresos_csv"><?= icono('descarga') ?>Descargar CSV</a>
        <?php if ($puedeRegistrar): ?><a class="boton acento" href="#nuevo-ingreso"><?= icono('mas') ?>Registrar ingreso</a><?php endif; ?>
    </div>
</div>
<p class="sutil">La mercadería entra con su documento (guía de remisión, factura o boleta del emisor). El stock sube al registrar,
   cada producto queda en el kardex y el ingreso en la auditoría.</p>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = 'Todavía no hay ingresos';
    $vacioTexto = $puedeRegistrar ? 'Cuando llegue mercadería, regístrala con su guía o factura y el stock subirá solo.' : 'El almacenero de la tienda registra aquí la mercadería que llega.';
    $vacioAccion = $puedeRegistrar ? ['#nuevo-ingreso', 'Registrar el primer ingreso'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">#</th><th scope="col">Registrado</th><th scope="col">Documento</th><th scope="col">Emisor</th><th scope="col" class="num">Productos</th><th scope="col" class="num">Total</th><th scope="col">Registró</th><th scope="col"><span class="visualmente-oculto">Detalle</span></th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php $i = $fila['ingreso']; ?>
        <tr id="fila-ing-<?= $i->getIdIngreso() ?>">
            <td><?= $i->getIdIngreso() ?></td>
            <td class="fecha"><?= e($i->getFechaHora()) ?></td>
            <td class="codigo"><?= e($i->documento()) ?><br><small>del <?= e($i->getFechaDocumento()) ?></small></td>
            <td><?= e($i->getRazonSocial()) ?><br><small>RUC <?= e($i->getRucEmisor()) ?></small></td>
            <td class="num"><?= (int) count($fila['detalle']) ?></td>
            <td class="num"><?= e(soles($i->getTotal())) ?></td>
            <td><?= e($nombresEmpleado[$i->getIdEmpleado()] ?? '-') ?></td>
            <td><a class="boton secundario chico" href="#ing-<?= $i->getIdIngreso() ?>"><?= icono('ver') ?>Ver</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../parciales/paginacion.php'; ?>

<?php foreach ($filas as $fila): ?>
    <?php
    $i = $fila['ingreso'];
    $drawerId = 'ing-' . $i->getIdIngreso();
    $drawerTitulo = 'Ingreso ' . $i->getIdIngreso() . ' · ' . $i->documento();
    $drawerSubtitulo = $i->getRazonSocial() . ' · RUC ' . $i->getRucEmisor();
    $drawerCerrar = '#fila-ing-' . $i->getIdIngreso();
    $drawerAncho = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <dl class="datos">
        <dt>Registrado</dt><dd><?= e($i->getFechaHora()) ?> por <?= e($nombresEmpleado[$i->getIdEmpleado()] ?? '-') ?></dd>
        <dt>Fecha del documento</dt><dd><?= e($i->getFechaDocumento()) ?></dd>
        <dt>Observación</dt><dd><?= e($i->getObservacion() ?? '-') ?></dd>
    </dl>
    <div class="tabla bloque">
    <table>
        <thead><tr><th scope="col">Producto</th><th scope="col" class="num">Cantidad</th><th scope="col" class="num">Costo unit.</th><th scope="col" class="num">Costo total</th><th scope="col">Vence</th></tr></thead>
        <tbody>
        <?php foreach ($fila['detalle'] as $d): ?>
            <?php $p = $productos[$d->getIdProducto()] ?? null; ?>
            <tr>
                <td><?= e($p?->getNombreProducto() ?? '-') ?></td>
                <td class="num"><?= e(number_format($d->getCantidad(), $p?->esAlPeso() ? 3 : 0)) ?></td>
                <td class="num"><?= e(soles($d->getCostoUnitario())) ?></td>
                <td class="num"><?= e(soles($d->getCostoTotal())) ?></td>
                <td class="fecha"><?= e($d->getFechaVencimiento() ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th scope="row" colspan="3">TOTAL</th><td class="num"><?= e(soles($i->getTotal())) ?></td><td></td></tr></tfoot>
    </table>
    </div>
    <p class="sutil">Cada producto entró al kardex como INGRESO DE MERCADERIA. El costo no cambia el precio de venta.</p>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($puedeRegistrar): ?>
    <?php
    $opciones = array_filter($productos, fn(Producto $p): bool => $p->estaActivo());
    $filasLineas = 6;
    $conCosto = true;
    $conVence = true;
    $tipoViejo = $valorViejo('tipo_documento');
    $drawerId = 'nuevo-ingreso';
    $drawerTitulo = 'Registrar ingreso';
    $drawerSubtitulo = 'Mercadería que llega a ' . $tienda->getNombreTienda();
    $drawerCerrar = '/?ruta=ingresos';
    $drawerAbierto = $error !== null; // si hubo un error, el formulario vuelve abierto con lo escrito
    $drawerAncho = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <form class="formulario" method="post" action="/?ruta=ingreso_registrar" data-confirmar="¿Registrar este ingreso? El stock de la tienda sube con estas cantidades." data-confirmar-boton="Sí, registrar">
        <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?= campoCsrf($csrf) ?>
        <fieldset>
            <legend>Documento que trae la mercadería</legend>
            <div class="campos">
                <div>
                    <label for="tipo_documento">Tipo</label>
                    <select id="tipo_documento" name="tipo_documento" required>
                        <?php foreach ($tiposDocumento as $tipo): ?>
                            <option value="<?= e($tipo) ?>" <?= $tipoViejo === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="numero_documento">Número <span class="ayuda">SERIE-NÚMERO, por ejemplo F001-00012345</span></label>
                    <input id="numero_documento" name="numero_documento" maxlength="13" required value="<?= e($valorViejo('numero_documento')) ?>">
                </div>
                <div>
                    <label for="fecha_documento">Fecha del documento</label>
                    <input id="fecha_documento" name="fecha_documento" type="date" max="<?= e($hoy) ?>" required value="<?= e($valorViejo('fecha_documento', $hoy)) ?>">
                </div>
                <div>
                    <label for="ruc_emisor">RUC del emisor <span class="ayuda">11 dígitos</span></label>
                    <input id="ruc_emisor" name="ruc_emisor" inputmode="numeric" maxlength="11" required value="<?= e($valorViejo('ruc_emisor')) ?>">
                </div>
            </div>
            <label for="razon_social">Razón social del emisor</label>
            <input id="razon_social" name="razon_social" maxlength="100" required value="<?= e($valorViejo('razon_social')) ?>">
        </fieldset>
        <h3>Productos que llegan</h3>
        <?php require __DIR__ . '/../parciales/lineas_producto.php'; ?>
        <label for="observacion">Observación <span class="ayuda">opcional</span></label>
        <input id="observacion" name="observacion" maxlength="200" value="<?= e($valorViejo('observacion')) ?>">
        <div class="botones-formulario">
            <button class="acento" type="submit"><?= icono('ingresos') ?>Registrar ingreso</button>
            <small>El costo es lo que pagó la tienda; no cambia el precio de venta.</small>
        </div>
    </form>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
