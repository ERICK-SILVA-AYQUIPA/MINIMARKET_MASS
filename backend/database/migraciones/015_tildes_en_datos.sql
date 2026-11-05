/* =====================================================================
   MINIMARKET MASS - migracion 015: tildes y enes en los datos de ejemplo

   Los 12 scripts de la base guardaron los textos sin tildes ni enes ("Lacteos", "Costeno",
   "Lucia", "Nunez", "Direccion de ejemplo"...). La base, sus tablas y la conexion ya son utf8mb4,
   asi que solo hay que corregir los textos: la pantalla, el ticket y los reportes los muestran tal cual.

   - Cada UPDATE busca la fila por su id Y por el texto viejo: si alguien ya lo cambio a mano
     (otra direccion, otro nombre) no se toca. Se puede correr sobre una base con datos reales.
   - Los codigos que el programa compara (estados, roles, metodos de pago, motivos) no cambian.
   - La busqueda no se afecta: utf8mb4_unicode_ci compara sin tildes ("azucar" encuentra "Azúcar").
   ===================================================================== */

SET NAMES utf8mb4;

-- Categorias, marcas y tiendas
UPDATE categoria SET nombre_categoria = 'Lácteos' WHERE id_categoria = 2 AND nombre_categoria = 'Lacteos';

UPDATE marca SET nombre_marca = 'Costeño'  WHERE id_marca = 2  AND nombre_marca = 'Costeno';
UPDATE marca SET nombre_marca = 'Bolívar'  WHERE id_marca = 6  AND nombre_marca = 'Bolivar';
UPDATE marca SET nombre_marca = 'Guaraná'  WHERE id_marca = 27 AND nombre_marca = 'Guarana';
UPDATE marca SET nombre_marca = 'Nescafé'  WHERE id_marca = 34 AND nombre_marca = 'Nescafe';
UPDATE marca SET nombre_marca = 'Ángel'    WHERE id_marca = 37 AND nombre_marca = 'Angel';

UPDATE tienda SET direccion = 'Dirección de ejemplo, Cayma'          WHERE id_tienda = 1 AND direccion = 'Direccion de ejemplo, Cayma';
UPDATE tienda SET direccion = 'Dirección de ejemplo, Cerro Colorado' WHERE id_tienda = 2 AND direccion = 'Direccion de ejemplo, Cerro Colorado';
UPDATE tienda SET direccion = 'Dirección de ejemplo, Yanahuara'      WHERE id_tienda = 3 AND direccion = 'Direccion de ejemplo, Yanahuara';

-- Personal
UPDATE empleado SET apellidos = 'Quispe Huamán'  WHERE id_empleado = 1 AND apellidos = 'Quispe Huaman';
UPDATE empleado SET apellidos = 'Cáceres Pinto'  WHERE id_empleado = 2 AND apellidos = 'Caceres Pinto';
UPDATE empleado SET nombres   = 'Lucía'          WHERE id_empleado = 7 AND nombres   = 'Lucia';
UPDATE empleado SET apellidos = 'Núñez Cuadros'  WHERE id_empleado = 8 AND apellidos = 'Nunez Cuadros';
UPDATE empleado SET nombres   = 'Sofía'          WHERE id_empleado = 9 AND nombres   = 'Sofia';
UPDATE empleado SET apellidos = 'Linares Ávila'  WHERE id_empleado = 9 AND apellidos = 'Linares Avila';

-- Tipos de movimiento del kardex (solo el nombre que se muestra: el programa usa el id)
UPDATE tipo_movimiento SET nombre_movimiento = 'ANULACIÓN DE VENTA'    WHERE id_tipo_movimiento = 2 AND nombre_movimiento = 'ANULACION DE VENTA';
UPDATE tipo_movimiento SET nombre_movimiento = 'INGRESO DE MERCADERÍA' WHERE id_tipo_movimiento = 8 AND nombre_movimiento = 'INGRESO DE MERCADERIA';
UPDATE tipo_movimiento SET nombre_movimiento = 'DEVOLUCIÓN DE VENTA'   WHERE id_tipo_movimiento = 9 AND nombre_movimiento = 'DEVOLUCION DE VENTA';

-- Productos (datos.sql y catalogo.sql)
UPDATE producto SET nombre_producto = 'Arroz Costeño extra 1 kg'                 WHERE id_producto = 2  AND nombre_producto = 'Arroz Costeno extra 1 kg';
UPDATE producto SET nombre_producto = 'Atún Florida filete 170 g'                WHERE id_producto = 5  AND nombre_producto = 'Atun Florida filete 170 g';
UPDATE producto SET nombre_producto = 'Detergente Bolívar 800 g'                 WHERE id_producto = 6  AND nombre_producto = 'Detergente Bolivar 800 g';
UPDATE producto SET nombre_producto = 'Plátano de seda (por kg)'                 WHERE id_producto = 9  AND nombre_producto = 'Platano de seda (por kg)';
UPDATE producto SET nombre_producto = 'Azúcar rubia Costeño 1 kg'                WHERE id_producto = 10 AND nombre_producto = 'Azucar rubia Costeno 1 kg';
UPDATE producto SET nombre_producto = 'Azúcar blanca Cartavio 1 kg'              WHERE id_producto = 14 AND nombre_producto = 'Azucar blanca Cartavio 1 kg';
UPDATE producto SET nombre_producto = 'Fideos tallarín Molitalia 500 g'          WHERE id_producto = 17 AND nombre_producto = 'Fideos tallarin Molitalia 500 g';
UPDATE producto SET nombre_producto = 'Atún Campomar trozos 170 g'               WHERE id_producto = 20 AND nombre_producto = 'Atun Campomar trozos 170 g';
UPDATE producto SET nombre_producto = 'Atún Florida trozos en aceite 140 g'      WHERE id_producto = 21 AND nombre_producto = 'Atun Florida trozos en aceite 140 g';
UPDATE producto SET nombre_producto = 'Lenteja Costeño 500 g'                    WHERE id_producto = 22 AND nombre_producto = 'Lenteja Costeno 500 g';
UPDATE producto SET nombre_producto = 'Fréjol canario Costeño 500 g'             WHERE id_producto = 23 AND nombre_producto = 'Frejol canario Costeno 500 g';
UPDATE producto SET nombre_producto = 'Sopa instantánea Ajinomen gallina 85 g'   WHERE id_producto = 32 AND nombre_producto = 'Sopa instantanea Ajinomen gallina 85 g';
UPDATE producto SET nombre_producto = 'Gaseosa Coca-Cola sin azúcar 500 ml'      WHERE id_producto = 44 AND nombre_producto = 'Gaseosa Coca-Cola sin azucar 500 ml';
UPDATE producto SET nombre_producto = 'Gaseosa Guaraná Backus 1.5 L'             WHERE id_producto = 47 AND nombre_producto = 'Gaseosa Guarana Backus 1.5 L';
UPDATE producto SET nombre_producto = 'Café instantáneo Nescafé Kirma 180 g'     WHERE id_producto = 55 AND nombre_producto = 'Cafe instantaneo Nescafe Kirma 180 g';
UPDATE producto SET nombre_producto = 'Café Altomayo tostado molido 250 g'       WHERE id_producto = 56 AND nombre_producto = 'Cafe Altomayo tostado molido 250 g';
UPDATE producto SET nombre_producto = 'Hojuelas Ángel Zucaritas 300 g'           WHERE id_producto = 58 AND nombre_producto = 'Hojuelas Angel Zucaritas 300 g';
UPDATE producto SET nombre_producto = 'Infusión manzanilla McCollins 25 und'     WHERE id_producto = 59 AND nombre_producto = 'Infusion manzanilla McCollins 25 und';
UPDATE producto SET nombre_producto = 'Pan francés (por kg)'                     WHERE id_producto = 62 AND nombre_producto = 'Pan frances (por kg)';
UPDATE producto SET nombre_producto = 'Chocolate Sublime clásico 30 g'           WHERE id_producto = 66 AND nombre_producto = 'Chocolate Sublime clasico 30 g';
UPDATE producto SET nombre_producto = 'Papas fritas Lays clásicas 150 g'         WHERE id_producto = 68 AND nombre_producto = 'Papas fritas Lays clasicas 150 g';
UPDATE producto SET nombre_producto = 'Lavavajilla Sapolio limón 450 g'          WHERE id_producto = 73 AND nombre_producto = 'Lavavajilla Sapolio limon 450 g';
UPDATE producto SET nombre_producto = 'Lejía Clorox 1 L'                         WHERE id_producto = 74 AND nombre_producto = 'Lejia Clorox 1 L';
UPDATE producto SET nombre_producto = 'Papel higiénico Elite 4 rollos'           WHERE id_producto = 76 AND nombre_producto = 'Papel higienico Elite 4 rollos';
UPDATE producto SET nombre_producto = 'Jabón Protex avena 110 g'                 WHERE id_producto = 79 AND nombre_producto = 'Jabon Protex avena 110 g';
UPDATE producto SET nombre_producto = 'Pasta dental Colgate triple acción 75 ml' WHERE id_producto = 80 AND nombre_producto = 'Pasta dental Colgate triple accion 75 ml';
UPDATE producto SET nombre_producto = 'Pañales Huggies etapa 3 x 30'             WHERE id_producto = 83 AND nombre_producto = 'Panales Huggies etapa 3 x 30';
UPDATE producto SET nombre_producto = 'Toallas higiénicas Nosotras 10 und'       WHERE id_producto = 84 AND nombre_producto = 'Toallas higienicas Nosotras 10 und';
UPDATE producto SET nombre_producto = 'Limón (por kg)'                           WHERE id_producto = 86 AND nombre_producto = 'Limon (por kg)';

-- Notas de los movimientos de ejemplo
UPDATE traslado_mercaderia SET observacion = 'Reposición de abarrotes y papa'
    WHERE id_traslado = 1 AND observacion = 'Reposicion de abarrotes y papa';
UPDATE anulacion_venta SET motivo = 'Error de producto: el cliente pidió otro artículo'
    WHERE id_anulacion = 1 AND motivo = 'Error de producto: el cliente pidio otro articulo';
UPDATE ingreso_mercaderia SET observacion = 'Surtido inicial del catálogo'
    WHERE id_ingreso IN (4, 5, 6) AND observacion = 'Surtido inicial del catalogo';
