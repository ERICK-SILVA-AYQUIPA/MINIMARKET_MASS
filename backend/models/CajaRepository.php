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
        ct.monto_cierre, ct.estado, ct.id_caja, ct.id_empleado, ct.monto_esperado, ct.diferencia, ct.en_pausa_desde,
        ct.observacion, ct.id_empleado_revisa, ct.fecha_hora_revision';

    // Billetes y monedas de soles que se cuentan en la gaveta (CHECK ck_arqueo_denominacion)
    public const DENOMINACIONES = ['200.00', '100.00', '50.00', '20.00', '10.00', '5.00', '2.00', '1.00', '0.50', '0.20', '0.10'];

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

    // El turno del cajero que espera revision (OBSERVADO) o un nuevo conteo (RECONTEO), o null
    public function turnoPendienteDe(int $idEmpleado): ?CajaTurno
    {
        $consulta = $this->db->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM caja_turno ct
              WHERE ct.id_empleado = :empleado AND ct.estado IN (\'OBSERVADO\', \'RECONTEO\')
              ORDER BY ct.id_caja_turno DESC LIMIT 1'
        );
        $consulta->execute(['empleado' => $idEmpleado]);
        $f = $consulta->fetch();
        return $f === false ? null : $this->crear($f);
    }

    /**
     * Reglas de caja de una tienda (regla_caja). Si la tienda aun no tiene fila, valen las de la tabla por defecto.
     * @return array{tolerancia: float, tope: float, ciego: bool, horas: int}
     */
    public function reglaDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare('SELECT tolerancia, tope_gaveta, cierre_ciego, horas_maximas FROM regla_caja WHERE id_tienda = :tienda');
        $consulta->execute(['tienda' => $idTienda]);
        $f = $consulta->fetch();
        return $f === false
            ? ['tolerancia' => 1.0, 'tope' => 500.0, 'ciego' => true, 'horas' => 10]
            : ['tolerancia' => (float) $f['tolerancia'], 'tope' => (float) $f['tope_gaveta'], 'ciego' => $f['cierre_ciego'] === 'S', 'horas' => (int) $f['horas_maximas']];
    }

    /**
     * El fondo fijo de cada caja de la tienda (el sencillo que se entrega en sobre).
     * @return array<int, float> id_caja => fondo
     */
    public function fondosDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare('SELECT id_caja, fondo_fijo FROM caja WHERE id_tienda = :tienda ORDER BY numero_caja');
        $consulta->execute(['tienda' => $idTienda]);
        $fondos = [];
        foreach ($consulta->fetchAll() as $f) {
            $fondos[(int) $f['id_caja']] = (float) $f['fondo_fijo'];
        }
        return $fondos;
    }

    /**
     * Los conteos de la gaveta (apertura, sorpresa y cierre) de los turnos de una tienda, con su desglose.
     * @return array<int, array<int, array{fecha_hora: string, tipo: string, contado: float, esperado: float, observacion: ?string, id_empleado: int, detalle: array<string, int>}>> por turno
     */
    public function arqueosDeTienda(int $idTienda): array
    {
        $consulta = $this->db->prepare(
            'SELECT a.id_arqueo, a.fecha_hora, a.tipo, a.monto_contado, a.monto_esperado, a.observacion, a.id_caja_turno, a.id_empleado
               FROM arqueo_caja a
               JOIN caja_turno ct ON ct.id_caja_turno = a.id_caja_turno
               JOIN caja c ON c.id_caja = ct.id_caja
              WHERE c.id_tienda = :tienda
              ORDER BY a.id_arqueo'
        );
        $consulta->execute(['tienda' => $idTienda]);
        $filas = $consulta->fetchAll();
        $detalle = [];
        if ($filas !== []) {
            $d = $this->db->prepare(
                'SELECT ad.id_arqueo, ad.denominacion, ad.cantidad
                   FROM arqueo_detalle ad
                   JOIN arqueo_caja a ON a.id_arqueo = ad.id_arqueo
                   JOIN caja_turno ct ON ct.id_caja_turno = a.id_caja_turno
                   JOIN caja c ON c.id_caja = ct.id_caja
                  WHERE c.id_tienda = :tienda
                  ORDER BY ad.denominacion DESC'
            );
            $d->execute(['tienda' => $idTienda]);
            foreach ($d->fetchAll() as $f) {
                $detalle[(int) $f['id_arqueo']][$f['denominacion']] = (int) $f['cantidad'];
            }
        }
        $porTurno = [];
        foreach ($filas as $f) {
            $porTurno[(int) $f['id_caja_turno']][] = [
                'fecha_hora' => $f['fecha_hora'], 'tipo' => $f['tipo'], 'contado' => (float) $f['monto_contado'],
                'esperado' => (float) $f['monto_esperado'], 'observacion' => $f['observacion'], 'id_empleado' => (int) $f['id_empleado'],
                'detalle' => $detalle[(int) $f['id_arqueo']] ?? [],
            ];
        }
        return $porTurno;
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
            // Al cerrar queda guardado lo esperado; si el turno sigue abierto se calcula al momento
            $esperado = $turno->getMontoEsperado() ?? round($turno->getMontoInicial() + $cobrado - $devoluciones + $ingresos - $retiros, 2);
            $filas[] = [
                'turno'           => $turno,
                'efectivoCobrado' => $cobrado,
                'devoluciones'    => $devoluciones,
                'ingresos'        => $ingresos,
                'retiros'         => $retiros,
                'esperado'        => $esperado,
                'diferencia'      => $turno->getDiferencia() ?? ($turno->getMontoCierre() === null ? null : round($turno->getMontoCierre() - $esperado, 2)),
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
            (int) $f['id_empleado'],
            $f['monto_esperado'] === null ? null : (float) $f['monto_esperado'],
            $f['diferencia'] === null ? null : (float) $f['diferencia'],
            $f['en_pausa_desde'],
            $f['observacion'],
            $f['id_empleado_revisa'] === null ? null : (int) $f['id_empleado_revisa'],
            $f['fecha_hora_revision']
        );
    }
}
