<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string)$request->input('search', ''));
        $perPage = (int)$request->input('per_page', 10);
        $soloMios = (bool)$request->input('solo_mios', false);
        $soloDia = (bool)$request->input('solo_dia', false);
        $ciVend = trim((string)$request->input('ci_vend', ''));
        $zona = trim((string)$request->input('zona', ''));
        $ventaEstado = trim((string)$request->input('venta_estado', ''));
        $dayName = strtolower((string)$request->input('dia', Carbon::now()->locale('es')->isoFormat('ddd')));
        $dayMap = [
            'lu' => 'lu', 'lun' => 'lu', 'lunes' => 'lu',
            'ma' => 'ma', 'mar' => 'ma', 'martes' => 'ma',
            'mi' => 'mi', 'mie' => 'mi', 'miercoles' => 'mi', 'miércoles' => 'mi',
            'ju' => 'ju', 'jue' => 'ju', 'jueves' => 'ju',
            'vi' => 'vi', 'vie' => 'vi', 'viernes' => 'vi',
            'sa' => 'sa', 'sab' => 'sa', 'sabado' => 'sa', 'sábado' => 'sa',
            'do' => 'do', 'dom' => 'do', 'domingo' => 'do',
        ];
        $dayField = $dayMap[$dayName] ?? null;
        $user = $request->user();

        return Cliente::query()
            ->with(['vendedorUser:id,name,username,avatar'])
            ->when($soloMios && $user, function ($q) use ($user) {
                $q->where('ci_vend', $user->username);
            })
            ->when($soloDia && $dayField, function ($q) use ($dayField) {
                $q->where($dayField, true);
            })
            ->when($ciVend !== '', function ($q) use ($ciVend) {
                $q->where('ci_vend', $ciVend);
            })
            ->when($zona !== '', function ($q) use ($zona) {
                $q->where('zona', $zona);
            })
            ->when($ventaEstado !== '', function ($q) use ($ventaEstado) {
                $q->where('venta_estado', $ventaEstado);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('nombre', 'like', "%{$search}%")
                        ->orWhere('nit', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                        ->orWhere('telefono', 'like', "%{$search}%")
                        ->orWhere('codcli', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function zonas()
    {
        return Cliente::query()
            ->selectRaw('zona, COUNT(*) as total')
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->groupBy('zona')
            ->orderBy('zona')
            ->get();
    }

    public function store(Request $request)
    {
        // verificar si existe alguno ci y complemento igual para evitar duplicados
        $ci = trim((string)$request->input('ci', ''));
        $complemento = trim((string)$request->input('complemento', ''));
        if ($ci !== '') {
            $exists = Cliente::query()
                ->where('ci', $ci)
                ->where(function ($q) use ($complemento) {
                    if ($complemento !== '') {
                        $q->where('complemento', $complemento);
                    } else {
                        $q->whereNull('complemento')->orWhere('complemento', '');
                    }
                })
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'Ya existe un cliente con el mismo CI y complemento'], 422);
            }
        }
        //si el campo nit no esta vacio verificar si sxiste alguno con el mismo nit para evitar duplicados
        $nit = trim((string)$request->input('nit', ''));
        if  ($nit !== '') {
            $exists = Cliente::query()
                ->where('nit', $nit)
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'Ya existe un cliente con el mismo NIT'], 422);
            }
        }
        $data = $this->validateData($request);
        $payload = $this->preparePayload($request, $data, null);

        $cliente = Cliente::create($payload);

        return response()->json($cliente->fresh('vendedorUser:id,name,username,avatar'), 201);
    }

    public function show(Cliente $cliente)
    {
        return $cliente->load('vendedorUser:id,name,username,avatar');
    }

    public function update(Request $request, Cliente $cliente)
    {
        // verificar si existe alguno ci y complemento igual para evitar duplicados sin considerar el cliente actual
        $ci = trim((string)$request->input('ci', ''));
        $complemento = trim((string)$request->input('complemento', ''));
        if ($ci !== '') {
            $exists = Cliente::query()
                ->where('id', '!=', $cliente->id)
                ->where('ci', $ci)
                ->where(function ($q) use ($complemento) {
                    if ($complemento !== '') {
                        $q->where('complemento', $complemento);
                    } else {
                        $q->whereNull('complemento')->orWhere('complemento', '');
                    }
                })
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'Ya existe otro cliente con el mismo CI y complemento'], 422);
            }
        }
        //si el campo nit no esta vacio verificar si sxiste alguno con el mismo nit para evitar duplicados sin considerar el cliente actual
        $nit = trim((string)$request->input('nit', ''));
        if  ($nit !== '') {
            $exists = Cliente::query()
                ->where('id', '!=', $cliente->id)
                ->where('nit', $nit)
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'Ya existe otro cliente con el mismo NIT'], 422);
            }
        }
        $data = $this->validateData($request, true);
        $payload = $this->preparePayload($request, $data, $cliente);
        // Nunca modificar el campo user en update
        unset($payload['username']);

        $cliente->update($payload);

        return $cliente->fresh('vendedorUser:id,name,username,avatar');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return response()->json(['success' => true]);
    }

    public function searchCliente(Request $request)
    {
        $nit = trim((string) $request->input('nit', ''));
        if ($nit === '') {
            return null;
        }

        return Cliente::query()
            ->where('nit', $nit)
            ->orWhere('ci', $nit)
            ->orWhere('id_externo', $nit)
            ->first();
    }

    private function validateData(Request $request, bool $isUpdate = false): array
    {
        // Todos estos campos son opcionales y de texto.
        // Si llegan como numero se convierten a string; si llegan vacios se guardan como null.
        $optionalTextFields = [
            'nit', 'ci', 'telefono', 'direccion', 'complemento', 'codigoTipoDocumentoIdentidad',
            'id_externo', 'cod_ciudad', 'cod_nacio', 'est_civ', 'edad', 'empresa', 'ci_vend',
            'motivo_list_black', 'tipo_paciente', 'supra_canal', 'canal', 'subcanal', 'zona',
            'transporte', 'territorio', 'clinew', 'venta_estado', 'complto', 'correcli',
            'profecion', 'sexo', 'tarjeta',
        ];

        $normalized = [];
        foreach ($optionalTextFields as $field) {
            if (!$request->has($field)) {
                continue;
            }
            $value = $request->input($field);
            if ($value === null) {
                $normalized[$field] = null;
                continue;
            }
            if (is_string($value)) {
                $trimmed = trim($value);
                $normalized[$field] = $trimmed === '' ? null : $trimmed;
                continue;
            }
            $normalized[$field] = (string) $value;
        }

        if (!empty($normalized)) {
            $request->merge($normalized);
        }

        $presence = $isUpdate ? 'sometimes' : 'nullable';
        $optionalString = fn (int $max) => [$presence, 'nullable', 'string', "max:$max"];
        $optionalInt = [$presence, 'nullable', 'integer'];
        $optionalBool = [$presence, 'nullable', 'boolean'];
        $optionalNumber = [$presence, 'nullable', 'numeric'];
        // ci no se repita
        $rules = [
            'nombre' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:150'],
            'nit' => $optionalString(50),
            'ci' => $optionalString(50),
            'telefono' => $optionalString(100),
            'direccion' => $optionalString(255),
            'complemento' => $optionalString(20),
            'codigoTipoDocumentoIdentidad' => $optionalString(20),
            'email' => [$presence, 'nullable', 'email', 'max:255'],

            'id_externo' => $optionalString(15),
            'cod_ciudad' => $optionalString(4),
            'cod_nacio' => $optionalString(4),
            'cod_car' => $optionalInt,
            'est_civ' => $optionalString(50),
            'edad' => $optionalString(3),
            'empresa' => $optionalString(150),
            'categoria' => $optionalInt,
            'imp_pieza' => [...$optionalNumber, 'min:0'],
            'ci_vend' => $optionalString(15),
            'list_blanck' => $optionalBool,
            'motivo_list_black' => $optionalString(90),
            'list_black' => $optionalBool,
            'tipo_paciente' => $optionalString(90),
            'supra_canal' => $optionalString(5),
            'canal' => $optionalString(80),
            'subcanal' => $optionalString(20),
            'zona' => $optionalString(20),
            'latitud' => [...$optionalNumber, 'between:-90,90'],
            'longitud' => [...$optionalNumber, 'between:-180,180'],
            'transporte' => $optionalString(60),
            'territorio' => $optionalString(10),
            'codcli' => $optionalInt,
            'clinew' => $optionalString(3),
            'venta_estado' => $optionalString(100),
            'complto' => $optionalString(5),
            'tipodocu' => $optionalInt,
            'lu' => $optionalBool,
            'ma' => $optionalBool,
            'mi' => $optionalBool,
            'ju' => $optionalBool,
            'vi' => $optionalBool,
            'sa' => $optionalBool,
            'do' => $optionalBool,
            'correcli' => $optionalString(50),
            'canmayni' => $optionalBool,
            'baja' => $optionalBool,
            'profecion' => $optionalString(60),
            'waths' => $optionalBool,
            'ctas_activo' => $optionalBool,
            'ctas_mont' => [...$optionalNumber, 'min:0'],
            'ctas_dias' => $optionalInt,
            'sexo' => $optionalString(20),
            'noesempre' => $optionalBool,
            'tarjeta' => $optionalString(20),
            'puede_credito' => $optionalBool,

            'fotos' => [$presence, 'array', 'max:3'],
            'fotos.*' => ['image', 'max:20120'],
            'remove_fotos' => [$presence, 'array'],
            'remove_fotos.*' => ['string'],
        ];

        return $request->validate($rules);
    }

    private function preparePayload(Request $request, array $data, ?Cliente $cliente): array
    {
        $existing = $cliente?->fotos ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }

        $remove = $data['remove_fotos'] ?? [];
        if (!empty($remove)) {
            $existing = array_values(array_filter($existing, function ($path) use ($remove) {
                return !in_array($path, $remove, true);
            }));

            foreach ($remove as $path) {
                if (is_string($path) && str_starts_with($path, 'uploads/clientes/')) {
                    $full = public_path($path);
                    if (File::exists($full)) {
                        File::delete($full);
                    }
                }
            }
        }

        if ($request->hasFile('fotos')) {
            File::ensureDirectoryExists(public_path('uploads/clientes'));
            foreach ($request->file('fotos') as $file) {
                if (count($existing) >= 3) {
                    break;
                }
                $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                $name = uniqid('cli_', true) . '.' . $ext;
                $file->move(public_path('uploads/clientes'), $name);
                $existing[] = 'uploads/clientes/' . $name;
            }
        }

        $payload = $data;
        unset($payload['fotos'], $payload['remove_fotos']);
        $payload['fotos'] = array_values($existing);

        // Keep CI/NIT synchronized when only one of them is sent.
        if (empty($payload['nit']) && !empty($payload['ci'])) {
            $payload['nit'] = $payload['ci'];
        }
        if (empty($payload['ci']) && !empty($payload['nit'])) {
            $payload['ci'] = $payload['nit'];
        }

        if (!isset($payload['venta_estado']) || empty($payload['venta_estado'])) {
            $payload['venta_estado'] = 'ACTIVO';
        }

        return $payload;
    }
}
