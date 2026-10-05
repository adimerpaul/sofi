<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller{

    public function index()
    {
        //
        return DB::SELECT('SELECT CodAut,ci,Nombre1,Nombre2,App1,Apm,TRIM(placa) placa from personal');
    }
    public function users(){
        $sql='
        SELECT CodAut id,
            TRIM(CONCAT_WS(" ", NULLIF(TRIM(Nombre1), ""), NULLIF(TRIM(Nombre2), ""), NULLIF(TRIM(App1), ""), NULLIF(TRIM(Apm), ""))) nombre
        FROM personal';
        return DB::select($sql);
    }
    public function permisosList(){
        return response()->json([
            'permisos'=>Permission::orderBy('name')->pluck('name'),
            'roles'=>Role::with('permissions')->orderBy('name')->get()->map(function($rol){
                return ['name'=>$rol->name,'permisos'=>$rol->permissions->pluck('name')];
            }),
            // Camiones para asignar al usuario: los de vehiculo y los que ya
            // tiene algun usuario (no siempre coinciden letra por letra).
            'placas'=>DB::table('vehiculo')->select(DB::raw('TRIM(placa) placa'))
                ->union(DB::table('personal')->select(DB::raw('TRIM(placa) placa')))
                ->get()->pluck('placa')
                ->filter(function($p){ return $p!==null && $p!==''; })
                ->unique()->sort()->values(),
        ]);
    }
    public function usuarioPermisos($id){
        $user=User::findOrFail($id);
        return response()->json([
            'user'=>['CodAut'=>$user->CodAut,'ci'=>trim($user->ci),'nombre'=>trim($user->Nombre1.' '.$user->App1)],
            'roles'=>$user->getRoleNames(),
            'permisos'=>$user->getDirectPermissions()->pluck('name'),
            'efectivos'=>$user->getAllPermissions()->pluck('name'),
        ]);
    }
    public function updateUsuarioPermisos(Request $request,$id){
        $user=User::findOrFail($id);
        $user->syncRoles($request->roles ?? []);
        $user->syncPermissions($request->permisos ?? []);
        $user=User::findOrFail($id);
        return response()->json([
            'roles'=>$user->getRoleNames(),
            'permisos'=>$user->getDirectPermissions()->pluck('name'),
            'efectivos'=>$user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function login(Request $request){
//        if (!Auth::attempt($request->all())){
//            return response()->json(['res'=>'No existe el usuario'],400);
//        }
//        if (User::where('email',$request->email)->whereDate('fechalimite','>',now())->get()->count()==0){
//            return response()->json(['res'=>'Su usuario sobre paso el limite de ingreso'],400);
//        }

        $ci=trim($request->ci ?? '');
        $pasw=trim($request->pasw ?? '');

        if ($ci==='' || $pasw===''){
            return response()->json(['res'=>'Debes ingresar tu carnet de identidad y tu contraseña'],400);
        }

        // Se busca primero solo por CI para poder diferenciar
        // "no existe el usuario" de "contraseña incorrecta".
        $porCi=DB::select("SELECT * FROM `personal` WHERE TRIM(ci)=?",[$ci]);
        if (sizeof($porCi)==0){
            return response()->json(['res'=>'No existe un usuario con el carnet '.$ci],400);
        }

        $user=DB::select("SELECT * FROM `personal` WHERE TRIM(ci)=? AND TRIM(pasw)=?",[$ci,$pasw]);
        if (sizeof($user)==0){
            return response()->json(['res'=>'La contraseña es incorrecta'],400);
        }
        if (sizeof($user)>1){
            return response()->json(['res'=>'El carnet '.$ci.' está registrado más de una vez, avisa al administrador'],400);
        }

        $user=User::whereRaw('TRIM(ci)=?',[$ci])
//            ->with('unid')
//            ->with('permisos')
            ->first();
        if ($user==null){
            return response()->json(['res'=>'El usuario no está habilitado en el sistema, avisa al administrador'],400);
        }
        $token=$user->createToken('auth_token')->plainTextToken;
        $user->permisos=$user->getAllPermissions()->pluck('name');
        return response()->json(['token'=>$token,'user'=>$user],200);

//        $validar= DB::SELECT("SELECT * from personal where TRIM(ci)='$request->ci' and TRIM(pasw) ='$request->pass' ");
//        if(sizeof($validar)==1)
//            echo 'Correcto';
//        else
//            echo 'No existe';
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */


    public function store(Request $request)
    {
        $datos=$this->validarUsuario($request,null);
        $user=new User();
        $user->timestamps=false; // personal no tiene created_at/updated_at
        $user->forceFill(array_merge($datos,[
            // Columnas legadas NOT NULL sin default: van con lo mismo que
            // tienen los usuarios existentes.
            'Fech_naci'=>$datos['Fech_naci'] ?? now()->toDateString(),
            'cod_Prof'=>0,
            'correo'=>'',
            'Salario'=>0,
            'direccion'=>$datos['direccion'] ?? '',
            'cod_car'=>1,
            'Nro'=>0,
            'NroAlm'=>0,
            'AccesoEmp'=>0,
        ]));
        $user->save();
        return response()->json($this->datosUsuario($user),201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return $this->datosUsuario(User::findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user=User::findOrFail($id);
        $datos=$this->validarUsuario($request,$user);
        // Contraseña vacia al editar = se mantiene la actual.
        if (($datos['pasw'] ?? '')==='') unset($datos['pasw']);
        if (!array_key_exists('direccion',$datos) || $datos['direccion']===null) unset($datos['direccion']);
        if (!array_key_exists('Fech_naci',$datos) || $datos['Fech_naci']===null) unset($datos['Fech_naci']);
        $user->timestamps=false; // personal no tiene created_at/updated_at
        $user->forceFill($datos)->save();
        return $this->datosUsuario($user->fresh());
    }

    private function validarUsuario(Request $request,$user){
        foreach (['ci','pasw','Nombre1','Nombre2','App1','Apm','direccion','placa'] as $campo){
            if ($request->has($campo)) $request->merge([$campo=>trim((string)$request->input($campo))]);
        }
        $request->validate([
            'ci'=>'required|max:15',
            'pasw'=>($user ? 'nullable' : 'required').'|max:15',
            'Nombre1'=>'required|max:15',
            'Nombre2'=>'nullable|max:15',
            'App1'=>'required|max:20',
            'Apm'=>'nullable|max:20',
            'Fech_naci'=>'nullable|date',
            'direccion'=>'nullable|max:250',
            'placa'=>'nullable|max:100',
        ],[],[
            'ci'=>'carnet','pasw'=>'contraseña','Nombre1'=>'nombre','Nombre2'=>'segundo nombre',
            'App1'=>'apellido paterno','Apm'=>'apellido materno','Fech_naci'=>'fecha de nacimiento',
        ]);
        // El login busca por TRIM(ci): no puede haber dos con el mismo carnet.
        $repetido=DB::table('personal')->whereRaw('TRIM(ci)=?',[$request->ci]);
        if ($user) $repetido->where('CodAut','<>',$user->CodAut);
        if ($repetido->exists()){
            abort(response()->json(['message'=>'El carnet '.$request->ci.' ya está registrado','errors'=>['ci'=>['El carnet ya está registrado']]],422));
        }
        return [
            'ci'=>$request->ci,
            'pasw'=>$request->pasw,
            'Nombre1'=>$request->Nombre1,
            'Nombre2'=>$request->Nombre2 ?? '',
            'App1'=>$request->App1,
            'Apm'=>$request->Apm ?? '',
            'Fech_naci'=>$request->Fech_naci ?: null,
            'direccion'=>$request->direccion,
            'placa'=>($request->placa ?? '')==='' ? null : $request->placa,
        ];
    }

    private function datosUsuario($user){
        return [
            'CodAut'=>$user->CodAut,
            'ci'=>trim($user->ci),
            'Nombre1'=>trim($user->Nombre1),
            'Nombre2'=>trim($user->Nombre2),
            'App1'=>trim($user->App1),
            'Apm'=>trim($user->Apm),
            'Fech_naci'=>$user->Fech_naci ? substr($user->Fech_naci,0,10) : null,
            'direccion'=>trim($user->direccion),
            'placa'=>$user->placa!==null ? trim($user->placa) : null,
        ];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();
        return response()->json(['res'=>'salido exitosamente'],200);
    }
    public function me(Request $request){
//        $user=$request->user()->with('unid')->with('permisos')->firstOrFail();
//        $user=$request->user()
        $user=User::where('CodAut',$request->user()->CodAut)
//            ->with('unid')
//            ->with('permisos')
            ->firstOrFail();
        $user->permisos=$user->getAllPermissions()->pluck('name');
        return $user;

//        return User::where('id',1)->with('unid')->get();
    }
}
