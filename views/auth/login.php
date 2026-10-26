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
<div class="panel" style="max-width: 360px; margin: 40px auto;">
    <h2>Ingresar</h2>
    <?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>
    <form class="formulario" method="post" action="/?ruta=login" autocomplete="off">
        <?= campoCsrf($csrf) ?>
        <label for="dni">DNI</label>
        <input id="dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required value="<?= e($dni) ?>">
        <label for="clave">Clave</label>
        <input id="clave" name="clave" type="password" required>
        <p><button type="submit">Entrar</button></p>
    </form>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
