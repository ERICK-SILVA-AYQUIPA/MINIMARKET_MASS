# Cambios por versión

La versión actual está en el archivo `VERSION` y sale en el pie de cada página, en **Ayuda** y en `GET /?ruta=salud`.
Al entregar una versión nueva: cambiar `VERSION` (formato `1.2.3`) y agregar aquí lo que cambió, lo más nuevo arriba.

## 1.0.0 · 2026-11-05

Primera versión de entrega: ventas con boleta y factura, caja con arqueo a ciegas, inventario por tienda
(ingresos, traslados, mermas, conteo, vencimientos, reposición y kardex), reportes y utilidad, auditoría,
roles con permisos distintos y la app Android.

En esta versión, además:

- **Plan si se cae el internet** (`docs/plan_sin_internet.md`): franja roja *Sin conexión con el servidor*,
  la venta armada se guarda en el navegador, una venta reenviada después de un corte no se registra dos veces
  y, al volver, el sistema dice si la última venta quedó registrada.
- **Versión y soporte visibles**: en el pie de cada página y en la nueva pantalla **Ayuda** (con el manual corto
  de cada rol). El contacto se configura en `.env` (`SOPORTE_*`).
- **Documentación de entrega**: guía de instalación corta (`docs/instalacion.md`) y manual de usuario con
  resumen en una página (`docs/manual_usuario.md`).
- `GET /?ruta=salud` para monitorear el servidor.
