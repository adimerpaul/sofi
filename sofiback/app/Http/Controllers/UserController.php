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
        return DB::SELECT('SELECT CodAut,ci,Nombre1,Nombre2,App1,Apm from personal');
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
        //
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
