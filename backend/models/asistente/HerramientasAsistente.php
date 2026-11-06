<?php
declare(strict_types=1);

require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../TiendaRepository.php';
require_once __DIR__ . '/../ReporteRepository.php';
require_once __DIR__ . '/../PanelRepository.php';
require_once __DIR__ . '/../AsistenteRepository.php';
require_once __DIR__ . '/Herramientas.php';

/**
 * Lo que el asistente puede consultar o hacer: cada herramienta es una consulta del sistema que ya existia
 * (reportes, stock, por reponer) o un enlace a una pantalla. Claude elige cual usar; los permisos se revisan
 * AQUI, en PHP, con el mismo Usuario que usa el resto de la pagina: el cajero no ve los reportes ni otra tienda
 * aunque se lo pida al asistente. Ninguna herramienta cambia datos: para vender, ingresar o ajustar, el asistente
 * lleva a la pantalla y el empleado lo confirma ahi.
 */
class HerramientasAsistente implements Herramientas
{
    private const DIAS_MAXIMO = 366;

    // Pantallas a las que el asistente puede llevar: [texto del boton, ruta]
    private const PAGINAS = [
        'nueva_venta'   => ['Nueva venta', 'venta_nueva'],
        'caja'          => ['Caja', 'caja'],
        'ventas'        => ['Ventas', 'ventas'],
        'clientes'      => ['Clientes', 'clientes'],
        'promociones'   => ['Promociones', 'promociones'],
        'catalogo'      => ['Catálogo de productos', 'productos'],
        'stock'         => ['Stock por tienda', 'stock'],
        'reponer'       => ['Productos por reponer', 'reponer'],
        'por_vencer'    => ['Productos por vencer', 'vencimientos'],
        'ingresos'      => ['Ingresos de mercadería', 'ingresos'],
        'traslados'     => ['Traslados', 'traslados'],
        'mermas'        => ['Mermas', 'mermas'],
        'kardex'        => ['Kardex', 'kardex'],
        'compras'       => ['Órdenes de compra', 'ordenes'],
        'reportes'      => ['Reportes de ventas', 'reportes'],
        'utilidad'      => ['Utilidad', 'utilidad'],
        'personal'      => ['Personal', 'empleados'],
        'ayuda'         => ['Ayuda', 'ayuda'],
    ];

    /** @var array<int, array{texto: string, url: string}> enlaces que dejo abrir_pagina en esta pregunta */
    private array $enlaces = [];

    public function __construct(
        private Usuario $usuario,
        private TiendaRepository $tiendas,
        private ReporteRepository $reportes,
        private PanelRepository $panel,
        private AsistenteRepository $consultas,
    ) {}

    /** @return array<int, array{texto: string, url: string}> */
    public function enlaces(): array
    {
        return $this->enlaces;
    }

    /**
     * Las herramientas en el formato de la API de Claude (name, description, input_schema).
     * Solo se ofrecen las que este rol puede usar: asi Claude ni intenta lo demas.
     * @return array<int, array<string, mixed>>
     */
    public function definiciones(): array
    {
        $fechas = [
            'desde' => ['type' => 'string', 'description' => 'Fecha inicial AAAA-MM-DD (incluida)'],
            'hasta' => ['type' => 'string', 'description' => 'Fecha final AAAA-MM-DD (incluida)'],
        ];
        $lista = [];
        if ($this->puedeVerReportes()) {
            $alcance = $this->usuario->puedeAbrirTiendas()
                ? ['alcance' => ['type' => 'string', 'enum' => ['tienda', 'cadena'],
                    'description' => 'tienda = solo la sucursal actual; cadena = todas las tiendas juntas y comparadas']]
                : [];
            $lista[] = [
                'name' => 'resumen_ventas',
                'description' => 'Total vendido, número de comprobantes, ticket promedio, devoluciones y ventas anuladas entre dos fechas. '
                    . 'Con alcance cadena también da el total de cada tienda.',
                'input_schema' => ['type' => 'object', 'properties' => $fechas + $alcance, 'required' => ['desde', 'hasta'],
                    'additionalProperties' => false],
            ];
            $lista[] = [
                'name' => 'detalle_ventas',
                'description' => 'Desglose de las ventas entre dos fechas: productos más vendidos, por cajero, por método de pago, '
                    . 'por categoría, por hora del día o por día.',
                'input_schema' => ['type' => 'object', 'properties' => $fechas + $alcance + [
                    'tipo' => ['type' => 'string', 'enum' => ['mas_vendidos', 'por_cajero', 'por_metodo_pago', 'por_categoria', 'por_hora', 'por_dia']],
                ], 'required' => ['desde', 'hasta', 'tipo'], 'additionalProperties' => false],
            ];
        }
        $lista[] = [
            'name' => 'buscar_productos',
            'description' => 'Busca productos activos por nombre, marca o código de barras. Devuelve precio de venta y stock '
                . '(cantidad actual y mínimo) en las tiendas que el usuario puede ver.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'texto' => ['type' => 'string', 'description' => 'Palabras del nombre o la marca, o el código de barras'],
            ], 'required' => ['texto'], 'additionalProperties' => false],
        ];
        $lista[] = [
            'name' => 'productos_por_reponer',
            'description' => 'Productos de la tienda actual que llegaron a su stock mínimo (stock bajo), los más urgentes primero.',
            'input_schema' => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
        ];
        $lista[] = [
            'name' => 'abrir_pagina',
            'description' => 'Muestra al usuario un botón para ir a una pantalla del sistema. Úsalo cuando pida HACER algo '
                . '(vender, registrar un ingreso, ver un reporte completo): la acción se hace y se confirma en esa pantalla.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'pagina' => ['type' => 'string', 'enum' => array_keys($this->paginasPermitidas())],
            ], 'required' => ['pagina'], 'additionalProperties' => false],
        ];
        return $lista;
    }

    /**
     * Ejecuta una herramienta y devuelve lo que vera Claude. Un dato malo o un permiso que falta
     * no lanza excepcion: vuelve como ['error' => ...] para que el asistente se lo explique al usuario.
     * @param array<string, mixed> $entrada
     * @return array<string, mixed>
     */
    public function ejecutar(string $nombre, array $entrada): array
    {
        return match ($nombre) {
            'resumen_ventas'        => $this->resumenVentas($entrada),
            'detalle_ventas'        => $this->detalleVentas($entrada),
            'buscar_productos'      => $this->buscarProductos($entrada),
            'productos_por_reponer' => $this->porReponer(),
            'abrir_pagina'          => $this->abrirPagina($entrada),
            default                 => ['error' => 'Esa herramienta no existe.'],
        };
    }

    // Lo que Claude sabe de quien pregunta (va en el system)
    public function contexto(): string
    {
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        return 'Usuario: ' . $this->usuario->nombreCompleto() . ' (' . strtolower($this->usuario->getNombreRol()) . ').'
            . ' Sucursal actual: ' . ($tienda?->getNombreTienda() ?? 'sin tienda') . '.'
            . ($this->puedeVerReportes() ? '' : ' Este rol NO puede ver reportes de ventas ni totales vendidos.')
            . ($this->usuario->puedeElegirTienda() ? ' Es administrador: puede ver todas las tiendas.' : ' Solo puede ver los datos de su tienda.');
    }

    private function puedeVerReportes(): bool
    {
        return $this->usuario->puedeAdministrarTienda($this->usuario->getIdTienda());
    }

    /** @return array<string, array{0: string, 1: string}> las pantallas que este rol ve en su menu */
    private function paginasPermitidas(): array
    {
        $u = $this->usuario;
        $id = $u->getIdTienda();
        $permiso = [
            'nueva_venta' => $u->puedeVender(),
            'caja'        => $u->puedeManejarCaja() || $u->puedeVerArqueo($id),
            'clientes'    => $u->puedeVender() || $u->puedeAdministrarTienda($id),
            'promociones' => $u->puedeVender() || $u->puedeAdministrarPromociones($id),
            'reponer'     => $u->puedeVerIngresos($id),
            'por_vencer'  => $u->puedeVerIngresos($id),
            'ingresos'    => $u->puedeVerIngresos($id),
            'compras'     => $u->puedeVerIngresos($id),
            'reportes'    => $this->puedeVerReportes(),
            'utilidad'    => $this->puedeVerReportes(),
            'personal'    => $this->puedeVerReportes(),
        ];
        return array_filter(self::PAGINAS, fn(string $clave): bool => $permiso[$clave] ?? true, ARRAY_FILTER_USE_KEY);
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} [desde, hasta, error] */
    private function rango(array $entrada): array
    {
        $desde = $this->fecha($entrada['desde'] ?? null);
        $hasta = $this->fecha($entrada['hasta'] ?? null);
        if ($desde === null || $hasta === null) {
            return [null, null, 'Las fechas deben ser AAAA-MM-DD y existir en el calendario.'];
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $dias = (new DateTimeImmutable($desde))->diff(new DateTimeImmutable($hasta))->days;
        if ($dias > self::DIAS_MAXIMO) {
            return [null, null, 'El rango no puede pasar de ' . self::DIAS_MAXIMO . ' días.'];
        }
        return [$desde, $hasta, null];
    }

    private function fecha(mixed $valor): ?string
    {
        if (!is_string($valor) || preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $valor, $m) !== 1) {
            return null;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $valor : null;
    }

    /** @return array{0: int|int[], 1: string}|null las tiendas del reporte y su nombre; null si pidio la cadena sin permiso */
    private function alcance(array $entrada): ?array
    {
        if (($entrada['alcance'] ?? 'tienda') === 'cadena') {
            if (!$this->usuario->puedeAbrirTiendas()) {
                return null;
            }
            return [array_keys($this->tiendas->todas()), 'toda la cadena'];
        }
        $id = $this->usuario->getIdTienda();
        return [$id, $this->tiendas->buscarPorId($id)?->getNombreTienda() ?? 'tienda ' . $id];
    }

    private function resumenVentas(array $entrada): array
    {
        if (!$this->puedeVerReportes()) {
            return ['error' => 'Tu rol no puede ver los reportes de ventas.'];
        }
        [$desde, $hasta, $error] = $this->rango($entrada);
        $alcance = $this->alcance($entrada);
        if ($error !== null || $alcance === null) {
            return ['error' => $error ?? 'Solo el administrador de la cadena ve todas las tiendas.'];
        }
        [$tiendas, $nombre] = $alcance;
        $datos = ['alcance' => $nombre, 'desde' => $desde, 'hasta' => $hasta, 'moneda' => 'PEN (soles)']
            + $this->reportes->resumen($tiendas, $desde, $hasta);
        if (is_array($tiendas)) {
            $datos['por_tienda'] = $this->reportes->porTienda($tiendas, $desde, $hasta);
        }
        $this->enlace('Ver el reporte', '/?ruta=reportes&desde=' . $desde . '&hasta=' . $hasta . (is_array($tiendas) ? '&alcance=cadena' : ''));
        return $datos;
    }

    private function detalleVentas(array $entrada): array
    {
        if (!$this->puedeVerReportes()) {
            return ['error' => 'Tu rol no puede ver los reportes de ventas.'];
        }
        [$desde, $hasta, $error] = $this->rango($entrada);
        $alcance = $this->alcance($entrada);
        if ($error !== null || $alcance === null) {
            return ['error' => $error ?? 'Solo el administrador de la cadena ve todas las tiendas.'];
        }
        [$tiendas, $nombre] = $alcance;
        $tipo = is_string($entrada['tipo'] ?? null) ? $entrada['tipo'] : '';
        $filas = match ($tipo) {
            'mas_vendidos'    => $this->reportes->masVendidos($tiendas, $desde, $hasta, 15),
            'por_cajero'      => $this->reportes->porCajero($tiendas, $desde, $hasta),
            'por_metodo_pago' => $this->reportes->porMetodoPago($tiendas, $desde, $hasta),
            'por_categoria'   => $this->reportes->porCategoria($tiendas, $desde, $hasta),
            'por_hora'        => $this->reportes->porHora($tiendas, $desde, $hasta),
            'por_dia'         => $this->reportes->porDia($tiendas, $desde, $hasta),
            default           => null,
        };
        if ($filas === null) {
            return ['error' => 'Tipo de detalle desconocido.'];
        }
        return ['alcance' => $nombre, 'desde' => $desde, 'hasta' => $hasta, 'tipo' => $tipo, 'filas' => $filas];
    }

    private function buscarProductos(array $entrada): array
    {
        $texto = is_string($entrada['texto'] ?? null) ? mb_substr(trim($entrada['texto']), 0, 80) : '';
        if ($texto === '') {
            return ['error' => 'Falta qué producto buscar.'];
        }
        $tiendas = $this->usuario->puedeElegirTienda() ? array_keys($this->tiendas->todas()) : [$this->usuario->getIdTienda()];
        $productos = [];
        foreach ($this->consultas->buscarProductos($texto, $tiendas) as $fila) {
            $id = (int) $fila['id_producto'];
            $productos[$id] ??= [
                'producto' => $fila['nombre_producto'],
                'marca'    => $fila['nombre_marca'],
                'precio'   => (float) $fila['precio_venta'],
                'unidad'   => $fila['abreviatura'],
                'codigo'   => $fila['codigo_barras'],
                'stock'    => [],
            ];
            if ($fila['nombre_tienda'] !== null) {
                $productos[$id]['stock'][] = ['tienda' => $fila['nombre_tienda'], 'cantidad' => (float) $fila['cantidad_actual'],
                    'minimo' => (float) $fila['stock_minimo'], 'bajo' => (float) $fila['cantidad_actual'] <= (float) $fila['stock_minimo']];
            }
        }
        if (count($productos) === 1) {
            $this->enlace('Ver ' . reset($productos)['producto'], '/?ruta=producto&id=' . array_key_first($productos));
        }
        return ['encontrados' => count($productos), 'productos' => array_values($productos)];
    }

    private function porReponer(): array
    {
        $id = $this->usuario->getIdTienda();
        $filas = array_map(fn(array $f): array => [
            'producto' => $f['nombre_producto'],
            'cantidad' => (float) $f['cantidad_actual'],
            'minimo'   => (float) $f['stock_minimo'],
            'unidad'   => $f['abreviatura'],
        ], $this->panel->porReponer($id, 30));
        if ($this->usuario->puedeVerIngresos($id)) {
            $this->enlace('Ver productos por reponer', '/?ruta=reponer');
        }
        return ['tienda' => $this->tiendas->buscarPorId($id)?->getNombreTienda(), 'total' => $this->panel->contarPorReponer($id),
            'mostrados' => $filas];
    }

    private function abrirPagina(array $entrada): array
    {
        $paginas = $this->paginasPermitidas();
        $clave = is_string($entrada['pagina'] ?? null) ? $entrada['pagina'] : '';
        if (!isset($paginas[$clave])) {
            return ['error' => 'Esa pantalla no existe o tu rol no la puede abrir.'];
        }
        [$texto, $ruta] = $paginas[$clave];
        $this->enlace($texto, '/?ruta=' . $ruta);
        return ['listo' => 'Se mostró al usuario un botón para abrir "' . $texto . '".'];
    }

    // Solo direcciones de este mismo sitio, escritas aqui (nunca una que invente el modelo)
    private function enlace(string $texto, string $url): void
    {
        foreach ($this->enlaces as $enlace) {
            if ($enlace['url'] === $url) {
                return;
            }
        }
        $this->enlaces[] = ['texto' => $texto, 'url' => $url];
    }
}
