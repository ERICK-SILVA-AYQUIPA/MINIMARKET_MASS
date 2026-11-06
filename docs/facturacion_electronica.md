# Ticket, IGV, facturación electrónica e impresión térmica

Qué hace hoy el sistema con el ticket de venta, qué falta para que sea un comprobante válido ante SUNAT
y cómo imprimir directo en una ticketera de 58 u 80 mm. El plan completo de SUNAT está en
[propuesta_sunat_y_lector.md](propuesta_sunat_y_lector.md).

> **Confirmar con un contador** antes de emitir comprobantes electrónicos: el régimen de la empresa,
> qué productos están exonerados de IGV (por ejemplo, frutas y verduras frescas) y si las boletas se
> envían una por una o en resumen diario.

## 1. Situación actual: TICKET INTERNO

- Las ventas **no se envían a SUNAT**. Por eso el ticket sale como **TICKET DE VENTA INTERNO** con el
  recuadro **«NO VÁLIDO COMO COMPROBANTE DE PAGO · Control interno de la tienda: no se envía a SUNAT»**, y la página del comprobante
  lo dice debajo del detalle.
- El número (`B001-00000012`) se mantiene como referencia interna: es el que se usará cuando se emita
  la boleta electrónica.
- `php backend/scripts/verificar_instalacion.php` avisa mientras los tickets sigan siendo internos.

## 2. Datos de la empresa en el ticket (`.env`)

| Variable | Ejemplo | Va en el ticket |
|---|---|---|
| `EMPRESA_RAZON_SOCIAL` | `MI EMPRESA S.A.C.` | Razón social |
| `EMPRESA_RUC` | `20XXXXXXXXX` | RUC (se valida el dígito verificador) |
| `EMPRESA_NOMBRE_COMERCIAL` | `TIENDA MASS` | Título del ticket |
| `EMPRESA_DIRECCION_FISCAL` | `Av. ..., Arequipa` | Dirección fiscal |
| `TICKET_ANCHO_MM` | `80` o `58` | Ancho del papel por defecto |

- Si faltan la razón social o el RUC, el ticket **no inventa** datos: la página del ticket (fuera del papel)
  muestra el aviso para configurarlos, y `verificar_instalacion.php` también.
- Debajo va la tienda: nombre, dirección y teléfono de **Tiendas → Editar**. Mientras la tienda tenga la
  dirección de los datos de prueba («Direccion de ejemplo…»), esa línea no se imprime.

## 3. Subtotal, IGV y total

El precio de venta ya incluye el IGV (18 %). El ticket y la página del comprobante muestran:

```
OP. GRAVADA   28.22
IGV 18%        5.08
TOTAL     S/  33.30
```

Se calcula línea por línea en céntimos enteros (`backend/models/DesgloseIgv.php`): valor sin IGV =
importe / 1.18 redondeado al céntimo, e IGV = importe − valor. Así gravada + IGV es siempre el total
exacto y coincide con lo que se mandaría al proveedor OSE/PSE. Hoy todo se trata como **gravado**;
los productos exonerados necesitarían una marca por producto **[contador]**.

## 4. Integración con un OSE/PSE (preparada, sin credenciales)

Código en `backend/models/facturacion/`:

| Archivo | Qué es |
|---|---|
| `ProveedorFacturacion.php` | Interfaz: `nombre()`, `estaListo()`, `enviar(ComprobanteParaEnviar)` |
| `SinFacturacionElectronica.php` | Lo de hoy: no envía nada, el ticket es interno |
| `NubefactProveedor.php` | Arma el JSON tipo Nubefact (cabecera, ítems con valor sin IGV e IGV, totales) y lo envía por HTTPS con el token. Si no hay internet devuelve «no aceptado» para reintentar después |
| `FacturacionElectronica.php` | Elige el proveedor según `.env` |
| `ComprobanteParaEnviar.php`, `RespuestaFacturacion.php` | Lo que se envía y lo que responde el proveedor (estado, hash, QR, PDF) |

`.env`:

```
FACTURACION_PROVEEDOR=ninguno      # o nubefact
NUBEFACT_URL=                      # la ruta https que da el proveedor al dueño
NUBEFACT_TOKEN=                    # el token: SOLO en .env, nunca en GitHub ni en la base
```

**Falta para que el ticket deje de ser interno** (sección 2.3 de la propuesta):

1. Tabla `comprobante_electronico` (estado PENDIENTE / ACEPTADO / RECHAZADO, hash, enlace, intentos).
2. Que `VentaServicio::registrar` deje el comprobante PENDIENTE en la misma transacción.
3. Un envío programado (`backend/scripts/enviar_sunat.php`, cada 5 minutos) que llame a `enviar()` y guarde la respuesta.
4. El ticket con el QR y el hash, y el texto «Representación impresa de la Boleta de Venta Electrónica».
5. Recién ahí, `FacturacionElectronica::$emisionConectada = true`. Hasta entonces el ticket sigue diciendo
   que es interno **aunque haya un proveedor configurado**, para no imprimir como válido algo que SUNAT no recibió.
6. Notas de crédito para devoluciones y comunicación de baja para anulaciones.

Los nombres de los campos de Nubefact siguen su documentación pública: revisarlos con la versión vigente
y con la cuenta de prueba (demo) del proveedor antes de pasar a producción.

## 5. Impresión en la ticketera térmica

- En el comprobante, **Imprimir en ticketera** abre el ticket y lanza la impresión sola; al terminar vuelve
  al comprobante. **Ver ticket / PDF** abre el ticket sin imprimir (para guardarlo en PDF).
- En la página del ticket se elige **58 mm** u **80 mm**; el navegador de cada caja recuerda su ancho.
  La hoja usa `@page { size: 58mm|80mm auto; margin: 0 }` y el ancho que imprime la cabeza (48 o 72 mm).
- En la impresora: elegir la ticketera, márgenes **Ninguno** y escala 100 %.

### Impresión directa, sin la ventana de impresión

Un navegador no puede imprimir sin preguntar por seguridad, salvo que se abra en modo quiosco. En la PC
de la caja:

1. Instalar el controlador de la ticketera y ponerla como **impresora predeterminada** de Windows, con el
   papel de 80 mm (o 58 mm).
2. Crear un acceso directo de Chrome con:
   `"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing http://localhost:8000`
3. Abrir el sistema solo desde ese acceso directo. **Imprimir en ticketera** sale directo al papel.

Si más adelante se necesita cortar el papel o abrir la gaveta, el paso siguiente es imprimir en ESC/POS
con un programa local (por ejemplo QZ Tray); no hace falta para el ticket.
