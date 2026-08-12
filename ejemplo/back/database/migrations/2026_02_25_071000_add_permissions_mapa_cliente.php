<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $names = ['Mapa cliente', 'Mapa cliente zonas'];
        $permissions = [];

        foreach ($names as $name) {
            $permissions[] = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        User::query()
            ->where('role', 'Admin')
            ->get()
            ->each(function (User $user) use ($permissions) {
                foreach ($permissions as $permission) {
                    $user->givePermissionTo($permission);
                }
            });
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', ['Mapa cliente', 'Mapa cliente zonas'])
            ->where('guard_name', 'web')
            ->delete();
    }
};

