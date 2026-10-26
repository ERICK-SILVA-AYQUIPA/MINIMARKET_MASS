<?php
declare(strict_types=1);
/**
 * V: el empleado cambia su propia clave.
 * @var ?string $error
 */
$titulo = 'Mi clave';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="panel" style="max-width: 420px;">
    <h1>Cambiar mi clave</h1>
    <?php if ($error !== null): ?><div class="aviso error"><?= e($error) ?></div><?php endif; ?>
    <form class="formulario" method="post" action="/?ruta=mi_clave_guardar" autocomplete="off">
        <?= campoCsrf($csrf) ?>
        <label for="actual">Clave actual</label>
        <input id="actual" name="actual" type="password" required autocomplete="current-password">
        <label for="nueva">Clave nueva (8 a 72 caracteres, con letras y números)</label>
        <input id="nueva" name="nueva" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
        <label for="repetida">Repite la clave nueva</label>
        <input id="repetida" name="repetida" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
        <p><button type="submit">Cambiar clave</button></p>
    </form>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
