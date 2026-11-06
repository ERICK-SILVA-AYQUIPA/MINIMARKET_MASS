# Asistente inteligente

Menú **Asistente** (y el botón amarillo *Asistente* abajo a la derecha de cada pantalla). El empleado pregunta en
español, escribiendo o con el micrófono, y el sistema responde con sus propios datos.

## Qué se le puede preguntar

| Pregunta de ejemplo | Qué usa | Quién |
|---|---|---|
| ¿Cuánto vendimos hoy? / ¿y el mes pasado? | `resumen_ventas` (ReporteRepository::resumen) | administrador de la tienda |
| ¿Cuánto vendió cada tienda esta semana? | `resumen_ventas` con alcance cadena | administrador de la cadena |
| Los 5 más vendidos, por cajero, por método de pago, por hora | `detalle_ventas` | administrador de la tienda |
| ¿Cuánto cuesta la leche Gloria? ¿Hay arroz Costeño? | `buscar_productos` (precio y stock) | todos; el cajero y el almacenero solo ven su tienda |
| ¿Qué productos tienen stock bajo? | `productos_por_reponer` (PanelRepository) | todos, de su tienda |
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

Límite: 20 preguntas cada 10 minutos por sesión (cada pregunta se paga a la API).

## Activarlo

En el `.env` del servidor:

```
ANTHROPIC_API_KEY=sk-ant-...      # se crea en https://platform.claude.com -> API keys
ANTHROPIC_MODEL=                  # opcional; por defecto claude-opus-5-5
```

Sin la clave, el menú y el botón no aparecen, y `/?ruta=asistente` explica qué falta. No hace falta migrar la base.
El servidor necesita internet y la extensión curl de PHP (ya la usa Docker y `docs/despliegue.md`).

## Pruebas

- `backend/tests/unitarios.php`: el ciclo de herramientas con un modelo falso (sin internet).
- `backend/tests/integracion.php`: cada herramienta contra la base, con los permisos de Rosa, Luis y Carmen.
- `backend/tests/web.php`: la página y la ruta POST sin clave (CSRF, JSON, aviso).
