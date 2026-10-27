<?php
declare(strict_types=1);
/**
 * V: datos, cajas y series de la tienda del administrador.
 * @var Tienda $tienda
 * @var array<int, string> $distritos
 * @var Caja[] $cajas
 * @var SerieComprobante[] $series
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Mi tienda';
require __DIR__ . '/../layout_inicio.php';
$valor = static fn(string $campo, string $guardado): string => is_string($viejo[$campo] ?? null) ? $viejo[$campo] : $guardado;
$distritoElegido = $valor('distrito', (string) $tienda->getIdDistrito());
$estadoElegido = $valor('estado', $tienda->getEstado());
?>
<p><a href="/?ruta=tiendas">← Volver a tiendas</a></p>
<h1><?= e($tienda->getNombreTienda()) ?></h1>
<?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>

<form class="formulario panel" method="post" action="/?ruta=tienda_guardar">
    <h2>Datos de la tienda</h2>
    <?= campoCsrf($csrf) ?>
    <label for="nombre">Nombre</label>
    <input id="nombre" name="nombre" maxlength="60" size="40" required value="<?= e($valor('nombre', $tienda->getNombreTienda())) ?>">
    <label for="direccion">Dirección</label>
    <input id="direccion" name="direccion" maxlength="120" size="60" required value="<?= e($valor('direccion', $tienda->getDireccion())) ?>">
    <label for="telefono">Teléfono (opcional)</label>
    <input id="telefono" name="telefono" maxlength="15" value="<?= e($valor('telefono', $tienda->getTelefono() ?? '')) ?>">
    <label for="distrito">Distrito</label>
    <select id="distrito" name="distrito" required>
        <?php foreach ($distritos as $id => $nombre): ?>
            <option value="<?= $id ?>" <?= (string) $id === $distritoElegido ? 'selected' : '' ?>><?= e($nombre) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="estado">Estado</label>
    <select id="estado" name="estado">
        <?php foreach (['ACTIVA', 'INACTIVA'] as $estado): ?>
            <option value="<?= e($estado) ?>" <?= $estadoElegido === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
        <?php endforeach; ?>
    </select>
    <p>INACTIVA: no se abren turnos ni se vende, y no recibe traslados. Se puede volver a activar.</p>
    <p><button type="submit">Guardar</button></p>
</form>

<div class="panel">
    <h2>Cajas</h2>
    <table>
        <tr><th>Caja</th><th>Estado</th><th></th></tr>
        <?php foreach ($cajas as $caja): ?>
            <tr>
                <td>Caja <?= $caja->getNumeroCaja() ?></td>
                <td><span class="etiqueta <?= $caja->getEstado() === 'ACTIVA' ? 'ok' : 'anulada' ?>"><?= e($caja->getEstado()) ?></span></td>
                <td>
                    <form method="post" action="/?ruta=caja_estado">
                        <?= campoCsrf($csrf) ?>
                        <input type="hidden" name="id" value="<?= $caja->getIdCaja() ?>">
                        <?php if ($caja->getEstado() === 'ACTIVA'): ?>
                            <input type="hidden" name="estado" value="INACTIVA">
                            <button class="peligro" type="submit">Desactivar</button>
                        <?php else: ?>
                            <input type="hidden" name="estado" value="ACTIVA">
                            <button type="submit">Activar</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <form method="post" action="/?ruta=caja_crear">
        <?= campoCsrf($csrf) ?>
        <p><button type="submit">+ Agregar caja</button></p>
    </form>
</div>

<div class="panel">
    <h2>Series de comprobante</h2>
    <table>
        <tr><th>Serie</th><th>Tipo</th><th>Último número emitido</th></tr>
        <?php foreach ($series as $s): ?>
            <tr>
                <td><?= e($s->getSerie()) ?></td>
                <td><?= $s->esFactura() ? 'Factura' : 'Boleta' ?></td>
                <td class="num"><?= $s->getUltimoNumero() ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p>El número avanza solo con cada venta y no se edita a mano: así no hay huecos ni repetidos (consulta 23).</p>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
