<?php
declare(strict_types=1);

/**
 * Codigo QR en SVG para las etiquetas de producto, sin librerias ni internet (la PC de la tienda puede no tenerlo).
 *
 * El QR lleva el codigo de barras del producto tal cual (7750000000011): la app lo lee como un numero de producto
 * y lo busca igual que un codigo de barras, asi que funciona con cualquier version de la app. Sirve para los
 * productos cuyo empaque trae otro codigo, ninguno o uno que la camara no lee bien.
 *
 * Es un QR normal (ISO/IEC 18004): modo byte, correccion M (aguanta ~15 % de la etiqueta rayada o sucia),
 * versiones 1 a 4 (hasta 62 caracteres; un codigo de producto ocupa 8 a 14).
 */
final class CodigoQr
{
    // Por version (1 a 4) con correccion M: [palabras de datos por bloque, bloques, palabras de correccion por bloque]
    private const BLOQUES = [1 => [16, 1, 10], 2 => [28, 1, 16], 3 => [44, 1, 26], 4 => [32, 2, 18]];

    /** @var array<int, array<int, bool>> fila => columna => oscuro */
    private array $modulos = [];
    /** @var array<int, array<int, bool>> modulos fijos (patrones, formato): la mascara no los toca */
    private array $fijos = [];
    private int $lado;

    private function __construct(private int $version)
    {
        $this->lado = 17 + 4 * $version;
    }

    /**
     * El QR de $texto como SVG listo para pegar en la pagina (con su margen blanco de 4 modulos).
     * @throws InvalidArgumentException si el texto esta vacio o pasa de 62 bytes
     */
    public static function svg(string $texto, string $titulo = ''): string
    {
        $matriz = self::matriz($texto);
        $lado = count($matriz);
        $total = $lado + 8;
        $trazo = '';
        foreach ($matriz as $y => $fila) {
            foreach ($fila as $x => $oscuro) {
                if ($oscuro) {
                    $trazo .= 'M' . ($x + 4) . ' ' . ($y + 4) . 'h1v1h-1z';
                }
            }
        }
        $etiqueta = $titulo !== '' ? '<title>' . htmlspecialchars($titulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>' : '';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges" role="img">'
            . $etiqueta . '<rect width="' . $total . '" height="' . $total . '" fill="#fff"/>'
            . '<path fill="#000" d="' . $trazo . '"/></svg>';
    }

    /**
     * Los modulos del QR (sin margen): true es negro.
     * @return array<int, array<int, bool>>
     */
    public static function matriz(string $texto): array
    {
        $largo = strlen($texto);
        if ($largo === 0) {
            throw new InvalidArgumentException('El QR necesita un texto.');
        }
        foreach (self::BLOQUES as $version => [$datos, $bloques]) {
            // 4 bits de modo + 8 del largo + 8 por byte
            if (12 + 8 * $largo <= 8 * $datos * $bloques) {
                $qr = new self($version);
                $qr->dibujar(self::palabras($texto, $version));
                return $qr->modulos;
            }
        }
        throw new InvalidArgumentException('El texto es muy largo para la etiqueta.');
    }

    /**
     * Los bytes que van en el QR: datos con relleno, partidos en bloques, cada uno con su correccion Reed-Solomon,
     * e intercalados como pide la norma.
     * @return int[]
     */
    private static function palabras(string $texto, int $version): array
    {
        [$porBloque, $bloques, $correccion] = self::BLOQUES[$version];
        $capacidad = $porBloque * $bloques;
        $bits = '0100' . sprintf('%08b', strlen($texto));
        foreach (str_split($texto) as $c) {
            $bits .= sprintf('%08b', ord($c));
        }
        $bits .= str_repeat('0', min(4, 8 * $capacidad - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $datos = array_map('bindec', str_split($bits, 8));
        for ($relleno = 0; count($datos) < $capacidad; $relleno++) {
            $datos[] = $relleno % 2 === 0 ? 0xEC : 0x11;
        }

        $partes = array_chunk($datos, $porBloque);
        $correcciones = array_map(static fn(array $b): array => self::reedSolomon($b, $correccion), $partes);
        $salida = [];
        for ($i = 0; $i < $porBloque; $i++) {
            foreach ($partes as $b) {
                $salida[] = $b[$i];
            }
        }
        for ($i = 0; $i < $correccion; $i++) {
            foreach ($correcciones as $b) {
                $salida[] = $b[$i];
            }
        }
        return $salida;
    }

    /**
     * Palabras de correccion de errores (Reed-Solomon sobre GF(256), polinomio 0x11D).
     * @param int[] $datos
     * @return int[]
     */
    private static function reedSolomon(array $datos, int $cantidad): array
    {
        // Generador: producto de (x - a^i) para i = 0 .. cantidad-1
        $generador = [1];
        $raiz = 1;
        for ($i = 0; $i < $cantidad; $i++) {
            $nuevo = array_fill(0, count($generador) + 1, 0);
            foreach ($generador as $j => $g) {
                $nuevo[$j] ^= $g;
                $nuevo[$j + 1] ^= self::multiplicar($g, $raiz);
            }
            $generador = $nuevo;
            $raiz = self::multiplicar($raiz, 2);
        }
        $resto = array_fill(0, $cantidad, 0);
        foreach ($datos as $d) {
            $factor = $d ^ $resto[0];
            array_shift($resto);
            $resto[] = 0;
            for ($j = 0; $j < $cantidad; $j++) {
                $resto[$j] ^= self::multiplicar($generador[$j + 1], $factor);
            }
        }
        return $resto;
    }

    private static function multiplicar(int $a, int $b): int
    {
        $r = 0;
        for ($i = 7; $i >= 0; $i--) {
            $r = ($r << 1) ^ (($r >> 7) * 0x11D);
            $r ^= (($b >> $i) & 1) * $a;
        }
        return $r & 0xFF;
    }

    /** @param int[] $palabras */
    private function dibujar(array $palabras): void
    {
        $n = $this->lado;
        $this->modulos = array_fill(0, $n, array_fill(0, $n, false));
        $this->fijos = $this->modulos;

        // Lineas de tiempo, los tres cuadros de las esquinas y (desde la version 2) el cuadro de alineacion
        for ($i = 0; $i < $n; $i++) {
            $this->fijar($i, 6, $i % 2 === 0);
            $this->fijar(6, $i, $i % 2 === 0);
        }
        foreach ([[3, 3], [$n - 4, 3], [3, $n - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $n && $y >= 0 && $y < $n) {
                        $d = max(abs($dx), abs($dy));
                        $this->fijar($x, $y, $d !== 2 && $d !== 4);
                    }
                }
            }
        }
        if ($this->version > 1) {
            $c = $n - 7;
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $this->fijar($c + $dx, $c + $dy, max(abs($dx), abs($dy)) !== 1);
                }
            }
        }
        // Se reserva el lugar del formato (se escribe al final, con la mascara elegida)
        $this->formato(0);

        // Datos en zigzag de a dos columnas, de abajo a la derecha hacia arriba
        $total = count($palabras) * 8;
        $i = 0;
        for ($derecha = $n - 1; $derecha >= 1; $derecha -= 2) {
            if ($derecha === 6) {
                $derecha = 5;
            }
            for ($v = 0; $v < $n; $v++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $derecha - $j;
                    $y = (($derecha + 1) & 2) === 0 ? $n - 1 - $v : $v;
                    if (!$this->fijos[$y][$x] && $i < $total) {
                        $this->modulos[$y][$x] = (($palabras[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }

        // La mascara que deja el QR mas facil de leer (menos penalidad), como pide la norma
        $mejor = 0;
        $menor = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $this->enmascarar($m);
            $this->formato($m);
            $p = $this->penalidad();
            if ($p < $menor) {
                $menor = $p;
                $mejor = $m;
            }
            $this->enmascarar($m);
        }
        $this->enmascarar($mejor);
        $this->formato($mejor);
    }

    private function fijar(int $x, int $y, bool $oscuro): void
    {
        $this->modulos[$y][$x] = $oscuro;
        $this->fijos[$y][$x] = true;
    }

    /** Nivel de correccion M (bits 00) y la mascara, con su BCH(15,5), en sus dos copias. */
    private function formato(int $mascara): void
    {
        $dato = $mascara; // (0b00 << 3) | mascara
        $resto = $dato;
        for ($i = 0; $i < 10; $i++) {
            $resto = ($resto << 1) ^ (($resto >> 9) * 0x537);
        }
        $bits = (($dato << 10) | $resto) ^ 0x5412;
        $bit = static fn(int $i): bool => (($bits >> $i) & 1) === 1;
        $n = $this->lado;
        for ($i = 0; $i <= 5; $i++) {
            $this->fijar(8, $i, $bit($i));
        }
        $this->fijar(8, 7, $bit(6));
        $this->fijar(8, 8, $bit(7));
        $this->fijar(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->fijar(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->fijar($n - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->fijar(8, $n - 15 + $i, $bit($i));
        }
        $this->fijar(8, $n - 8, true); // modulo oscuro fijo
    }

    /** Aplica (o quita: es un XOR) la mascara a los modulos de datos. */
    private function enmascarar(int $m): void
    {
        for ($y = 0; $y < $this->lado; $y++) {
            for ($x = 0; $x < $this->lado; $x++) {
                if ($this->fijos[$y][$x]) {
                    continue;
                }
                $invertir = match ($m) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($invertir) {
                    $this->modulos[$y][$x] = !$this->modulos[$y][$x];
                }
            }
        }
    }

    /** Penalidad de la norma: rachas del mismo color, cuadros 2x2, falsos patrones de esquina y balance. */
    private function penalidad(): int
    {
        $n = $this->lado;
        $p = 0;
        $lineas = [];
        for ($i = 0; $i < $n; $i++) {
            $lineas[] = $this->modulos[$i];
            $lineas[] = array_column($this->modulos, $i);
        }
        foreach ($lineas as $linea) {
            $racha = 1;
            for ($i = 1; $i <= $n; $i++) {
                if ($i < $n && $linea[$i] === $linea[$i - 1]) {
                    $racha++;
                    continue;
                }
                if ($racha >= 5) {
                    $p += $racha - 2;
                }
                $racha = 1;
            }
            $texto = implode('', array_map(static fn(bool $b): string => $b ? '1' : '0', $linea));
            $p += 40 * (substr_count($texto, '10111010000') + substr_count($texto, '00001011101'));
        }
        $oscuros = 0;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $oscuros += $this->modulos[$y][$x] ? 1 : 0;
                if ($x < $n - 1 && $y < $n - 1) {
                    $c = $this->modulos[$y][$x];
                    if ($c === $this->modulos[$y][$x + 1] && $c === $this->modulos[$y + 1][$x] && $c === $this->modulos[$y + 1][$x + 1]) {
                        $p += 3;
                    }
                }
            }
        }
        $p += 10 * intdiv(abs($oscuros * 20 - $n * $n * 10), $n * $n);
        return $p;
    }
}
