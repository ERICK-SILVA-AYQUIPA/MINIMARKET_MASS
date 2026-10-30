# Cómo aportar al proyecto

Reglas del equipo para subir cambios sin malograr `main`. Siguen el manual Kanban del docente.

## Reglas

1. **Una tarjeta, una rama, un Pull Request.** Nadie sube directo a `main`: está protegida.
2. **Cada uno con su cuenta.** Tus commits deben salir con tu nombre y tu correo de GitHub.
3. **Otro compañero revisa y aprueba.** No se mezcla un PR propio sin aprobación.
4. **La CI en verde.** Si las pruebas fallan por tu cambio, lo arreglas en tu misma rama.
5. **Máximo 2 tarjetas "En curso"** por persona.

## Paso a paso

```powershell
# 1. Una sola vez: tu nombre y el correo de tu cuenta de GitHub
git config --global user.name "Tu Nombre"
git config --global user.email "tu-correo@ejemplo.com"

# 2. Partir de main actualizado
git checkout main
git pull origin main

# 3. Crear tu rama
git checkout -b feature/<tu-nombre>-<tarea>

# 4. Probar
php -S localhost:8000 -t frontend/public
php backend/tests/unitarios.php

# 5. Guardar y subir solo tus archivos
git status
git add <archivos de tu tarjeta>
git commit -m "feat: lo que hiciste"
git push -u origin feature/<tu-nombre>-<tarea>
```

Luego abre el Pull Request en GitHub, pon a tu revisor y enlaza tu tarjeta.

## Nombres

| Qué | Formato | Ejemplo |
|---|---|---|
| Rama | `feature/<nombre>-<tarea>` o `fix/<tema>` | `feature/gerald-login` |
| Commit | `tipo: descripción` | `feat: bloqueo tras 3 intentos` |

Tipos de commit: `feat` (función nueva), `fix` (corrección), `docs` (documentación), `test` (pruebas), `refactor` (ordenar código sin cambiar lo que hace).

## Dónde va cada cosa

| Carpeta | Qué contiene |
|---|---|
| `backend/config/` | Conexión a MySQL y sesión |
| `backend/controllers/` | Controladores web; `backend/controllers/api/` los de la API de la app Android |
| `backend/models/` | Clases del negocio, repositorios, `pagos/` y `servicios/` |
| `frontend/views/` | Pantallas por módulo, `parciales/` y `errores/` |
| `frontend/public/` | Lo único que ve el navegador: `index.php`, `api/index.php`, CSS, JS, fuentes, fotos y las pruebas `poo_*.php` de la sesión 4 |
| `backend/database/` | `schema.sql` y los scripts que se ejecutan en el orden de `docker-compose.yml`; `instalar_laragon.sql` los junta todos |
| `backend/tests/` | Pruebas que corre la CI |
| `backend/scripts/` | Respaldo, restauración y revisión de la instalación |
| `deploy/` | Configuración de Apache y Nginx |
| `docs/` | Documentación; `docs/equipo/` tiene la nota de cada integrante |
| `android-app/` | App Android (proyecto Gradle aparte) |

Nunca subas `.env` (tiene claves) ni fotos de `frontend/public/img/productos/`: ya están en `.gitignore`.
