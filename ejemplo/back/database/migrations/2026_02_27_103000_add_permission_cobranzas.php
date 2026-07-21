<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::firstOrCreate([
            'name' => 'Cobranzas',
            'guard_name' => 'web',
        ]);

        User::query()
            ->whereIn('role', ['Admin', 'Cobrador'])
            ->each(function (User $user) use ($perm) {
                $user->givePermissionTo($perm);
            });
    }

    public function down(): void
    {
        Permission::query()->where('name', 'Cobranzas')->delete();
    }
};

