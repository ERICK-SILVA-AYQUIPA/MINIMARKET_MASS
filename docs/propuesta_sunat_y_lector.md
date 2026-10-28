# Propuesta: boleta electrónica SUNAT y lector de código de barras

Esta es una **propuesta escrita, todavía sin implementar**. Explica qué haría falta para que Minimarket Mass emita comprobantes electrónicos válidos ante SUNAT y para vender con un lector de código de barras. Los dos puntos se pueden hacer por separado. Antes de programar, el grupo debe confirmar los datos marcados como **[revisar]** en la página de SUNAT, porque la norma cambia.

## 1. Situación actual

- La venta ya guarda todo lo que pide un comprobante: serie (`B001`, `F001`), correlativo, fecha, cliente (DNI o RUC), detalle con precio y los pagos (`venta`, `detalle_venta`, `pago_*`).
- Las series ya tienen el formato de las series electrónicas: empiezan con **B** las boletas y con **F** las facturas, seguidas de 3 caracteres.
- El ticket (`comprobante_imprimir`) es una **representación interna**: no tiene firma digital, hash ni código QR, y SUNAT no lo recibe. Por eso hoy **no vale como comprobante electrónico**.
- La anulación y las devoluciones parciales funcionan dentro del sistema, pero ante SUNAT cada una necesitaría su documento electrónico (sección 2.4).

## 2. Boleta y factura electrónica

### 2.1 Qué pide SUNAT (resumen)

| Requisito | Qué es | Quién lo consigue |
|---|---|---|
| RUC activo y **Clave SOL** | La empresa que emite | El dueño de la tienda |
| Alta como **emisor electrónico** | En SUNAT Operaciones en Línea | El dueño, con su Clave SOL |
| **Certificado digital** | Firma cada XML. No hace falta si se usa un PSE u OSE que firma por la tienda **[revisar]** | Una entidad certificadora o el proveedor |
| Formato **UBL 2.1** | Cada comprobante es un XML con estructura fija | El sistema o el proveedor |
| Envío y **CDR** | SUNAT, o el OSE, responde con una Constancia de Recepción: aceptado, observado o rechazado | El sistema guarda la respuesta |
| Representación impresa | El ticket debe llevar el **código QR** y el valor resumen (hash), y decir "Representación impresa de la Boleta de Venta Electrónica" | El sistema, en el ticket |

Plazos y reglas que el grupo debe confirmar **[revisar]**:

- Plazo máximo para enviar facturas y boletas desde su emisión.
- Monto desde el cual la boleta debe identificar al cliente con DNI. Hoy se usa el tope de S/ 700.
- Si las boletas se envían una por una o en el **resumen diario**.

### 2.2 Tres caminos posibles

| Camino | Cómo funciona | A favor | En contra |
|---|---|---|---|
| **A. Proveedor PSE/OSE con API** (recomendado) | El sistema manda la venta en JSON por HTTPS a un proveedor autorizado. El proveedor arma el XML, lo firma, lo envía a SUNAT y devuelve el estado, el hash y el enlace al PDF | Poco código, sin certificado propio, el proveedor sigue los cambios de la norma | Costo mensual o por comprobante; depende de internet y del proveedor |
| B. SEE del contribuyente, directo a SUNAT | El sistema arma el UBL 2.1, lo firma con el certificado y lo envía al servicio web de SUNAT | Sin intermediario | Mucho trabajo: XML, firma XMLDSig, SOAP, catálogos de SUNAT y pruebas de homologación |
| C. SEE-SOL (portal de SUNAT) | Se emite a mano en la web de SUNAT | Gratis | No sirve para una caja con muchas ventas al día |

Para un minimarket con 3 tiendas, el camino **A** es el más realista. El código del sistema queda casi igual y el proveedor se puede cambiar después.

### 2.3 Cambios en el sistema (camino A)

**Base de datos** (un script nuevo, por ejemplo `database/sunat.sql`):

- Tabla `comprobante_electronico`:
  - Columnas: `id_venta`, `tipo` (`BOLETA`, `FACTURA`, `NOTA_CREDITO`, `BAJA`), `estado` (`PENDIENTE`, `ACEPTADO`, `OBSERVADO`, `RECHAZADO`), `hash`, `enlace_pdf`, `codigo_respuesta`, `mensaje_respuesta`, `intentos`, `fecha_envio`, `fecha_respuesta`.
  - Un comprobante por documento, con su historial de intentos.
- Tabla `nota_credito`, para las devoluciones parciales: serie `BC01` o `FC01`, correlativo, motivo (catálogo 09 de SUNAT) y la `id_devolucion` de la que sale.
- Columnas en `tienda` o en la configuración para la ruta y el token del proveedor. El token **no** va en la base ni en GitHub: va en `.env` (`SUNAT_API_URL`, `SUNAT_API_TOKEN`).

**Código:**

1. **La venta no espera a SUNAT.**
   - `VentaServicio::registrar` sigue igual y deja el comprobante `PENDIENTE` en la misma transacción.
   - El envío corre aparte, así una caída de internet no detiene la caja.
2. Un servicio nuevo, `SunatServicio`:
   - Arma el JSON de la venta (cabecera, detalle con IGV 18 % incluido en el precio, total en letras y pagos).
   - Lo envía con `curl`, con tiempo límite.
   - Guarda la respuesta del proveedor.
3. Un **script programado** cada 5 minutos, igual que el respaldo (`scripts/enviar_sunat.php`):
   - Reintenta los comprobantes `PENDIENTE`.
   - Avisa en el inicio del administrador si alguno quedó `RECHAZADO`.
4. **Ticket:**
   - Agregar el QR, el hash y la leyenda de representación impresa.
   - Mientras el comprobante siga pendiente, imprimir "Pendiente de envío a SUNAT".
5. **Anular y devolver:**
   - Anular una factura manda una comunicación de baja.
   - Anular una boleta va en el resumen diario con estado anulado **[revisar]**.
   - Cada devolución parcial emite una **nota de crédito** por lo devuelto.
6. **Pantalla nueva "Comprobantes SUNAT"** (administrador):
   - Muestra el estado de cada comprobante.
   - Tiene un botón para reintentar y la descarga en CSV.

**Pruebas:**

- Unitarias del JSON y del IGV: base imponible = total / 1.18, redondeado a 2 decimales y cuadrando con el total.
- Integración contra el entorno de **pruebas (beta)** del proveedor, que no tiene validez tributaria.

### 2.4 Riesgos y decisiones del grupo

- **IGV:**
  - Hoy los precios incluyen IGV y no se separan.
  - Hay que separar base e IGV por línea sin que el total cambie un céntimo.
  - Se resolvería con céntimos enteros, como ya hace `Dinero`.
- **Productos exonerados:** algunos productos agrícolas no pagan IGV **[revisar]**. Haría falta una columna `afectacion_igv` en `producto`. El catálogo CSV ganaría esa columna.
- **Contingencia:** si internet cae todo el día, los comprobantes se acumulan en `PENDIENTE` y salen cuando vuelve. Hay que respetar el plazo de envío **[revisar]**.
- **Costo:** el del proveedor. Comparar 2 o 3 proveedores autorizados por SUNAT antes de elegir.

**Esfuerzo estimado:** de 2 a 3 semanas para una persona, contando pruebas con el proveedor. La primera semana solo boletas y facturas; la segunda, notas de crédito y bajas.

## 3. Lector de código de barras

### 3.1 Cómo funciona un lector

Un lector USB o inalámbrico común se comporta como un **teclado**: al leer, "escribe" los dígitos del código y aprieta Enter. No necesita programas ni drivers, y cuesta poco. Por eso el sistema solo necesita un campo donde caiga lo que el lector escribe.

El catálogo ya está listo para esto:

- Cada producto por unidad tiene `codigo_barras` único (8 a 14 dígitos).
- Ya existe `ProductoRepository::buscarPorCodigo`.
- La ficha se abre por código (`?ruta=producto&codigo=...`).

### 3.2 Cambios propuestos

1. **Nueva venta:**
   - Un campo "Escanear código" arriba de la lista de productos, con el cursor puesto ahí al abrir la pantalla.
   - En `views/parciales/lineas_producto.php`, cada `<option>` gana `data-codigo="..."`.
   - En `public/app.js`, al recibir Enter en ese campo:
     - Busca la opción con ese código.
     - Si el producto ya está en una fila, suma 1 a la cantidad. Si no, lo pone en la primera fila vacía.
     - Limpia el campo y deja el cursor ahí para el siguiente producto.
     - Si el código no existe, muestra "Código no registrado" y suena un aviso.
     - Con `textContent`, sin `innerHTML`, como pide `tests/seguridad.php`.
   - El Enter del lector **no** debe enviar el formulario: se ataja con `preventDefault` solo en ese campo.
2. **Sin JavaScript** sigue funcionando: el cajero elige en la lista como hoy, y el servidor sigue validando todo en `VentaServicio`.
3. **Ingreso de mercadería, traslado, merma y conteo:** el mismo campo, para que el almacenero escanee al recibir. El conteo físico es donde más tiempo ahorra.
4. **Catálogo:** el buscador ya acepta el código. Si el campo recibe un código exacto, abre la ficha.
5. **Productos al peso:**
   - Papa, plátano y similares no tienen código fijo.
   - Con una **balanza etiquetadora**, la etiqueta trae un EAN-13 que empieza con 2: código interno del producto más peso o precio.
   - Se puede leer en una segunda etapa, con una columna `codigo_balanza` en `producto` **[revisar formato de la balanza]**.

**Pruebas:**

- Una prueba web que mande el código como lo haría el lector.
- En el navegador (Playwright): "escribir" `7750000000011` + Enter dos veces deja 1 fila de leche con cantidad 2 y el total correcto.

**Esfuerzo estimado:** de 1 a 2 días para la venta y el ingreso. La balanza etiquetadora es aparte.

## 4. Orden sugerido

1. **Lector de código de barras**: es poco trabajo, se nota al momento en la caja y no depende de nadie de afuera.
2. **Comprobante electrónico**: primero elegir proveedor y conseguir el acceso de pruebas. Después boletas y facturas, y al final notas de crédito y bajas.
