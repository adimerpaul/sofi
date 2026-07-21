<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateUsersFromPersonalSeeder extends Seeder
{
    public function run(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('personal')) {
            $this->command?->warn('Tabla personal no existe. Seeder omitido.');
            return;
        }

        $rows = DB::table('personal')->get();
        $migrated = 0;

        foreach ($rows as $row) {
            $rawPassword = trim((string)($row->pasw ?? ''));
            if ($rawPassword === '') {
                $rawPassword = trim((string)($row->ci ?? '123456'));
            }

            $username = $this->buildUsername($row);
            $email = $this->normalizeEmail($row->correo ?? null, $username);

            $fullName = trim(implode(' ', array_filter([
                $row->Nombre1 ?? null,
                $row->Nombre2 ?? null,
                $row->App1 ?? null,
                $row->Apm ?? null,
            ])));
            if ($fullName === '') {
                $fullName = $username;
            }

            $payload = [
                'name' => Str::upper($fullName),
                'username' => $username,
                'email' => $email,
                'password' => $rawPassword,
                'clave' => $rawPassword,
                'role' => 'Usuario',
                'agencia' => 'Sofia',
                'active' => '1',
                'avatar' => 'avatar.png',
                'celular' => null,
                'es_camion' => $this->isCamion($row),
                'cod_aut' => $this->nullableInt($row->CodAut ?? null),
                'fecha_nacimiento' => $row->Fech_naci ?? null,
                'ci' => $this->nullableString($row->ci ?? null),
                'cod_prof' => $this->nullableInt($row->cod_Prof ?? null),
                'app1' => $this->nullableString($row->App1 ?? null),
                'app2' => $this->nullableString($row->Apm ?? null),
                'nombre1' => $this->nullableString($row->Nombre1 ?? null),
                'nombre2' => $this->nullableString($row->Nombre2 ?? null),
                'salario' => $row->Salario ?? null,
                'direccion' => $this->nullableString($row->direccion ?? null),
                'cod_car' => $this->nullableInt($row->cod_car ?? null),
                'nro' => $this->nullableInt($row->Nro ?? null),
                'nro_alm' => $this->nullableInt($row->NroAlm ?? null),
                'acceso_emp' => $this->nullableBool($row->AccesoEmp ?? null),
                'placa' => $this->nullableString($row->placa ?? null),
            ];

            $existing = User::query()
                ->when($payload['ci'], fn($q) => $q->where('ci', $payload['ci']))
                ->orWhere('username', $payload['username'])
                ->first();

            if ($existing) {
                $existing->update($payload);
            } else {
                User::query()->create($payload);
            }

            $migrated++;
        }

        $this->command?->info("Migración personal -> users completada. Registros procesados: {$migrated}");
    }

    private function buildUsername(object $row): string
    {
        $base = trim((string)($row->ci ?? ''));
        if ($base === '') {
            $base = trim((string)($row->correo ?? ''));
        }
        if ($base === '') {
            $base = 'user_' . ($row->CodAut ?? Str::random(6));
        }

        $base = Str::lower(Str::ascii($base));
        $base = preg_replace('/[^a-z0-9_\\.\\-]/', '', $base) ?: 'user';
        $username = substr($base, 0, 40);

        $suffix = 1;
        $candidate = $username;
        while (User::where('username', $candidate)->exists()) {
            $candidate = substr($username, 0, 36) . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function normalizeEmail(?string $email, string $username): ?string
    {
        $email = trim((string)$email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $candidate = Str::lower($email);
        if (!User::where('email', $candidate)->exists()) {
            return $candidate;
        }

        return $username . '@local.invalid';
    }

    private function isCamion(object $row): bool
    {
        $placa = trim((string)($row->placa ?? ''));
        return $placa !== '';
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }

    private function nullableBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (bool)$value;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
