# Plan si se cae el internet

Qué pasa con Minimarket Mass cuando se corta el internet (o el servidor), qué hace el sistema solo para no perder ni duplicar ventas, cómo se recupera una venta y qué hace la tienda mientras tanto. Para el cajero, la versión corta está en la pantalla **Ayuda** del sistema (pie de cada página).

## 1. Resumen

- **Servidor**: las 3 tiendas comparten **una sola base** (stock de la cadena, traslados, correlativos de boletas). El servidor está en internet (`docs/despliegue.md`, sección 6), así que **si una tienda pierde internet, esa tienda no llega al sistema**. La tienda donde está el servidor (si se instala en una PC de la tienda) sigue vendiendo por su red local.
- **Lo más efectivo y barato**: un **internet de respaldo** en cada tienda (router con chip de datos de otro operador que entra solo cuando cae la línea principal) y un **UPS** para el router y la PC de caja (sección 5).
- **Lo que ya hace el sistema** (sección 2): avisa en rojo que no hay conexión, no envía la venta mientras tanto, guarda la venta armada en el navegador, **nunca registra dos veces** la misma venta aunque se reenvíe, y al volver dice si la última venta quedó registrada.
- **Si el corte dura**: contingencia en papel, solo en efectivo, y se pasa al sistema al volver (sección 4).
- **No hay un modo sin conexión completo** (vender sin servidor y sincronizar después): ver sección 7.

## 2. Lo que hace el sistema cuando se corta

| Qué | Cómo | Dónde está |
|---|---|---|
| Aviso **Sin conexión con el servidor** | Franja roja arriba de la página. En **Nueva venta** se pregunta al servidor (`/?ruta=salud`) cada 30 segundos, y cada 5 mientras está caído. Cuando vuelve sale **Conexión recuperada** en verde | `frontend/public/js/app.js` (conexión) |
| No envía la venta sin conexión | Con la franja roja, **Registrar venta** no sale: un aviso dice que la venta no se envió y queda guardada | `app.js` (venta sin terminar) |
| Guarda la venta armada | Productos, cantidades, boleta o factura, DNI o RUC y pagos se guardan en el navegador de esa PC, para ese cajero, hasta 12 horas. Si se cierra la pestaña, se apaga la PC o se va la luz, al volver a **Nueva venta** aparece armada | `app.js` |
| Una venta no se registra dos veces | Cada pantalla de **Nueva venta** trae una clave única (`clave_operacion`). Si el envío llegó al servidor y se manda otra vez (F5, Atrás y Registrar, o la venta recuperada), el servidor devuelve **el mismo comprobante** con el aviso *"Esta venta ya estaba registrada"*: no cobra ni descuenta stock otra vez | `VentaController::registrar` con `OperacionMovilServicio` (la misma tabla `api_operacion` que usa la app Android) |
| ¿Se registró mi venta? | Al volver a **Nueva venta** después de un envío que no terminó, el sistema pregunta al servidor (`/?ruta=venta_estado`). Si se registró, muestra su número y **Ver comprobante**; si no, la devuelve armada para registrarla | `VentaController::estado` |
| Monitor externo | `GET /?ruta=salud` responde `{"estado":"ok","base":"ok","version":"1.0.0",...}` sin login (503 si MySQL está caído). La app Android ya tenía `GET /api/v1/salud` | `frontend/public/index.php` |

La app Android ya enviaba cada venta e ingreso con su `Idempotency-Key` (`docs/api_movil.md`): reintentar no duplica.

## 3. Cómo se recupera una venta (cajero)

| Pasó esto | Qué hacer |
|---|---|
| Salió la franja roja mientras armaba la venta | No cerrar la página. Esperar a que diga **Conexión recuperada** y pulsar **Registrar venta**. Si el cliente no puede esperar: contingencia (sección 4) y **Vaciar** la venta del sistema |
| Pulsé **Registrar venta** y el navegador mostró un error (sin página, "no se puede acceder") | **No cobrar otra vez.** Cuando vuelva la conexión, entrar a **Nueva venta**: dice si la venta ya quedó registrada (con su número) o la devuelve armada para registrarla. También sirve recargar (F5) y aceptar reenviar: si ya estaba, sale el mismo comprobante |
| Se cerró el navegador, se fue la luz o se apagó la PC | Volver a entrar **en la misma PC y con el mismo usuario** a **Nueva venta**: la venta que estaba armando aparece de nuevo con un aviso amarillo |
| No sé si una venta se registró y ya no está en el navegador | **Ventas** → filtro de hoy → buscar por la hora y el total. O **Caja** → el turno: lo cobrado debe cuadrar con el efectivo de la gaveta |

Regla para el cajero: **enviar otra vez nunca duplica**; cobrar otra vez al cliente, sí. Ante la duda, se pregunta al sistema, no al cliente.

## 4. Contingencia en papel (el sistema no vuelve pronto)

La decide el administrador (o el cajero si no hay nadie más) cuando no hay conexión por más de 10 minutos y hay clientes esperando.

1. **Solo efectivo.** La tarjeta (POS del banco) y Sip necesitan internet propio; si el POS tiene chip de datos puede seguir funcionando, pero ese pago se anota igual.
2. **Anotar cada venta** en la hoja de contingencia (plantilla abajo). Si el cliente pide comprobante, se le entrega una nota de venta manual con el número de la fila de la hoja.
3. **No mezclar el efectivo**: el efectivo de la contingencia va en un sobre aparte dentro de la gaveta, hasta pasarlo al sistema.
4. **Al volver el sistema**: el mismo cajero, con su turno abierto, registra cada fila en **Nueva venta** (en efectivo, con el DNI o RUC si lo pidieron) y escribe en la hoja el número de comprobante que salió. Recién entonces el efectivo del sobre pasa a la gaveta.
5. **El administrador revisa** la hoja contra **Ventas** del día y la guarda (foto o escaneada) con el cierre de caja. El sistema registra la hora en que se pasó la venta, no la de la hoja: la hoja es el respaldo de la hora real.

**Comprobantes ante SUNAT**: hoy el sistema **no envía** boletas a SUNAT (es un ticket interno; ver `docs/propuesta_sunat_y_lector.md`). Cuando se integre la facturación electrónica, la emisión en contingencia tiene reglas propias (comprobantes de contingencia autorizados y su declaración posterior en un plazo). **Confirmarlas con el contador** antes de usar este plan con comprobantes reales.

### Plantilla de la hoja de contingencia

Imprimir 2 o 3 y dejarlas en cada caja.

```
MINIMARKET MASS · HOJA DE CONTINGENCIA          Tienda: ____________   Fecha: ___/___/______
Cajero: ______________________   Caja N.°: ___   Inicio del corte: ___:___   Fin: ___:___

N.° | Hora  | Productos y cantidades                         | Total S/ | DNI/RUC      | Comprobante al pasar
----+-------+------------------------------------------------+----------+--------------+---------------------
 1  |       |                                                |          |              |
 2  |       |                                                |          |              |
 3  |       |                                                |          |              |
 …  |       |                                                |          |              |

Total en efectivo de la hoja: S/ ________   Pasadas al sistema por: __________   Revisó (administrador): __________
```

## 5. Preparar la tienda (administrador)

| Medida | Para qué |
|---|---|
| **Internet de respaldo**: router con chip de datos de **otro operador**, con cambio automático (failover) | Un corte de la línea principal ni se nota. Es lo que más ventas salva |
| **UPS** para el router, la PC de caja y la ticketera | Un corte de luz corto no apaga la caja (y la venta armada no se pierde) |
| **Monitor externo** de `https://TU-DOMINIO/?ruta=salud` (cualquier servicio gratuito de monitoreo de páginas web) | Avisa por correo o celular si el servidor se cae, antes de que llame una tienda |
| **Hojas de contingencia** impresas en cada caja | Seguir vendiendo en efectivo sin improvisar |
| **Practicar una vez** con el equipo: desconectar el router 5 minutos | Que cada cajero haya visto la franja roja y la recuperación |
| **Respaldo diario** (`backend/scripts/programar_respaldo`) | El respaldo local no necesita internet; la copia fuera del servidor (`RESPALDO_REMOTO`) se pone al día en la siguiente corrida |
| **Contacto de soporte** en `.env` (`SOPORTE_*`) | Sale en el pie de cada página y en Ayuda: el cajero sabe a quién llamar |

## 6. Servidor dentro de la tienda

Si el sistema se usa en **una sola tienda**, o se instala el servidor en una PC de la tienda principal, las cajas de esa tienda lo abren por la red local (`http://IP-del-servidor`) y **siguen vendiendo sin internet**. La instalación es la misma (`docs/instalacion.md`): una PC que quede prendida, con Docker o Laragon, IP fija en el router y el respaldo diario copiado a un USB o a la nube.

Las otras tiendas entran a ese servidor por internet (IP pública o VPN), así que para ellas vale lo mismo de las secciones 3 a 5. **No conviene un servidor separado por tienda**: cada uno tendría su propia base y se perderían el stock de la cadena, los traslados, el reporte de las 3 tiendas y la numeración única de los comprobantes.

## 7. Lo que no hace (y por qué)

**No hay un modo sin conexión completo** (vender con el servidor caído y sincronizar después). No se implementó porque:

- **El número del comprobante** (B001-00000123) lo asigna la base al registrar, en orden y sin huecos. Dos cajas sin conexión no pueden saber qué número sigue.
- **El stock** se valida en el servidor en el momento de vender, con las 3 tiendas y los traslados. Sin conexión se podría vender lo que ya no hay.
- **El precio** que vale es el de la base; un navegador sin conexión puede tener precios viejos.
- Hacerlo bien es un proyecto aparte: una base en cada caja, una serie de comprobantes por caja, y reglas para resolver choques al sincronizar.

Por eso el plan combina lo que sí es seguro: no perder la venta armada, no duplicarla al reenviarla, saber si se registró, y la contingencia en papel para un corte largo.

## 8. Cómo se prueba

| Prueba | Qué revisa |
|---|---|
| `backend/tests/api.php`, *Venta web reenviada* | El mismo formulario enviado dos veces deja una sola venta, el stock baja una vez, el segundo envío avisa "ya estaba registrada" y `venta_estado` devuelve el comprobante; otro cajero no ve esa clave |
| `backend/tests/web.php`, *Versión, soporte y sin internet* | `?ruta=salud` sin login y sin cookie, la clave única en cada Nueva venta, que un error 422 conserve la clave, y `venta_estado` solo para el cajero |
| `backend/tests/seguridad.php` | `app.js` solo llama a `/?ruta=...` de este mismo sitio |
| A mano, en el navegador | Con la venta armada: DevTools → Network → **Offline** → sale la franja roja y **Registrar venta** no envía; volver a **Online** → *Conexión recuperada* y se registra. Recargar la página con productos en el carrito → la venta vuelve armada |
