# Sistema de diseño de la página web

La página es PHP con CSS propio, **sin Tailwind** ni paso de compilación. Por eso las reglas de diseño
(grilla de 4/8, una sola letra para la interfaz, un solo acento, curvas suaves) no se escriben con clases
como `p-4` o `shadow-xl`, sino con **variables CSS** que usan los mismos números y nombres que Tailwind.

| Archivo | Qué tiene |
|---|---|
| `frontend/public/css/tokens.css` | **La única fuente** de colores, espacios, radios, sombras, vidrio, letras y curvas. Se carga antes que todo. |
| `frontend/public/css/estilos.css` | Los componentes (botones, tablas, caja, venta…). Solo usa las variables de `tokens.css`: no lleva valores sueltos de espacio, radio ni curva. |
| `frontend/public/fuentes/` | Inter, JetBrains Mono y Outfit, guardadas en el propio sitio (licencia OFL, ver `LICENCIA.txt`). |

## Equivalencias con Tailwind

| Tailwind | En este proyecto | Valor |
|---|---|---|
| `p-1` `p-2` `p-3` `p-4` `p-6` `p-8` | `var(--s-1)` `var(--s-2)` `var(--s-3)` `var(--s-4)` `var(--s-6)` `var(--s-8)` | 4, 8, 12, 16, 24, 32 px |
| `gap-6`, `m-8` | `gap: var(--s-6)`, `margin: var(--s-8)` | 24 px, 32 px |
| `rounded`, `rounded-lg`, `rounded-xl`, `rounded-2xl`, `rounded-full` | `--radio-base`, `--radio-lg`, `--radio-xl`, `--radio-2xl`, `--radio-full` | 4, 8, 12, 16, 9999 px |
| `shadow-sm` … `shadow-xl` | `--sombra-xs`, `--sombra-sm`, `--sombra-md`, `--sombra-lg`, `--sombra-xl` | sombras en capas, suaves |
| `border-slate-200`, `border-white/10` | `--borde` (`rgba(15,23,42,.08)`), `--borde-oscuro` (`rgba(255,255,255,.10)`) | bordes de baja opacidad |
| `text-slate-500` | `--suave` (`#64748b`) | texto secundario |
| `backdrop-blur` | `--vidrio`, `--vidrio-oscuro`, `--vidrio-desenfoque` | vidrio |
| `font-sans`, `font-mono` | `--letra` (Inter), `--mono` (JetBrains Mono) | |

Los nombres viejos `--e1`…`--e6`, `--radio`, `--radio-chico`, `--sombra` y `--sombra-alta` siguen
funcionando (apuntan a los nuevos) para no romper estilos que alguien tenga en una rama abierta.

## Reglas

**Grilla de 4/8.** Todo `padding`, `margin` y `gap` sale de `--s-*`, y las alturas de los controles
(botones, chips, campos) son múltiplos de 4. `--s-px` (1 px) y `--s-0_5` (2 px) solo existen para líneas
finas y ajustes ópticos. Anchos de íconos, fotos y columnas no entran en la grilla.

**Letras.** Inter para toda la interfaz; títulos con tracking reducido (`--tracking-titulo`, −0.02 em) y
rótulos en MAYÚSCULAS con tracking aumentado (`--tracking-mayusculas`, 0.08 em). JetBrains Mono para los
números: todo lo que antes pedía cifras tabulares (montos, totales, conteos, códigos, la columna `.num`
de las tablas, el comprobante de cierre). Outfit queda solo para la marca (logo, «Minimarket Mass», la
portada del login y la bienvenida).

**Color.** Base neutra *slate* + **un solo acento**: el amarillo de la marca (`--marca`), en el botón
principal, el activo del menú, el halo del foco y la guía «Cómo se usa». El menú lateral y las barras
oscuras mantienen el oscuro de la marca. Verde, rojo y ámbar no son acentos: solo dicen ok, error o alerta
(con contraste AA). Los enlaces dejaron de ser azules: van en el color del texto, subrayados.

**Profundidad.** Bordes de baja opacidad y sombras suaves en capas (`--sombra-xs` a `--sombra-xl`).

**Vidrio.** Solo en lo que flota: el modal de confirmación y su fondo, los paneles laterales y su velo,
la barra del turno (fija al bajar), la barra del celular, los avisos cortos (*toast*) y el globo del menú
plegado. Si el navegador no sabe desenfocar (`@supports not (backdrop-filter…)`), quedan sólidos.

**Movimiento.** Ninguna curva lineal en lo que se toca:

- `--curva` = `cubic-bezier(0.32, 0.72, 0, 1)` para colores, sombras, paneles y apariciones.
- `--resorte` = un *spring* hecho con `linear()` (pasa un 8 % y vuelve) para escalas: botones, tarjetas,
  fotos. Los navegadores sin `linear()` usan `ease`.
- Duraciones: `--dur-1` 150 ms, `--dur-2` 250 ms, `--dur-3` 400 ms, `--dur-resorte` 450 ms.
- Los bucles de ambiente (el punto que late, la ruedita de «cargando», el brillo que flota en el login)
  quedan como estaban: la ruedita tiene que girar a velocidad constante.
- `prefers-reduced-motion` sigue apagando todas las animaciones.

**Micro-interacciones.** Los botones crecen a `scale(1.02)` con más sombra al pasar el cursor y se hunden a
`scale(0.97)` al presionar; las tarjetas de producto, los chips y los productos de la venta crecen a 1.03.
Los valores están en `--escala-hover`, `--escala-hover-tarjeta` y `--escala-press`.

## Cambiar algo

1. Cambia el valor en `tokens.css` (no en `estilos.css`).
2. Sube el `?v=` de `tokens.css` o `estilos.css` en `frontend/views/layout_inicio.php` para que los
   navegadores no usen la copia vieja.
3. Vuelve a generar las capturas de las pruebas E2E (README, *Pruebas E2E y capturas*) y revisa en el diff
   que el cambio sea el que querías.

## Figma

El proyecto no tiene archivo de Figma ni conector, así que los tokens se definieron directo en código. Si
más adelante hay uno, sus variables (colores, radios, letras, espacios) se exportan a estos mismos nombres
de `tokens.css` y el resto del CSS no cambia.

## Lo que no se tocó

- `frontend/views/ventas/ticket.php`: el ticket de 80 mm tiene su propio estilo de impresora térmica.
- Los fondos decorativos de la portada del login y del panel de inicio (degradados y rejilla) se dejaron
  como estaban; solo cambiaron sus letras y tonos neutros.
