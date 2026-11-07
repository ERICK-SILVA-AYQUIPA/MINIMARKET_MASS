<?php
declare(strict_types=1);

require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../TiendaRepository.php';
require_once __DIR__ . '/../ReporteRepository.php';
require_once __DIR__ . '/../PanelRepository.php';
require_once __DIR__ . '/../AsistenteRepository.php';
require_once __DIR__ . '/../IngresoRepository.php';
require_once __DIR__ . '/../AnalisisVentas.php';
require_once __DIR__ . '/../Reposicion.php';
require_once __DIR__ . '/../Vencimiento.php';
require_once __DIR__ . '/../Cantidad.php';
require_once __DIR__ . '/Herramientas.php';

/**
 * Lo que el asistente puede consultar o hacer: cada herramienta es una consulta del sistema que ya existia
 * (reportes, stock, por reponer, por vencer) o un enlace a una pantalla. Las "inteligentes" (cuanto pedir,
 * pronostico de la semana, horas punta) hacen sus cuentas con Reposicion y AnalisisVentas, sin IA. Claude elige cual usar; los permisos se revisan
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
        'estadisticas'  => ['Estadísticas', 'estadisticas'],
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
        private IngresoRepository $ingresos,
        private DateTimeImmutable $hoy = new DateTimeImmutable('today'),
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
            'name' => 'productos_sin_venta',
            'description' => 'Productos de la tienda actual con stock que no se venden hace varios días (o nunca): lo que está '
                . 'en el estante y no sale. Útil para promociones o para dejar de pedirlos.',
            'input_schema' => ['type' => 'object', 'properties' => [
                'dias' => ['type' => 'integer', 'description' => 'Días sin venderse (7 a 180; por defecto 30)'],
            ], 'additionalProperties' => false],
        ];
        if ($this->puedeVerAlmacen()) {
            $lista[] = [
                'name' => 'sugerencia_pedido',
                'description' => 'Qué pedir al proveedor y cuánto: para cada producto en stock mínimo de la tienda actual, lo que se vende '
                    . 'por día (últimos ' . Reposicion::DIAS_VENTA . ' días), para cuántos días alcanza lo que queda y la cantidad sugerida '
                    . 'para cubrir ' . Reposicion::DIAS_OBJETIVO . ' días. Los que se acaban antes van primero.',
                'input_schema' => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
            ];
            $lista[] = [
                'name' => 'productos_por_vencer',
                'description' => 'Lotes de la tienda actual que vencen pronto o ya vencieron, con cuánto queda y su valor al costo.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'dias' => ['type' => 'integer', 'description' => 'Que venzan en estos días o antes (7 a 60; por defecto 30)'],
                ], 'additionalProperties' => false],
            ];
        }
        if ($this->puedeVerReportes()) {
            $lista[] = [
                'name' => 'pronostico_ventas',
                'description' => 'Cuánto se espera vender en la tienda actual cada uno de los próximos 7 días (desde hoy), según lo vendido '
                    . 'el mismo día de la semana en las últimas ' . AnalisisVentas::SEMANAS_PRONOSTICO . ' semanas, y si las ventas van '
                    . 'subiendo o bajando. Es una estimación, no una promesa.',
                'input_schema' => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
            ];
            $lista[] = [
                'name' => 'horas_punta',
                'description' => 'Horas y día de la semana con más clientes (comprobantes) en la tienda actual en los últimos '
                    . AnalisisVentas::DIAS_HORAS_PUNTA . ' días, y las horas más tranquilas. Sirve para armar los turnos de caja.',
                'input_schema' => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
            ];
        }
        if ($this->puedeVerClientes()) {
            $lista[] = [
                'name' => 'mejores_clientes',
                'description' => 'Los clientes (con DNI o RUC) que más compraron en la tienda actual entre dos fechas: compras y total.',
                'input_schema' => ['type' => 'object', 'properties' => $fechas, 'required' => ['desde', 'hasta'], 'additionalProperties' => false],
            ];
        }
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
            'productos_sin_venta'   => $this->sinVenta($entrada),
            'mejores_clientes'      => $this->mejoresClientes($entrada),
            'sugerencia_pedido'     => $this->sugerenciaPedido(),
            'productos_por_vencer'  => $this->porVencer($entrada),
            'pronostico_ventas'     => $this->pronostico(),
            'horas_punta'           => $this->horasPunta(),
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
            'estadisticas' => $this->puedeVerReportes(),
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

    // Quien recibe la mercaderia y su administrador (los que ven Reponer, Ingresos y Por vencer)
    private function puedeVerAlmacen(): bool
    {
        return $this->usuario->puedeVerIngresos($this->usuario->getIdTienda());
    }

    private function nombreTienda(): ?string
    {
        return $this->tiendas->buscarPorId($this->usuario->getIdTienda())?->getNombreTienda();
    }

    private function sugerenciaPedido(): array
    {
        if (!$this->puedeVerAlmacen()) {
            return ['error' => 'Lo que hay que pedir lo ven el almacenero y el administrador de la tienda.'];
        }
        $filas = [];
        foreach ($this->panel->reposicion($this->usuario->getIdTienda(), Reposicion::DIAS_VENTA) as $f) {
            $diaria = Reposicion::ventaDiaria($f['vendido']);
            $pedir = Reposicion::sugerido($f['actual'], $f['minimo'], $diaria, $f['al_peso']);
            if ($pedir <= 0) {
                continue;
            }
            $filas[] = [
                'producto'         => $f['nombre'],
                'unidad'           => $f['unidad'],
                'queda'            => $f['actual'] / 1000,
                'minimo'           => $f['minimo'] / 1000,
                'venta_por_dia'    => round($diaria / 1000, 2),
                'alcanza_dias'     => Reposicion::diasCobertura($f['actual'], $diaria),
                'pedir'            => $pedir / 1000,
                'otras_tiendas'    => array_slice($f['otras'], 0, 2),
            ];
        }
        // Lo que se acaba antes primero; lo que no se vende (sin dias) al final
        usort($filas, fn(array $a, array $b): int => [$a['alcanza_dias'] ?? PHP_INT_MAX, $a['producto']]
            <=> [$b['alcanza_dias'] ?? PHP_INT_MAX, $b['producto']]);
        foreach ($filas as &$fila) {
            $fila['otras_tiendas'] = array_map(fn(array $o): array => ['tienda' => $o['tienda'], 'le_sobra' => $o['sobrante'] / 1000],
                $fila['otras_tiendas']);
        }
        unset($fila);
        if ($filas !== []) {
            $this->enlace('Armar la orden de compra', '/?ruta=ordenes&desde=reponer');
        }
        $this->enlace('Ver productos por reponer', '/?ruta=reponer');
        return ['tienda' => $this->nombreTienda(), 'dias_que_debe_durar' => Reposicion::DIAS_OBJETIVO, 'total' => count($filas),
            'mostrados' => array_slice($filas, 0, 20)];
    }

    private function porVencer(array $entrada): array
    {
        if (!$this->puedeVerAlmacen()) {
            return ['error' => 'Los productos por vencer los ven el almacenero y el administrador de la tienda.'];
        }
        $dias = is_int($entrada['dias'] ?? null) ? max(7, min(60, $entrada['dias'])) : Vencimiento::DIAS_POR_DEFECTO;
        $hoy = $this->hoy->format('Y-m-d');
        $lotes = [];
        foreach ($this->ingresos->lotesConVencimiento($this->usuario->getIdTienda()) as $idProducto => $datos) {
            foreach (Vencimiento::porVencer(Vencimiento::repartirStock($datos['lotes'], $datos['stock']), $hoy, $dias) as $lote) {
                $lotes[] = ['id' => $idProducto, 'vence' => (string) $lote['fecha_vencimiento'], 'dias' => (int) $lote['dias'],
                    'queda' => $lote['restante'] / 1000, 'valor' => round($lote['restante'] / 1000 * $lote['costo'], 2)];
            }
        }
        usort($lotes, fn(array $a, array $b): int => [$a['vence'], $a['id']] <=> [$b['vence'], $b['id']]);
        $nombres = $this->consultas->nombresProductos(array_column($lotes, 'id'));
        $filas = array_map(fn(array $l): array => [
            'producto' => $nombres[$l['id']]['nombre'] ?? 'Producto ' . $l['id'],
            'unidad'   => $nombres[$l['id']]['unidad'] ?? '',
            'vence'    => $l['vence'],
            'estado'   => Vencimiento::describir($l['dias']),
            'vencido'  => $l['dias'] < 0,
            'queda'    => $l['queda'],
            'valor'    => $l['valor'],
        ], $lotes);
        $this->enlace('Ver productos por vencer', '/?ruta=vencimientos&dias=' . (in_array($dias, Vencimiento::DIAS_PERMITIDOS, true) ? $dias : 30));
        return ['tienda' => $this->nombreTienda(), 'dias' => $dias, 'total' => count($filas),
            'vencidos' => count(array_filter($filas, fn(array $f): bool => $f['vencido'])),
            'valor_en_riesgo' => round(array_sum(array_column($filas, 'valor')), 2), 'moneda' => 'PEN (soles)',
            'mostrados' => array_slice($filas, 0, 20)];
    }

    private function pronostico(): array
    {
        if (!$this->puedeVerReportes()) {
            return ['error' => 'Tu rol no puede ver los reportes de ventas.'];
        }
        $hoy = $this->hoy;
        $desde = $hoy->modify('-' . (7 * AnalisisVentas::SEMANAS_PRONOSTICO) . ' days')->format('Y-m-d');
        $porDia = $this->reportes->porDia($this->usuario->getIdTienda(), $desde, $hoy->modify('-1 day')->format('Y-m-d'));
        if ($porDia === []) {
            return ['tienda' => $this->nombreTienda(), 'sin_historia' => true,
                'nota' => 'No hay ventas en las últimas semanas para estimar.'];
        }
        $this->enlace('Ver estadísticas', '/?ruta=estadisticas');
        return ['tienda' => $this->nombreTienda(), 'moneda' => 'PEN (soles)']
            + AnalisisVentas::pronostico($porDia, $hoy)
            + ['nota' => 'Promedio del mismo día de la semana; feriados y quincenas pueden cambiarlo.'];
    }

    private function horasPunta(): array
    {
        if (!$this->puedeVerReportes()) {
            return ['error' => 'Tu rol no puede ver los reportes de ventas.'];
        }
        $hoy = $this->hoy;
        $filas = $this->reportes->porDiaSemanaYHora($this->usuario->getIdTienda(),
            $hoy->modify('-' . AnalisisVentas::DIAS_HORAS_PUNTA . ' days')->format('Y-m-d'), $hoy->modify('-1 day')->format('Y-m-d'));
        $this->enlace('Ver estadísticas', '/?ruta=estadisticas');
        return ['tienda' => $this->nombreTienda(), 'dias' => AnalisisVentas::DIAS_HORAS_PUNTA] + AnalisisVentas::horasPunta($filas);
    }

    private function puedeVerClientes(): bool
    {
        return $this->usuario->puedeVender() || $this->puedeVerReportes();
    }

    private function sinVenta(array $entrada): array
    {
        $dias = is_int($entrada['dias'] ?? null) ? max(7, min(180, $entrada['dias'])) : 30;
        $id = $this->usuario->getIdTienda();
        $limite = $this->hoy->modify('-' . $dias . ' days')->format('Y-m-d');
        $quietos = [];
        foreach ($this->reportes->catalogoConStock($id) as $f) {
            $ultima = is_string($f['ultima'] ?? null) ? substr($f['ultima'], 0, 10) : null;
            if ((float) $f['stock'] > 0 && ($ultima === null || $ultima < $limite)) {
                $quietos[] = ['producto' => (string) $f['producto'], 'stock' => (float) $f['stock'], 'unidad' => (string) $f['unidad'],
                    'precio' => (float) $f['precio'], 'ultima_venta' => $ultima];
            }
        }
        // Los que mas plata tienen parada en el estante primero
        usort($quietos, fn(array $a, array $b): int => $b['stock'] * $b['precio'] <=> $a['stock'] * $a['precio']);
        if ($this->puedeVerReportes()) {
            $this->enlace('Ver estadísticas', '/?ruta=estadisticas');
        }
        return ['tienda' => $this->tiendas->buscarPorId($id)?->getNombreTienda(), 'dias' => $dias, 'total' => count($quietos),
            'mostrados' => array_slice($quietos, 0, 15)];
    }

    private function mejoresClientes(array $entrada): array
    {
        if (!$this->puedeVerClientes()) {
            return ['error' => 'Tu rol no puede ver los clientes.'];
        }
        [$desde, $hasta, $error] = $this->rango($entrada);
        if ($error !== null) {
            return ['error' => $error];
        }
        $filas = array_map(fn(array $f): array => ['cliente' => (string) $f['cliente'], 'compras' => (int) $f['compras'],
            'total' => (float) $f['total']], $this->consultas->mejoresClientes($this->usuario->getIdTienda(), $desde, $hasta));
        $this->enlace('Ver clientes', '/?ruta=clientes');
        return ['desde' => $desde, 'hasta' => $hasta, 'clientes' => $filas];
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
            if ($enlace['url'] === $url || $enlace['texto'] === $texto) {
                return;
            }
        }
        $this->enlaces[] = ['texto' => $texto, 'url' => $url];
    }
}
