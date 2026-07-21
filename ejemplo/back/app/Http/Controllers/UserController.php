<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Spatie\Permission\Models\Permission;

class UserController extends Controller{
    function usuariosRole(){
        $psicologos = User::where('role', 'Psicologo')->get();
        $abogados = User::where('role', 'Abogado')->get();
        $sociales = User::where('role', 'Social')->get();
        return response()->json([
            'psicologos' => $psicologos,
            'abogados' => $abogados,
            'sociales' => $sociales,
        ]);
    }
    public function updateAvatar(Request $request, $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $path = public_path('images/' . $filename);

            // Crear instancia del gestor de imágenes
            $manager = new ImageManager(new Driver()); // O new Imagick\Driver()

            // Redimensionar y comprimir
            $manager->read($file->getPathname())
                ->resize(300, 300) // o no pongas resize si no quieres cambiar tamaño
                ->toJpeg(70)       // calidad 70%
                ->save($path);

            $user->avatar = $filename;
            $user->save();

            return response()->json(['message' => 'Avatar actualizado', 'avatar' => $filename]);
        }

        return response()->json(['message' => 'No se ha enviado un archivo'], 400);
    }
    function login(Request $request){
        $credentials = $request->only('username', 'password');
        $user = User::where('username', $credentials['username'])->with('permissions:id,name')->first();
        if (!$user || !password_verify($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Usuario o contraseña incorrectos',
            ], 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }
    function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'Token eliminado',
        ]);
    }
    function me(Request $request){
        $user = $request->user();
        $user->load('permissions:id,name');
//        return response()->json($user);
        return [
            'user' => User::find($user->id),
            'permissions' => $user->permissions,
            'datos' => [
                'nit' => env('NIT'),
                'razon' => env("RAZON"),
                'direccion' => env("DIRECCION"),
                'telefono' => env("TELEFONO"),
                'url' => env("URL_SIAT"),
                'url2' => env("URL_SIAT2")
            ]
        ];
    }
    function index(){
        return User::where('id', '!=', 0)
            ->with('permissions:id,name')
            ->orderBy('id', 'desc')
            ->get();
    }
    function update(Request $request, $id){
        $user = User::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255|unique:users,username,' . $user->id,
            'role' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email|max:255|unique:users,email,' . $user->id,
            'celular' => 'sometimes|nullable|string|max:100',
            'es_camion' => 'sometimes|boolean',
            'cod_aut' => 'sometimes|nullable|integer',
            'fecha_nacimiento' => 'sometimes|nullable|date',
            'ci' => 'sometimes|nullable|string|max:15',
            'cod_prof' => 'sometimes|nullable|integer',
            'app1' => 'sometimes|nullable|string|max:20',
            'app2' => 'sometimes|nullable|string|max:20',
            'nombre1' => 'sometimes|nullable|string|max:15',
            'nombre2' => 'sometimes|nullable|string|max:15',
            'salario' => 'sometimes|nullable|numeric',
            'direccion' => 'sometimes|nullable|string|max:250',
            'cod_car' => 'sometimes|nullable|integer',
            'nro' => 'sometimes|nullable|integer',
            'nro_alm' => 'sometimes|nullable|integer',
            'acceso_emp' => 'sometimes|nullable|boolean',
            'placa' => 'sometimes|nullable|string|max:100',
        ]);
        $user->update($data);
        error_log('User' . json_encode($user));
        return $user;
    }
    function updatePassword(Request $request, $id){
        $user = User::findOrFail($id);
        $request->validate([
            'password' => 'required|string|min:1',
        ]);
        $user->update([
            'password' => $request->password,
            'clave' => $request->password,
        ]);
        return $user;
    }
    function store(Request $request){
        $request->validate([
            'username' => 'required|unique:users',
            'password' => 'required',
            'name' => 'required',
            'role' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email',
            'celular' => 'nullable|string|max:100',
            'es_camion' => 'nullable|boolean',
            'cod_aut' => 'nullable|integer',
            'fecha_nacimiento' => 'nullable|date',
            'ci' => 'nullable|string|max:15',
            'cod_prof' => 'nullable|integer',
            'app1' => 'nullable|string|max:20',
            'app2' => 'nullable|string|max:20',
            'nombre1' => 'nullable|string|max:15',
            'nombre2' => 'nullable|string|max:15',
            'salario' => 'nullable|numeric',
            'direccion' => 'nullable|string|max:250',
            'cod_car' => 'nullable|integer',
            'nro' => 'nullable|integer',
            'nro_alm' => 'nullable|integer',
            'acceso_emp' => 'nullable|boolean',
            'placa' => 'nullable|string|max:100',
//            'email' => 'required|email|unique:users',
        ]);
        $user = User::create([
            'username' => $request->username,
            'password' => $request->password,
            'clave' => $request->password,
            'name' => $request->name,
            'role' => $request->role ?? 'Usuario',
            'email' => $request->email,
            'celular' => $request->celular,
            'es_camion' => (bool) ($request->es_camion ?? false),
            'cod_aut' => $request->cod_aut,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'ci' => $request->ci,
            'cod_prof' => $request->cod_prof,
            'app1' => $request->app1,
            'app2' => $request->app2,
            'nombre1' => $request->nombre1,
            'nombre2' => $request->nombre2,
            'salario' => $request->salario,
            'direccion' => $request->direccion,
            'cod_car' => $request->cod_car,
            'nro' => $request->nro,
            'nro_alm' => $request->nro_alm,
            'acceso_emp' => $request->acceso_emp,
            'placa' => $request->placa,
        ]);
        return $user;
    }
    function destroy($id){
        return User::destroy($id);
    }
    public function getPermissions($userId)
    {
        $user = User::findOrFail($userId);
        // devuelve IDs de permisos del usuario
        return $user->permissions()->pluck('id');
    }

    public function syncPermissions(Request $request, $userId)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $user = User::findOrFail($userId);
        $perms = Permission::whereIn('id', $request->permissions ?? [])->get();
        $user->syncPermissions($perms);

        return response()->json([
            'message' => 'Permisos actualizados',
            'permissions' => $user->permissions()->pluck('name'),
        ]);
    }
}
