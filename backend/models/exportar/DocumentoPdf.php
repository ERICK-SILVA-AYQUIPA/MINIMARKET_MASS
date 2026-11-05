<?php
declare(strict_types=1);

/**
 * Arma un PDF A4 sencillo (titulos, textos, tablas y graficos de barras), sin librerias.
 * Usa Helvetica, que todo lector de PDF ya trae: no hay que incrustar fuentes. Las tildes y la ñ
 * van en WinAnsi (Windows-1252), que es la codificacion de esas fuentes.
 */
final class DocumentoPdf
{
    private const ANCHO = 595.28;   // A4 en puntos
    private const ALTO = 841.89;
    private const MARGEN = 40.0;
    private const PIE = 40.0;

    // Ancho de cada letra de Helvetica (milesimas del tamaño de letra), de la 32 (espacio) a la 126 (~)
    private const ANCHOS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var string[] contenido de cada pagina */
    private array $paginas = [];
    private string $actual = '';
    private float $y = 0.0;
    private bool $abierta = false;

    public function __construct(private string $titulo, private string $pie = '')
    {
        $this->nuevaPagina();
    }

    public function titulo(string $texto, float $tamano = 16): void
    {
        $this->espacio($tamano + 10);
        $this->escribir(self::MARGEN, $this->y - $tamano, $texto, $tamano, true);
        $this->y -= $tamano + 8;
    }

    public function subtitulo(string $texto): void
    {
        $this->espacio(40);   // que no quede un titulo solo al pie de la pagina
        $this->y -= 8;
        $this->escribir(self::MARGEN, $this->y - 12, $texto, 12, true);
        $this->y -= 18;
    }

    public function texto(string $texto, float $tamano = 9, bool $gris = true): void
    {
        foreach ($this->cortarEnLineas($texto, $tamano, self::ANCHO - 2 * self::MARGEN) as $linea) {
            $this->espacio($tamano + 4);
            $this->actual .= $gris ? "0.35 0.39 0.45 rg\n" : '';
            $this->escribir(self::MARGEN, $this->y - $tamano, $linea, $tamano, false);
            $this->actual .= $gris ? "0 0 0 rg\n" : '';
            $this->y -= $tamano + 4;
        }
    }

    /**
     * Tabla con encabezado en negrita; si no entra en la pagina sigue en la siguiente, repitiendo el encabezado.
     * @param array<int, array{0: string, 1: float, 2: string}> $columnas [titulo, ancho relativo, 'izq' o 'der']
     * @param array<int, array<int, string>> $filas textos ya formateados
     */
    public function tabla(array $columnas, array $filas, string $vacio = 'Sin datos en estas fechas.'): void
    {
        $total = array_sum(array_column($columnas, 1)) ?: 1;
        $disponible = self::ANCHO - 2 * self::MARGEN;
        $anchos = array_map(fn(array $c): float => $c[1] / $total * $disponible, $columnas);
        $alto = 15.0;
        $encabezado = function () use ($columnas, $anchos, $alto): void {
            $this->rectangulo(self::MARGEN, $this->y - $alto, self::ANCHO - 2 * self::MARGEN, $alto, '1 0.882 0.302');
            $this->fila(array_column($columnas, 0), $columnas, $anchos, $alto, true);
        };
        $this->espacio($alto * 2);
        $encabezado();
        if ($filas === []) {
            $this->fila([$vacio], [['', 1, 'izq']], [$disponible], $alto, false);
        }
        foreach ($filas as $i => $fila) {
            if ($this->y - $alto < self::PIE) {
                $this->nuevaPagina();
                $encabezado();
            }
            if ($i % 2 === 1) {
                $this->rectangulo(self::MARGEN, $this->y - $alto, $disponible, $alto, '0.96 0.965 0.97');
            }
            $this->fila(array_values($fila), $columnas, $anchos, $alto, false);
        }
        $this->y -= 6;
    }

    /**
     * Grafico de barras horizontales: una barra por fila, con su etiqueta y su valor.
     * @param string[] $etiquetas
     * @param float[] $valores
     * @param string[] $textos el valor ya formateado (S/ 12.50)
     */
    public function barras(array $etiquetas, array $valores, array $textos): void
    {
        $maximo = max([0.0, ...$valores]);
        $alto = 14.0;
        $xBarra = self::MARGEN + 150;
        $anchoBarra = self::ANCHO - self::MARGEN - $xBarra - 80;
        foreach ($etiquetas as $i => $etiqueta) {
            $this->espacio($alto);
            $base = $this->y - $alto;
            $this->escribir(self::MARGEN, $base + 4, $this->recortar($etiqueta, 8, 145), 8, false);
            $largo = $maximo > 0 ? max(1.0, $valores[$i] / $maximo * $anchoBarra) : 1.0;
            $this->rectangulo($xBarra, $base + 3, $largo, $alto - 6, '0.122 0.176 0.239');
            $this->escribir($xBarra + $largo + 4, $base + 4, $textos[$i] ?? '', 8, false);
            $this->y -= $alto;
        }
        $this->y -= 6;
    }

    public function contenido(): string
    {
        $this->cerrarPagina();
        $objetos = [];
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $hijos = [];
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objetos[5] = '<< /Title ' . $this->cadena($this->titulo) . ' /Producer (Minimarket Mass) >>';
        $n = 6;
        $total = count($this->paginas);
        foreach ($this->paginas as $i => $flujo) {
            // Pie de cada pagina: el texto del sistema y "Pagina 1 de 3"
            $pie = '0.45 0.49 0.55 rg BT /F1 7.5 Tf ' . self::MARGEN . ' 22 Td ' . $this->cadena($this->pie) . " Tj ET\n"
                . 'BT /F1 7.5 Tf ' . sprintf('%.2F', self::ANCHO - self::MARGEN - 60) . ' 22 Td '
                . $this->cadena('Página ' . ($i + 1) . ' de ' . $total) . " Tj ET\n";
            $flujo .= $pie;
            $objetos[$n] = '<< /Length ' . strlen($flujo) . " >>\nstream\n" . $flujo . "\nendstream";
            $objetos[$n + 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::ANCHO . ' ' . self::ALTO . ']'
                . ' /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $n . ' 0 R >>';
            $hijos[] = ($n + 1) . ' 0 R';
            $n += 2;
        }
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $hijos) . '] /Count ' . $total . ' >>';
        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posiciones = [];
        foreach ($objetos as $id => $cuerpo) {
            $posiciones[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $cuerpo . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
        foreach ($posiciones as $posicion) {
            $pdf .= sprintf("%010d 00000 n \n", $posicion);
        }
        return $pdf . 'trailer << /Size ' . (count($objetos) + 1) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }

    // ----- Por dentro -----

    /**
     * @param string[] $valores
     * @param array<int, array{0: string, 1: float, 2: string}> $columnas
     * @param float[] $anchos
     */
    private function fila(array $valores, array $columnas, array $anchos, float $alto, bool $negrita): void
    {
        $x = self::MARGEN;
        foreach ($anchos as $c => $ancho) {
            $texto = $this->recortar((string) ($valores[$c] ?? ''), 8.5, $ancho - 8);
            $xTexto = ($columnas[$c][2] ?? 'izq') === 'der' ? $x + $ancho - 4 - $this->anchoTexto($texto, 8.5, $negrita) : $x + 4;
            $this->escribir($xTexto, $this->y - $alto + 4.5, $texto, 8.5, $negrita);
            $x += $ancho;
        }
        $this->y -= $alto;
    }

    private function escribir(float $x, float $y, string $texto, float $tamano, bool $negrita): void
    {
        $this->actual .= sprintf("BT /%s %.2F Tf %.2F %.2F Td %s Tj ET\n", $negrita ? 'F2' : 'F1', $tamano, $x, $y, $this->cadena($texto));
    }

    private function rectangulo(float $x, float $y, float $ancho, float $alto, string $color): void
    {
        $this->actual .= sprintf("q %s rg %.2F %.2F %.2F %.2F re f Q\n", $color, $x, $y, $ancho, $alto);
    }

    // Si no queda lugar para lo que sigue, pasa a una pagina nueva
    private function espacio(float $necesario): void
    {
        if ($this->y - $necesario < self::PIE) {
            $this->nuevaPagina();
        }
    }

    private function nuevaPagina(): void
    {
        $this->cerrarPagina();
        $this->actual = '';
        $this->abierta = true;
        $this->y = self::ALTO - self::MARGEN;
    }

    private function cerrarPagina(): void
    {
        if ($this->abierta) {
            $this->paginas[] = $this->actual;
            $this->abierta = false;
        }
    }

    private function anchoTexto(string $texto, float $tamano, bool $negrita): float
    {
        $suma = 0;
        foreach (mb_str_split(self::sinTildes($texto)) as $letra) {
            $codigo = ord($letra[0]);
            $suma += self::ANCHOS[$codigo - 32] ?? 556;
        }
        return $suma * $tamano / 1000 * ($negrita ? 1.06 : 1.0);
    }

    // Corta un texto con "..." para que entre en el ancho
    private function recortar(string $texto, float $tamano, float $ancho): string
    {
        if ($this->anchoTexto($texto, $tamano, false) <= $ancho) {
            return $texto;
        }
        while ($texto !== '' && $this->anchoTexto($texto . '...', $tamano, false) > $ancho) {
            $texto = mb_substr($texto, 0, -1);
        }
        return rtrim($texto) . '...';
    }

    /** @return string[] */
    private function cortarEnLineas(string $texto, float $tamano, float $ancho): array
    {
        $lineas = [];
        $linea = '';
        foreach (preg_split('/\s+/', trim($texto)) ?: [] as $palabra) {
            $prueba = $linea === '' ? $palabra : $linea . ' ' . $palabra;
            if ($linea !== '' && $this->anchoTexto($prueba, $tamano, false) > $ancho) {
                $lineas[] = $linea;
                $linea = $palabra;
            } else {
                $linea = $prueba;
            }
        }
        return $linea === '' ? $lineas : [...$lineas, $linea];
    }

    // Para medir: una letra con tilde mide lo mismo que sin ella
    private static function sinTildes(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', '–' => '-', '−' => '-']);
    }

    // Texto UTF-8 -> cadena PDF en Windows-1252, con ( ) \ escapados
    private function cadena(string $texto): string
    {
        $texto = strtr($texto, ['−' => '-']);
        $convertido = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
        $convertido = (string) preg_replace('/[\x00-\x1F]/', ' ', $convertido);
        return '(' . strtr($convertido, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']) . ')';
    }
}
