<?php
declare(strict_types=1);
/**
 * V: directorio de proveedores de la cadena, con lo comprado a cada uno en la tienda del usuario.
 * El administrador agrega (#nuevo-proveedor) y corrige (#prov-ID) en paneles laterales.
 * @var Tienda $tienda
 * @var array<int, Proveedor> $proveedores
 * @var array<int, array{ingresos: int, comprado: float, ultimo: ?string, abiertas: int}> $resumen
 * @var bool $puedeEditar
 * @var bool $puedePedir
 * @var int $activos
 * @var array $viejo
 * @var ?string $error
 * @var ?int $idConError  el proveedor cuyo formulario volvio con error (null: el de alta)
 */
$titulo = 'Proveedores';
require __DIR__ . '/../layout_inicio.php';
$viejoTexto = static fn(string $campo, string $porDefecto = ''): string => is_string($viejo[$campo] ?? null) ? $viejo[$campo] : $porDefecto;
?>
<div class="cabecera">
    <div>
        <h1>Proveedores</h1>
        <p class="sutil"><?= (int) $activos ?> activos de <?= count($proveedores) ?> · lo comprado es en <?= e($tienda->getNombreTienda()) ?></p>
    </div>
    <div class="acciones">
        <?php if ($puedePedir): ?><a class="boton secundario" href="/?ruta=ordenes&amp;nueva=1"><?= icono('orden') ?>Nueva orden de compra</a><?php endif; ?>
        <?php if ($puedeEditar): ?><a class="boton acento" href="#nuevo-proveedor"><?= icono('mas') ?>Agregar proveedor</a><?php endif; ?>
    </div>
</div>
<?php
$guiaClave = 'proveedores';
$guiaTitulo = 'Para qué sirve el directorio de proveedores';
$guiaPasos = [
    'Cada proveedor tiene su RUC, a quién llamar y cuántos días tarda en entregar. Con eso se arma la orden de compra.',
    'Si llega mercadería de un RUC nuevo, el ingreso lo agrega solo; el administrador completa luego su contacto.',
    'Un proveedor inactivo ya no recibe órdenes nuevas, pero lo que se le compró sigue en los ingresos y el kardex.',
];
require __DIR__ . '/../parciales/guia.php';
?>
<?php // Un error sin formulario que volver a mostrar (activar, desactivar o un proveedor que no existe) va arriba ?>
<?php if ($error !== null && ($viejo === [] || ($idConError !== null && !isset($proveedores[$idConError])))): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>

<?php if ($proveedores === []): ?>
    <?php
    $vacioTitulo = 'Todavía no hay proveedores';
    $vacioTexto = $puedeEditar ? 'Agrega a quién le compra la cadena para armar órdenes de compra.' : 'El administrador agrega aquí a quién le compra la cadena.';
    $vacioAccion = $puedeEditar ? ['#nuevo-proveedor', 'Agregar el primer proveedor'] : null;
    require __DIR__ . '/../parciales/vacio.php';
    ?>
<?php else: ?>
<?php
$buscadorTabla = 'filas-proveedores';
$buscadorTexto = 'Buscar por razón social o RUC';
require __DIR__ . '/../parciales/buscador.php';
?>
<div class="tabla">
<table>
    <thead>
        <tr><th scope="col">Proveedor</th><th scope="col">Contacto</th><th scope="col" class="num">Entrega</th><th scope="col" class="num">Comprado aquí</th><th scope="col">Última compra</th><th scope="col" class="num">Órdenes abiertas</th><th scope="col">Estado</th><th scope="col"><span class="visualmente-oculto">Acciones</span></th></tr>
    </thead>
    <tbody id="filas-proveedores">
    <?php foreach ($proveedores as $id => $p): ?>
        <?php $r = $resumen[$id] ?? ['ingresos' => 0, 'comprado' => 0.0, 'ultimo' => null, 'abiertas' => 0]; ?>
        <tr id="fila-prov-<?= (int) $id ?>">
            <td><?= e($p->getRazonSocial()) ?><br><small>RUC <?= e($p->getRuc()) ?></small></td>
            <td><?= e($p->getContacto() ?? '-') ?><?php if ($p->getTelefono() !== null || $p->getCorreo() !== null): ?><br><small><?= e(implode(' · ', array_filter([$p->getTelefono(), $p->getCorreo()]))) ?></small><?php endif; ?></td>
            <td class="num"><?= $p->getDiasEntrega() === null ? '<span class="sutil">-</span>' : e($p->getDiasEntrega() === 1 ? '1 día' : $p->getDiasEntrega() . ' días') ?></td>
            <td class="num"><?= e(soles($r['comprado'])) ?><br><small><?= (int) $r['ingresos'] ?> ingreso(s)</small></td>
            <td class="fecha"><?= e(fecha($r['ultimo'])) ?></td>
            <td class="num"><?php if ($r['abiertas'] > 0): ?><a href="/?ruta=ordenes&amp;estado=ABIERTAS"><?= (int) $r['abiertas'] ?></a><?php else: ?><span class="sutil">0</span><?php endif; ?></td>
            <td><span class="etiqueta <?= $p->estaActivo() ? 'ok' : 'anulada' ?>"><?= e($p->getEstado()) ?></span></td>
            <td>
                <?php if ($puedePedir && $p->estaActivo()): ?><a class="boton secundario chico" href="/?ruta=ordenes&amp;nueva=1&amp;proveedor=<?= (int) $id ?>"><?= icono('orden') ?>Pedir</a><?php endif; ?>
                <?php if ($puedeEditar): ?><a class="boton secundario chico" href="#prov-<?= (int) $id ?>"><?= icono('editar') ?>Editar</a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php if ($puedeEditar): ?>
    <?php
    // Un formulario por proveedor (editar) y uno para el alta; el que volvio con error se abre con lo escrito
    $formularios = [0 => null] + $proveedores;   // 0: el alta (ningun proveedor tiene id 0)
    ?>
    <?php foreach ($formularios as $idForm => $p): ?>
        <?php
        $esNuevo = $p === null;
        $conError = $error !== null && $viejo !== [] && $idConError === ($esNuevo ? null : $idForm);
        $valor = static fn(string $campo, ?string $actual) => $conError ? $viejoTexto($campo) : (string) $actual;
        $sufijo = $esNuevo ? 'nuevo' : (string) $idForm;
        $drawerId = $esNuevo ? 'nuevo-proveedor' : 'prov-' . $idForm;
        $drawerTitulo = $esNuevo ? 'Agregar proveedor' : $p->getRazonSocial();
        $drawerSubtitulo = $esNuevo ? 'Queda disponible para las órdenes de compra de todas las tiendas' : 'RUC ' . $p->getRuc();
        $drawerCerrar = $esNuevo ? '/?ruta=proveedores' : '#fila-prov-' . $idForm;
        $drawerAbierto = $conError;
        $drawerAncho = false;
        require __DIR__ . '/../parciales/drawer_abrir.php';
        ?>
        <form class="formulario" method="post" action="/?ruta=proveedor_guardar" autocomplete="off">
            <?php if ($conError): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <?= campoCsrf($csrf) ?>
            <?php if ($esNuevo): ?>
                <label for="ruc-<?= e($sufijo) ?>">RUC <span class="ayuda">11 dígitos; no se cambia después</span></label>
                <input id="ruc-<?= e($sufijo) ?>" name="ruc" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" required value="<?= e($valor('ruc', '')) ?>">
            <?php else: ?>
                <input type="hidden" name="id" value="<?= (int) $idForm ?>">
            <?php endif; ?>
            <label for="razon-<?= e($sufijo) ?>">Razón social</label>
            <input id="razon-<?= e($sufijo) ?>" name="razon_social" maxlength="100" required value="<?= e($valor('razon_social', $p?->getRazonSocial())) ?>">
            <label for="contacto-<?= e($sufijo) ?>">Contacto <span class="ayuda">opcional: vendedor o quien toma los pedidos</span></label>
            <input id="contacto-<?= e($sufijo) ?>" name="contacto" maxlength="80" value="<?= e($valor('contacto', $p?->getContacto())) ?>">
            <div class="campos">
                <div>
                    <label for="telefono-<?= e($sufijo) ?>">Teléfono <span class="ayuda">opcional</span></label>
                    <input id="telefono-<?= e($sufijo) ?>" name="telefono" inputmode="tel" maxlength="15" value="<?= e($valor('telefono', $p?->getTelefono())) ?>">
                </div>
                <div>
                    <label for="dias-<?= e($sufijo) ?>">Días de entrega <span class="ayuda">opcional, 0 a 90</span></label>
                    <input id="dias-<?= e($sufijo) ?>" name="dias_entrega" inputmode="numeric" maxlength="2" value="<?= e($valor('dias_entrega', $p?->getDiasEntrega() === null ? '' : (string) $p->getDiasEntrega())) ?>">
                </div>
            </div>
            <label for="correo-<?= e($sufijo) ?>">Correo <span class="ayuda">opcional</span></label>
            <input id="correo-<?= e($sufijo) ?>" name="correo" type="email" maxlength="100" value="<?= e($valor('correo', $p?->getCorreo())) ?>">
            <div class="botones-formulario"><button class="acento" type="submit"><?= $esNuevo ? 'Agregar' : 'Guardar cambios' ?></button></div>
        </form>
        <?php if (!$esNuevo): ?>
            <form class="formulario bloque" method="post" action="/?ruta=proveedor_estado"
                  data-confirmar="<?= e($p->estaActivo() ? '¿Desactivar a ' . $p->getRazonSocial() . '? Ya no recibirá órdenes nuevas.' : '¿Activar otra vez a ' . $p->getRazonSocial() . '?') ?>"
                  data-confirmar-boton="<?= $p->estaActivo() ? 'Sí, desactivar' : 'Sí, activar' ?>">
                <?= campoCsrf($csrf) ?>
                <input type="hidden" name="id" value="<?= (int) $idForm ?>">
                <input type="hidden" name="estado" value="<?= $p->estaActivo() ? 'INACTIVO' : 'ACTIVO' ?>">
                <button class="secundario" type="submit"><?= $p->estaActivo() ? 'Desactivar proveedor' : 'Activar proveedor' ?></button>
            </form>
        <?php endif; ?>
        <?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
