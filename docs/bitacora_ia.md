# Bitácora de uso de IA · Sesión 4 (POO y MVC)

Herramienta: Claude (asistente de código). Fechas: 23/10/2026 (código) y 25/10/2026 (revisión y auditoría).

> Lo marcado con **[Grupo]** solo lo puede confirmar el grupo: la IA no sabe qué escribió o corrigió cada integrante a mano.

## Contexto común de los briefs

Todos los pasos usaron el mismo bloque de CONTEXTO y SEGURIDAD; solo cambian OBJETIVO, ENTRADAS Y SALIDAS, REGLAS y DÓNDE VA.

- **CONTEXTO:** Proyecto PHP 8.3, arquitectura MVC manual sin framework. MySQL en el puerto 3307, pero todavía NO se conecta: hoy los datos van en una lista fija dentro del Repository. Carpetas: models/, controllers/, views/, public/.
- **SEGURIDAD:** declare(strict_types=1), tipos en parámetros y retornos, propiedades private, ningún dato sensible en el código.
- **Cierre:** Explica cada línea del resultado y dime qué supuestos hiciste.

## Brief por paso

Los pasos 2 a 6 se escribieron con la regla anterior; abajo está cada brief armado con los datos que se usaron en el código entregado. **[Grupo]** confirmen que coincide con lo que pegó cada uno.

### Paso 1 · Clase de la entidad (`models/Producto.php`, `models/Stock.php`)
- **OBJETIVO:** Una clase Producto que represente una fila de mi tabla producto, y una clase Stock para la tabla stock.
- **ENTRADAS Y SALIDAS:** El constructor recibe las columnas de mi tabla: id_producto INT UNSIGNED, nombre_producto VARCHAR(100), codigo_barras VARCHAR(14) NULL, precio_venta DECIMAL(10,2), se_vende_por_peso CHAR(1), estado VARCHAR(10), id_categoria SMALLINT, id_marca SMALLINT, id_unidad TINYINT. Stock: id_tienda, id_producto, cantidad_actual DECIMAL(10,3), stock_minimo DECIMAL(10,3). Un método get por propiedad y métodos para la regla de negocio.
- **REGLAS:** Un producto al peso se vende en kg con hasta 3 decimales y no tiene código de barras; por unidad solo cantidades enteras. El importe es ROUND(cantidad * precio_venta, 2). Se repone cuando cantidad_actual <= stock_minimo.
- **Prueba:** `public/poo_producto.php`.

### Paso 2 · Roles con herencia (`models/Usuario.php`, `Administrador.php`, `Cajero.php`, `Almacenero.php`)
- **OBJETIVO:** Una clase base Usuario (tabla empleado) y una clase hija por cada rol de mi tabla rol.
- **ENTRADAS Y SALIDAS:** id_empleado, nombres VARCHAR(60), apellidos VARCHAR(60), dni CHAR(8), estado ('ACTIVO'/'INACTIVO'), id_tienda. Roles: 1 ADMINISTRADOR, 2 CAJERO, 3 ALMACENERO.
- **REGLAS:** Solo el CAJERO vende; solo el ADMINISTRADOR anula, y solo ventas de su tienda; el ALMACENERO registra traslados y mermas. Un empleado INACTIVO no puede nada. El DNI no se muestra completo.
- **Prueba:** `public/poo_roles.php`.

### Paso 3 · Repository (`models/ProductoRepository.php`)
- **OBJETIVO:** Un ProductoRepository que sea la única clase que sabe de dónde salen los datos.
- **ENTRADAS Y SALIDAS:** todos(), buscarPorCodigo(string), buscarPorId(int), stockEnTienda(idProducto, idTienda), tiendas(). Devuelve objetos o null, nunca HTML.
- **REGLAS:** Los 12 productos, las 3 tiendas y las 36 filas de stock copiados de `database/datos.sql`.
- **Prueba:** `public/poo_repository.php`.

### Paso 4 · Controlador (`controllers/ProductoController.php`)
- **OBJETIVO:** Un ProductoController que reciba el pedido, valide la entrada, pida datos al Repository y elija la vista.
- **ENTRADAS Y SALIDAS:** lista(tienda), detalle(codigo, id, tienda), noEncontrado(mensaje). Sin HTML ni SQL.
- **REGLAS:** El código de barras se valida con `^[0-9]{8,14}$`; la tienda debe existir, si no se usa Mass Cayma; si el producto no existe, 404.

### Paso 5 · Vistas (`views/`)
- **OBJETIVO:** Vistas que solo muestren: catálogo, ficha de producto y 404, con un layout común.
- **REGLAS:** Cero consultas y cero reglas en la vista; todo texto pasa por htmlspecialchars(..., ENT_QUOTES, 'UTF-8').

### Paso 6 · Punto de entrada (`public/index.php`)
- **OBJETIVO:** Un index.php que lea `?ruta=` y llame al controlador.
- **REGLAS:** Solo acepta las rutas `productos` y `producto` (switch con default 404) y solo valores string de `$_GET`.

## Supuestos que hizo la IA y cómo los revisamos

Revisados el 25/10/2026 contra los .sql del repositorio.

| Supuesto | Por qué | Revisado |
|---|---|---|
| No se calcula IGV | `database/boleta (1).sql`, línea 12: "el modelo no guarda subtotales ni impuestos aparte" | ☑ |
| `Usuario` representa la tabla `empleado` | `schema.sql` no tiene tabla usuario; `empleado.dni` tiene el comentario "con el DNI ingresa al sistema" | ☑ |
| El stock va en una clase `Stock` aparte | La tabla `producto` no tiene columna de stock; existe la tabla `stock` por tienda | ☑ |
| Anular solo ADMINISTRADOR de la misma tienda | Consulta 27 de `consultas.sql` marca como incoherente la anulación hecha por alguien que no es administrador de su tienda | ☑ |
| Vender solo CAJERO | Consulta 25 de `consultas.sql` marca las ventas hechas por un empleado que no es cajero | ☑ |
| Tienda por defecto: Mass Cayma (id 1) | `datos.sql`: id_tienda 1 = 'Mass Cayma'. Elegirla como defecto es decisión del grupo **[Grupo]** | ☑ |
| `se_vende_por_peso` y `estado` se guardan como el texto de la columna ('S'/'N', 'ACTIVO') | `schema.sql`: CHAR(1) con CHECK IN ('S','N') y VARCHAR(10) con CHECK IN ('ACTIVO','DESCONTINUADO') | ☑ |

## Qué corregimos del código generado

- 24/10/2026: el mensaje de commit pasaba de 70 caracteres; se acortó a `feat: agregar estructura MVC de la sesion 4`.
- 25/10/2026, auditoría (abajo): no hubo hallazgos reales, así que el código PHP no cambió.
- **[Grupo]** Anoten aquí lo que cada integrante cambió a mano al revisar su parte. Si nadie cambió nada, escriban "sin cambios manuales".

## Auditoría de seguridad (25/10/2026)

Prompt de auditor usado:

> Actúa como auditor de seguridad de PHP 8.3. Revisa estos archivos MVC y reporta solo problemas reales, con archivo y línea: (1) XSS: datos impresos sin htmlspecialchars(..., ENT_QUOTES, 'UTF-8'); (2) datos de la URL que se ejecutan o usan sin validar; (3) propiedades public; (4) archivos sin declare(strict_types=1) o funciones sin tipos en parámetros y retornos; (5) contraseñas, claves o datos reales en el código o en los .sql. Para cada hallazgo di cómo reproducirlo.

| Revisión | Resultado |
|---|---|
| XSS | Todo texto en `views/` y `public/poo_*.php` pasa por `e()` o `$e()`. Lo que se imprime sin escapar son enteros (`$idTienda`, `count()`, `getIdRol()`), números ya formateados o textos fijos. Probado: `?ruta=<script>…`, `?tienda=<script>` y `?ruta=producto&codigo=<b>` no devuelven ninguna etiqueta inyectada. |
| Datos de la URL | `index.php` solo lee strings (`?ruta[]=x` se ignora) y solo ejecuta `productos` y `producto`; lo demás da 404. El código se valida con regex, el id con `ctype_digit` y la tienda contra la lista. Probado: `id=-1`, `id=99999999999999999999999` y `tienda=1e1` no fallan. |
| Encapsulamiento | Ninguna propiedad `public`: todas son `private`. El DNI no tiene getter, solo `dniOculto()`. |
| Tipos | Todos los archivos PHP con código tienen `declare(strict_types=1)` y tipos en parámetros y retornos. |
| Secretos | Sin contraseñas ni claves. Los DNI (`70000001`…), códigos y precios son los ficticios de `datos.sql`. |
| Archivos fuera de `public/` | `models/`, `controllers/` y `views/` no se pueden abrir desde el navegador, porque el servidor sirve solo `public/`. |

Para decidir **[Grupo]**: las páginas de prueba `public/poo_*.php` se pueden abrir desde el navegador. Sirven para la sesión, pero en una versión real se quitarían de `public/`.

## Verificación

- `php -l` sin errores en todos los archivos.
- `php -S localhost:8000 -t public`: catálogo 200, ficha por código y por id 200, código inexistente 404, ruta desconocida 404, `<script>` en la URL no se ejecuta.
