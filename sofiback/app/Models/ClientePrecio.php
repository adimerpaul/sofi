<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * El precio (1 a 13) que se le cobra a un cliente en un grupo de productos.
 * Todo cliente tiene una fila por grupo; por defecto el precio 1.
 */
class ClientePrecio extends Model
{
    protected $table = 'cliente_precios';
    protected $fillable = [
        'cliente_id',
        'cod_grup',
        'precio',
        'created_by',
        'updated_by',
    ];
    protected $casts = [
        'cliente_id' => 'integer',
        'precio' => 'integer',
    ];

    /** Los 13 precios del producto, en el orden de sus columnas. */
    public const CAMPOS = [
        1 => 'Precio', 2 => 'Precio_Costo', 3 => 'Precio3', 4 => 'Precio4', 5 => 'Precio5',
        6 => 'Precio6', 7 => 'Precio7', 8 => 'Precio8', 9 => 'Precio9', 10 => 'Precio10',
        11 => 'Precio11', 12 => 'Precio12', 13 => 'Precio13',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'Cod_Aut');
    }

    /** Le agrega a un cliente los grupos que le falten, con el precio 1. */
    public static function completarCliente($clienteId, $usuarioId = null)
    {
        $ahora = date('Y-m-d H:i:s');
        $filas = DB::table('tbgrupos')->whereRaw("TRIM(Cod_grup) <> ''")
            ->pluck(DB::raw('TRIM(Cod_grup)'))->unique()
            ->map(function ($grupo) use ($clienteId, $usuarioId, $ahora) {
                return ['cliente_id' => (int) $clienteId, 'cod_grup' => $grupo, 'precio' => 1,
                    'created_by' => $usuarioId, 'updated_by' => $usuarioId, 'created_at' => $ahora, 'updated_at' => $ahora];
            })->values()->all();
        // La clave unica (cliente, grupo) deja las que ya estaban como estaban.
        DB::table('cliente_precios')->insertOrIgnore($filas);
    }

    /** Le agrega un grupo nuevo a todos los clientes, con el precio 1. */
    public static function completarGrupo($codGrup, $usuarioId = null)
    {
        $ahora = date('Y-m-d H:i:s');
        DB::statement('
            INSERT IGNORE INTO cliente_precios (cliente_id, cod_grup, precio, created_by, updated_by, created_at, updated_at)
            SELECT Cod_Aut, ?, 1, ?, ?, ?, ? FROM tbclientes
        ', [trim($codGrup), $usuarioId, $usuarioId, $ahora, $ahora]);
    }
}
