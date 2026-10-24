<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Tipo de un pedido para facturacion.
 *
 * tbpedidos.tipo dice NORMAL (embutidos), POLLO, CERDO o RES. Podium y huevo
 * se piden dentro de un pedido de embutidos, pero se facturan aparte: cada
 * linea con un codigo de PODIUM cuenta como un pedido PODIUM con el mismo
 * NroPed, y cada una de HUEVO como un pedido HUEVO; dejan de aparecer en
 * EMBUTIDOS. Hasta SEPARA_HUEVO (fecha de entrega) huevo iba junto con
 * podium en PODIUM ("Podium y Huevo"): asi se facturo y asi se sigue
 * leyendo, para que lo ya cobrado no reaparezca como pendiente.
 * Desde POR_TIPO_PRODUCTO (fecha de entrega) ya no mandan esas listas fijas
 * sino el tipo administrable del producto (Productos > Tipo): cada linea va a
 * la boleta de su tipo (HUEVO, PET -> PODIUM, POLLO, CERDO, RES), asi un
 * pedido mixto sale en una boleta por tipo. Los embutidos (y lo que no tenga
 * tipo) siguen en la boleta que dice tbpedidos.tipo.
 * Los valores son cortos porque alguna columna pedido_tipo es varchar(10).
 *
 * No se toca tbpedidos: el tipo se calcula al consultar, con sql().
 */
class TipoPedido
{
    const TIPOS = ['NORMAL', 'POLLO', 'CERDO', 'RES', 'PODIUM', 'HUEVO'];

    /** Primer dia de entrega en que huevo se factura aparte de podium. */
    const SEPARA_HUEVO = '2026-10-17';

    /**
     * Primer dia de entrega en que la boleta sale del tipo de producto. Lo de
     * antes ya se cobro con las listas fijas y se sigue leyendo igual.
     */
    const POR_TIPO_PRODUCTO = '2026-10-21';

    /**
     * Tipo de producto (tipos_producto.nombre) => tipo de pedido, en el orden
     * en que se evaluan. EMBUTIDO no esta: va donde diga tbpedidos.tipo.
     */
    const DE_TIPO_PRODUCTO = [
        'HUEVO' => 'HUEVO',
        'PET'   => 'PODIUM',
        'POLLO' => 'POLLO',
        'CERDO' => 'CERDO',
        'RES'   => 'RES',
    ];

    /** Codigos por tipo de pedido, leidos una vez por request. */
    private static $porTipoProducto = null;

    const HUEVO = [
        '590209', '590210', '590211', '590144', '590104', '590105', '590228', '590108', '590208',
    ];

    const PODIUM = [
        '210005', '210007', '640000', '640002', '640005', '640007', '640008', '641004',
        '650101', '650102', '650103', '650104', '650112', '650113', '650114',
        '650121', '650122', '650123', '650124', '650131', '650132', '650133', '650134',
        '650200', '650202', '650256', '650257', '650303', '651250', '651251', '651300',
        '660000', '660002', '660003', '660001', '210006', '211006', '201002', '201001', '201003',
        '650301',
    ];

    /** Para las reglas de validacion: "in:NORMAL,POLLO,...". */
    public static function regla()
    {
        return 'in:' . implode(',', self::TIPOS);
    }

    /**
     * El tipo de una fila de tbpedidos en SQL, en lugar de UPPER(TRIM(alias.tipo)).
     * $alias es el de tbpedidos en la consulta ('' si va sin alias).
     */
    public static function sql($alias = 'p')
    {
        $a = $alias !== '' ? $alias . '.' : '';
        $listas = "(CASE WHEN TRIM({$a}cod_prod) IN (" . self::lista(self::PODIUM) . ") THEN 'PODIUM'"
            . " WHEN TRIM({$a}cod_prod) IN (" . self::lista(self::HUEVO) . ")"
            . " THEN (CASE WHEN {$a}fecha_entrega >= '" . self::SEPARA_HUEVO . "' THEN 'HUEVO' ELSE 'PODIUM' END)"
            . " ELSE UPPER(TRIM({$a}tipo)) END)";

        $porProducto = '';
        foreach (self::codigosPorTipoProducto() as $tipo => $codigos) {
            $porProducto .= " WHEN TRIM({$a}cod_prod) IN (" . self::lista($codigos) . ") THEN '{$tipo}'";
        }
        if ($porProducto === '') {
            return $listas;
        }

        return "(CASE WHEN {$a}fecha_entrega >= '" . self::POR_TIPO_PRODUCTO . "'"
            . " THEN (CASE{$porProducto} ELSE UPPER(TRIM({$a}tipo)) END)"
            . " ELSE {$listas} END)";
    }

    /** Si el codigo es huevo, por la lista fija o por su tipo de producto. */
    public static function esHuevo($codProd)
    {
        $codigo = trim((string) $codProd);
        return in_array($codigo, self::HUEVO, true)
            || in_array($codigo, self::codigosPorTipoProducto()['HUEVO'] ?? [], true);
    }

    /**
     * Tipo de pedido => codigos de producto, segun Productos > Tipo. Solo
     * entran codigos simples (digitos, letras, punto, guion): van escritos
     * en el SQL, y uno raro se queda en la boleta de tbpedidos.tipo.
     */
    private static function codigosPorTipoProducto()
    {
        if (self::$porTipoProducto !== null) {
            return self::$porTipoProducto;
        }

        $filas = DB::table('tbproductos as pr')
            ->join('tipos_producto as tp', 'tp.id', '=', 'pr.tipo_producto_id')
            ->get([DB::raw('UPPER(TRIM(tp.nombre)) as tipo'), DB::raw('TRIM(pr.cod_prod) as cod')]);

        $porTipo = [];
        foreach (self::DE_TIPO_PRODUCTO as $tipo) {
            $porTipo[$tipo] = [];
        }
        foreach ($filas as $fila) {
            $tipo = self::DE_TIPO_PRODUCTO[$fila->tipo] ?? null;
            if ($tipo && preg_match('/^[0-9A-Za-z._-]+$/', (string) $fila->cod)) {
                $porTipo[$tipo][] = $fila->cod;
            }
        }

        return self::$porTipoProducto = array_map('array_unique', array_filter($porTipo));
    }

    /**
     * Para el SELECT de una consulta agrupada por sql(): con ONLY_FULL_GROUP_BY
     * (el servidor lo tiene, local no) MySQL rechaza la expresion suelta porque
     * usa cod_prod, que no esta en el GROUP BY. Dentro del grupo el tipo es
     * uno solo, asi que MIN() da lo mismo y pasa en cualquier modo.
     */
    public static function sqlAgrupado($alias = 'p')
    {
        return 'MIN' . self::sql($alias);
    }

    private static function lista(array $codigos)
    {
        // Son constantes de esta clase (solo digitos): se pueden escribir en el SQL.
        return "'" . implode("','", $codigos) . "'";
    }
}
