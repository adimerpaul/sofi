<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permisos del modulo de facturacion.
 *
 * El menu del front muestra cada opcion segun can('<permiso>'), asi que el
 * nombre del permiso es el mismo que la ruta.
 */
class AddPermissionFacturacion extends Migration
{
    private const PERMISOS = [
        // Ver el listado de facturas emitidas desde el sistema.
        'facturacion',
        // Crear una venta o factura nueva.
        'facturacionNueva',
        // Anular una ya registrada.
        'facturacionAnular',
    ];

    public function up()
    {
        foreach (self::PERMISOS as $nombre) {
            Permission::findOrCreate($nombre, 'web');
        }

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Permission::whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();

        app()['cache']->forget('spatie.permission.cache');
    }
}
