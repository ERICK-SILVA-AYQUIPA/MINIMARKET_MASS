<?php
declare(strict_types=1);

/**
 * Una celda de los CSV que se descargan (kardex, stock, ingresos, reportes, catalogo).
 * Excel abre como formula un texto que empieza con = + - @ (o tabulador / retorno): a ese texto se le antepone
 * un apostrofo para que se vea tal cual y no se ejecute.
 * Un numero se deja como numero, aunque sea negativo (-5.000 en las salidas del kardex): asi Excel lo puede sumar.
 * Es una clase sin base de datos: se prueba sola en tests/unitarios.php.
 */
final class CeldaCsv
{
    // Solo digitos con signo y punto decimal opcionales: -5, 12.50, -0.250. Nada de 1e3, espacios ni otros signos
    private const NUMERO = '/^-?[0-9]+(\.[0-9]+)?$/D';

    public static function segura(mixed $valor): string
    {
        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }
        $texto = (string) $valor;
        if (preg_match(self::NUMERO, $texto) === 1) {
            return $texto;
        }
        return preg_match('/^[=+\-@\t\r]/', $texto) === 1 ? "'" . $texto : $texto;
    }
}
