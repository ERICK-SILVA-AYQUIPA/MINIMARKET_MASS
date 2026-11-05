<?php
declare(strict_types=1);

/**
 * Arma un libro de Excel (.xlsx) con varias hojas, sin librerias: un .xlsx es un ZIP con archivos XML.
 * Los textos van como texto (nunca como formula) y los numeros como numeros, asi Excel suma y grafica.
 * Se guarda sin comprimir para no depender de la extension zip de PHP (en Laragon a veces viene apagada).
 */
final class LibroExcel
{
    /** @var array<int, array{nombre: string, encabezado: string[], filas: array<int, array<int, string|int|float|null>>, formatos: string[]}> */
    private array $hojas = [];

    /**
     * @param string[] $encabezado
     * @param array<int, array<int, string|int|float|null>> $filas
     * @param string[] $formatos por columna: 'texto', 'numero', 'soles' o 'porcentaje'
     */
    public function agregarHoja(string $nombre, array $encabezado, array $filas, array $formatos = []): void
    {
        // Excel: maximo 31 letras, sin : \ / ? * [ ], y sin repetir nombre
        $limpio = mb_substr(trim((string) preg_replace('#[:\\\\/?*\[\]]#', ' ', $nombre)), 0, 31) ?: 'Hoja';
        $base = $limpio;
        for ($n = 2; in_array($limpio, array_column($this->hojas, 'nombre'), true); $n++) {
            $limpio = mb_substr($base, 0, 28) . ' ' . $n;
        }
        $this->hojas[] = ['nombre' => $limpio, 'encabezado' => $encabezado, 'filas' => $filas, 'formatos' => $formatos];
    }

    public function contenido(): string
    {
        $hojas = $this->hojas ?: [['nombre' => 'Hoja', 'encabezado' => [], 'filas' => [], 'formatos' => []]];
        $archivos = [
            '[Content_Types].xml' => $this->tiposDeContenido(count($hojas)),
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => $this->libro($hojas),
            'xl/_rels/workbook.xml.rels' => $this->relacionesDelLibro(count($hojas)),
            'xl/styles.xml' => $this->estilos(),
        ];
        foreach ($hojas as $i => $hoja) {
            $archivos['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->hoja($hoja);
        }
        return self::zip($archivos);
    }

    /** @param array{nombre: string, encabezado: string[], filas: array<int, array<int, string|int|float|null>>, formatos: string[]} $hoja */
    private function hoja(array $hoja): string
    {
        // Estilos (styles.xml): 0 normal, 1 encabezado en negrita, 2 numero con 2 decimales, 3 numero, 4 porcentaje (0.0)
        $estiloDe = ['soles' => 2, 'numero' => 3, 'porcentaje' => 4];
        $anchos = array_map(fn(string $t): int => mb_strlen($t), $hoja['encabezado']);
        $xml = '';
        $fila = 1;
        if ($hoja['encabezado'] !== []) {
            $xml .= '<row r="1">';
            foreach ($hoja['encabezado'] as $c => $titulo) {
                $xml .= $this->celdaTexto($c, 1, $titulo, 1);
            }
            $xml .= '</row>';
            $fila = 2;
        }
        foreach ($hoja['filas'] as $valores) {
            $xml .= '<row r="' . $fila . '">';
            foreach (array_values($valores) as $c => $valor) {
                $formato = $hoja['formatos'][$c] ?? 'texto';
                $anchos[$c] = max($anchos[$c] ?? 0, mb_strlen((string) $valor));
                if ($valor === null || $valor === '') {
                    continue;
                }
                if (isset($estiloDe[$formato]) && is_numeric($valor)) {
                    $xml .= '<c r="' . self::columna($c) . $fila . '" s="' . $estiloDe[$formato] . '"><v>' . (float) $valor . '</v></c>';
                } else {
                    $xml .= $this->celdaTexto($c, $fila, (string) $valor, 0);
                }
            }
            $xml .= '</row>';
            $fila++;
        }
        $columnas = '';
        foreach ($anchos as $c => $ancho) {
            $columnas .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . min(60, max(10, $ancho + 2)) . '" customWidth="1"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0">'
            . ($hoja['encabezado'] !== [] ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' : '')
            . '</sheetView></sheetViews>'
            . ($columnas !== '' ? '<cols>' . $columnas . '</cols>' : '')
            . '<sheetData>' . $xml . '</sheetData></worksheet>';
    }

    private function celdaTexto(int $columna, int $fila, string $texto, int $estilo): string
    {
        // Quita los caracteres de control que XML no acepta
        $texto = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto);
        return '<c r="' . self::columna($columna) . $fila . '" t="inlineStr"' . ($estilo > 0 ? ' s="' . $estilo . '"' : '')
            . '><is><t xml:space="preserve">' . htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
    }

    // 0 -> A, 25 -> Z, 26 -> AA
    private static function columna(int $indice): string
    {
        $letras = '';
        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26) . $letras;
        }
        return $letras;
    }

    /** @param array<int, array{nombre: string}> $hojas */
    private function libro(array $hojas): string
    {
        $lista = '';
        foreach ($hojas as $i => $hoja) {
            $lista .= '<sheet name="' . htmlspecialchars($hoja['nombre'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $lista . '</sheets></workbook>';
    }

    private function relacionesDelLibro(int $hojas): string
    {
        $xml = '';
        for ($i = 1; $i <= $hojas; $i++) {
            $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . ($hojas + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $xml . '</Relationships>';
    }

    private function tiposDeContenido(int $hojas): string
    {
        $xml = '';
        for ($i = 1; $i <= $hojas; $i++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $xml . '</Types>';
    }

    private function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="0.0&quot;%&quot;"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFE14D"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="5">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    /**
     * ZIP sin compresion (metodo 0 "stored"): cabecera local + datos de cada archivo, el directorio central y el cierre.
     * @param array<string, string> $archivos ruta dentro del zip => contenido
     */
    private static function zip(array $archivos): string
    {
        $fechaDos = ((2026 - 1980) << 9) | (1 << 5) | 1; // 2026-01-01 00:00 (fecha fija: el mismo reporte da el mismo archivo)
        $datos = '';
        $central = '';
        foreach ($archivos as $nombre => $contenido) {
            $crc = crc32($contenido);
            $largo = strlen($contenido);
            $comun = pack('vvvVVVVv', 20, 0x0800, 0, $fechaDos << 16, $crc, $largo, $largo, strlen($nombre)); // hora 00:00 + fecha
            $central .= pack('V', 0x02014b50) . pack('v', 20) . $comun . pack('vvvvVV', 0, 0, 0, 0, 0, strlen($datos)) . $nombre;
            $datos .= pack('V', 0x04034b50) . $comun . pack('v', 0) . $nombre . $contenido;
        }
        return $datos . $central
            . pack('VvvvvVVv', 0x06054b50, 0, 0, count($archivos), count($archivos), strlen($central), strlen($datos), 0);
    }
}
