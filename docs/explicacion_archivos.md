# Qué hace cada archivo (para estudiar antes de explicar)

Lee cada archivo con esta guía al lado. Si alguna línea no la puedes contar con tus palabras, ábrela y pregunta antes de la entrega.

## Recorrido de un pedido

1. El navegador pide `http://localhost:8000/?ruta=productos&tienda=3`.
2. `public/index.php` lee `ruta` y llama a `ProductoController->lista('3')`.
3. El controlador valida la tienda y le pide los productos y el stock a `ProductoRepository`.
4. El Repository devuelve objetos `Producto` y `Stock`, sin HTML.
5. El controlador carga `views/productos/lista.php`, que dibuja la tabla.

## models/ (M · datos y reglas)

| Archivo | Qué hace |
|---|---|
| `Producto.php` | Una fila de la tabla `producto`. El constructor rechaza datos que la base tampoco aceptaría (precio 0, código mal formado). Reglas: `esAlPeso()`, `estaActivo()`, `cantidadValida()` (kg con 3 decimales o unidades enteras) e `importe()` = cantidad × precio redondeado a 2. |
| `Stock.php` | Cuánto hay de un producto en una tienda (tabla `stock`). `necesitaReponer()` es verdadero cuando lo actual llega al mínimo; `hayStock()` dice si alcanza para vender una cantidad. |
| `ProductoRepository.php` | La única clase que sabe de dónde salen los datos. Hoy son listas copiadas de `datos.sql`; en la sesión 5 leerá MySQL con PDO. Busca por código o por id y devuelve `null` si no encuentra. |
| `Usuario.php` | Clase base `abstract` de los roles (tabla `empleado`). Guarda lo común (nombres, DNI, tienda) y por defecto niega todos los permisos. El DNI solo se muestra oculto. |
| `Administrador.php` | Hereda de Usuario. Puede anular ventas, pero solo de su propia tienda. |
| `Cajero.php` | Hereda de Usuario. Es el único que puede vender. |
| `Almacenero.php` | Hereda de Usuario. Registra traslados y mermas. |

## controllers/ (C · coordina)

| Archivo | Qué hace |
|---|---|
| `ProductoController.php` | Recibe lo que pidió el usuario, valida la tienda, el código o el id, pide datos al Repository y elige la vista. Si el producto no existe responde 404. No tiene HTML ni SQL. |

## views/ (V · muestra)

| Archivo | Qué hace |
|---|---|
| `layout_inicio.php` | Cabecera HTML y estilos comunes. Define `e()` (escapa texto con `htmlspecialchars` para evitar XSS) y `soles()` (formato S/ 0.00). |
| `layout_fin.php` | Cierra el HTML que abrió el layout de inicio. |
| `productos/lista.php` | Tabla del catálogo con el stock de la tienda elegida y la etiqueta REPONER. Solo muestra. |
| `productos/detalle.php` | Ficha de un producto: precio, forma de venta, importe de ejemplo y stock en la tienda. |
| `errores/404.php` | Página "No encontrado" con un mensaje fijo, que no copia nada de la URL. |

## public/ (lo único que abre el navegador)

| Archivo | Qué hace |
|---|---|
| `index.php` | Punto de entrada. Lee `?ruta=` (solo strings) y con un `switch` llama al método del controlador. Cualquier otra ruta da 404. |
| `poo_producto.php` | Prueba del paso 1: un producto por unidad, uno al peso y un stock por debajo del mínimo. |
| `poo_roles.php` | Prueba del paso 2: tres empleados de Cayma. El mismo método `puedeAnularVenta()` responde distinto según la clase (polimorfismo). |
| `poo_repository.php` | Prueba del paso 3: busca un código que existe y uno que no (`null`), y lista lo que hay que reponer. |

## Conceptos que te pueden preguntar

- **Clase y objeto:** `Producto` es el molde; el yogurt de S/ 7.90 es un objeto hecho con ese molde.
- **private:** nadie cambia la propiedad desde afuera; solo se lee con `get...()`.
- **Herencia:** `Cajero extends Usuario` reutiliza lo común y cambia solo lo suyo.
- **abstract:** no se puede crear un `Usuario` sin rol.
- **Repository:** si mañana los datos vienen de MySQL, solo cambia este archivo.
- **strict_types:** PHP no convierte tipos por su cuenta; si pasas un texto donde va un número, falla.
- **htmlspecialchars:** convierte `<` en `&lt;` para que un `<script>` se muestre como texto y no se ejecute.
