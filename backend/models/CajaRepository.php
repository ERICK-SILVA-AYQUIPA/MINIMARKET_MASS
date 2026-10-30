<?php
declare(strict_types=1);

require_once __DIR__ . '/CajaTurno.php';

/**
 * Turnos de caja y su arqueo (consulta 19 de consultas.sql).
 * Las escrituras (abrir y cerrar turno, retiros e ingresos) estan en servicios/CajaServicio.php.
 */
class CajaRepository
{
    private const COLUMNAS = 'ct.id_caja_turno, ct.fecha_hora_apertura, ct.monto_inicial, ct.fecha_hora_cierre,
        ct.monto_cierre, ct.estado, ct.id_caja, ct.id_empleado';

    public function __construct(private PDO $db) {}

    // El turno ABIERTO de un cajero, o null si no tiene ninguno
    public function turnoAbiertoDe(int $idEmpleado): ?CajaTurno
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM caja_turno ct
              WHERE ct.id_empleado = :empleado AND ct.estado = \'ABIERTO\'
              ORDER BY ct.id_caja_turno DESC LIMIT 1'
        );
        $consulta->execute(['empleado' => $idEmpleado]);
        $f = $consulta->fetch();
        return $f === false ? null : $this->crear($f);
    }

    /**
     * Turnos de una tienda con su arqueo:
     * esperado = monto inicial + efectivo valido cobrado - devoluciones + ingresos de sencillo - retiros.
     * @return array<int, array{turno: CajaTurno, efectivoCobrado: float, devoluciones: float, ingresos: float, retiros: float, esperado: float, diferencia: ?float}>
     */
    public function arqueoDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ',
                    COALESCE((SELECT SUM(pv.monto_pagado)
                                FROM venta v
                                JOIN pago_venta pv ON pv.id_venta = v.id_venta
                               WHERE v.id_caja_turno = ct.id_caja_turno
                                 AND pv.estado = \'VALIDO\' AND pv.id_metodo_pago = 1), 0) AS efectivo_cobrado,
                    COALESCE((SELECT SUM(d.total) FROM devolucion_venta d WHERE d.id_caja_turno = ct.id_caja_turno), 0) AS devoluciones,
                    COALESCE((SELECT SUM(m.monto) FROM movimiento_caja m
                               WHERE m.id_caja_turno = ct.id_caja_turno AND m.tipo = \'INGRESO\'), 0) AS ingresos,
                    COALESCE((SELECT SUM(m.monto) FROM movimiento_caja m
                               WHERE m.id_caja_turno = ct.id_caja_turno AND m.tipo = \'RETIRO\'), 0) AS retiros
               FROM caja_turno ct
               JOIN caja c ON c.id_caja = ct.id_caja
              WHERE c.id_tienda = :tienda
              ORDER BY ct.id_caja_turno DESC'
        );
        $consulta->execute(['tienda' => $idTienda]);

        $filas = [];
        foreach ($consulta->fetchAll() as $f) {
            $turno = $this->crear($f);
            $cobrado = (float) $f['efectivo_cobrado'];
            $devoluciones = (float) $f['devoluciones'];
            $ingresos = (float) $f['ingresos'];
            $retiros = (float) $f['retiros'];
            $esperado = round($turno->getMontoInicial() + $cobrado - $devoluciones + $ingresos - $retiros, 2);
            $filas[] = [
                'turno'           => $turno,
                'efectivoCobrado' => $cobrado,
                'devoluciones'    => $devoluciones,
                'ingresos'        => $ingresos,
                'retiros'         => $retiros,
                'esperado'        => $esperado,
                'diferencia'      => $turno->getMontoCierre() === null ? null : round($turno->getMontoCierre() - $esperado, 2),
            ];
        }
        return $filas;
    }

    /**
     * Retiros e ingresos de efectivo de un turno, del primero al ultimo.
     * @return array<int, array{fecha_hora: string, tipo: string, monto: float, motivo: string}>
     */
    public function movimientosDeTurno(int $idTurno): array
    {
        $consulta = $this->db->prepare(
            'SELECT fecha_hora, tipo, monto, motivo FROM movimiento_caja WHERE id_caja_turno = :turno ORDER BY id_movimiento_caja'
        );
        $consulta->execute(['turno' => $idTurno]);
        return array_map(fn(array $f): array => [
            'fecha_hora' => $f['fecha_hora'], 'tipo' => $f['tipo'], 'monto' => (float) $f['monto'], 'motivo' => $f['motivo'],
        ], $consulta->fetchAll());
    }

    private function crear(array $f): CajaTurno
    {
        return new CajaTurno(
            (int) $f['id_caja_turno'],
            $f['fecha_hora_apertura'],
            (float) $f['monto_inicial'],
            $f['fecha_hora_cierre'],
            $f['monto_cierre'] === null ? null : (float) $f['monto_cierre'],
            $f['estado'],
            (int) $f['id_caja'],
            (int) $f['id_empleado']
        );
    }
}
