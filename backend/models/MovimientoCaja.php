<?php
declare(strict_types=1);

/**
 * Reglas de los movimientos de efectivo de un turno (tabla movimiento_caja, migracion 014).
 * Un movimiento es dinero que entra o sale de la gaveta y NO es una venta: por eso lleva una categoria.
 * Las devoluciones a un cliente no estan aqui: van por devolucion_venta y ya se restan del esperado.
 */
final class MovimientoCaja
{
    /** Categorias que acepta cada tipo (CHECK ck_movimiento_caja_categoria) y su nombre para la pantalla. */
    public const CATEGORIAS = [
        'RETIRO' => [
            'DEPOSITO'  => 'Retiro de seguridad',
            'PROVEEDOR' => 'Pago a proveedor',
            'GASTO'     => 'Gasto menor de la tienda',
            'OTRO'      => 'Otro retiro',
        ],
        'INGRESO' => [
            'SENCILLO' => 'Sencillo o cambio',
            'APORTE'   => 'Aporte de la tienda',
            'OTRO'     => 'Otro ingreso',
        ],
    ];

    // Lo que se deja en la gaveta al hacer un retiro de seguridad se redondea a billetes de S/ 10
    private const MULTIPLO_RETIRO = 1000;

    public static function categoriaValida(string $tipo, string $categoria): bool
    {
        return isset(self::CATEGORIAS[$tipo][$categoria]);
    }

    public static function nombreCategoria(string $tipo, string $categoria): string
    {
        return self::CATEGORIAS[$tipo][$categoria] ?? ($tipo === 'RETIRO' ? 'Retiro' : 'Ingreso');
    }

    /**
     * Retiro de seguridad sugerido, en centimos: cuando la gaveta pasa el tope de la tienda, se saca todo
     * lo que pasa del fondo fijo de la caja, en multiplos de S/ 10 (asi queda el sencillo para dar vuelto).
     * 0 = no hace falta retirar. Nunca sugiere mas de lo que hay.
     */
    public static function retiroSugerido(int $esperado, int $tope, int $fondo): int
    {
        if ($esperado <= $tope || $esperado <= $fondo) {
            return 0;
        }
        return intdiv($esperado - max(0, $fondo), self::MULTIPLO_RETIRO) * self::MULTIPLO_RETIRO;
    }

    /**
     * N.° de la boleta, factura o recibo que respalda el movimiento: opcional, letras, numeros, guion y barra.
     * null = no se escribio. Lanza ReglaNegocioException si no es valido.
     */
    public static function comprobante(?string $texto): ?string
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return null;
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9\/-]{0,19}$/', $texto) !== 1) {
            throw new ReglaNegocioException('El N.° de comprobante va con letras, números, guion o barra (hasta 20).');
        }
        return strtoupper($texto);
    }
}
