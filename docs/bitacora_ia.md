# Bitácora de uso de IA · Sesión 4 (POO y MVC)

Herramienta: Claude (asistente de código). Fecha: 23/10/2026.

## Brief usado (paso 1)

CONTEXTO: Proyecto PHP 8.3, arquitectura MVC manual sin framework. MySQL en el puerto 3307, pero todavía NO se conecta: hoy los datos van en una lista fija dentro del Repository. Carpetas: models/, controllers/, views/, public/.
OBJETIVO: Una clase Producto que represente una fila de mi tabla producto.
ENTRADAS Y SALIDAS: El constructor recibe las columnas de mi tabla: id_producto INT UNSIGNED, nombre_producto VARCHAR(100), codigo_barras VARCHAR(14) NULL, precio_venta DECIMAL(10,2), se_vende_por_peso CHAR(1), estado VARCHAR(10), id_categoria SMALLINT, id_marca SMALLINT, id_unidad TINYINT. Tiene un método get por propiedad y métodos para mi regla de negocio.
REGLAS DE NEGOCIO: Un producto al peso se vende en kg con hasta 3 decimales y no tiene código de barras; por unidad solo cantidades enteras. El importe es ROUND(cantidad * precio_venta, 2). El stock está en la tabla stock (por tienda) y se repone cuando cantidad_actual <= stock_minimo.
SEGURIDAD: declare(strict_types=1), tipos en parámetros y retornos, propiedades private, ningún dato sensible en el código.
DÓNDE VA: models/Producto.php (una clase por archivo). Explica cada línea del resultado y dime qué supuestos hiciste.

Los pasos 2 a 6 usaron el mismo contexto cambiando OBJETIVO y DÓNDE VA (roles ADMINISTRADOR, CAJERO, ALMACENERO; Repository; controlador; vista; index.php).

## Supuestos que hizo la IA y cómo los revisamos

| Supuesto | Por qué | Revisado |
|---|---|---|
| No se calcula IGV | `boleta.sql` dice que el modelo no guarda subtotales ni impuestos | ☐ |
| `Usuario` representa la tabla `empleado` | En el diseño no hay tabla usuario; el empleado entra con su DNI | ☐ |
| El stock va en una clase `Stock` aparte | La tabla `producto` no tiene stock; está en `stock` por tienda | ☐ |
| Anular solo ADMINISTRADOR de la misma tienda | Consulta 27 de `consultas.sql` | ☐ |
| Vender solo CAJERO | Consulta 25 de `consultas.sql` | ☐ |
| Tienda por defecto: Mass Cayma (id 1) | Es la tienda del ejemplo de la guía | ☐ |
| `se_vende_por_peso` y `estado` se guardan como el texto de la columna ('S'/'N', 'ACTIVO') | Así el Repository con PDO pasará la fila tal cual en la sesión 5 | ☐ |

## Qué cambiamos nosotros

- (Anota aquí lo que corrigieron del código generado.)

## Verificación

- `php -l` sin errores en todos los archivos.
- `php -S localhost:8000 -t public`: catálogo 200, ficha 200, código inexistente 404, ruta desconocida 404, `<script>` en la URL no se ejecuta.
