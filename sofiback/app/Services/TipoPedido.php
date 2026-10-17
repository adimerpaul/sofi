<?php

namespace App\Services;

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
 * Los valores son cortos porque alguna columna pedido_tipo es varchar(10).
 *
 * No se toca tbpedidos: el tipo se calcula al consultar, con sql().
 */
class TipoPedido
{
    const TIPOS = ['NORMAL', 'POLLO', 'CERDO', 'RES', 'PODIUM', 'HUEVO'];

    /** Primer dia de entrega en que huevo se factura aparte de podium. */
    const SEPARA_HUEVO = '2026-10-17';

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
        return "(CASE WHEN TRIM({$a}cod_prod) IN (" . self::lista(self::PODIUM) . ") THEN 'PODIUM'"
            . " WHEN TRIM({$a}cod_prod) IN (" . self::lista(self::HUEVO) . ")"
            . " THEN (CASE WHEN {$a}fecha_entrega >= '" . self::SEPARA_HUEVO . "' THEN 'HUEVO' ELSE 'PODIUM' END)"
            . " ELSE UPPER(TRIM({$a}tipo)) END)";
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
