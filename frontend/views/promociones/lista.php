<?php
declare(strict_types=1);
/**
 * V: promociones de la tienda (y las de toda la cadena). El administrador las crea desde un panel lateral
 * (#nueva-promocion) y las pausa o reactiva; el cajero solo las ve: se aplican solas en Nueva venta.
 * @var Tienda $tienda
 * @var string $hoy  AAAA-MM-DD
 * @var array<int, array{promocion: Promocion, producto: ?string, al_peso: bool, usos: int, descontado: int}> $filas
 * @var bool $puedeCrear
 * @var bool $puedeCadena
 * @var Producto[] $productos  los activos, para elegir
 * @var array<int, string> $nombresEmpleado
 * @var string $topeCajero  "10 %"
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Promociones';
require __DIR__ . '/../layout_inicio.php';
$v = fn(string $campo): string => is_string($viejo[$campo] ?? null) ? $viejo[$campo] : '';
$tipos = [
    'PORCENTAJE' => 'Producto con % de descuento',
    'PRECIO'     => 'Producto a precio de oferta',
    'PACK'       => 'Pack: N unidades por un precio',
    'VENTA'      => '% a toda la compra',
];
$claseSituacion = ['VIGENTE' => 'ok', 'PROGRAMADA' => 'info', 'VENCIDA' => 'neutra', 'PAUSADA' => 'alerta'];
$tipoViejo = isset($tipos[$v('tipo')]) ? $v('tipo') : 'PORCENTAJE';
?>
<div class="cabecera">
    <div>
        <h1>Promociones de <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil">Se aplican solas al vender mientras estén vigentes. Si un producto tiene varias, se usa la que más ahorra al cliente.</p>
    </div>
    <?php if ($puedeCrear): ?><div class="acciones"><a class="boton acento" href="#nueva-promocion"><?= icono('oferta') ?>Nueva promoción</a></div><?php endif; ?>
</div>
<?php
$guiaClave = 'promociones';
$guiaTitulo = 'Cómo funcionan los descuentos';
$guiaPasos = [
    'Promoción de producto: un % menos, un precio de oferta o un pack (por ejemplo 3 x S/ 10.00). Se ve en la foto del producto en Nueva venta.',
    'Promoción de toda la compra: un % desde un monto mínimo. No se suma con el descuento del cajero: se aplica el mayor de los dos.',
    'El cajero puede rebajar a mano hasta el ' . $topeCajero . ' de la compra con su motivo; queda en la Auditoría. Para más, crea una promoción.',
    'Pausar una promoción la quita al momento; las ventas ya hechas guardan su descuento.',
];
require __DIR__ . '/../parciales/guia.php';
?>

<?php if ($filas === []): ?>
    <?php
    $vacioTitulo = 'Todavía no hay promociones';
    $vacioTexto = $puedeCrear ? 'Crea la primera: una oferta de un producto, un pack o un descuento a toda la compra.' : 'Cuando el administrador cree una, aparecerá aquí y se aplicará sola al vender.';
    $vacioAccion = $puedeCrear ? ['#nueva-promocion', 'Nueva promoción'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Promoción</th><th scope="col">Producto</th><th scope="col">Descuento</th><th scope="col">Vigencia</th><th scope="col">Alcance</th>
            <th scope="col" class="num">Ventas</th><th scope="col" class="num">Ahorro dado</th><th scope="col">Estado</th><?php if ($puedeCrear): ?><th scope="col"><span class="visualmente-oculto">Acción</span></th><?php endif; ?></tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $fila): ?>
        <?php
        $pr = $fila['promocion'];
        $situacion = $pr->situacion($hoy);
        $propia = $puedeCrear && ($pr->getIdTienda() !== null || $puedeCadena);
        ?>
        <tr>
            <td><strong><?= e($pr->getNombre()) ?></strong><br><small class="sutil">Creó <?= e($nombresEmpleado[$pr->getIdEmpleado()] ?? '-') ?></small></td>
            <td><?= e($fila['producto'] ?? 'Toda la compra') ?></td>
            <td><?= e($pr->descripcion($fila['al_peso'])) ?></td>
            <td class="fecha"><?= e(fecha($pr->getFechaInicio())) ?> al <?= e(fecha($pr->getFechaFin())) ?></td>
            <td><?= $pr->getIdTienda() === null ? 'Toda la cadena' : 'Esta tienda' ?></td>
            <td class="num"><?= (int) $fila['usos'] ?></td>
            <td class="num"><?= e(soles($fila['descontado'] / 100)) ?></td>
            <td><span class="etiqueta <?= e($claseSituacion[$situacion]) ?>"><?= e(ucfirst(mb_strtolower($situacion))) ?></span></td>
            <?php if ($puedeCrear): ?>
            <td>
                <?php if ($propia && $situacion !== 'VENCIDA'): ?>
                    <form method="post" action="/?ruta=promocion_estado">
                        <?= campoCsrf($csrf) ?>
                        <input type="hidden" name="id_promocion" value="<?= $pr->getIdPromocion() ?>">
                        <input type="hidden" name="estado" value="<?= $pr->estaPausada() ? 'ACTIVA' : 'PAUSADA' ?>">
                        <button class="secundario chico" type="submit"><?= icono($pr->estaPausada() ? 'reanudar' : 'pausa') ?><?= $pr->estaPausada() ? 'Reactivar' : 'Pausar' ?></button>
                    </form>
                <?php endif; ?>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php if ($puedeCrear): ?>
    <?php
    $drawerId = 'nueva-promocion';
    $drawerTitulo = 'Nueva promoción';
    $drawerSubtitulo = $tienda->getNombreTienda();
    $drawerCerrar = '/?ruta=promociones';
    $drawerAbierto = $error !== null; // si hubo un error, vuelve abierto con lo escrito
    require __DIR__ . '/../parciales/drawer_abrir.php';
    ?>
    <form class="formulario" method="post" action="/?ruta=promocion_crear" data-promocion>
        <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?= campoCsrf($csrf) ?>
        <label for="promo-nombre">Nombre <span class="ayuda">lo ve el cliente en su ticket</span></label>
        <input id="promo-nombre" name="nombre" maxlength="60" required placeholder="Ej.: Semana del lácteo" value="<?= e($v('nombre')) ?>">

        <label for="promo-tipo">Tipo</label>
        <select id="promo-tipo" name="tipo" data-promo-tipo>
            <?php foreach ($tipos as $valorTipo => $textoTipo): ?>
                <option value="<?= e($valorTipo) ?>" <?= $tipoViejo === $valorTipo ? 'selected' : '' ?>><?= e($textoTipo) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="pago-medio" data-promo-campo="PORCENTAJE PRECIO PACK">
            <label for="promo-producto">Producto</label>
            <select id="promo-producto" name="id_producto">
                <option value="">Elige el producto</option>
                <?php foreach ($productos as $producto): ?>
                    <option value="<?= $producto->getIdProducto() ?>" <?= $v('id_producto') === (string) $producto->getIdProducto() ? 'selected' : '' ?>><?= e($producto->getNombreProducto()) ?> · <?= e(soles($producto->getPrecioVenta())) ?><?= $producto->esAlPeso() ? '/kg' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="pago-medio" data-promo-campo="PORCENTAJE VENTA">
            <label for="promo-porcentaje">Porcentaje de descuento <span class="ayuda">de 0.01 a 90</span></label>
            <input id="promo-porcentaje" name="porcentaje" inputmode="decimal" placeholder="15" value="<?= e($v('porcentaje')) ?>">
        </div>
        <div class="pago-medio" data-promo-campo="PACK">
            <label for="promo-pack">Unidades del pack <span class="ayuda">3 para un 3x2 o un "3 por S/ 10"</span></label>
            <input id="promo-pack" name="cantidad_pack" inputmode="numeric" maxlength="2" placeholder="3" value="<?= e($v('cantidad_pack')) ?>">
        </div>
        <div class="pago-medio" data-promo-campo="PRECIO PACK">
            <label for="promo-precio">Precio de oferta (S/) <span class="ayuda">por unidad o kg; en un pack, el precio del pack entero</span></label>
            <input id="promo-precio" name="precio" inputmode="decimal" value="<?= e($v('precio')) ?>">
        </div>
        <div class="pago-medio" data-promo-campo="VENTA">
            <label for="promo-minimo">Compra mínima (S/) <span class="ayuda">vacío = cualquier compra</span></label>
            <input id="promo-minimo" name="monto_minimo" inputmode="decimal" placeholder="100.00" value="<?= e($v('monto_minimo')) ?>">
        </div>

        <div class="descuento-valor">
            <div>
                <label for="promo-inicio">Desde</label>
                <input id="promo-inicio" name="fecha_inicio" type="date" required value="<?= e($v('fecha_inicio') ?: $hoy) ?>">
            </div>
            <div>
                <label for="promo-fin">Hasta <span class="ayuda">inclusive</span></label>
                <input id="promo-fin" name="fecha_fin" type="date" required value="<?= e($v('fecha_fin')) ?>">
            </div>
        </div>

        <?php if ($puedeCadena): ?>
        <fieldset class="sin-borde">
            <legend>Dónde rige</legend>
            <div class="opciones">
                <label><input type="radio" name="alcance" value="TIENDA" <?= $v('alcance') !== 'CADENA' ? 'checked' : '' ?>> Solo <?= e($tienda->getNombreTienda()) ?></label>
                <label><input type="radio" name="alcance" value="CADENA" <?= $v('alcance') === 'CADENA' ? 'checked' : '' ?>> Toda la cadena</label>
            </div>
        </fieldset>
        <?php endif; ?>

        <div class="botones-formulario">
            <button class="acento" type="submit"><?= icono('oferta') ?>Crear promoción</button>
            <small>Queda en la Auditoría con tu nombre. Puedes pausarla cuando quieras.</small>
        </div>
    </form>
    <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
