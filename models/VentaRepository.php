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
 * Ventas con su serie, turno, detalle, pagos y anulacion, leidas de MySQL.
 * Busca el comprobante por serie + correlativo, igual que sp_boleta (boleta.sql).
 */
class VentaRepository
{
    private const COLUMNAS_VENTA = 'v.id_venta, v.numero_correlativo, v.fecha_hora, v.total, v.estado,
        v.id_serie, v.id_caja_turno, v.id_cliente';

    private const COLUMNAS_SERIE = 'id_serie, serie, ultimo_numero, id_tienda, id_tipo_comprobante';

    public function __construct(private PDO $db) {}

    public function buscarSerie(int $idSerie): ?SerieComprobante
    {
        $consulta = $this->db->prepare('SELECT ' . self::COLUMNAS_SERIE . ' FROM serie_comprobante WHERE id_serie = :id');
        $consulta->execute(['id' => $idSerie]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $this->crearSerie($fila);
    }

    /** @return SerieComprobante[] */
    public function seriesDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS_SERIE . ' FROM serie_comprobante WHERE id_tienda = :tienda ORDER BY id_serie'
        );
        $consulta->execute(['tienda' => $idTienda]);
        return array_map(fn(array $fila): SerieComprobante => $this->crearSerie($fila), $consulta->fetchAll());
    }

    public function buscarTurno(int $idCajaTurno): ?CajaTurno
    {
        $consulta = $this->db->prepare(
            'SELECT id_caja_turno, fecha_hora_apertura, monto_inicial, fecha_hora_cierre, monto_cierre,
                    estado, id_caja, id_empleado
               FROM caja_turno
              WHERE id_caja_turno = :id'
        );
        $consulta->execute(['id' => $idCajaTurno]);
        $fila = $consulta->fetch();
        if ($fila === false) {
            return null;
        }
        return new CajaTurno(
            (int) $fila['id_caja_turno'],
            $fila['fecha_hora_apertura'],
            (float) $fila['monto_inicial'],
            $fila['fecha_hora_cierre'],
            $fila['monto_cierre'] === null ? null : (float) $fila['monto_cierre'],
            $fila['estado'],
            (int) $fila['id_caja'],
            (int) $fila['id_empleado']
        );
    }

    /**
     * Ventas de una tienda: la tienda sale de la serie del comprobante.
     * @return Venta[]
     */
    public function ventasDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS_VENTA . '
               FROM venta v
               JOIN serie_comprobante s ON s.id_serie = v.id_serie
              WHERE s.id_tienda = :tienda
              ORDER BY v.fecha_hora, v.id_venta'
        );
        $consulta->execute(['tienda' => $idTienda]);
        return array_map(fn(array $fila): Venta => $this->crearVenta($fila), $consulta->fetchAll());
    }

    /**
     * Una pagina de ventas de una tienda, de la mas reciente a la mas antigua.
     * @param array{desde?: ?string, hasta?: ?string, estado?: ?string} $filtro fechas AAAA-MM-DD ya validadas
     * @return Venta[]
     */
    public function buscarVentas(int $idTienda, array $filtro, int $limite, int $offset): array
    {
        [$donde, $parametros] = $this->donde($idTienda, $filtro);
        // LIMIT y OFFSET son enteros calculados en PHP: se pegan ya convertidos con (int)
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS_VENTA . '
               FROM venta v
               JOIN serie_comprobante s ON s.id_serie = v.id_serie
              WHERE ' . $donde . '
              ORDER BY v.fecha_hora DESC, v.id_venta DESC
              LIMIT ' . (int) $limite . ' OFFSET ' . (int) $offset
        );
        $consulta->execute($parametros);
        return array_map(fn(array $fila): Venta => $this->crearVenta($fila), $consulta->fetchAll());
    }

    /**
     * Cuantas ventas cumplen el filtro y cuanto suman las EMITIDAS.
     * @return array{cantidad: int, totalEmitido: float}
     */
    public function resumenVentas(int $idTienda, array $filtro): array
    {
        [$donde, $parametros] = $this->donde($idTienda, $filtro);
        $consulta = $this->db->prepare(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(CASE WHEN v.estado = 'EMITIDA' THEN v.total END), 0) AS total
               FROM venta v
               JOIN serie_comprobante s ON s.id_serie = v.id_serie
              WHERE " . $donde
        );
        $consulta->execute($parametros);
        $f = $consulta->fetch();
        return ['cantidad' => (int) $f['cantidad'], 'totalEmitido' => (float) $f['total']];
    }

    /** @return Venta[] compras de un cliente en las 3 tiendas, la mas reciente primero */
    public function ventasDeCliente(int $idCliente): array
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS_VENTA . ' FROM venta v WHERE v.id_cliente = :cliente ORDER BY v.fecha_hora DESC, v.id_venta DESC'
        );
        $consulta->execute(['cliente' => $idCliente]);
        return array_map(fn(array $fila): Venta => $this->crearVenta($fila), $consulta->fetchAll());
    }

    /** @return array{0: string, 1: array<string, int|string>} condicion WHERE y sus parametros */
    private function donde(int $idTienda, array $filtro): array
    {
        $condiciones = ['s.id_tienda = :tienda'];
        $parametros = ['tienda' => $idTienda];
        if (($filtro['desde'] ?? null) !== null) {
            $condiciones[] = 'v.fecha_hora >= :desde';
            $parametros['desde'] = $filtro['desde'] . ' 00:00:00';
        }
        if (($filtro['hasta'] ?? null) !== null) {
            $condiciones[] = 'v.fecha_hora < :hasta + INTERVAL 1 DAY';
            $parametros['hasta'] = $filtro['hasta'];
        }
        if (in_array($filtro['estado'] ?? null, ['EMITIDA', 'ANULADA'], true)) {
            $condiciones[] = 'v.estado = :estado';
            $parametros['estado'] = $filtro['estado'];
        }
        return [implode(' AND ', $condiciones), $parametros];
    }

    // Serie + correlativo identifican una sola venta. null si no existe.
    public function buscarPorComprobante(string $serie, int $numeroCorrelativo): ?Venta
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS_VENTA . '
               FROM venta v
               JOIN serie_comprobante s ON s.id_serie = v.id_serie
              WHERE s.serie = :serie AND v.numero_correlativo = :numero'
        );
        $consulta->execute(['serie' => $serie, 'numero' => $numeroCorrelativo]);
        $fila = $consulta->fetch();
        return $fila === false ? null : $this->crearVenta($fila);
    }

    /** @return DetalleVenta[] */
    public function detallesDeVenta(int $idVenta): array
    {
        $consulta = $this->db->prepare(
            'SELECT id_detalle_venta, cantidad, precio_unitario, importe, id_venta, id_producto
               FROM detalle_venta
              WHERE id_venta = :venta
              ORDER BY id_detalle_venta'
        );
        $consulta->execute(['venta' => $idVenta]);
        return array_map(fn(array $f): DetalleVenta => new DetalleVenta(
            (int) $f['id_detalle_venta'],
            (float) $f['cantidad'],
            (float) $f['precio_unitario'],
            (float) $f['importe'],
            (int) $f['id_venta'],
            (int) $f['id_producto']
        ), $consulta->fetchAll());
    }

    /**
     * Cada pago con los datos de su subtipo (efectivo, tarjeta o Sip), como el bloque 3 de sp_boleta.
     * @return PagoVenta[]
     */
    public function pagosDeVenta(int $idVenta): array
    {
        $consulta = $this->db->prepare(
            'SELECT pv.id_pago, pv.monto_pagado, pv.fecha_hora, pv.estado, pv.id_venta, pv.id_metodo_pago,
                    ef.monto_recibido, ef.vuelto,
                    ta.tipo_tarjeta, ta.marca_tarjeta, ta.ultimos_4_digitos, ta.codigo_autorizacion,
                    bs.modalidad_pago, bs.codigo_operacion
               FROM pago_venta pv
               LEFT JOIN efectivo ef              ON ef.id_pago = pv.id_pago
               LEFT JOIN tarjeta ta               ON ta.id_pago = pv.id_pago
               LEFT JOIN billetera_digital_sip bs ON bs.id_pago = pv.id_pago
              WHERE pv.id_venta = :venta
              ORDER BY pv.id_pago'
        );
        $consulta->execute(['venta' => $idVenta]);
        return array_map(fn(array $fila): PagoVenta => $this->crearPago($fila), $consulta->fetchAll());
    }

    public function anulacionDeVenta(int $idVenta): ?AnulacionVenta
    {
        $consulta = $this->db->prepare(
            'SELECT id_anulacion, fecha_hora, motivo, id_venta, id_empleado FROM anulacion_venta WHERE id_venta = :venta'
        );
        $consulta->execute(['venta' => $idVenta]);
        $f = $consulta->fetch();
        if ($f === false) {
            return null;
        }
        return new AnulacionVenta((int) $f['id_anulacion'], $f['fecha_hora'], $f['motivo'], (int) $f['id_venta'], (int) $f['id_empleado']);
    }

    private function crearSerie(array $f): SerieComprobante
    {
        return new SerieComprobante(
            (int) $f['id_serie'],
            $f['serie'],
            (int) $f['ultimo_numero'],
            (int) $f['id_tienda'],
            (int) $f['id_tipo_comprobante']
        );
    }

    private function crearVenta(array $f): Venta
    {
        return new Venta(
            (int) $f['id_venta'],
            (int) $f['numero_correlativo'],
            $f['fecha_hora'],
            (float) $f['total'],
            $f['estado'],
            (int) $f['id_serie'],
            (int) $f['id_caja_turno'],
            $f['id_cliente'] === null ? null : (int) $f['id_cliente']
        );
    }

    // El subtipo se elige por id_metodo_pago: 1 EFECTIVO, 2 TARJETA, 3 BILLETERA DIGITAL SIP
    private function crearPago(array $f): PagoVenta
    {
        $base = [(int) $f['id_pago'], (float) $f['monto_pagado'], $f['fecha_hora'], $f['estado'], (int) $f['id_venta']];

        return match ((int) $f['id_metodo_pago']) {
            1 => new PagoEfectivo(...[...$base, (float) $f['monto_recibido'], (float) $f['vuelto']]),
            2 => new PagoTarjeta(...[...$base, $f['tipo_tarjeta'], $f['marca_tarjeta'], $f['ultimos_4_digitos'], $f['codigo_autorizacion']]),
            3 => new PagoSip(...[...$base, $f['modalidad_pago'], $f['codigo_operacion']]),
            default => throw new UnexpectedValueException('Metodo de pago desconocido: ' . $f['id_metodo_pago']),
        };
    }
}
