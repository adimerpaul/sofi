<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'Mis pedidos totales',
            'guard_name' => 'web',
        ]);

        User::query()
            ->where('role', 'Admin')
            ->get()
            ->each(function (User $user) use ($permission) {
                $user->givePermissionTo($permission);
            });
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', 'Mis pedidos totales')
            ->where('guard_name', 'web')
            ->delete();
    }
};
