<?php
declare(strict_types=1);
/**
 * V: las tiendas con su distrito, cajas, series y personal.
 * @var array<int, array{tienda: Tienda, distrito: string, cajas: Caja[], series: SerieComprobante[], empleados: Usuario[]}> $filas
 * @var bool $puedeAbrir  administrador: abrir tiendas nuevas
 * @var ?Usuario $usuarioActual
 */
$titulo = 'Tiendas';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="cabecera">
    <div>
        <h1>Tiendas de Arequipa</h1>
        <p class="sutil"><?= count($filas) ?> tiendas con sus cajas, series y personal.</p>
    </div>
    <?php if ($puedeAbrir): ?>
        <div class="acciones">
            <a class="boton secundario" href="/?ruta=tienda_editar"><?= icono('editar') ?>Mi tienda, cajas y series</a>
            <a class="boton acento" href="/?ruta=tienda_nueva"><?= icono('mas') ?>Abrir una tienda</a>
        </div>
    <?php endif; ?>
</div>

<?php foreach ($filas as $fila): ?>
    <?php $t = $fila['tienda']; ?>
    <section class="panel">
        <div class="titulo-seccion">
            <h2><?= e($t->getNombreTienda()) ?></h2>
            <span class="etiqueta <?= $t->estaActiva() ? 'ok' : 'neutra' ?>"><?= e($t->getEstado()) ?></span>
        </div>
        <dl class="datos">
            <dt>Dirección</dt><dd><?= e($t->getDireccion()) ?> · <?= e($fila['distrito']) ?></dd>
            <dt>Teléfono</dt><dd><?= e($t->getTelefono() ?? '(no registrado)') ?></dd>
            <dt>Cajas</dt>
            <dd><ul class="lista-chips">
                <?php foreach ($fila['cajas'] as $c): ?>
                    <li>Caja <?= $c->getNumeroCaja() ?> · <?= e(strtolower($c->getEstado())) ?></li>
                <?php endforeach; ?>
            </ul></dd>
            <dt>Series</dt>
            <dd><ul class="lista-chips">
                <?php foreach ($fila['series'] as $s): ?>
                    <li><?= e($s->getSerie()) ?> <?= e($s->esFactura() ? 'factura' : 'boleta') ?> · último <?= $s->getUltimoNumero() ?></li>
                <?php endforeach; ?>
            </ul></dd>
        </dl>
        <h3 class="bloque">Personal y permisos</h3>
        <div class="tabla">
        <table>
            <thead><tr><th scope="col">Empleado</th><th scope="col">DNI</th><th scope="col">Rol</th><th scope="col">Vende</th><th scope="col">Anula ventas de su tienda</th><th scope="col">Traslados y mermas</th></tr></thead>
            <tbody>
            <?php foreach ($fila['empleados'] as $u): ?>
                <tr>
                    <td><?= e($u->nombreCompleto()) ?></td>
                    <td><?= e($u->dniOculto()) ?></td>
                    <td><?= e($u->getNombreRol()) ?></td>
                    <td><?= $u->puedeVender() ? '<span class="etiqueta ok">Sí</span>' : '<span class="sutil">No</span>' ?></td>
                    <td><?= $u->puedeAnularVenta($t->getIdTienda()) ? '<span class="etiqueta ok">Sí</span>' : '<span class="sutil">No</span>' ?></td>
                    <td><?= $u->puedeMoverMercaderia() ? '<span class="etiqueta ok">Sí</span>' : '<span class="sutil">No</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
<?php endforeach; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
