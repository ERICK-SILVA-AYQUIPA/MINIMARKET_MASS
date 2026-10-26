<?php
declare(strict_types=1);
/**
 * V: personal de la tienda del administrador y alta de un empleado.
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
<h1>Personal de <?= e($tienda->getNombreTienda()) ?></h1>

<div class="tabla">
<table>
    <tr><th>Nombre</th><th>DNI</th><th>Rol</th><th>Estado</th><th></th></tr>
    <?php foreach ($personal as $empleado): ?>
        <tr>
            <td><?= e($empleado->nombreCompleto()) ?></td>
            <td><?= e($empleado->dniOculto()) ?></td>
            <td><?= e($empleado->getNombreRol()) ?></td>
            <td><span class="etiqueta <?= $empleado->estaActivo() ? 'ok' : 'anulada' ?>"><?= e($empleado->getEstado()) ?></span></td>
            <td><a href="/?ruta=empleado&amp;id=<?= $empleado->getIdEmpleado() ?>">Editar</a></td>
        </tr>
    <?php endforeach; ?>
</table>
</div>

<form class="formulario panel" method="post" action="/?ruta=empleado_crear" autocomplete="off">
    <h2>Registrar empleado</h2>
    <?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>
    <?= campoCsrf($csrf) ?>
    <label for="nombres">Nombres</label>
    <input id="nombres" name="nombres" maxlength="60" required value="<?= e($viejoTexto('nombres')) ?>">
    <label for="apellidos">Apellidos</label>
    <input id="apellidos" name="apellidos" maxlength="60" required value="<?= e($viejoTexto('apellidos')) ?>">
    <label for="dni">DNI (con él ingresa)</label>
    <input id="dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required value="<?= e($viejoTexto('dni')) ?>">
    <label for="rol">Rol</label>
    <select id="rol" name="rol" required>
        <?php foreach ($roles as $id => $nombre): ?>
            <option value="<?= $id ?>" <?= $viejoTexto('rol') === (string) $id ? 'selected' : '' ?>><?= e($nombre) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="clave">Clave inicial (8 a 72 caracteres, con letras y números)</label>
    <input id="clave" name="clave" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
    <p><button type="submit">Registrar</button></p>
</form>
<?php require __DIR__ . '/../layout_fin.php'; ?>
