<?php
declare(strict_types=1);
/**
 * V: formulario de ingreso con DNI y clave.
 * @var string $dni
 * @var ?string $error
 */
$titulo = 'Ingresar';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="marca-acceso"><span class="logo" aria-hidden="true">M</span> Minimarket Mass</div>
<div class="panel">
    <h1>Ingresar</h1>
    <p class="sutil">Usa tu DNI y tu clave de empleado.</p>
    <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form class="formulario" method="post" action="/?ruta=login" autocomplete="off">
        <?= campoCsrf($csrf) ?>
        <label for="dni">DNI</label>
        <input id="dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required autofocus
               autocomplete="username" value="<?= e($dni) ?>">
        <label for="clave">Clave</label>
        <input id="clave" name="clave" type="password" required autocomplete="current-password">
        <div class="botones-formulario"><button class="acento grande" type="submit">Entrar</button></div>
    </form>
</div>
<p class="sutil centro">Si olvidaste tu clave, pide al administrador de tu tienda que te asigne una nueva.</p>
<?php require __DIR__ . '/../layout_fin.php'; ?>
