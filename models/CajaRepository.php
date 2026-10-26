<?php
declare(strict_types=1);

require_once __DIR__ . '/CajaTurno.php';

/**
 * Turnos de caja y su arqueo (consulta 19 de consultas.sql).
 * Las escrituras (abrir y cerrar turno) estan en servicios/CajaServicio.php.
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
     * Turnos de una tienda con su arqueo: esperado = monto inicial + efectivo valido cobrado.
     * @return array<int, array{turno: CajaTurno, efectivoCobrado: float, esperado: float, diferencia: ?float}>
     */
    public function arqueoDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ',
                    COALESCE((SELECT SUM(pv.monto_pagado)
                                FROM venta v
                                JOIN pago_venta pv ON pv.id_venta = v.id_venta
                               WHERE v.id_caja_turno = ct.id_caja_turno
                                 AND pv.estado = \'VALIDO\' AND pv.id_metodo_pago = 1), 0) AS efectivo_cobrado
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
            $esperado = round($turno->getMontoInicial() + $cobrado, 2);
            $filas[] = [
                'turno'           => $turno,
                'efectivoCobrado' => $cobrado,
                'esperado'        => $esperado,
                'diferencia'      => $turno->getMontoCierre() === null ? null : round($turno->getMontoCierre() - $esperado, 2),
            ];
        }
        return $filas;
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
