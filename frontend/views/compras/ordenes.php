<?php
declare(strict_types=1);
/**
 * V: ordenes de compra de la tienda del usuario. Cada orden abre su detalle en un panel (#oc-ID) con lo pedido,
 * lo que ya llego y lo que falta; desde ahi se recibe (Ingresos), se imprime o se cancela.
 * La orden nueva va en otro panel (#nueva-orden); desde Reponer llega llena con lo sugerido.
 * @var Tienda $tienda
 * @var ?string $estado
 * @var array<string, string> $estados
 * @var array<int, Proveedor> $proveedores
 * @var array<int, Producto> $productos
 * @var array<int, string> $nombresEmpleado
 * @var string $hoy
 * @var bool $puedePedir
 * @var bool $puedeRecibir
 * @var bool $puedeCancelar
 * @var Paginacion $paginacion
 * @var array<int, array{orden: OrdenCompra, detalle: array<int, DetalleOrdenCompra>, ingresos: array<int, array{id_ingreso: int, fecha_hora: string, documento: string, total: float, id_empleado: int}>}> $filas
 * @var int $abiertas
 * @var string $urlPagina
 * @var bool $abrirNueva
 * @var ?Proveedor $proveedorElegido
 * @var int $sugeridos
 * @var ?string $desde
 * @var int $filasNueva
 * @var array $viejo
 * @var ?string $error
 * @var ?string $errorOrden
 */
$titulo = 'Órdenes de compra';
require __DIR__ . '/../layout_inicio.php';
$valorViejo = fn(string $clave, string $porDefecto = ''): string => is_string($viejo[$clave] ?? null) ? $viejo[$clave] : $porDefecto;
$claseEstado = ['PENDIENTE' => 'info', 'PARCIAL' => 'alerta', 'RECIBIDA' => 'ok', 'ANULADA' => 'anulada', 'CERRADA' => 'neutra'];
?>
<div class="cabecera">
    <div>
        <h1>Órdenes de compra</h1>
        <p class="sutil"><?= e($tienda->getNombreTienda()) ?> · <?= (int) $abiertas ?> abierta(s) esperando mercadería</p>
    </div>
    <div class="acciones">
        <a class="boton secundario" href="/?ruta=reponer"><?= icono('alerta') ?>Ver qué reponer</a>
        <?php if ($puedePedir): ?><a class="boton acento" href="#nueva-orden"><?= icono('mas') ?>Nueva orden</a><?php endif; ?>
    </div>
</div>
<?php
$guiaClave = 'ordenes';
$guiaTitulo = 'Del pedido a la mercadería en el estante';
$guiaPasos = [
    'Arma la orden desde Reponer (llega llena con lo sugerido) o a mano: proveedor, productos, cantidad y costo pactado.',
    'Imprímela o guárdala en PDF y envíasela al proveedor. La orden no cambia el stock.',
    'Cuando llegue el pedido, abre la orden y pulsa Recibir: anotas el documento y lo que de verdad llegó. Ahí sube el stock y queda en el kardex.',
    'Si el proveedor no va a mandar lo que falta, el administrador cierra la orden. Si no llegó nada, la anula.',
];
require __DIR__ . '/../parciales/guia.php';
?>
<?php if ($errorOrden !== null): ?><div class="aviso error" role="alert"><?= e($errorOrden) ?></div><?php endif; ?>

<form class="filtros" method="get" action="/">
    <input type="hidden" name="ruta" value="ordenes">
    <label>Estado
        <select name="estado">
            <option value="">Todas</option>
            <?php foreach ($estados as $valor => $texto): ?>
                <option value="<?= e($valor) ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Filtrar</button>
    <?php if ($estado !== null): ?><a class="boton secundario" href="/?ruta=ordenes">Quitar filtro</a><?php endif; ?>
</form>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = $estado !== null ? 'No hay órdenes en ese estado' : 'Todavía no hay órdenes de compra';
    $vacioTexto = $puedePedir ? 'Arma la primera desde Reponer: llega llena con lo que hace falta.' : 'El almacenero arma aquí los pedidos a los proveedores.';
    $vacioAccion = $puedePedir && $estado === null ? ['/?ruta=ordenes&desde=reponer', 'Armar orden con lo sugerido'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Orden</th><th scope="col">Emitida</th><th scope="col">Proveedor</th><th scope="col">Entrega</th><th scope="col" class="num">Llegó</th><th scope="col" class="num">Total</th><th scope="col">Estado</th><th scope="col"><span class="visualmente-oculto">Detalle</span></th></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php
        $o = $fila['orden'];
        $completos = count(array_filter($fila['detalle'], fn(DetalleOrdenCompra $d): bool => $d->pendiente() === 0));
        ?>
        <tr id="fila-oc-<?= $o->getIdOrdenCompra() ?>">
            <td class="codigo"><?= e($o->numero()) ?></td>
            <td class="fecha"><?= e(fecha($o->getFechaHora())) ?></td>
            <td><?= e(($proveedores[$o->getIdProveedor()] ?? null)?->getRazonSocial() ?? '-') ?></td>
            <td class="fecha"><?= e(fecha($o->getFechaEntrega())) ?><?php if ($o->estaAtrasada($hoy)): ?> <span class="etiqueta reponer">atrasada</span><?php endif; ?></td>
            <td class="num"><?= (int) $completos ?> de <?= (int) count($fila['detalle']) ?></td>
            <td class="num"><?= e(soles($o->getTotal())) ?></td>
            <td><span class="etiqueta <?= e($claseEstado[$o->getEstado()]) ?>"><?= e($o->getEstado()) ?></span></td>
            <td><a class="boton secundario chico" href="#oc-<?= $o->getIdOrdenCompra() ?>"><?= icono('ver') ?>Ver</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../parciales/paginacion.php'; ?>

<?php foreach ($filas as $fila): ?>
    <?php
    $o = $fila['orden'];
    $proveedor = $proveedores[$o->getIdProveedor()] ?? null;
    $drawerId = 'oc-' . $o->getIdOrdenCompra();
    $drawerTitulo = 'Orden ' . $o->numero() . ' · ' . $o->getEstado();
    $drawerSubtitulo = ($proveedor?->getRazonSocial() ?? '-') . ' · RUC ' . ($proveedor?->getRuc() ?? '-');
    $drawerCerrar = '#fila-oc-' . $o->getIdOrdenCompra();
    $drawerAbierto = false;
    $drawerAncho = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <dl class="datos">
        <dt>Emitida</dt><dd><?= e(fecha($o->getFechaHora())) ?> por <?= e($nombresEmpleado[$o->getIdEmpleado()] ?? '-') ?></dd>
        <dt>Entrega esperada</dt><dd><?= e(fecha($o->getFechaEntrega())) ?><?php if ($o->estaAtrasada($hoy)): ?> <span class="etiqueta reponer">atrasada</span><?php endif; ?></dd>
        <?php if ($proveedor !== null && ($proveedor->getContacto() !== null || $proveedor->getTelefono() !== null)): ?>
            <dt>Contacto</dt><dd><?= e(implode(' · ', array_filter([$proveedor->getContacto(), $proveedor->getTelefono(), $proveedor->getCorreo()]))) ?></dd>
        <?php endif; ?>
        <dt>Observación</dt><dd><?= e($o->getObservacion() ?? '-') ?></dd>
        <?php if ($o->getFechaCierre() !== null): ?>
            <dt><?= $o->getEstado() === 'ANULADA' ? 'Anulada' : 'Cerrada' ?></dt>
            <dd><?= e(fecha($o->getFechaCierre())) ?> por <?= e($nombresEmpleado[$o->getIdEmpleadoCierre() ?? 0] ?? '-') ?>: <?= e($o->getMotivoCierre() ?? '') ?></dd>
        <?php endif; ?>
    </dl>
    <div class="tabla bloque">
    <table>
        <thead><tr><th scope="col">Producto</th><th scope="col" class="num">Pedido</th><th scope="col" class="num">Llegó</th><th scope="col" class="num">Falta</th><th scope="col" class="num">Costo unit.</th><th scope="col" class="num">Costo total</th></tr></thead>
        <tbody>
        <?php foreach ($fila['detalle'] as $idProducto => $d): ?>
            <?php $p = $productos[$idProducto] ?? null; $alPeso = $p?->esAlPeso() === true; ?>
            <tr>
                <td><?= e($p?->getNombreProducto() ?? '-') ?></td>
                <td class="num"><?= e(cantidad($d->getPedida(), $alPeso)) ?></td>
                <td class="num"><?= e(cantidad($d->getRecibida(), $alPeso)) ?></td>
                <td class="num"><?php if ($d->pendiente() > 0 && $o->estaAbierta()): ?><strong><?= e(cantidad($d->pendiente(), $alPeso)) ?></strong><?php elseif ($d->pendiente() > 0): ?><span class="sutil"><?= e(cantidad($d->pendiente(), $alPeso)) ?> (no vendrá)</span><?php else: ?><span class="sutil">—</span><?php endif; ?></td>
                <td class="num"><?= e(soles($d->getCostoUnitario() / 100)) ?></td>
                <td class="num"><?= e(soles($d->getCostoTotal() / 100)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th scope="row" colspan="5">TOTAL PEDIDO</th><td class="num"><?= e(soles($o->getTotal())) ?></td></tr></tfoot>
    </table>
    </div>
    <?php if ($fila['ingresos'] !== []): ?>
        <h3>Mercadería recibida</h3>
        <ul class="lista-movimientos">
            <?php foreach ($fila['ingresos'] as $ing): ?>
                <li><a href="/?ruta=ingresos#ing-<?= (int) $ing['id_ingreso'] ?>">Ingreso <?= (int) $ing['id_ingreso'] ?></a> · <?= e($ing['documento']) ?> · <?= e(fecha($ing['fecha_hora'])) ?> · <?= e(soles($ing['total'])) ?> · <?= e($nombresEmpleado[$ing['id_empleado']] ?? '-') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <div class="botones-formulario">
        <?php if ($o->estaAbierta() && $puedeRecibir): ?><a class="boton acento" href="/?ruta=ingresos&amp;orden=<?= $o->getIdOrdenCompra() ?>#nuevo-ingreso"><?= icono('ingresos') ?>Recibir mercadería</a><?php endif; ?>
        <a class="boton secundario" href="/?ruta=orden_imprimir&amp;id=<?= $o->getIdOrdenCompra() ?>" target="_blank" rel="noopener"><?= icono('imprimir') ?>Imprimir o PDF</a>
    </div>
    <?php if ($o->estaAbierta() && $puedeCancelar): ?>
        <?php $anular = $o->getEstado() === 'PENDIENTE'; ?>
        <form class="formulario bloque" method="post" action="/?ruta=orden_cancelar"
              data-confirmar="<?= e($anular ? '¿Anular la orden ' . $o->numero() . '? No llegó nada y ya no se espera.' : '¿Cerrar la orden ' . $o->numero() . '? Lo que llegó se mantiene y lo que falta ya no se espera.') ?>"
              data-confirmar-boton="<?= $anular ? 'Sí, anular' : 'Sí, cerrar' ?>">
            <?= campoCsrf($csrf) ?>
            <input type="hidden" name="id" value="<?= $o->getIdOrdenCompra() ?>">
            <label for="motivo-<?= $o->getIdOrdenCompra() ?>"><?= $anular ? 'Anular la orden' : 'Cerrar lo que falta' ?> <span class="ayuda">motivo, queda en la auditoría</span></label>
            <input id="motivo-<?= $o->getIdOrdenCompra() ?>" name="motivo" maxlength="200" required>
            <button class="secundario" type="submit"><?= $anular ? 'Anular orden' : 'Cerrar orden' ?></button>
        </form>
    <?php endif; ?>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($puedePedir): ?>
    <?php
    $activos = array_filter($proveedores, fn(Proveedor $p): bool => $p->estaActivo());
    $opciones = array_filter($productos, fn(Producto $p): bool => $p->estaActivo());
    $filasLineas = $filasNueva;
    $conCosto = true;
    $conVence = false;
    $proveedorViejo = $proveedorElegido !== null ? (string) $proveedorElegido->getIdProveedor() : $valorViejo('proveedor');
    $drawerId = 'nueva-orden';
    $drawerTitulo = 'Nueva orden de compra';
    $drawerSubtitulo = 'Para ' . $tienda->getNombreTienda();
    $drawerCerrar = '/?ruta=ordenes';
    $drawerAbierto = $abrirNueva;
    $drawerAncho = true;
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <?php if ($activos === []): ?>
        <p>No hay proveedores activos. <a href="/?ruta=proveedores">Agrega uno en Proveedores</a> para pedirle mercadería.</p>
    <?php else: ?>
    <form class="formulario" method="post" action="/?ruta=orden_registrar" data-confirmar="¿Emitir esta orden de compra? El stock no cambia hasta que llegue la mercadería." data-confirmar-boton="Sí, emitir">
        <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($desde === 'reponer' && $error === null): ?>
            <div class="aviso <?= $sugeridos > 0 ? 'ok' : 'alerta' ?>" role="status">
                <?php if ($sugeridos > 0): ?>
                    Llena con <?= (int) $sugeridos ?> producto(s) de Reponer<?php if ($proveedorElegido !== null): ?> que se le compraron por última vez a <?= e($proveedorElegido->getRazonSocial()) ?><?php endif; ?>,
                    descontando lo que ya está pedido. Revisa cantidades y costos antes de emitir.
                <?php else: ?>
                    No hay nada sugerido<?php if ($proveedorElegido !== null): ?> para <?= e($proveedorElegido->getRazonSocial()) ?><?php endif; ?>: lo que falta ya está pedido o no llegó a su mínimo.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?= campoCsrf($csrf) ?>
        <div class="campos">
            <div>
                <label for="proveedor">Proveedor</label>
                <select id="proveedor" name="proveedor" required>
                    <option value="">— Elegir proveedor —</option>
                    <?php foreach ($activos as $idP => $p): ?>
                        <option value="<?= (int) $idP ?>" <?= $proveedorViejo === (string) $idP ? 'selected' : '' ?>><?= e($p->getRazonSocial()) ?> · RUC <?= e($p->getRuc()) ?><?php if ($p->getDiasEntrega() !== null): ?> · entrega en <?= (int) $p->getDiasEntrega() ?> d<?php endif; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="fecha_entrega">Entrega esperada <span class="ayuda">opcional</span></label>
                <input id="fecha_entrega" name="fecha_entrega" type="date" min="<?= e($hoy) ?>" value="<?= e($valorViejo('fecha_entrega')) ?>">
            </div>
        </div>
        <h3>Productos que se piden</h3>
        <?php require __DIR__ . '/../parciales/lineas_producto.php'; ?>
        <label for="observacion">Observación <span class="ayuda">opcional: condiciones, forma de pago</span></label>
        <input id="observacion" name="observacion" maxlength="200" value="<?= e($valorViejo('observacion')) ?>">
        <div class="botones-formulario">
            <button class="acento" type="submit"><?= icono('orden') ?>Emitir orden</button>
            <small>El costo es el pactado con el proveedor. Si al recibir la factura trae otro, se anota el de la factura.</small>
        </div>
    </form>
    <?php endif; ?>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
