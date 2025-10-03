<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClientePhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

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

        // 4) Image manager (GD por defecto; usa Imagick si lo prefieres)
        $manager = new ImageManager(new Driver());

        // 5) Guardar cada imagen comprimida a JPG
        $saved = [];
        foreach ($files as $file) {
            // leer
            $image = $manager->read($file->getPathname());

            // reducir sin sobre-escalar (máximo 1600x1600)
            $image->resizeDown(width: 1600, height: 1600);

            // nombre único y ruta
            $filename = Str::uuid()->toString() . '.jpg';
            $fullPath = $destDir . DIRECTORY_SEPARATOR . $filename;

            // guardar JPEG calidad 85
            $image->toJpeg(quality: 85)->save($fullPath);

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
