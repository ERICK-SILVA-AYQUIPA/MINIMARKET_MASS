<?php
declare(strict_types=1);
/**
 * V: editar un empleado de la tienda y ponerle una clave nueva.
 * @var Usuario $empleado
 * @var array<int, string> $roles
 * @var bool $esElMismo  el administrador editandose a si mismo
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Editar empleado';
require __DIR__ . '/../layout_inicio.php';
$valor = static fn(string $campo, string $guardado): string => is_string($viejo[$campo] ?? null) ? $viejo[$campo] : $guardado;
$rolElegido = $valor('rol', (string) $empleado->getIdRol());
$estadoElegido = $valor('estado', $empleado->getEstado());
?>
<p><a href="/?ruta=empleados">← Volver al personal</a></p>
<h1><?= e($empleado->nombreCompleto()) ?></h1>
<?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>

<form class="formulario panel" method="post" action="/?ruta=empleado_guardar">
    <h2>Datos</h2>
    <?= campoCsrf($csrf) ?>
    <input type="hidden" name="id" value="<?= $empleado->getIdEmpleado() ?>">
    <p>DNI: <?= e($empleado->dniOculto()) ?> (no se cambia: es su usuario)</p>
    <label for="nombres">Nombres</label>
    <input id="nombres" name="nombres" maxlength="60" required value="<?= e($valor('nombres', $empleado->getNombres())) ?>">
    <label for="apellidos">Apellidos</label>
    <input id="apellidos" name="apellidos" maxlength="60" required value="<?= e($valor('apellidos', $empleado->getApellidos())) ?>">
    <?php if ($esElMismo): ?>
        <p>No puedes cambiar tu propio rol ni desactivarte.</p>
        <input type="hidden" name="rol" value="<?= $empleado->getIdRol() ?>">
        <input type="hidden" name="estado" value="ACTIVO">
    <?php else: ?>
        <label for="rol">Rol</label>
        <select id="rol" name="rol">
            <?php foreach ($roles as $id => $nombre): ?>
                <option value="<?= $id ?>" <?= $rolElegido === (string) $id ? 'selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <?php foreach (['ACTIVO', 'INACTIVO'] as $estado): ?>
                <option value="<?= e($estado) ?>" <?= $estadoElegido === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
            <?php endforeach; ?>
        </select>
        <p>INACTIVO: ya no puede ingresar, pero sus ventas y movimientos se conservan.</p>
    <?php endif; ?>
    <p><button type="submit">Guardar</button></p>
</form>

<?php if (!$esElMismo): ?>
<form class="formulario panel" method="post" action="/?ruta=empleado_clave" autocomplete="off">
    <h2>Poner una clave nueva</h2>
    <?= campoCsrf($csrf) ?>
    <input type="hidden" name="id" value="<?= $empleado->getIdEmpleado() ?>">
    <label for="clave">Clave nueva (8 a 72 caracteres, con letras y números)</label>
    <input id="clave" name="clave" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
    <p><button type="submit">Cambiar clave</button></p>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../layout_fin.php'; ?>
