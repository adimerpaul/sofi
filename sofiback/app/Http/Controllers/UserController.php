<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    public function login(Request $request){
//        if (!Auth::attempt($request->all())){
//            return response()->json(['res'=>'No existe el usuario'],400);
//        }
//        if (User::where('email',$request->email)->whereDate('fechalimite','>',now())->get()->count()==0){
//            return response()->json(['res'=>'Su usuario sobre paso el limite de ingreso'],400);
//        }

//        $user =User::where('TRIM(ci)',$request->ci)->where('TRIM(pasw)',$request->pasw)->get();
        $user=DB::select("SELECT * FROM `personal` WHERE TRIM(ci)='".$request->ci."' AND TRIM(pasw)='".$request->pasw."'");
//        return sizeof($user);
        if (sizeof($user)==1){
            $user=User::where('ci',$request->ci)
//            ->with('unid')
//            ->with('permisos')
                ->firstOrFail();
            $token=$user->createToken('auth_token')->plainTextToken;
            return response()->json(['token'=>$token,'user'=>$user],200);
        }else{
            return response()->json(['res'=>'Usuario no encontrado'],400);
        }

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
        return $user;

//        return User::where('id',1)->with('unid')->get();
    }
}
