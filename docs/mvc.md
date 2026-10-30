# Minimarket Mass · POO y MVC (Sesión 4, HO-09)

PHP 8.3 sin framework. Los Repository leen de MySQL 8.4.11 en Docker (puerto 3307) con PDO y consultas preparadas.
Al pasar de listas fijas a MySQL, los controladores y las vistas no cambiaron: esa es la ventaja de separar.

## Cómo correrlo

```powershell
php -v                                  # PHP 8.3...
copy .env.example .env                  # solo la primera vez; cambia las claves
docker compose up -d                    # MySQL 8.4.11 con schema.sql, datos.sql y boleta.sql
php -S localhost:8000 -t frontend/public         # déjalo abierto
```

| URL | Qué muestra |
|---|---|
| http://localhost:8000 | Panel de inicio de la tienda del usuario (pide login) |
| http://localhost:8000/?ruta=productos&tienda=3 | Catálogo con stock de Mass Yanahuara (la leche sale REPONER) |
| http://localhost:8000/?ruta=producto&codigo=7750000000011 | Ficha de un producto por código de barras |
| http://localhost:8000/?ruta=producto&id=8 | Ficha de un producto al peso (no tiene código de barras) |
| http://localhost:8000/?ruta=xyz | Página 404 |
| http://localhost:8000/?ruta=stock | Stock de los 12 productos en las 3 tiendas |
| http://localhost:8000/?ruta=ventas&tienda=1 | Ventas de Mass Cayma (B001-00000002 sale ANULADA) |
| http://localhost:8000/?ruta=comprobante&serie=F001&numero=1 | Factura con pago mixto (tarjeta + efectivo) |
| http://localhost:8000/?ruta=tiendas | Las 3 tiendas con cajas, series y personal |
| /poo_producto.php · /poo_roles.php · /poo_repository.php · /poo_ventas.php | Páginas de prueba |

Pruebas: `php backend/tests/unitarios.php` (sin base), `php backend/tests/seguridad.php` (revisión del código), `php backend/tests/web.php` (por HTTP, sin cambiar datos) y `php backend/tests/integracion.php` (con la base recién creada). En GitHub las corre `.github/workflows/ci.yml` en cada pull request.

La lista completa de archivos está en `docs/explicacion_archivos.md`.

## Estructura

```
MinimarketMass/
├── backend/controllers/ProductoController.php  C: valida la entrada, llama al Repository y elige la vista
├── backend/models/
│   ├── Producto.php                    M: tabla producto + reglas (al peso, importe, estado)
│   ├── Stock.php                       M: tabla stock + regla REPONER (cantidad_actual <= stock_minimo)
│   ├── ProductoRepository.php          M: de dónde salen los datos (MySQL con PDO)
│   ├── Usuario.php                     M: clase base abstracta (tabla empleado)
│   ├── Administrador.php               id_rol 1: anula ventas de su tienda
│   ├── Cajero.php                      id_rol 2: vende
│   └── Almacenero.php                  id_rol 3: ingresos, traslados, mermas y conteo
├── frontend/views/
│   ├── layout_inicio.php / layout_fin.php   cabecera, estilos y e() = htmlspecialchars
│   ├── productos/lista.php · productos/detalle.php
│   └── errores/404.php
├── frontend/public/index.php                    único punto de entrada (switch de rutas + 404)
├── backend/database/                           schema.sql, datos.sql, consultas.sql, boleta.sql (sesión 3, sin cambios)
└── docs/bitacora_ia.md
```

## Reglas de negocio que salen del diseño

- **Producto al peso** (`se_vende_por_peso = 'S'`): se vende en kg con hasta 3 decimales y no tiene código de barras. Por unidad, solo cantidades enteras.
- **Importe** = `ROUND(cantidad * precio_venta, 2)`, igual que el CHECK `ck_detalle_importe` de `detalle_venta`.
- **REPONER** cuando `cantidad_actual <= stock_minimo` en la tienda.
- **Roles**: solo el CAJERO vende; solo el ADMINISTRADOR anula, y solo ventas de su tienda; el ALMACENERO registra traslados y mermas (consultas 25 y 27 de `consultas.sql`).
- El diseño no guarda IGV ni subtotal (lo dice `boleta.sql`), así que el código no lo calcula.

## Reparto en el grupo (4 integrantes)

| Integrante | Pasos | Rama | Archivos | Commit sugerido |
|---|---|---|---|---|
| 1 | 1 · Modelo | `feature/<nombre>-modelo` | backend/models/Producto.php, backend/models/Stock.php, frontend/public/poo_producto.php | `feat: clase Producto y Stock con reglas de peso, importe y reposicion` |
| 2 | 2 · Roles | `feature/<nombre>-roles` | backend/models/Usuario.php, Administrador.php, Cajero.php, Almacenero.php, frontend/public/poo_roles.php | `feat: roles con herencia desde Usuario y permisos por rol` |
| 3 | 3 y 4 · Repository y controlador | `feature/<nombre>-repository` | backend/models/ProductoRepository.php, backend/controllers/ProductoController.php, frontend/public/poo_repository.php | `feat: ProductoRepository con datos de prueba y ProductoController` |
| 4 | 5 y 6 · Vista e index | `feature/<nombre>-vista` | frontend/views/, frontend/public/index.php | `feat: vistas del catalogo, ficha, 404 y punto de entrada` |

Cada uno, desde `main` actualizado:

```powershell
git pull
git switch -c feature/<nombre>-modelo
git add backend/models/Producto.php backend/models/Stock.php frontend/public/poo_producto.php
git commit -m "feat: clase Producto y Stock con reglas de peso, importe y reposicion"
git push -u origin feature/<nombre>-modelo
```

Orden de merge: 1 → 2 → 3 → 4 (el 3 necesita la clase del 1 y el 4 necesita al 3). Después de cada merge, todos hacen `git pull`.

## Seguridad aplicada

- `declare(strict_types=1)`, tipos en parámetros y retornos, propiedades `private`.
- Todo lo que se imprime pasa por `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- `index.php` solo acepta strings de `$_GET`; el código de barras se valida con `^[0-9]{8,14}$` y el id de tienda contra la lista de tiendas.
- Consultas preparadas con marcadores (`:codigo`, `:serie`) y `ATTR_EMULATE_PREPARES = false`: lo que llega en la URL nunca se pega al SQL.
- Las claves de la base van en `.env`, que está en `.gitignore`. Si la base falla, el navegador ve un mensaje genérico (503 o 500) y el detalle va al log.
- Sin contraseñas ni datos reales: DNI, códigos y precios son los ficticios de `datos.sql`, y en pantalla el DNI se muestra oculto.
- Login con DNI y `password_verify()`; en la base solo va el hash. La cookie de sesión es `httponly` y `SameSite=Lax`, y el id cambia al entrar.
- Cada formulario lleva un token CSRF que se compara con `hash_equals()`. Después de un POST se redirige (303) para que F5 no repita la venta.
- Los permisos se preguntan al objeto del rol (`puedeVender()`, `puedeAnularVenta()`, `puedeMoverMercaderia()`, `puedeManejarCaja()`, `puedeAdministrarCatalogo()`, `puedeAdministrarTienda()`, `puedeAjustarStock()`, `puedeRegistrarIngreso()`, `puedeVerIngresos()`, `puedeAbrirTiendas()`); si responde no, 403. El servicio vuelve a preguntar antes de escribir, por si alguien manda el formulario a mano.
- Las escrituras van en transacciones con `SELECT ... FOR UPDATE`: no se vende stock que no hay ni se repite un número de comprobante.
- Ingreso: 5 claves erradas seguidas para un DNI (o 20 desde una IP) bloquean 15 minutos (`intento_login`); la sesión vence a los 30 minutos sin uso.
- `Content-Security-Policy` sin scripts: aunque se colara una etiqueta `<script>`, el navegador no la ejecuta.
- Listas largas con `LIMIT` y `OFFSET` calculados en PHP como enteros (`Paginacion`); la página fuera de rango se corrige.
- Cada formulario que cambia datos revisa el permiso en el controlador (403) y otra vez en el servicio. Un dato mal escrito vuelve al formulario con 422 y el mensaje; un POST a una ruta de lectura da 405.
- Un monto o una cantidad que no cabe en su columna (DECIMAL(10,2) o DECIMAL(10,3)) se rechaza con un mensaje, en vez de un error de MySQL.
- El CSV antepone un apóstrofo a lo que empieza con `= + - @`, tabulador o retorno, para que Excel no lo lea como fórmula.
- Las acciones sensibles (anular, cambiar precios, personal, claves, tiendas, cajas e ingresos de mercadería) quedan en la tabla `auditoria`, dentro de la misma transacción; el administrador la ve en `?ruta=auditoria`.
- `backend/tests/seguridad.php` revisa todo el código en cada push: que los 28 formularios POST lleven token, que las salidas de las vistas pasen por `e()` y que ningún `prepare()` pegue datos de afuera. El detalle está en `docs/seguridad.md`.
