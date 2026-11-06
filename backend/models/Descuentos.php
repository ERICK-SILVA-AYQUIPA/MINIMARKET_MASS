<?php
declare(strict_types=1);

require_once __DIR__ . '/Promocion.php';
require_once __DIR__ . '/ReglaNegocioException.php';

/**
 * Calcula los descuentos de una venta, sin tocar la base (lo usan VentaServicio, la vista previa del carrito y la API).
 *
 * 1. Cada linea toma la promocion de su producto que mas le conviene al cliente (no se acumulan dos en una linea).
 * 2. A toda la venta se le aplica UN descuento general: la promocion VENTA que mas descuente o el descuento
 *    manual del cajero (con motivo y hasta su tope), el que sea mayor. Tampoco se acumulan.
 * 3. El descuento general se reparte entre las lineas en proporcion a lo que cuesta cada una (centimo por centimo,
 *    sin perder ni sumar nada). Asi una devolucion parcial devuelve lo que el cliente pago de verdad.
 *
 * Todo en centimos. El total siempre queda mayor que 0: ninguna promocion pasa del 90 %.
 */
final class Descuentos
{
    /**
     * @param array<int, array{milesimas: int, precio: int, al_peso?: bool, nombre?: string}> $lineas  id_producto => linea
     * @param Promocion[] $promociones  las que rigen hoy en la tienda (de producto y de venta)
     * @param ?array{unidad: string, valor: int, motivo: string} $manual  unidad PORCENTAJE (centesimas) o MONTO (centimos)
     * @param int $topeManual  centesimas: el maximo que este rol puede descontar a mano (0 = no puede)
     * @return array{
     *     lineas: array<int, array{importe: int, descuento: int, id_promocion: ?int, promocion: ?string, general: int, neto: int}>,
     *     subtotal: int, descuentoProductos: int,
     *     general: ?array{origen: string, id_promocion: ?int, nombre: string, porcentaje: ?int, monto: int, motivo: ?string},
     *     manualIgnorado: bool, descuentoTotal: int, total: int
     * }
     */
    public static function calcular(array $lineas, array $promociones, ?array $manual = null, int $topeManual = 0): array
    {
        $porProducto = [];
        $deVenta = [];
        foreach ($promociones as $promo) {
            if ($promo->esDeVenta()) {
                $deVenta[] = $promo;
            } else {
                $porProducto[(int) $promo->getIdProducto()][] = $promo;
            }
        }

        // 1. Promocion de cada linea
        $resultado = [];
        $subtotal = 0;
        $descuentoProductos = 0;
        foreach ($lineas as $idProducto => $l) {
            $importe = Cantidad::importe($l['milesimas'], $l['precio']);
            $mejor = null;
            $descuento = 0;
            foreach ($porProducto[$idProducto] ?? [] as $promo) {
                $d = $promo->descuentoLinea($l['milesimas'], $l['precio'], (bool) ($l['al_peso'] ?? false));
                // Empate: la mas antigua (id menor), para que el resultado no dependa del orden de la consulta
                if ($d > $descuento || ($d === $descuento && $d > 0 && $promo->getIdPromocion() < $mejor?->getIdPromocion())) {
                    $mejor = $promo;
                    $descuento = $d;
                }
            }
            $resultado[$idProducto] = [
                'importe'      => $importe,
                'descuento'    => $descuento,
                'id_promocion' => $descuento > 0 ? $mejor?->getIdPromocion() : null,
                'promocion'    => $descuento > 0 ? $mejor?->getNombre() : null,
                'general'      => 0,
                'neto'         => $importe - $descuento,
            ];
            $subtotal += $importe;
            $descuentoProductos += $descuento;
        }
        $base = $subtotal - $descuentoProductos;

        // 2. Descuento general: la mejor promocion VENTA o el manual, el mayor
        $general = null;
        foreach ($deVenta as $promo) {
            $monto = $promo->descuentoVenta($base);
            if ($monto > 0 && ($general === null || $monto > $general['monto'])) {
                $general = ['origen' => 'PROMOCION', 'id_promocion' => $promo->getIdPromocion(), 'nombre' => $promo->getNombre(),
                    'porcentaje' => $promo->getPorcentaje(), 'monto' => $monto, 'motivo' => null];
            }
        }
        $manualIgnorado = false;
        if ($manual !== null) {
            $montoManual = self::montoManual($manual, $base, $topeManual);
            if ($general === null || $montoManual > $general['monto']) {
                $general = ['origen' => 'MANUAL', 'id_promocion' => null, 'nombre' => 'Descuento del cajero',
                    'porcentaje' => $manual['unidad'] === 'PORCENTAJE' ? $manual['valor'] : null, 'monto' => $montoManual,
                    'motivo' => $manual['motivo']];
            } else {
                $manualIgnorado = true;
            }
        }
        if ($general !== null && $general['monto'] >= $base) {
            throw new ReglaNegocioException('El descuento no puede dejar la venta en S/ 0.00.');
        }

        // 3. Reparto del descuento general entre las lineas
        if ($general !== null) {
            foreach (self::repartir($general['monto'], array_map(fn(array $r): int => $r['neto'], $resultado)) as $id => $parte) {
                $resultado[$id]['general'] = $parte;
                $resultado[$id]['neto'] -= $parte;
            }
        }

        $descuentoTotal = $descuentoProductos + ($general['monto'] ?? 0);
        return [
            'lineas'             => $resultado,
            'subtotal'           => $subtotal,
            'descuentoProductos' => $descuentoProductos,
            'general'            => $general,
            'manualIgnorado'     => $manualIgnorado,
            'descuentoTotal'     => $descuentoTotal,
            'total'              => $subtotal - $descuentoTotal,
        ];
    }

    /**
     * Valida el descuento manual y lo pasa a centimos.
     * @param array{unidad: string, valor: int, motivo: string} $manual
     */
    private static function montoManual(array $manual, int $base, int $topeManual): int
    {
        if ($topeManual <= 0) {
            throw new ReglaNegocioException('Tu rol no puede dar descuentos a mano.');
        }
        if (trim($manual['motivo']) === '') {
            throw new ReglaNegocioException('Escribe el motivo del descuento.');
        }
        if ($manual['valor'] <= 0) {
            throw new ReglaNegocioException('El descuento debe ser mayor que 0.');
        }
        $monto = $manual['unidad'] === 'PORCENTAJE' ? Promocion::porcentajeDe($base, $manual['valor']) : $manual['valor'];
        $tope = Promocion::porcentajeDe($base, $topeManual);
        if (($manual['unidad'] === 'PORCENTAJE' && $manual['valor'] > $topeManual) || $monto > $tope) {
            throw new ReglaNegocioException(sprintf('Puedes descontar hasta el %s de la compra (S/ %s). Para más, pide al administrador una promoción.',
                Promocion::porcentajeATexto($topeManual), Dinero::aTexto($tope)));
        }
        if ($monto <= 0) {
            throw new ReglaNegocioException('El descuento es tan pequeño que no llega a un céntimo.');
        }
        return $monto;
    }

    /**
     * Reparte $monto en proporcion a $pesos (metodo del mayor resto): la suma da exactamente $monto
     * y ninguna parte pasa de su peso (porque $monto < suma de pesos).
     * @param array<int, int> $pesos
     * @return array<int, int>
     */
    public static function repartir(int $monto, array $pesos): array
    {
        $suma = array_sum($pesos);
        if ($suma <= 0 || $monto <= 0) {
            return array_map(fn(): int => 0, $pesos);
        }
        $partes = [];
        $restos = [];
        foreach ($pesos as $id => $peso) {
            $partes[$id] = intdiv($monto * $peso, $suma);
            $restos[$id] = ($monto * $peso) % $suma;
        }
        $falta = $monto - array_sum($partes);
        // Los centimos que faltan van a los mayores restos (empate: el id menor)
        uksort($restos, fn(int $a, int $b): int => [$restos[$b], $a] <=> [$restos[$a], $b]);
        foreach (array_keys($restos) as $id) {
            if ($falta <= 0) {
                break;
            }
            if ($partes[$id] < $pesos[$id]) {
                $partes[$id]++;
                $falta--;
            }
        }
        return $partes;
    }
}
