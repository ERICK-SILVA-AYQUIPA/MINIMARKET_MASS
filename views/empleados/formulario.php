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
<a class="volver" href="/?ruta=empleados"><?= icono('volver') ?>Volver al personal</a>
<div class="cabecera">
    <div>
        <h1><?= e($empleado->nombreCompleto()) ?></h1>
        <p class="sutil"><?= e(ucfirst(strtolower($empleado->getNombreRol()))) ?> · DNI <?= e($empleado->dniOculto()) ?> (no se cambia: es su usuario)</p>
    </div>
</div>
<?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="rejilla">

<form class="formulario panel" method="post" action="/?ruta=empleado_guardar">
    <h2>Datos</h2>
    <?= campoCsrf($csrf) ?>
    <input type="hidden" name="id" value="<?= $empleado->getIdEmpleado() ?>">
    <label for="nombres">Nombres</label>
    <input id="nombres" name="nombres" maxlength="60" required value="<?= e($valor('nombres', $empleado->getNombres())) ?>">
    <label for="apellidos">Apellidos</label>
    <input id="apellidos" name="apellidos" maxlength="60" required value="<?= e($valor('apellidos', $empleado->getApellidos())) ?>">
    <?php if ($esElMismo): ?>
        <div class="aviso info bloque">No puedes cambiar tu propio rol ni desactivarte.</div>
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
        <span class="ayuda">INACTIVO: ya no puede ingresar, pero sus ventas y movimientos se conservan.</span>
    <?php endif; ?>
    <div class="botones-formulario"><button class="acento" type="submit">Guardar</button></div>
</form>

<?php if (!$esElMismo): ?>
<form class="formulario panel" method="post" action="/?ruta=empleado_clave" autocomplete="off">
    <h2>Poner una clave nueva</h2>
    <?= campoCsrf($csrf) ?>
    <input type="hidden" name="id" value="<?= $empleado->getIdEmpleado() ?>">
    <p class="sutil">Úsalo si la persona olvidó su clave. Dásela en persona; luego ella la cambia en Mi clave.</p>
    <label for="clave">Clave nueva <span class="ayuda">8 a 72 caracteres, con letras y números</span></label>
    <input id="clave" name="clave" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
    <div class="botones-formulario"><button type="submit"><?= icono('clave') ?>Cambiar clave</button></div>
</form>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
