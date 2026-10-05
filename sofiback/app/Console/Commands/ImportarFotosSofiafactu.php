<?php

namespace App\Console\Commands;

use App\Models\ClientePhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Trae las fotos de clientes cargadas en sofiafactu (clientes.fotos) y las guarda
 * como cliente_photos de este sistema, en public/cliente_photos/{Cod_Aut}/.
 *
 * El cliente se busca por cod_aut (= tbclientes.Cod_Aut); si no viene, por CI/NIT (= tbclientes.Id).
 * Se puede correr varias veces: una foto ya importada no se vuelve a bajar.
 *
 *   php artisan clientes:importar-fotos usuario          (lee los clientes por la API)
 *   php artisan clientes:importar-fotos --bd=sofifactu   (lee la BD de sofiafactu en el mismo MySQL)
 *   La migracion importar_fotos_clientes_sofiafactu usa la segunda forma.
 */
class ImportarFotosSofiafactu extends Command
{
    protected $signature = 'clientes:importar-fotos
                            {usuario? : Usuario de sofiafactu (para leer los clientes por la API)}
                            {--password= : Contrasena (si no se pasa, se pregunta)}
                            {--bd= : Leer los clientes directo de esta BD de sofiafactu en el mismo MySQL, sin usuario}
                            {--url=https://bsofiafactu.tuprogam.com/ : URL base del backend de sofiafactu}';

    protected $description = 'Importa las fotos de clientes desde sofiafactu a cliente_photos';

    private $importadas = 0;
    private $yaExistian = 0;
    private $errores = 0;
    private $sinCliente = [];

    public function handle()
    {
        $base = rtrim($this->option('url'), '/') . '/';

        if ($this->option('bd')) {
            $bd = preg_replace('/[^A-Za-z0-9_]/', '', $this->option('bd'));
            $clientes = DB::table("$bd.clientes")
                ->whereNull('deleted_at')
                ->whereNotNull('fotos')
                ->where('fotos', '!=', '[]')
                ->get(['nombre', 'cod_aut', 'ci', 'nit', 'fotos']);
            $this->info("Clientes con fotos en $bd: " . count($clientes));
            foreach ($clientes as $cli) {
                $this->importarCliente((array) $cli, $base);
            }
            return $this->resumen();
        }

        if (!$this->argument('usuario')) {
            $this->error('Indica el usuario de sofiafactu o la opcion --bd');
            return 1;
        }
        $password = $this->option('password') ?: $this->secret('Contrasena de ' . $this->argument('usuario'));

        $login = Http::acceptJson()->post($base . 'api/login', [
            'username' => $this->argument('usuario'),
            'password' => $password,
        ]);
        if (!$login->successful() || !$login->json('token')) {
            $this->error('No se pudo iniciar sesion en sofiafactu: ' . ($login->json('message') ?? $login->status()));
            return 1;
        }
        $token = $login->json('token');
        $pagina = 1;

        do {
            $res = Http::withToken($token)->acceptJson()->timeout(60)
                ->get($base . 'api/clientes', ['page' => $pagina, 'per_page' => 200]);
            if (!$res->successful()) {
                $this->error("Error al leer la pagina $pagina de clientes: " . $res->status());
                return 1;
            }
            $ultimaPagina = (int) $res->json('last_page', 1);
            $this->info("Pagina $pagina de $ultimaPagina");

            foreach ($res->json('data', []) as $cli) {
                $this->importarCliente($cli, $base);
            }
            $pagina++;
        } while ($pagina <= $ultimaPagina);

        return $this->resumen();
    }

    private function importarCliente(array $cli, string $base)
    {
        $fotos = $cli['fotos'] ?? [];
        if (is_string($fotos)) $fotos = json_decode($fotos, true) ?: [];
        if (empty($fotos)) return;

        $codAut = $this->buscarCliente($cli);
        if (!$codAut) {
            $this->sinCliente[] = ($cli['nombre'] ?? '') . ' (cod_aut ' . ($cli['cod_aut'] ?? '-') . ', ci ' . ($cli['ci'] ?? '-') . ')';
            return;
        }

        foreach ($fotos as $ruta) {
            if (!is_string($ruta) || $ruta === '') continue;
            // El nombre lleva el prefijo sofiafactu_ para reconocer lo ya importado.
            $relativa = "cliente_photos/{$codAut}/sofiafactu_" . basename($ruta);
            if (ClientePhoto::withTrashed()->where('cliente_id', $codAut)->where('photo_path', $relativa)->exists()) {
                $this->yaExistian++;
                continue;
            }

            try {
                $img = Http::timeout(60)->get($base . ltrim($ruta, '/'));
            } catch (\Throwable $e) {
                $img = null;
            }
            if (!$img || !$img->successful()) {
                $this->warn("No se pudo bajar $ruta" . ($img ? ' (' . $img->status() . ')' : ''));
                $this->errores++;
                continue;
            }

            $dir = public_path("cliente_photos/{$codAut}");
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            file_put_contents(public_path($relativa), $img->body());

            ClientePhoto::create(['cliente_id' => $codAut, 'photo_path' => $relativa]);
            $this->importadas++;
        }
    }

    private function resumen()
    {
        $this->info("Fotos importadas: {$this->importadas}");
        $this->info("Ya existian: {$this->yaExistian}");
        if ($this->errores) $this->warn("No se pudieron bajar: {$this->errores}");
        if ($this->sinCliente) {
            $this->warn('Clientes con fotos que no se encontraron en tbclientes: ' . count($this->sinCliente));
            foreach ($this->sinCliente as $s) $this->line('  - ' . $s);
        }
        return 0;
    }

    private function buscarCliente(array $cli)
    {
        if (!empty($cli['cod_aut'])) {
            $codAut = DB::table('tbclientes')->where('Cod_Aut', $cli['cod_aut'])->value('Cod_Aut');
            if ($codAut) return $codAut;
        }
        foreach (['ci', 'nit'] as $campo) {
            $ci = trim((string) ($cli[$campo] ?? ''));
            if ($ci === '') continue;
            $codAut = DB::table('tbclientes')->whereRaw('TRIM(Id) = ?', [$ci])->value('Cod_Aut');
            if ($codAut) return $codAut;
        }
        return null;
    }
}
