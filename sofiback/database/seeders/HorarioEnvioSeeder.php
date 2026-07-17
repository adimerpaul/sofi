<?php

namespace Database\Seeders;

use App\Models\HorarioEnvio;
use Illuminate\Database\Seeder;

class HorarioEnvioSeeder extends Seeder
{
    public function run()
    {
        HorarioEnvio::firstOrCreate(
            ['hora' => '23:00:00'],
            ['dias' => '1,2,3,4,5,6,7', 'activo' => 1]
        );
    }
}
