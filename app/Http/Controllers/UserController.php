<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use DB;

class UserController extends Controller {
    //
    public function inicioSesion($nroDoc, $pass){
        $resp["success"]= false;
        //Validaos por numero de documento, email y usuario
        $usuario = User::select(
                "users.id"
                ,"users.nro_documento"
                ,"users.usuario"
                ,"users.password"
                ,"users.nombre"
                ,"users.nombre1"
                ,"users.nombre2"
                ,"users.apellido1"
                ,"users.apellido2"
                ,"users.foto"
                ,"users.email"
                ,"users.telefono"
                ,"users.fk_municipio"
                ,"users.fk_perfil"
                ,"m.name AS ciudad_nombre"
                ,"d.id AS dep_id"
                ,"d.name AS dep_nombre"
                ,"p.id AS pais_id"
                ,"p.name AS pais_nombre"
            )->join("municipios AS m", "users.fk_municipio", "m.id")
            ->join("departamentos AS d", "m.state_id", "d.id")
            ->join("paises AS p", "m.country_id", "p.id")
            ->where(function($query) use ($nroDoc) {
                $query->orWhere('nro_documento', $nroDoc)
                    ->orWhere('email', $nroDoc)
                    ->orWhere('usuario', $nroDoc);
            })->first();

        if (is_object($usuario)){
            if(Hash::check($pass, $usuario->password)){
                $usuario->nombreCompleto = $usuario->nombre;

                $resp['success'] = true;
                $resp['menu'] = $this->permisos($usuario->id, $usuario->fk_perfil, true);
                $resp['permisos'] = $this->permisos($usuario->id, $usuario->fk_perfil, true, null, false);
                $resp['msj'] = $usuario;
            }else{
                $resp["msj"] = 'Contraseña incorrecta';
            }
        }else {
            $resp["msj"] = 'Usuario no existe';
        }

        return $resp;
    } 

    public function crear(Request $request){
        $resp["success"] = false;
        $datos = json_decode($request->datos);
        $validar = User::where('nro_documento', $datos->nro_documento)->get();
        
        if($validar->isEmpty()){
            $validar = User::where('usuario', $datos->usuario)->get();
            if($validar->isEmpty()){
                $validar = User::where('email', $datos->email)->get();
                if($validar->isEmpty()){

                    $usuario = new User;
                    $usuario->nro_documento = $datos->nro_documento;
                    $usuario->usuario = $datos->usuario;
                    $usuario->password = Hash::make($datos->nro_documento, ['rounds' => 15]);
                    $usuario->nombre = $datos->nombre1 . ' ' . (strlen($datos->nombre2) > 0 ? $datos->nombre2 . ' ' : '') . $datos->apellido1 . (strlen($datos->apellido2) > 0 ? ' ' . $datos->apellido2 : '');
                    $usuario->nombre1 = $datos->nombre1;
                    $usuario->nombre2 = $datos->nombre2;
                    $usuario->apellido1 = $datos->apellido1;
                    $usuario->apellido2 = $datos->apellido2;
                    $usuario->email = $datos->email;
                    $usuario->telefono = $datos->telefono;
                    $usuario->estado = $datos->estado;
                    $usuario->fk_municipio = $datos->fk_municipio;
                    $usuario->fk_perfil = $datos->fk_perfil == "null" ? null : $datos->fk_perfil;

                    DB::beginTransaction();

                    if( $usuario->save() ){
                        $rutaFotoPerfil = "foto";
                        if(isset($datos->fotoPerfil)){
                            try {
                                $rutaFotoPerfil = Storage::putFileAs('public/fotosPerfil/', $request->fotoPerfil, $usuario->id . "." . $request->file('fotoPerfil')->getClientOriginalExtension());

                                $usuario->foto = $usuario->id . "." . $request->file('fotoPerfil')->getClientOriginalExtension();

                                $usuario->save();

                            } catch (\Exception $e) {
                                DB::rollback();
                                $rutaFotoPerfil = 0;
                                $resp["msj"] = "Error al subir la foto de perfil.";
                            }
                        }

                        if ($rutaFotoPerfil !== 0){
                            DB::commit();
                            $resp["success"] = true;
                            $resp["msj"] = "Se ha creado el usuario";
                            $resp["id"]= $usuario->id;
                        } else {
                            $resp["msj"] = "Error al crear el usuario";
                            DB::rollback();
                        }

                    }else{
                        DB::rollback();
                        $resp["msj"] = "No se ha creado el usuario";
                    }
                } else {
                    $resp["msj"] = "El correo " . $datos->email . " ya se encuentra registrado";
                }
            } else {
                $resp["msj"] = "El usuario " . $datos->usuario . " ya se encuentra registrado";
            }
        }else{
            $resp["msj"] = "El número de documento " . $datos->documento . " ya se encuentra registrado";
        }
    
        return $resp;
    }

    public function setFoto(Request $request){
        $resp["success"] = false;

        $user = User::find($request->id);
        if (is_object($user)){
            
            $user->foto = $request->fotoUrl;
           
            if( $user->save() ){
                $resp["success"] = true;
                $resp["msj"] = "Foto Actualizada";
            }else{
                $resp["msj"] = "No se actualizado foto";
            }
               
        }else{
            $resp["msj"] = "El usuario no se encuentra";
        }
    
        return $resp;
    }

    public function obtener(Request $request){
        $query = User::select(
            "users.id"
            ,"users.nro_documento"
            ,"users.usuario"
            ,"users.password"
            ,"users.nombre1"
            ,"users.nombre2"
            ,"users.apellido1"
            ,"users.apellido2"
            ,"users.foto"
            ,"users.email"
            ,"users.telefono"
            ,"users.created_at"
            ,"users.estado"
            ,"users.fk_municipio"
            ,"users.fk_perfil"
            ,"m.name AS ciudad_nombre"
            ,"d.id AS dep_id"
            ,"d.name AS dep_nombre"
            ,"p.id AS pais_id"
            ,"p.name AS pais_nombre"
            ,"per.nombre AS perfil_nombre"
        )->join("municipios AS m", "users.fk_municipio", "m.id")
        ->join("departamentos AS d", "m.state_id", "d.id")
        ->join("paises AS p", "m.country_id", "p.id")
        ->leftjoin("perfiles AS per", "users.fk_perfil", "per.id")
        ->where([
            ["m.flag", 1]
            ,["d.flag", 1]
            ,["p.flag", 1]
        ]);

        if (isset($request->estado)) {
            $query = $query->where("users.estado", $request->estado);
        }

        if (isset($request->paises)) {
            $query = $query->whereIn("p.id", $request->paises);
        }

        if (isset($request->departamentos)) {
            $query = $query->whereIn("d.id", $request->departamentos);
        }

        if (isset($request->ciudades)) {
            $query = $query->whereIn("m.id", $request->ciudades);
        }
        
        return datatables()->eloquent($query)->toJson();
    }

    public function cambiarEstado(Request $request){
        $resp["success"] = false;
        $user = User::find($request->id);
        
        if (is_object($user)){
            $user->estado = $request->estado;
        
            if ($user->save()) {
                $resp["success"] = true;
                $resp["msj"] = "El usuario " . $user->usuario . " se ha " . ($request->estado == 1 ? 'habilitado' : 'deshabilitado') . " correctamente.";
                DB::commit();
            } else {
                DB::rollBack();
                $resp["msj"] = "No se han guardado cambios";
            }
        } else {
            $resp["msj"] = "No se ha encontrado el usuario";
        }
        return $resp; 
    }

    public function eliminar(Request $request){
        $resp["success"] = false;
        $usuario = User::find($request->id);
        
        if(!is_null($usuario)){    
          if ($usuario->delete()) {
            $resp["success"] = true;
            $resp["msj"] = "Se ha eliminado el usuario";
          } else {
            $resp["msj"] = "No se ha eliminado el usuario";
          }
        } else {
          $resp["msj"] = "No se ha encontrado el usuario";
        }
        return $resp; 
    }

    public function editar(Request $request){
        $resp["success"] = false;
        $datos = json_decode($request->datos);
        $validar = User::where([
                  ['id', '<>', $datos->id],
                  ['nro_documento', $datos->nro_documento]
                ])->get();
        
        if ($validar->isEmpty()) {
            $validar = User::where([
                ['id', '<>', $datos->id],
                ['usuario', $datos->usuario]
            ])->get();
            if ($validar->isEmpty()) {
                $validar = User::where([
                    ['id', '<>', $datos->id],
                    ['email', $datos->email]
                ])->get();
                if ($validar->isEmpty()) {
                    $usuario = User::find($datos->id);
                    if(!empty($usuario)){
                        if (
                            $usuario->nro_documento != $datos->nro_documento ||
                            $usuario->usuario != $datos->usuario ||
                            $usuario->nombre1 != $datos->nombre1 ||
                            $usuario->nombre2 != $datos->nombre2 ||
                            $usuario->apellido1 != $datos->apellido1 ||
                            $usuario->apellido2 != $datos->apellido2 ||
                            $usuario->email != $datos->email ||
                            $usuario->telefono != $datos->telefono ||
                            $usuario->estado != $datos->estado ||
                            $usuario->fk_municipio != $datos->fk_municipio ||
                            $usuario->fk_perfil != $datos->fk_perfil ||
                            $usuario->foto != $datos->foto
                        ) {

                        if (is_null($usuario->fk_perfil) && $datos->fk_perfil > 0) {
                            DB::table('permisos_sistema')->where('fk_usuario', $datos->id)->whereNull('fk_permiso')->whereNull('fk_perfil')->delete();
                        }

                        $usuario->nro_documento = $datos->nro_documento;
                        $usuario->usuario = $datos->usuario;
                        $usuario->nombre1 = $datos->nombre1;
                        $usuario->nombre2 = $datos->nombre2;
                        $usuario->apellido1 = $datos->apellido1;
                        $usuario->apellido2 = $datos->apellido2; 
                        $usuario->nombre = $datos->nombre1 . ' ' . (strlen($datos->nombre2) > 0 ? $datos->nombre2 . ' ' : '') . $datos->apellido1 . (strlen($datos->apellido2) > 0 ? ' ' . $datos->apellido2 : '');
                        $usuario->email = $datos->email; 
                        $usuario->telefono = $datos->telefono; 
                        $usuario->estado = $datos->estado; 
                        $usuario->fk_municipio = $datos->fk_municipio;
                        $usuario->fk_perfil = $datos->fk_perfil == "null" ? null : $datos->fk_perfil;
                        DB::beginTransaction();
                        
                        if ($usuario->save()) {

                            $rutaFotoPerfil = "foto";
                            if(isset($datos->fotoPerfil)){
                                try {
                                    $rutaFotoPerfil = Storage::putFileAs('public/fotosPerfil/', $request->fotoPerfil, $usuario->id . "." . $request->file('fotoPerfil')->getClientOriginalExtension());

                                    $usuario->foto = $usuario->id . "." . $request->file('fotoPerfil')->getClientOriginalExtension();

                                    $usuario->save();

                                } catch (\Exception $e) {
                                    DB::rollback();
                                    $rutaFotoPerfil = 0;
                                    $resp["msj"] = "Error al subir la foto de perfil.";
                                }
                            }

                            if ($rutaFotoPerfil !== 0){
                                DB::commit();
                                $resp["success"] = true;
                                $resp["msj"] = "Se han actualizado los datos";
                                $resp["id"]= $usuario->id;
                            } else {
                                $resp["msj"] = "Error al editar el usuario";
                                DB::rollback();
                            }

                        }else{
                            $resp["success"] = false;
                            $resp["msj"] = "No se han guardado cambios";
                        }
                        } else {
                        $resp["success"] = false;
                        $resp["msj"] = "Por favor realice algún cambio";
                        }
                    }else{
                        $resp["msj"] = "No se ha encontrado el usuario";
                    }
                } else {
                    $resp["msj"] = "El correo " . $datos->email . " ya se encuentra registrado";  
                }
            } else {
                $resp["msj"] = "El usuario " . $datos->usuario . " ya se encuentra registrado";
            }
        } else {
            $resp["msj"] = "El número de documento " . $datos->nro_documento . " ya se encuentra registrado";
        }
        return $resp; 
    }

    public function permisos($idUsuario, $idPerfil, $menu = false, $permiso = null, $hijos = true){
        $idPerfil = is_null($idPerfil) ? 0 : $idPerfil;

        $query = DB::table("permisos AS p")
                    ->select(
                        "p.id"
                        ,"p.nombre"
                        ,"p.tag"
                        ,"p.icono"
                        ,"p.ruta"
                        ,"p.fk_permiso"
                    )->addSelect(['contHijos' => DB::table("permisos AS per")->selectRaw('count(*)')->whereColumn('per.fk_permiso', 'p.id')])
                    ->selectRaw("(CASE WHEN ps.fk_usuario IS NULL THEN CASE WHEN ps.fk_perfil IS NULL THEN 0 ELSE 1 END ELSE 1 END) AS aplicaPermiso")
                    ->leftjoin("permisos_sistema as ps", function ($join) use ($idUsuario, $idPerfil) {
                        $join->on('p.id', 'ps.fk_permiso')
                        ->where(function($query) use ($idUsuario, $idPerfil) {
                            return $query->where("ps.fk_usuario", $idUsuario)
                                        ->orWhere("ps.fk_perfil", $idPerfil);
                        })->where('ps.estado', 1);
                    });
        
        if ($menu == true) {
            $query = $query->where(function($query) use ($idUsuario, $idPerfil) {
                return $query->whereNotNull("ps.fk_usuario")
                            ->orWhereNotNull("ps.fk_perfil");
            });
        }

        if ($hijos){
            if ($permiso == null) {
                $query = $query->whereNull('p.fk_permiso');
            } else {
                $query = $query->where('p.fk_permiso', $permiso);
            }
        }
        
        $query = $query->groupBy("p.id", "p.nombre", "p.tag", "p.icono", "p.ruta", "p.fk_permiso")->get();
        if ($hijos){ 
            foreach ($query as $per) {
                if ($per->contHijos > 0) {
                    $per->hijos = $this->permisos($idUsuario, $idPerfil, $menu, $per->id);
                }
            }
        }
        return $query; 
    }

    public function checkearUsuario($usuario){
        $existeUsuario = User::where('usuario', $usuario)->get();

        return $existeUsuario->isEmpty();
        
    }

    public function guardarPermiso2(Request $request){
        $resp["success"] = false;
        DB::beginTransaction();

        DB::table('permisos_sistema')->where("fk_usuario", $request->idUsuario)->delete(); 
        
        $cont = 0;
        foreach ($request->permisos as $value) {
            try {
                DB::table('permisos_sistema')->insert([
                    "fk_usuario" => $request->idUsuario
                    ,"fk_permiso" => $value
                    ,"tipo" => 0
                ]);
            } catch (\Exception $e) {
                DB::rollback();
                $cont++;
                break;
            }
        }

        if ($cont == 0) {
            DB::commit();
            $resp["success"] = true;
            $resp["msj"] = "Permisos actualizados correctamente.";
        } else {
            DB::rollback();
            $resp["msj"] = "Error al actualizar los permisos.";
        }

        return $resp;
    }

    public function guardarPermiso(Request $request){
        $resp["success"] = false;
        DB::beginTransaction();

        DB::table('permisos_sistema')->whereNull("fk_escuelas")->where("fk_usuario", $request->idUsuario)->delete(); 
        
        $cont = 0;
        $perfil = new PerfilesController();
        $permisoSeleccionados = $perfil->permisosGuardar($request->permisos, []);
        foreach ($permisoSeleccionados as $value) {
            try {
                $validar = DB::table('permisos_sistema')
                    ->select("id")
                    ->where("fk_perfil", $request->idPerfil)
                    ->where("fk_permiso", $value)
                    ->whereNull('fk_usuario')
                    ->first();
                if (!isset($validar->id)) {
                    $validar = DB::table('permisos_sistema')
                        ->select("id")
                        ->where("fk_usuario", $request->idUsuario)
                        ->where("fk_permiso", $value)
                        ->first();
                    if (!isset($validar->id)) {
                        DB::table('permisos_sistema')->insert([
                            "fk_usuario" => $request->idUsuario
                            ,"fk_permiso" => $value
                            ,"fk_perfil" => $request->idPerfil
                            ,"tipo" => 0
                        ]);    
                    }
                }
            } catch (\Exception $e) {
                DB::rollback();
                $cont++;
                break;
            }
        }

        if ($cont == 0) {
            DB::commit();
            $resp["success"] = true;
            $resp["msj"] = "Permisos actualizados correctamente.";
        } else {
            DB::rollback();
            $resp["msj"] = "Error al actualizar los permisos.";
        }

        return $resp;
    }

    public function escuelas($idUsuario, $idRol) {


        $leccProg = DB::table('lecciones_progreso_usuarios')
            ->selectRaw('fecha_completado, fk_leccion')
            ->where('fk_user', $idUsuario);
        
        $unidades = DB::table('lecciones_unidades')
            ->selectRaw('COUNT(*) AS cantLecc,
                COUNT(LPU.fecha_completado) AS cantLeccCompletas,
                lecciones_unidades.fk_unidad
            ')
            ->leftJoinSub($leccProg, "LPU", function ($join) {
                $join->on("lecciones_unidades.fk_leccion", "=", "LPU.fk_leccion");
            })
            ->where('lecciones_unidades.estado', 1)
            ->groupBy('lecciones_unidades.fk_unidad');

        $cursos = DB::table('unidades_cursos')
            ->selectRaw('SUM(UCT.cantLeccCompletas) AS cantLeccComple
                , unidades_cursos.fk_curso
                , SUM(UCT.cantLecc) AS cantLecc
                , COUNT(*) AS cantUnidades
            ')
            ->leftJoinSub($unidades, "UCT", function ($join) {
                $join->on("unidades_cursos.fk_unidad", "=", "UCT.fk_unidad");
            })
            ->where('unidades_cursos.estado', 1)
            ->groupBy('unidades_cursos.fk_curso');

        $escuelas = DB::table('escuelas_cursos')
            ->selectRaw('SUM(ECU.cantLecc) AS cantCursos
                , SUM(ECU.cantLeccComple) AS cantCursCompletados
                , escuelas_cursos.fk_escuela
            ')
            ->leftJoinSub($cursos, "ECU", function ($join) {
                $join->on("escuelas_cursos.fk_curso", "=", "ECU.fk_curso");
            })
            ->where('escuelas_cursos.estado', 1)
            ->groupBy('escuelas_cursos.fk_escuela');
        
        $cantCursos = DB::table('escuelas_cursos')
            ->selectRaw('COUNT(*) cantCursos, escuelas_cursos.fk_escuela')
            ->where('escuelas_cursos.estado', 1)
            ->groupBy('escuelas_cursos.fk_escuela');

        $query = DB::table("permisos_sistema AS PS")
                ->select(
                    "E.id"
                    ,"E.nombre"
                    ,"E.descripcion"
                    ,"CTC.cantCursos"
                )->selectRaw(
                    "(
                        (
                            IF(ECT.cantCursCompletados IS NULL, 0, ECT.cantCursCompletados) * 100
                        ) / IF(ECT.cantCursos IS NULL, 0, ECT.cantCursos)
                    ) AS progresoActual"
                )
                ->join("escuelas AS E", function ($join) {
                    $join->on('PS.fk_escuelas', 'E.id')->where('E.estado', 1);
                })
                ->leftJoinSub($cantCursos, "CTC", function ($join) {
                    $join->on("E.id", "=", "CTC.fk_escuela");
                })
                ->leftJoinSub($escuelas, "ECT", function ($join) {
                    $join->on("E.id", "=", "ECT.fk_escuela");
                })
                ->where(function($query) use ($idUsuario, $idRol) {
                    return $query->where("PS.fk_perfil", $idRol)
                                ->orWhere("PS.fk_usuario", $idUsuario);
                })->whereNotNull("PS.fk_escuelas")->get();

        return $query; 
    }

    public function listaUnidadesLeccionesAvance($id, $idUser, $tipo) {

        if ($tipo == 'unidades') {

            $cantLecciones = DB::table('lecciones_unidades')
                ->selectRaw('COUNT(*) AS cantLecciones, lecciones_unidades.fk_unidad')
                ->where('lecciones_unidades.estado', 1)
                ->groupBy('lecciones_unidades.fk_unidad');
    
            $lecciones = DB::table('lecciones_unidades')
                ->selectRaw('
                    COUNT(lecciones_progreso_usuarios.fecha_completado) AS TotalLeccionesComple,
                    lecciones_unidades.fk_unidad
                ')
                ->leftJoin('lecciones_progreso_usuarios', 'lecciones_unidades.fk_leccion', '=', 'lecciones_progreso_usuarios.fk_leccion')
                ->where('lecciones_progreso_usuarios.fk_user', $idUser)
                ->groupBy('lecciones_unidades.fk_unidad');
    
            $query = DB::table('unidades_cursos')
                ->join('unidades', 'unidades_cursos.fk_unidad', '=', 'unidades.id')
                ->leftJoinSub($lecciones, "lecciones2", function ($join) {
                    $join->on("lecciones2.fk_unidad", "=", "unidades.id");
                })
                ->leftJoinSub($cantLecciones, "LCT", function ($join) {
                    $join->on("LCT.fk_unidad", "=", "unidades.id");
                })
                ->where('unidades_cursos.fk_curso', $id)
                ->where('unidades_cursos.estado', 1)
                ->select(
                    "unidades.id AS unidadId",
                    "unidades.nombre AS nombre",
                    "unidades.color AS color",
                    "lecciones2.TotalLeccionesComple",
                    "LCT.cantLecciones"
                )
                ->orderBy('unidades_cursos.orden','asc');
            
            return $query->get();
        } else {
            
            $intentosLec = DB::table('intento_leccion_usuario')
            ->selectRaw('COUNT(*) AS intentos, intento_leccion_usuario.fk_leccion_progreso')
            ->groupBy('intento_leccion_usuario.fk_leccion_progreso');
            
            $progresoAct = DB::table('lecciones_progreso_usuarios')
            ->select(
                'lecciones_progreso_usuarios.fecha_completado AS fechProgCompleto',
                'lecciones_progreso_usuarios.fk_leccion',
                'lecciones_progreso_usuarios.intentos_adicionales',
                'lecciones_progreso_usuarios.id',
                'il.intentos'
            )->leftJoinSub($intentosLec, "il", function ($join) {
                $join->on("lecciones_progreso_usuarios.id", "=", "il.fk_leccion_progreso");
            })->where('lecciones_progreso_usuarios.fk_user', $idUser);

            $info = DB::table('lecciones_unidades')
                ->select(
                    "lecciones_unidades.id as unidadesId",
                    "lecciones.id as id",
                    "lecciones.nombre as nombre", 
                    "lecciones.tipo as tipo",
                    "lpu.fechProgCompleto",
                    "lpu.id AS idProgreso",
                    "lpu.intentos_adicionales",
                    "lpu.intentos",
                    "lecciones.intentos_base",
                )->selectRaw('IF(lecciones.intentos_base IS NULL, 0 , lecciones.intentos_base) + IF(lpu.intentos_adicionales IS NULL, 0, lpu.intentos_adicionales) AS sumaIntentos')
                ->join('lecciones', 'lecciones_unidades.fk_leccion', '=', 'lecciones.id')
                ->leftJoinSub($progresoAct, "lpu", function ($join) {
                    $join->on("lecciones.id", "=", "lpu.fk_leccion");
                })
                ->where('lecciones_unidades.fk_unidad', $id)
                ->where('lecciones_unidades.estado',1)
                ->orderBy('lecciones_unidades.orden','asc')
                ->get();
            return $info;
        }

    }

    public function escuelasSinPerfil($idUser, $idPerfil) {
        $query = DB::table("permisos_sistema AS PS")
            ->select(
                "PS.id", "PS.fk_escuelas", "PS.fk_perfil"
            )->whereNull("PS.fk_perfil")
            ->where("PS.estado", 1)
            ->where("PS.fk_usuario", $idUser)
            ->orWhere("PS.fk_perfil", $idPerfil);
        
        $escuelas = DB::table('escuelas')
            ->selectRaw('escuelas.id AS value, escuelas.nombre AS text, PST.id AS idPermUser, PST.fk_perfil AS perfPermUser')
            ->leftJoinSub($query, "PST", function ($join) {
                $join->on("escuelas.id", "=", "PST.fk_escuelas");
            })
            ->where('escuelas.estado', 1)
            ->get();
        return $escuelas;
    }

    public function guardarEscualesSinPerfil(Request $request) {
        $user = $request->usuario;

        DB::table('permisos_sistema')->whereNull("fk_permiso")->whereNull("fk_perfil")->where('fk_usuario', $user)->delete();

        $cont = 0;
        foreach ($request->escuelas as $value) {
            try {
                DB::table('permisos_sistema')->insert([
                    "fk_usuario" => $user
                    ,"fk_escuelas" => $value
                    ,"tipo" => 0
                ]);
            } catch (\Exception $e) {
                DB::rollback();
                $cont++;
                break;
            }
        }

        if ($cont == 0) {
            DB::commit();
            $resp["success"] = true;
            $resp["msj"] = "Escuelas actualizadas correctamente.";
        } else {
            DB::rollback();
            $resp["msj"] = "Error al actualizar las escuelas.";
        }
        return $resp;
    }

    public function editarPefil(Request $request){
        $resp["success"] = false;
        $usuario = User::find($request->idUsuario);

        if(!empty($usuario)){
            if (
                $usuario->nombre1 != $request->nombre1 ||
                $usuario->nombre2 != $request->nombre2 ||
                $usuario->apellido1 != $request->apellido1 ||
                $usuario->apellido2 != $request->apellido2 ||
                $usuario->email != $request->email ||
                $usuario->telefono != $request->telefono ||
                $usuario->fk_municipio != $request->idCiudad
            ) {
            
                $usuario->nombre1 = $request->nombre1;
                $usuario->nombre2 = $request->nombre2;
                $usuario->apellido1 = $request->apellido1;
                $usuario->apellido2 = $request->apellido2; 
                $usuario->nombre = $request->nombre1 . ' ' . (strlen($request->nombre2) > 0 ? $request->nombre2 . ' ' : '') . $request->apellido1 . (strlen($request->apellido2) > 0 ? ' ' . $request->apellido2 : '');
                $usuario->email = $request->email; 
                $usuario->telefono = $request->telefono; 
                $usuario->fk_municipio = $request->idCiudad;

                if ($usuario->save()) {
                    $resp["success"] = true;
                    $resp["msj"] = "Se han actualizado los datos";
                    $resp['nombreCompleto'] = $usuario->nombre;
                }else{
                    $resp["msj"] = "No se han guardado cambios";
                }
            } else {
                $resp["msj"] = "Por favor realice algún cambio";
            }
        }else{
            $resp["msj"] = "No se ha encontrado el usuario";
        }
        
        return $resp;
    }

    public function cambiarPass(Request $request){
        $resp["success"] = false;
        $usuario = User::find($request->idUsuario);

        if(!empty($usuario)){
            if(Hash::check($request->passActual, $usuario->password)){
                if(trim($request->passNueva) === trim($request->passNuevaConfirm)){
                    $usuario->password = Hash::make(trim($request->passNueva), ['rounds' => 15]);
                    if($usuario->save()){
                        $resp["success"] = true;
                        $resp["msj"] = "Contraseña actualizada";
                    }else{
                        $resp["msj"] = "No se ha actualizado la contraseña";
                    }
                } else {
                    $resp["msj"] = "La contraseña nueva no coincide.";
                }
            } else {
                $resp["msj"] = "La contraseña actual no coincide.";
            }
        }else{
            $resp["msj"] = "No se ha encontrado el usuario";
        }

        return $resp;

    }
    
    public function upload(Request $request){
    
        $uploaded = Storage::putFileAs('public/'.$request->ruta, $request->file, $request->nombre);

        $resp["success"] = true;
        $resp["ruta"] = $uploaded;

        return $resp;
    }

    public function categorias($idUsuario, $idPerfil){
        $query = DB::table("permisos_sistema AS PS")
                ->select(
                    "TCC.id"
                    ,"TCC.nombre"
                )->join("toma_control_categorias AS TCC", "PS.fk_categorias_toma_control", "=", "TCC.id")  
                ->where(function($query) use ($idUsuario, $idPerfil) {
                    return $query->where("PS.fk_perfil", $idPerfil)
                                ->orWhere("PS.fk_usuario", $idUsuario);
                })->whereNotNull("PS.fk_categorias_toma_control")
                ->where("TCC.estado", 1)->get();

        return $query; 
    }

    public function fotoPerfil($idUsuario){

        $user = User::find($idUsuario);

        if(!empty($user)){
            if(!is_null($user->foto)){
                $path = storage_path('app/public/fotosPerfil/'. $user->foto);
            } else {
                $path = resource_path('assets/image/user.png');
            }
            
        } else {
            $path = resource_path('assets/image/user.png');
        }

        if (!File::exists($path)) {
            $path = resource_path('assets/image/user.png');
        }

        $file = File::get($path);
        $type = File::mimeType($path);

        $response = Response::make($file, 200);
        $response->header("Content-Type", $type); 

        return $response;
    }

    public function actualizarFotoPerfil(Request $request) {
        $resp["success"] = true;
        $resp["msj"] = "Foto actualizada correctamente.";
        try {

            $usuario = User::find($request->usuario);
            Storage::delete('public/fotosPerfil/' . $usuario->foto);

            $extend = $request->file('fotoPerfil')->getClientOriginalExtension();
            $rutaFotoPerfil = Storage::putFileAs('public/fotosPerfil/', $request->fotoPerfil, $request->usuario . "." . $extend);
            DB::beginTransaction();
            $usuario->foto = $request->usuario . "." . $extend;
            $usuario->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            $resp["success"] = false;
            $resp["msj"] = "Error al actualizar la foto.";
        }
        return $resp;
    }
}
