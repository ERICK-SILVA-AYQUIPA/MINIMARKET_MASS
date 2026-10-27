<?php
declare(strict_types=1);
/**
 * V: ingresos de mercaderia de la tienda del usuario y formulario para su almacenero.
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
<h1>Ingresos de mercadería de <?= e($tienda->getNombreTienda()) ?></h1>
<p>La mercadería que llega a la tienda entra con su documento (guía de remisión, factura o boleta del emisor).
   El stock sube al registrar, cada producto queda en el kardex y el ingreso en la auditoría.
   <a href="/?ruta=ingresos_csv">Descargar en CSV (Excel)</a></p>

<?php if ($puedeRegistrar): ?>
    <?php
    $opciones = array_filter($productos, fn(Producto $p): bool => $p->estaActivo());
    $filasLineas = 6;
    $conCosto = true;
    $tipoViejo = $valorViejo('tipo_documento');
    ?>
    <form class="formulario panel" method="post" action="/?ruta=ingreso_registrar">
        <h2>Registrar ingreso</h2>
        <?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>
        <?= campoCsrf($csrf) ?>
        <label for="tipo_documento">Documento</label>
        <select id="tipo_documento" name="tipo_documento" required>
            <?php foreach ($tiposDocumento as $tipo): ?>
                <option value="<?= e($tipo) ?>" <?= $tipoViejo === $tipo ? 'selected' : '' ?>><?= e($tipo) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="numero_documento">Número (SERIE-NÚMERO, por ejemplo F001-00012345)</label>
        <input id="numero_documento" name="numero_documento" maxlength="13" required size="16" value="<?= e($valorViejo('numero_documento')) ?>">
        <label for="fecha_documento">Fecha del documento</label>
        <input id="fecha_documento" name="fecha_documento" type="date" max="<?= e($hoy) ?>" required value="<?= e($valorViejo('fecha_documento', $hoy)) ?>">
        <label for="ruc_emisor">RUC del emisor (11 dígitos)</label>
        <input id="ruc_emisor" name="ruc_emisor" inputmode="numeric" maxlength="11" required size="13" value="<?= e($valorViejo('ruc_emisor')) ?>">
        <label for="razon_social">Razón social del emisor</label>
        <input id="razon_social" name="razon_social" maxlength="100" required size="50" value="<?= e($valorViejo('razon_social')) ?>">
        <?php require __DIR__ . '/../parciales/lineas_producto.php'; ?>
        <label for="observacion">Observación (opcional)</label>
        <input id="observacion" name="observacion" maxlength="200" size="60" value="<?= e($valorViejo('observacion')) ?>">
        <p><button type="submit">Registrar ingreso</button>
           <small>El costo es lo que pagó la tienda; no cambia el precio de venta.</small></p>
    </form>
<?php endif; ?>

<div class="tabla">
<table>
    <tr><th>#</th><th>Registrado</th><th>Documento</th><th>Emisor</th><th>Productos</th><th>Total</th><th>Registró</th></tr>
    <?php foreach ($filas as $fila): ?>
        <?php $i = $fila['ingreso']; ?>
        <tr>
            <td><?= $i->getIdIngreso() ?></td>
            <td><?= e($i->getFechaHora()) ?></td>
            <td><?= e($i->documento()) ?><br><small>del <?= e($i->getFechaDocumento()) ?></small></td>
            <td><?= e($i->getRazonSocial()) ?><br><small>RUC <?= e($i->getRucEmisor()) ?></small></td>
            <td>
                <?php foreach ($fila['detalle'] as $d): ?>
                    <?php $p = $productos[$d->getIdProducto()] ?? null; ?>
                    <?= e(number_format($d->getCantidad(), $p?->esAlPeso() ? 3 : 0)) ?> · <?= e($p?->getNombreProducto() ?? '-') ?>
                    × <?= e(soles($d->getCostoUnitario())) ?> = <?= e(soles($d->getCostoTotal())) ?><br>
                <?php endforeach; ?>
                <?php if ($i->getObservacion() !== null): ?><small><?= e($i->getObservacion()) ?></small><?php endif; ?>
            </td>
            <td class="num"><?= e(soles($i->getTotal())) ?></td>
            <td><?= e($nombresEmpleado[$i->getIdEmpleado()] ?? '-') ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</div>
<?php require __DIR__ . '/../parciales/paginacion.php'; ?>
<p><?= $paginacion->getTotalFilas() ?> ingresos.</p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
