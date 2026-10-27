<?php
declare(strict_types=1);
/**
 * V: personal de la tienda del administrador. El alta se hace en un panel lateral (#nuevo-empleado).
 * @var Tienda $tienda
 * @var Usuario[] $personal
 * @var array<int, string> $roles
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Personal';
require __DIR__ . '/../layout_inicio.php';
$viejoTexto = static fn(string $campo): string => is_string($viejo[$campo] ?? null) ? $viejo[$campo] : '';
?>
<div class="cabecera">
    <div>
        <h1>Personal de <?= e($tienda->getNombreTienda()) ?></h1>
        <p class="sutil"><?= count($personal) ?> personas. Cada una entra con su DNI y su propia clave.</p>
    </div>
    <div class="acciones"><a class="boton acento" href="#nuevo-empleado"><?= icono('mas') ?>Registrar empleado</a></div>
</div>

<div class="tabla">
<table>
    <thead><tr><th scope="col">Nombre</th><th scope="col">DNI</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col"><span class="visualmente-oculto">Acciones</span></th></tr></thead>
    <tbody>
    <?php foreach ($personal as $empleado): ?>
        <tr>
            <td><?= e($empleado->nombreCompleto()) ?></td>
            <td><?= e($empleado->dniOculto()) ?></td>
            <td><?= e($empleado->getNombreRol()) ?></td>
            <td><span class="etiqueta <?= $empleado->estaActivo() ? 'ok' : 'anulada' ?>"><?= e($empleado->getEstado()) ?></span></td>
            <td><a class="boton secundario chico" href="/?ruta=empleado&amp;id=<?= $empleado->getIdEmpleado() ?>"><?= icono('editar') ?>Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php
$drawerId = 'nuevo-empleado';
$drawerTitulo = 'Registrar empleado';
$drawerSubtitulo = 'Trabajará en ' . $tienda->getNombreTienda();
$drawerCerrar = '/?ruta=empleados';
$drawerAbierto = $error !== null; // si hubo un error, vuelve abierto con lo escrito
require __DIR__ . '/../parciales/drawer_abrir.php';
?>
<form class="formulario" method="post" action="/?ruta=empleado_crear" autocomplete="off">
    <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?= campoCsrf($csrf) ?>
    <label for="nombres">Nombres</label>
    <input id="nombres" name="nombres" maxlength="60" required value="<?= e($viejoTexto('nombres')) ?>">
    <label for="apellidos">Apellidos</label>
    <input id="apellidos" name="apellidos" maxlength="60" required value="<?= e($viejoTexto('apellidos')) ?>">
    <label for="dni">DNI <span class="ayuda">8 dígitos; con él ingresa al sistema</span></label>
    <input id="dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required value="<?= e($viejoTexto('dni')) ?>">
    <label for="rol">Rol</label>
    <select id="rol" name="rol" required>
        <?php foreach ($roles as $id => $nombre): ?>
            <option value="<?= $id ?>" <?= $viejoTexto('rol') === (string) $id ? 'selected' : '' ?>><?= e($nombre) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="clave">Clave inicial <span class="ayuda">8 a 72 caracteres, con letras y números. La persona la cambia en Mi clave.</span></label>
    <input id="clave" name="clave" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
    <div class="botones-formulario"><button class="acento" type="submit">Registrar</button></div>
</form>
<?php require __DIR__ . '/../parciales/drawer_cerrar.php'; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
