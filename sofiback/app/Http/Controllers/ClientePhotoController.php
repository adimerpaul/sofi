<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClientePhoto;
use Illuminate\Http\Request;
use App\Services\FotoWebp;
use Illuminate\Support\Str;

class ClientePhotoController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = $request->query('cliente_id');

        if ($clienteId) {
            $fotos = ClientePhoto::where('cliente_id', (int)$clienteId)
                ->latest('id')
                ->get(['id', 'photo_path', 'created_at']);

            $baseUrl = url('/');
            return $fotos->map(function ($p) use ($baseUrl) {
                return [
                    'id'         => $p->id,
                    'photo_path' => $p->photo_path,
                    'url'        => $baseUrl . '/' . ltrim($p->photo_path, '/'),
                    'created_at' => $p->created_at,
                ];
            });
        }

        $search = trim((string)$request->input('search', ''));
        $q = Cliente::query();
        if ($search !== '') {
            $q->where(function ($s) use ($search) {
                $s->where('Nombres', 'like', "%{$search}%")
                    ->orWhere('Cod_Aut', 'like', "%{$search}%")
                    ->orWhere('Id', 'like', "%{$search}%");
            });
        }

        return $q->orderBy('Nombres')->paginate(10);
    }

    public function store(Request $request)
    {
        // 1) Datos básicos
        $clienteId = (int) $request->input('cliente_id');

        // 2) Reunir archivos: admite 'photo' (uno) o 'photos[]' (múltiples)
        $files = [];
        if ($request->hasFile('photos')) {
            foreach ((array) $request->file('photos') as $f) {
                if ($f && $f->isValid()) {
                    $files[] = $f;
                }
            }
        }
        if ($request->hasFile('photo')) {
            $f = $request->file('photo');
            if ($f && $f->isValid()) {
                $files[] = $f;
            }
        }

        // Si no hay archivos, corta simple (sin validación formal)
        if (empty($files)) {
            return response()->json(['message' => 'No se enviaron imágenes'], 400);
        }

        // 3) Carpeta destino en /public/cliente_photos/{cliente_id}
        $destDir = public_path("cliente_photos/{$clienteId}");
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }

        // 4) Guardar cada imagen en WebP comprimido (maximo 1600px, sin deformar)
        $saved = [];
        foreach ($files as $file) {
            $filename = Str::uuid()->toString() . '.webp';
            $fullPath = $destDir . DIRECTORY_SEPARATOR . $filename;
            FotoWebp::guardar($file->getPathname(), $fullPath);

            // ruta relativa para servir desde /public
            $relativePath = "cliente_photos/{$clienteId}/{$filename}";

            // guardar en DB
            $photo = ClientePhoto::create([
                'cliente_id' => $clienteId,
                'photo_path' => $relativePath,
            ]);

            // armar respuesta
            $saved[] = [
                'id'  => $photo->id,
                'url' => url($relativePath),
            ];
        }

        return response()->json(['uploaded' => $saved], 201);
    }

    public function destroy($id)
    {
        $photo = ClientePhoto::find($id);
        if (!$photo) {
            return response()->json(['message' => 'Foto no encontrada'], 404);
        }

        $fullPath = public_path($photo->photo_path);
        if (is_file($fullPath)) @unlink($fullPath);

        $photo->delete();
        return response()->json(['message' => 'Eliminado']);
    }
}
