# Asistente inteligente

Menú **Asistente** (y el botón amarillo *Asistente* abajo a la derecha del panel de Inicio). El empleado toca una
**pregunta rápida** o pregunta en español, escribiendo o con el micrófono, y el sistema responde con los datos de la tienda.

## Preguntas rápidas (sin IA, sin internet)

Botones que se responden al instante desde la base (`RespuestasRapidas`), aunque no haya `ANTHROPIC_API_KEY`.
Cada rol ve solo las que puede usar:

| Pregunta | Quién |
|---|---|
| ¿Cuánto vendimos hoy? / ¿ayer? / ¿este mes? | administrador de la tienda |
| Los 5 más vendidos de la semana · ¿Cuánto vendió cada cajero hoy? · ¿Cómo pagaron hoy los clientes? | administrador de la tienda |
| ¿Cuánto vendió cada tienda este mes? | administrador de la cadena |
| ¿Qué productos tienen stock bajo? · ¿Qué productos no se venden hace 30 días? | todos (su tienda) |
| ¿Quiénes son los mejores clientes del mes? | cajero y administrador |
| ¿Qué alertas hay en la tienda? (stock bajo, por vencer y lo que no se vende, en un solo mensaje) | todos (su tienda) |
| ¿Qué pido al proveedor y cuánto? · ¿Qué productos están por vencer? | almacenero y administrador |
| ¿Cuánto venderemos esta semana? · ¿Cuáles son las horas punta? | administrador de la tienda |
| Quiero hacer una venta (botón a Nueva venta) | quien vende |

Sin clave, lo que se escribe también funciona: se reconoce por palabras clave ("cuánto vendimos ayer", "stock bajo",
"clientes", "yape") y, si no, se busca como producto ("precio del arroz Costeño"). Con clave, lo escrito lo responde
Claude; si Claude no responde (sin internet, clave mala), se usa este mismo modo.

Las respuestas "inteligentes" (qué pedir, pronóstico, horas punta, alertas) son cuentas sobre las ventas guardadas,
no IA: funcionan sin clave y terminan con una idea concreta (pedir, poner en promoción, poner más cajeros).

## Qué se le puede preguntar

| Pregunta de ejemplo | Qué usa | Quién |
|---|---|---|
| ¿Cuánto vendimos hoy? / ¿y el mes pasado? | `resumen_ventas` (ReporteRepository::resumen) | administrador de la tienda |
| ¿Cuánto vendió cada tienda esta semana? | `resumen_ventas` con alcance cadena | administrador de la cadena |
| Los 5 más vendidos, por cajero, por método de pago, por hora | `detalle_ventas` | administrador de la tienda |
| ¿Cuánto cuesta la leche Gloria? ¿Hay arroz Costeño? | `buscar_productos` (precio y stock) | todos; el cajero y el almacenero solo ven su tienda |
| ¿Qué productos tienen stock bajo? | `productos_por_reponer` (PanelRepository) | todos, de su tienda |
| ¿Qué no se vende? | `productos_sin_venta` (ReporteRepository::catalogoConStock) | todos, de su tienda |
| ¿Qué pido y cuánto? | `sugerencia_pedido`: Reposicion (venta por día de 14 días, para cuántos días alcanza, cuánto pedir para 14 días) y botón *Armar la orden de compra* | almacenero y administrador |
| ¿Qué vence pronto? | `productos_por_vencer`: Vencimiento (reparto FIFO de los lotes), con su valor al costo | almacenero y administrador |
| ¿Cuánto venderemos esta semana? | `pronostico_ventas`: AnalisisVentas::pronostico, el promedio del mismo día de la semana en las últimas 8 semanas y la tendencia | administrador de la tienda |
| ¿A qué hora viene más gente? | `horas_punta`: AnalisisVentas::horasPunta, con el mapa día × hora de 28 días | administrador de la tienda |
| ¿Quiénes compran más? | `mejores_clientes` (AsistenteRepository) | cajero y administrador |
| Quiero hacer una venta / registrar un ingreso | `abrir_pagina`: botón a esa pantalla | según el menú de cada rol |

El asistente **no cambia datos**: para vender, recibir mercadería o anular, da el botón a la pantalla donde se hace y
se confirma como siempre.

## Cómo funciona

1. `app.js` envía la pregunta a `POST /?ruta=asistente_preguntar` (con el token CSRF). Sin JavaScript, el formulario
   recarga la página.
2. `AsistenteController` arma `Asistente` con las `HerramientasAsistente` del usuario que entró.
3. `Asistente` llama a la API de Claude (`ClienteClaude`, curl a `api.anthropic.com`). Claude elige qué herramienta
   usar; PHP la ejecuta con los permisos del usuario (los mismos de `Usuario`) y le devuelve el resultado; Claude
   arma la respuesta en español. Máximo 6 vueltas por pregunta.
4. La conversación (solo texto, las últimas 8 preguntas) se guarda en la sesión: se borra al salir.

Límite: 20 preguntas a Claude cada 10 minutos por sesión (cada una se paga a la API). Las rápidas no cuentan.
Claude solo habla de la tienda: si se le pregunta otra cosa, lo dice y sugiere una pregunta de la tienda.

## Activarlo

En el `.env` del servidor:

```
ANTHROPIC_API_KEY=sk-ant-...      # se crea en https://platform.claude.com -> API keys
ANTHROPIC_MODEL=                  # opcional; por defecto claude-opus-5-5
```

Sin la clave el asistente igual aparece y responde las preguntas rápidas y las palabras clave. No hace falta migrar la base.
El servidor necesita internet y la extensión curl de PHP (ya la usa Docker y `docs/despliegue.md`).

## Pruebas

- `backend/tests/unitarios.php`: el ciclo de herramientas con un modelo falso (sin internet).
- `backend/tests/integracion.php`: cada herramienta contra la base, con los permisos de Rosa, Luis y Carmen.
- `backend/tests/web.php`: la página y la ruta POST sin clave (CSRF, JSON, aviso).
