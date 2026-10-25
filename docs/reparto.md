# Reparto en ramas: un Pull Request por integrante

Úsalo si el docente pide que cada integrante suba su parte con su propio PR.

**Importante:** el MVC ya está en `main` (PR #1). Un PR sin cambios no se puede abrir, así que cada integrante debe cambiar algo real en sus archivos: revisarlos, agregar un caso límite a su página de prueba o mejorar un comentario. Ese cambio y lo que aprendió lo anota en `docs/bitacora_ia.md`.

Cambia `<nombre>` por tu nombre en minúsculas y sin tildes (por ejemplo `gerald`).

## Antes de empezar (todos)

```powershell
git checkout main
git pull
php -S localhost:8000 -t public   # en otra terminal, para probar
```

## Integrante 1 · Paso 1 · Modelo

Archivos: `models/Producto.php`, `models/Stock.php`, `public/poo_producto.php`

```powershell
git checkout -b feature/<nombre>-modelo
# ... editar y probar en http://localhost:8000/poo_producto.php
git status
git add models/Producto.php models/Stock.php public/poo_producto.php docs/bitacora_ia.md
git commit -m "feat: revisar clase Producto y Stock"
git push -u origin feature/<nombre>-modelo
```

## Integrante 2 · Paso 2 · Roles y herencia

Archivos: `models/Usuario.php`, `models/Administrador.php`, `models/Cajero.php`, `models/Almacenero.php`, `public/poo_roles.php`

```powershell
git checkout -b feature/<nombre>-roles
# ... editar y probar en http://localhost:8000/poo_roles.php
git status
git add models/Usuario.php models/Administrador.php models/Cajero.php models/Almacenero.php public/poo_roles.php docs/bitacora_ia.md
git commit -m "feat: revisar roles con herencia"
git push -u origin feature/<nombre>-roles
```

## Integrante 3 · Pasos 3 y 4 · Repository y controlador

Archivos: `models/ProductoRepository.php`, `controllers/ProductoController.php`, `public/poo_repository.php`

```powershell
git checkout -b feature/<nombre>-repository
# ... editar y probar en http://localhost:8000/poo_repository.php
git status
git add models/ProductoRepository.php controllers/ProductoController.php public/poo_repository.php docs/bitacora_ia.md
git commit -m "feat: revisar Repository y controlador"
git push -u origin feature/<nombre>-repository
```

## Integrante 4 · Pasos 5 y 6 · Vistas e index

Archivos: `views/layout_inicio.php`, `views/layout_fin.php`, `views/productos/lista.php`, `views/productos/detalle.php`, `views/errores/404.php`, `public/index.php`

```powershell
git checkout -b feature/<nombre>-vista
# ... editar y probar en http://localhost:8000 y http://localhost:8000/?ruta=xyz
git status
git add views/layout_inicio.php views/layout_fin.php views/productos/lista.php views/productos/detalle.php views/errores/404.php public/index.php docs/bitacora_ia.md
git commit -m "feat: revisar vistas y punto de entrada"
git push -u origin feature/<nombre>-vista
```

## Después del push

1. En GitHub, botón **Compare & pull request**, base `main`.
2. Otro integrante lo revisa y hace el merge, en orden 1 → 2 → 3 → 4.
3. Después de cada merge, todos ejecutan `git checkout main` y `git pull`.

Si dos integrantes editan `docs/bitacora_ia.md` al mismo tiempo, GitHub puede marcar un conflicto. Quien hace el segundo merge ejecuta `git pull origin main` en su rama, deja las dos anotaciones y vuelve a subir.

## Reglas de la guía

- Con `git status` deben aparecer solo tus archivos. Agrégalos por nombre, nunca con `git add .`.
- Nada de `.env`, contraseñas ni archivos de otro integrante.
- Mensaje de commit de menos de unos 70 caracteres, con `feat:`, `fix:`, `refactor:` o `docs:`.
