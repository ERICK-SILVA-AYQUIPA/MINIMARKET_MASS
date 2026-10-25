<?php
declare(strict_types=1);

require_once __DIR__ . '/SerieComprobante.php';
require_once __DIR__ . '/CajaTurno.php';
require_once __DIR__ . '/Venta.php';
require_once __DIR__ . '/DetalleVenta.php';
require_once __DIR__ . '/AnulacionVenta.php';
require_once __DIR__ . '/pagos/PagoEfectivo.php';
require_once __DIR__ . '/pagos/PagoTarjeta.php';
require_once __DIR__ . '/pagos/PagoSip.php';

/**
 * Ventas con su serie, turno, detalle, pagos y anulacion.
 * Hoy: listas fijas copiadas de database/datos.sql.
 * Sesion 5: los mismos metodos leeran de MySQL (vw_comprobante y sp_boleta de boleta.sql).
 */
class VentaRepository
{
    /** @var SerieComprobante[] */
    private array $series;

    /** @var CajaTurno[] */
    private array $turnos;

    /** @var Venta[] */
    private array $ventas;

    /** @var DetalleVenta[] */
    private array $detalles;

    /** @var PagoVenta[] */
    private array $pagos;

    /** @var AnulacionVenta[] */
    private array $anulaciones;

    public function __construct()
    {
        // INSERT INTO serie_comprobante ... (datos.sql): id, serie, ultimo_numero, id_tienda, id_tipo_comprobante
        $this->series = [
            new SerieComprobante(1, 'B001', 4, 1, 1),
            new SerieComprobante(2, 'F001', 3, 1, 2),
            new SerieComprobante(3, 'B002', 4, 2, 1),
            new SerieComprobante(4, 'F002', 0, 2, 2),
            new SerieComprobante(5, 'B003', 2, 3, 1),
            new SerieComprobante(6, 'F003', 1, 3, 2),
        ];

        // INSERT INTO caja_turno ... (datos.sql): turnos 1 a 4 cerrados, 5 y 6 abiertos
        $this->turnos = [
            new CajaTurno(1, '2026-10-17 08:00:00', 100.00, '2026-10-17 16:00:00', 150.70, 'CERRADO', 1, 2),
            new CajaTurno(2, '2026-10-17 08:00:00', 100.00, '2026-10-17 16:00:00', 100.00, 'CERRADO', 3, 5),
            new CajaTurno(3, '2026-10-18 08:00:00', 100.00, '2026-10-18 16:00:00', 100.00, 'CERRADO', 1, 2),
            new CajaTurno(4, '2026-10-18 08:00:00', 100.00, '2026-10-18 16:00:00', 151.30, 'CERRADO', 5, 8),
            new CajaTurno(5, '2026-10-19 08:00:00', 100.00, null, null, 'ABIERTO', 1, 2),
            new CajaTurno(6, '2026-10-19 08:00:00', 100.00, null, null, 'ABIERTO', 3, 5),
        ];

        // INSERT INTO venta ... (datos.sql): la venta 4 esta ANULADA
        $this->ventas = [
            new Venta(1, 1, '2026-10-17 09:15:00', 14.20, 'EMITIDA', 1, 1, null),
            new Venta(2, 1, '2026-10-17 11:40:00', 136.50, 'EMITIDA', 2, 1, 2),
            new Venta(3, 1, '2026-10-17 12:30:00', 15.75, 'EMITIDA', 3, 2, 1),
            new Venta(4, 2, '2026-10-17 14:05:00', 5.20, 'ANULADA', 1, 1, null),
            new Venta(5, 2, '2026-10-17 15:00:00', 17.50, 'EMITIDA', 3, 2, null),
            new Venta(6, 1, '2026-10-18 09:30:00', 26.50, 'EMITIDA', 5, 4, null),
            new Venta(7, 3, '2026-10-18 10:10:00', 19.40, 'EMITIDA', 1, 3, 3),
            new Venta(8, 1, '2026-10-18 11:45:00', 104.70, 'EMITIDA', 6, 4, 2),
            new Venta(9, 2, '2026-10-18 13:20:00', 40.50, 'EMITIDA', 2, 3, 3),
            new Venta(10, 2, '2026-10-18 14:10:00', 24.80, 'EMITIDA', 5, 4, null),
            new Venta(11, 4, '2026-10-19 08:45:00', 12.60, 'EMITIDA', 1, 5, null),
            new Venta(12, 3, '2026-10-19 09:20:00', 37.50, 'EMITIDA', 3, 6, 1),
            new Venta(13, 3, '2026-10-19 10:05:00', 99.80, 'EMITIDA', 2, 5, 2),
            new Venta(14, 4, '2026-10-19 11:30:00', 17.25, 'EMITIDA', 3, 6, null),
        ];

        // INSERT INTO detalle_venta ... (datos.sql): id, cantidad, precio_unitario, importe, id_venta, id_producto
        $this->detalles = [
            new DetalleVenta(1, 2.000, 4.50, 9.00, 1, 1),
            new DetalleVenta(2, 1.000, 3.20, 3.20, 1, 7),
            new DetalleVenta(3, 0.800, 2.50, 2.00, 1, 8),
            new DetalleVenta(4, 6.000, 6.50, 39.00, 2, 3),
            new DetalleVenta(5, 12.000, 5.50, 66.00, 2, 5),
            new DetalleVenta(6, 3.000, 10.50, 31.50, 2, 4),
            new DetalleVenta(7, 1.000, 9.90, 9.90, 3, 6),
            new DetalleVenta(8, 1.500, 3.90, 5.85, 3, 9),
            new DetalleVenta(9, 1.000, 5.20, 5.20, 4, 2),
            new DetalleVenta(10, 2.000, 4.80, 9.60, 5, 10),
            new DetalleVenta(11, 1.000, 7.90, 7.90, 5, 11),
            new DetalleVenta(12, 3.000, 4.50, 13.50, 6, 1),
            new DetalleVenta(13, 2.000, 6.50, 13.00, 6, 3),
            new DetalleVenta(14, 1.250, 3.20, 4.00, 7, 12),
            new DetalleVenta(15, 2.000, 2.50, 5.00, 7, 8),
            new DetalleVenta(16, 2.000, 5.20, 10.40, 7, 2),
            new DetalleVenta(17, 4.000, 10.50, 42.00, 8, 4),
            new DetalleVenta(18, 3.000, 9.90, 29.70, 8, 6),
            new DetalleVenta(19, 6.000, 5.50, 33.00, 8, 5),
            new DetalleVenta(20, 2.000, 10.50, 21.00, 9, 4),
            new DetalleVenta(21, 3.000, 6.50, 19.50, 9, 3),
            new DetalleVenta(22, 4.000, 3.20, 12.80, 10, 7),
            new DetalleVenta(23, 2.000, 4.50, 9.00, 10, 1),
            new DetalleVenta(24, 1.200, 2.50, 3.00, 10, 8),
            new DetalleVenta(25, 2.000, 5.50, 11.00, 11, 5),
            new DetalleVenta(26, 0.500, 3.20, 1.60, 11, 12),
            new DetalleVenta(27, 3.000, 6.50, 19.50, 12, 3),
            new DetalleVenta(28, 4.000, 4.50, 18.00, 12, 1),
            new DetalleVenta(29, 10.000, 4.80, 48.00, 13, 10),
            new DetalleVenta(30, 10.000, 3.20, 32.00, 13, 7),
            new DetalleVenta(31, 2.000, 9.90, 19.80, 13, 6),
            new DetalleVenta(32, 2.500, 3.90, 9.75, 14, 9),
            new DetalleVenta(33, 3.000, 2.50, 7.50, 14, 8),
        ];

        // INSERT INTO pago_venta + efectivo / tarjeta / billetera_digital_sip (datos.sql)
        // Cada pago se crea con la clase de su subtipo; la venta 2, la 8 y la 13 tienen dos pagos
        $this->pagos = [
            new PagoEfectivo(1, 14.20, '2026-10-17 09:15:00', 'VALIDO', 1, 20.00, 5.80),
            new PagoTarjeta(2, 100.00, '2026-10-17 11:40:00', 'VALIDO', 2, 'CREDITO', 'VISA', '1234', 'A12345'),
            new PagoEfectivo(3, 36.50, '2026-10-17 11:40:00', 'VALIDO', 2, 40.00, 3.50),
            new PagoSip(4, 15.75, '2026-10-17 12:30:00', 'VALIDO', 3, 'QR', 'SIP-EJEMPLO-0001'),
            new PagoEfectivo(5, 5.20, '2026-10-17 14:05:00', 'ANULADO', 4, 10.00, 4.80),
            new PagoTarjeta(6, 17.50, '2026-10-17 15:00:00', 'VALIDO', 5, 'DEBITO', 'MASTERCARD', '5678', 'B54321'),
            new PagoEfectivo(7, 26.50, '2026-10-18 09:30:00', 'VALIDO', 6, 30.00, 3.50),
            new PagoSip(8, 19.40, '2026-10-18 10:10:00', 'VALIDO', 7, 'TARJETA_SIP', 'SIP-EJEMPLO-0002'),
            new PagoTarjeta(9, 60.00, '2026-10-18 11:45:00', 'VALIDO', 8, 'CREDITO', 'VISA', '4321', 'C11111'),
            new PagoSip(10, 44.70, '2026-10-18 11:45:00', 'VALIDO', 8, 'QR', 'SIP-EJEMPLO-0003'),
            new PagoTarjeta(11, 40.50, '2026-10-18 13:20:00', 'VALIDO', 9, 'CREDITO', 'MASTERCARD', '8765', 'D22222'),
            new PagoEfectivo(12, 24.80, '2026-10-18 14:10:00', 'VALIDO', 10, 25.00, 0.20),
            new PagoEfectivo(13, 12.60, '2026-10-19 08:45:00', 'VALIDO', 11, 20.00, 7.40),
            new PagoSip(14, 37.50, '2026-10-19 09:20:00', 'VALIDO', 12, 'QR', 'SIP-EJEMPLO-0004'),
            new PagoEfectivo(15, 50.00, '2026-10-19 10:05:00', 'VALIDO', 13, 50.00, 0.00),
            new PagoTarjeta(16, 49.80, '2026-10-19 10:05:00', 'VALIDO', 13, 'DEBITO', 'VISA', '1111', 'E33333'),
            new PagoEfectivo(17, 17.25, '2026-10-19 11:30:00', 'VALIDO', 14, 20.00, 2.75),
        ];

        // INSERT INTO anulacion_venta ... (datos.sql): la anula la administradora de Cayma
        $this->anulaciones = [
            new AnulacionVenta(1, '2026-10-17 14:20:00', 'Error de producto: el cliente pidio otro articulo', 4, 1),
        ];
    }

    public function buscarSerie(int $idSerie): ?SerieComprobante
    {
        foreach ($this->series as $serie) {
            if ($serie->getIdSerie() === $idSerie) {
                return $serie;
            }
        }
        return null;
    }

    /** @return SerieComprobante[] */
    public function seriesDeTienda(int $idTienda): array
    {
        return array_values(array_filter(
            $this->series,
            fn(SerieComprobante $serie): bool => $serie->getIdTienda() === $idTienda
        ));
    }

    public function buscarTurno(int $idCajaTurno): ?CajaTurno
    {
        foreach ($this->turnos as $turno) {
            if ($turno->getIdCajaTurno() === $idCajaTurno) {
                return $turno;
            }
        }
        return null;
    }

    /**
     * Ventas de una tienda: la tienda sale de la serie del comprobante.
     * @return Venta[]
     */
    public function ventasDeTienda(int $idTienda): array
    {
        $idsSerie = array_map(
            fn(SerieComprobante $serie): int => $serie->getIdSerie(),
            $this->seriesDeTienda($idTienda)
        );
        return array_values(array_filter(
            $this->ventas,
            fn(Venta $venta): bool => in_array($venta->getIdSerie(), $idsSerie, true)
        ));
    }

    // Serie + correlativo identifican una sola venta (como sp_boleta). null si no existe.
    public function buscarPorComprobante(string $serie, int $numeroCorrelativo): ?Venta
    {
        foreach ($this->series as $s) {
            if ($s->getSerie() !== $serie) {
                continue;
            }
            foreach ($this->ventas as $venta) {
                if ($venta->getIdSerie() === $s->getIdSerie() && $venta->getNumeroCorrelativo() === $numeroCorrelativo) {
                    return $venta;
                }
            }
        }
        return null;
    }

    /** @return DetalleVenta[] */
    public function detallesDeVenta(int $idVenta): array
    {
        return array_values(array_filter(
            $this->detalles,
            fn(DetalleVenta $detalle): bool => $detalle->getIdVenta() === $idVenta
        ));
    }

    /** @return PagoVenta[] */
    public function pagosDeVenta(int $idVenta): array
    {
        return array_values(array_filter(
            $this->pagos,
            fn(PagoVenta $pago): bool => $pago->getIdVenta() === $idVenta
        ));
    }

    public function anulacionDeVenta(int $idVenta): ?AnulacionVenta
    {
        foreach ($this->anulaciones as $anulacion) {
            if ($anulacion->getIdVenta() === $idVenta) {
                return $anulacion;
            }
        }
        return null;
    }
}
