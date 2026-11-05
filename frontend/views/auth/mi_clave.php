<?php
declare(strict_types=1);
/**
 * V: el empleado cambia su propia clave.
 * @var ?string $error
 * @var bool $obligatorio  la clave la puso el administrador (o es la de prueba): no se puede seguir sin cambiarla
 */
$titulo = 'Mi clave';
$anchoMain = 'angosto';
require __DIR__ . '/../layout_inicio.php';
?>
<div class="cabecera">
    <div>
        <h1>Cambiar mi clave</h1>
        <p class="sutil">La nueva clave reemplaza a la anterior desde ahora: se cierran tus sesiones en otras PCs y en la app.</p>
    </div>
</div>
<div class="panel">
    <?php if ($obligatorio): ?><div class="aviso alerta" role="status">Tu clave la puso el administrador o es la de prueba. Cámbiala por una que solo tú sepas para seguir usando el sistema.</div><?php endif; ?>
    <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form class="formulario" method="post" action="/?ruta=mi_clave_guardar" autocomplete="off">
        <?= campoCsrf($csrf) ?>
        <label for="actual">Clave actual</label>
        <input id="actual" name="actual" type="password" required autocomplete="current-password">
        <label for="nueva">Clave nueva <span class="ayuda">De 8 a 72 caracteres, con al menos una letra y un número. No puede ser la clave de prueba ni llevar tu DNI.</span></label>
        <input id="nueva" name="nueva" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
        <label for="repetida">Repite la clave nueva</label>
        <input id="repetida" name="repetida" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
        <div class="botones-formulario"><button type="submit">Cambiar clave</button></div>
    </form>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
