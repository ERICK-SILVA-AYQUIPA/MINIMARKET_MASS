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
<a class="volver" href="/?ruta=tiendas"><?= icono('volver') ?>Volver a tiendas</a>
<div class="cabecera">
    <div>
        <h1><?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Datos, cajas y series de tu tienda.</p>
    </div>
</div>
<?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="rejilla">

<form class="formulario panel" method="post" action="/?ruta=tienda_guardar">
    <h2>Datos de la tienda</h2>
    <?= campoCsrf($csrf) ?>
    <label for="nombre">Nombre</label>
    <input id="nombre" name="nombre" maxlength="60" size="40" required value="<?= e($valor('nombre', $tienda->getNombreTienda())) ?>">
    <label for="direccion">Dirección</label>
    <input id="direccion" name="direccion" maxlength="120" size="60" required value="<?= e($valor('direccion', $tienda->getDireccion())) ?>">
    <label for="telefono">Teléfono <span class="ayuda">opcional</span></label>
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
    <span class="ayuda">INACTIVA: no se abren turnos ni se vende, y no recibe traslados. Se puede volver a activar.</span>
    <div class="botones-formulario"><button class="acento" type="submit">Guardar</button></div>
</form>

<div class="panel">
    <h2>Cajas</h2>
    <div class="tabla">
    <table>
        <thead><tr><th scope="col">Caja</th><th scope="col">Estado</th><th scope="col"><span class="visualmente-oculto">Acción</span></th></tr></thead>
        <tbody>
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
                            <button class="peligro chico" type="submit">Desactivar</button>
                        <?php else: ?>
                            <input type="hidden" name="estado" value="ACTIVA">
                            <button class="chico" type="submit">Activar</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <form method="post" action="/?ruta=caja_crear">
        <?= campoCsrf($csrf) ?>
        <button class="secundario" type="submit"><?= icono('mas') ?>Agregar caja</button>
    </form>
</div>

<div class="panel">
    <h2>Series de comprobante</h2>
    <div class="tabla">
    <table>
        <thead><tr><th scope="col">Serie</th><th scope="col">Tipo</th><th scope="col" class="num">Último número emitido</th></tr></thead>
        <tbody>
        <?php foreach ($series as $s): ?>
            <tr>
                <td><?= e($s->getSerie()) ?></td>
                <td><?= $s->esFactura() ? 'Factura' : 'Boleta' ?></td>
                <td class="num"><?= $s->getUltimoNumero() ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="sutil">El número avanza solo con cada venta y no se edita a mano: así no hay huecos ni repetidos (consulta 23).</p>
</div>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
