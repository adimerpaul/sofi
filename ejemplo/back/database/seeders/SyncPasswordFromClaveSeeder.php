<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SyncPasswordFromClaveSeeder extends Seeder
{
    public function run(): void
    {
        $rows = DB::table('users')->select('id', 'clave')->get();
        $count = 0;

        foreach ($rows as $row) {
            if (empty($row->clave)) {
                continue;
            }

            // Si clave está cifrada (Laravel encrypted), se descifra.
            // Si está en texto plano, se usa tal cual.
            try {
                $plain = Crypt::decryptString($row->clave);
            } catch (\Throwable $e) {
                $plain = (string) $row->clave;
            }

            if ($plain === '') {
                continue;
            }

            DB::table('users')
                ->where('id', $row->id)
                ->update([
                    'password' => Hash::make($plain),
                    'updated_at' => now(),
                ]);

            $count++;
        }

        $this->command?->info("Passwords actualizados: {$count}");
    }
}
