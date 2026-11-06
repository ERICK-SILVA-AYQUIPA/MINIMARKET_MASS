<?php
declare(strict_types=1);

/**
 * Reglas de los codigos de barras de producto (GTIN: EAN-8, UPC-A, EAN-13 y GTIN-14 / ITF-14), sin base de datos.
 *
 * El mismo producto puede llegar escrito de varias formas y antes solo se buscaba la forma exacta:
 * - Con ceros adelante o sin ellos: un QR GS1 trae 14 digitos (07751271011013), el empaque 13 (7751271011013);
 *   un UPC-A tiene 12 (012345678905) y el catalogo puede tenerlo con un 0 adelante (0012345678905).
 *   GS1 compara los GTIN completandolos con ceros a 14 digitos: esas formas son el mismo producto.
 * - Escrito a mano: con espacios o guiones ("775 1271 011013").
 * Las variantes() son todas esas formas; la que se leyo va primero, asi una coincidencia exacta gana.
 * El UPC-E (8 digitos, empaques chicos) lo expande la app a su UPC-A, porque la camara sabe que es un UPC-E:
 * aqui no se adivina, un EAN-8 tambien tiene 8 digitos y se confundiria con otro producto.
 */
final class CodigoBarras
{
    // Lo mismo que el CHECK ck_producto_codigo de la tabla producto
    private const PATRON = '/^[0-9]{8,14}$/';

    // Largos de un GTIN: EAN-8, UPC-A, EAN-13 y GTIN-14
    private const LARGOS = [8, 12, 13, 14];

    /** Quita espacios, guiones y puntos: "775 1271-011013" -> "7751271011013". Lo demas queda igual. */
    public static function limpiar(string $texto): string
    {
        return (string) preg_replace('/[\s.\-]+/u', '', trim($texto));
    }

    /** De 8 a 14 digitos, como lo guarda el catalogo. */
    public static function esValido(string $codigo): bool
    {
        return preg_match(self::PATRON, $codigo) === 1;
    }

    /** Digito de control GS1 (modulo 10, pesos 3 y 1 desde la derecha) del codigo completo. */
    public static function digitoControlValido(string $codigo): bool
    {
        if (!self::esValido($codigo)) {
            return false;
        }
        return self::digitoControl(substr($codigo, 0, -1)) === (int) substr($codigo, -1);
    }

    /** El digito de control que le toca a $cuerpo (el codigo sin su ultimo digito). */
    private static function digitoControl(string $cuerpo): int
    {
        $suma = 0;
        $pesos = 0;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += (int) $cuerpo[$i] * ($pesos++ % 2 === 0 ? 3 : 1);
        }
        return (10 - $suma % 10) % 10;
    }

    /**
     * Todas las formas en que el catalogo puede tener el producto de este codigo, la leida (limpia) primero.
     * Vacio si no es un codigo de producto (letras, menos de 8 o mas de 14 digitos).
     * @return string[]
     */
    public static function variantes(string $codigo): array
    {
        $codigo = self::limpiar($codigo);
        if (!self::esValido($codigo)) {
            return [];
        }
        $variantes = [$codigo];
        $nucleo = ltrim($codigo, '0');
        if ($nucleo !== '') {
            foreach (self::LARGOS as $largo) {
                if (strlen($nucleo) <= $largo) {
                    $variantes[] = str_pad($nucleo, $largo, '0', STR_PAD_LEFT);
                }
            }
        }
        return array_values(array_unique($variantes));
    }
}
