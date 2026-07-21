<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'Mapa zona',
            'guard_name' => 'web',
        ]);

        User::query()
            ->where('role', 'Admin')
            ->each(function (User $user) use ($permission) {
                $user->givePermissionTo($permission);
            });
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', 'Mapa zona')
            ->where('guard_name', 'web')
            ->delete();
    }
};
