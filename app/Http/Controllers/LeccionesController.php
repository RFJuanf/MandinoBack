<?php

namespace App\Http\Controllers;

use App\Models\lecciones;
use App\Models\evaluacion_pregunta;
use App\Models\evaluacion_preguntas_opcione;
use App\Models\evaluacion_respuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use DB;
use DateTime;
use FFMpeg AS FFMpeg2;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Filters\Frame\FrameFilters;

class LeccionesController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request){
        $nombre = $request->nombre;
        $contenido = $request->contenido;
        $estado = $request->estado;
        $tipo = $request->tipo;
        $url_contenido = $request->url_contenido;
        $mensaje_ganar = $request->mensaje_ganar;
        $mensaje_perder = $request->mensaje_perder;

        return $this->crearLeccion($nombre, $contenido, $estado, $tipo, $url_contenido, null, null, $mensaje_ganar, $mensaje_perder);
    }

    public function crearLeccion($nombre, $contenido, $estado, $tipo, $url_contenido, $intentos_base = null, $porcentaje_ganar = null, $mensaje_ganar = null, $mensaje_perder = null){
        $resp["success"] = false;
    
         
        $validar = lecciones::where([
            ['nombre', $nombre], 
        ])->get();

        if($validar->isEmpty()){
            $leccion = new lecciones;
            $leccion->nombre = $nombre;
            $leccion->contenido = $contenido;
            $leccion->estado = $estado;
            $leccion->tipo = $tipo;
            $leccion->url_contenido = $url_contenido;
            $leccion->intentos_base = $intentos_base;
            $leccion->porcentaje_ganar = $porcentaje_ganar;
            $leccion->mensaje_ganar = $mensaje_ganar;
            $leccion->mensaje_perder = $mensaje_perder;

            if($leccion->save()){
                $resp["success"] = true;
                $resp["msj"] = "Se ha creado la lección correctamente.";
                $resp["id"] = $leccion->id;
            }else{
                $resp["msj"] = "No se ha creado la lección {$request->nombre}";
            }
        }else{
            $resp["msj"] = "La lección {$nombre} ya se encuentra registrada.";
        }

        return $resp;

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\lecciones  $lecciones
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request) {
        $query = lecciones::select('id', 'nombre', 'contenido', 'estado', 'tipo','intentos_base', 'porcentaje_ganar','mensaje_ganar', 'mensaje_perder', 'created_at');
        if ($request->estado != '') {
            $query->where("estado", $request->estado);
        }
        return datatables()->eloquent($query)->rawColumns(['nombre', 'tipo'])->make(true);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\lecciones  $lecciones
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Request $request) {
        $resp["success"] = false;
        $validar = lecciones::where([
            ['id', '<>', $request->id],
            ['nombre', $request->nombre]
        ])->get();
  
        if ($validar->isEmpty()) {

            $leccion = lecciones::find($request->id);

            if(!empty($leccion)){
                if ($leccion->contenido != $request->contenido || $leccion->estado != $request->estado || $leccion->nombre != $request->nombre || $leccion->url_contenido != $request->url_contenido || $leccion->tipo != $request->tipo ) {

                    $leccion->nombre = $request->nombre;
                    $leccion->contenido = $request->contenido;
                    $leccion->estado = $request->estado;
                    $leccion->tipo = $request->tipo;
                    $leccion->url_contenido = $request->url_contenido;
                    
                    if ($leccion->save()) {
                        $resp["success"] = true;
                        $resp["msj"] = "Se han actualizado los datos";
                    }else{
                        $resp["msj"] = "No se han guardado cambios";
                    }
                } else {
                    $resp["msj"] = "no hay cambios";
                }
            }else{
                $resp["msj"] = "No se ha encontrado la lección";
            }
        }else{
            $resp["msj"] = "la lección " . $request->nombre . " ya se encuentra registrada";
        }
        
        return $resp;
    }

    public function cambiarEstado(Request $request){
        $resp["success"] = false;
        $leccion = lecciones::find($request->id);
        
        if(is_object($leccion)){
            DB::beginTransaction();
            $leccion->estado = $request->estado;
        
            if ($leccion->save()) {
                $resp["success"] = true;
                $resp["msj"] = "La escuela " . $leccion->nombre . " se ha " . ($request->estado == 1 ? 'habilitado' : 'deshabilitado') . " correctamente.";
                DB::commit();
            }else{
                DB::rollBack();
                $resp["msj"] = "No se han guardado cambios";
            }
        }else{
            $resp["msj"] = "No se ha encontrado la escuela";
        }
        return $resp; 
    }

    public function traerLeccion($id){
        return lecciones::select("*")->where("id", $id)->get();
    }

    // listado lecciones_unidades
    public function listarLeccionesUnidades($idUnidad){
    
        $query = DB::table('lecciones_unidades')->join('lecciones', 'lecciones_unidades.fk_leccion', '=', 'lecciones.id');
        $query->where('lecciones_unidades.fk_unidad', $idUnidad);
        $query->where('lecciones_unidades.estado',1);
        $query->select(
            "lecciones_unidades.id as lecciones_unidades_id",
            "lecciones_unidades.estado as lecciones_unidades_estado", 
            "lecciones_unidades.orden as lecciones_unidades_orden", 
            "lecciones_unidades.fk_unidad as fk_unidad",
            "lecciones_unidades.tiempo_dependencia as lecciones_unidades_tiempo_dependencia", 
            "lecciones_unidades.fk_leccion_dependencia as lecciones_unidades_dependencia",
            "lecciones.id as lecciones_id", "lecciones.nombre as lecciones_nombre", 
            "lecciones.tipo as lecciones_tipo"
        );
        $query->orderBy('lecciones_unidades_orden','asc');
        
        return $query->get();

    }

    // asignación lecciones_unidades
    public function asignar(Request $request){
        $resp["success"] = false;
        try {

            $fk_unidad = $request->fk_unidad;
            $fk_leccion = $request->fk_leccion;
            $fk_leccion_dependencia = $request->fk_leccion_dependencia;
            $estado = 1;
            $orden = $request->orden;  
            DB::commit();
            return $this->asignarLeccionUnidad($fk_unidad, $fk_leccion, $fk_leccion_dependencia, $orden);
        } catch (\Exception $e) {
            DB::rollback();
            $resp["msj"] = " error al asignar lección.";

            return $resp;
        }
    }

    public function asignarLeccionUnidad($fk_unidad, $fk_leccion, $fk_leccion_dependencia, $orden){

        $resp["success"] = false;
        try {
            $query = DB::table('lecciones_unidades')->insert([
               "fk_unidad" => $fk_unidad,
               "fk_leccion" => $fk_leccion,
               "fk_leccion_dependencia" => $fk_leccion_dependencia,
               "estado" => 1,
               "orden" => $orden        
            ]);

            $resp["success"] = true;
            $resp["msj"] = " lección asignada correctamente.";
            DB::commit();

            return $resp;
        } catch (\Exception $e) {
            DB::rollback();

            $resp["msj"] = " error al asignar lección.";
            return $resp;
        }

    }

    //desasignar lecciones_unidades
    public function desasignar(Request $request){

        $resp["success"] = false;

        $validar =  DB::table('lecciones_unidades')->where([
            ['id', '=', $request->id],
        ])->get();
  

        if (!$validar->isEmpty()) {

            $dependencias = DB::table('lecciones_unidades')->where('fk_leccion_dependencia',$request->leccion_id)->where('estado',1)->where('fk_unidad',$request->fk_unidad)->get();

            if($dependencias->isEmpty()){
                $unidadCurso = DB::table('lecciones_unidades')->where('id',$request->id);
                
                if ( $unidadCurso->update(["estado" => 0]) ) {
                    $resp["success"] = true;
                    $resp["msj"] = "Se ha desasignado la leccion";
                }else{
                    $resp["msj"] = "Error al desasignar";
                }
            }else{
                $resp["msj"] = "Leccion es dependencia de otras leccion, no se puede desasignar";
            }
        
            
        }else{
            $resp["msj"] = "no se encuentra lección asignada";
        }
        
        return $resp;

    }

    //actualizar orden lecciones_unidades
    public function actualizarOrden(Request $request){

        $resp["success"] = false;
        $opts = [];

        foreach ($request->lecciones as $leccion) {

            $id = $leccion['lecciones_unidades_id'];
            $orden = $leccion['lecciones_unidades_orden'];
        
            try {
    
                $leccionUnidad = DB::table('lecciones_unidades')->where('id',$id);

                if ( $leccionUnidad->update(["orden" => $orden]) ) {
                    $resp["success"] = true;
                    $resp["msj"] = "Se ha cambiado el orden de lección";
                    array_push($opts, $id, $orden);
                }
            } catch (\Exception $e) {
                DB::rollback();
                $resp["msj"] = 'exepcion';
                return $resp;
                break;
            }

        }

        $resp["msj"] = "orden cambiado correctamente.";
        return $resp;
    }
    
    //agregar dependencia lecciones_unidades
    public function agregarDependencia(Request $request){
        $resp["success"] = false;

        $id = $request['id'];
        $idDependencia =  $request['idDependencia'];
        $diasDependencia =  $request['diasDependencia'];

        $validar =  DB::table('lecciones_unidades')->where([
            ['id', '<>', $request->id],
        ])->get();
    
        if (!$validar->isEmpty()) {
            $escuelaCurso = DB::table('lecciones_unidades')->where('id',$id);

            if ( $escuelaCurso->update(["fk_leccion_dependencia" => $idDependencia, "tiempo_dependencia" => $diasDependencia]) ) {
                $resp["success"] = true;

                $resp["msj"] = "dependencia".($idDependencia == null ? " des" : " ")."asignada";
            }else{
                $resp["msj"] = "error al asignar dependencia";
            }
        }else{
            $resp["msj"] = "no se encuentra la lección asignado";
        }
        
        return $resp;

    }

    // listado lecciones_unidades
    public function listarLeccionesProgreso($idUnidad, $usuario, $vista) {

        $info = array();

        $progresoAct = DB::table('lecciones_progreso_usuarios')
            ->select(
                'lecciones_progreso_usuarios.fecha_completado AS fechProgCompleto',
                'lecciones_progreso_usuarios.tiempo_video AS tiempoVideoProg',
                'lecciones_progreso_usuarios.id AS idProgreso',
                'lecciones_progreso_usuarios.intentos_adicionales',
                'lecciones_progreso_usuarios.fk_leccion'
            )->where('lecciones_progreso_usuarios.fk_user', $usuario);

        if ($vista == 'estudiar') {
    
            $progresoActDepende = DB::table('lecciones_progreso_usuarios')
                ->select(
                    'lecciones_progreso_usuarios.fecha_completado AS fechProgCompletoDepende',
                    'lecciones_progreso_usuarios.fk_leccion'
                )->where('lecciones_progreso_usuarios.fk_user', $usuario)
                ->whereNotNull('lecciones_progreso_usuarios.fecha_completado');
        
            $query = DB::table('lecciones_unidades')
                ->select(
                    "lecciones_unidades.id as unidadesId",
                    "lecciones_unidades.fk_leccion_dependencia as leccionDependencia",
                    "lecciones_unidades.tiempo_dependencia as tiempoDependencia",
                    "lecciones.id as id",
                    "lecciones.contenido",
                    "lecciones.nombre as nombre", 
                    "lecciones.tipo as tipo",
                    "lpu.fechProgCompleto",
                    "lpu.tiempoVideoProg",
                    "lpu.idProgreso",
                    "lpu2.fechProgCompletoDepende"
                )->selectRaw(
                    "(IF(lpu.intentos_adicionales IS NULL, 0, lpu.intentos_adicionales) + IF(lecciones.intentos_base IS NULL, 0, lecciones.intentos_base)) AS totalIntentos"
                )
                ->join("lecciones", function ($join) {
                    $join->on('lecciones_unidades.fk_leccion', 'lecciones.id')->where('lecciones.estado', 1);
                })
                ->leftJoinSub($progresoAct, "lpu", function ($join) {
                    $join->on("lecciones.id", "=", "lpu.fk_leccion");
                })
                ->leftJoinSub($progresoActDepende, "lpu2", function ($join) {
                    $join->on("lecciones_unidades.fk_leccion_dependencia", "=", "lpu2.fk_leccion");
                })
                ->where('lecciones_unidades.fk_unidad', $idUnidad)
                ->where('lecciones_unidades.estado',1)
                ->orderBy('lecciones_unidades.orden','asc');
            
            $info = $query->get();

        } else {

            $info = DB::table('lecciones_unidades')
                ->select(
                    "lecciones_unidades.id as unidadesId",
                    "lecciones_unidades.fk_leccion_dependencia as leccionDependencia",
                    "lecciones_unidades.tiempo_dependencia as tiempoDependencia",
                    "lecciones.id as id",
                    "lecciones.contenido",
                    "lecciones.nombre as nombre", 
                    "lecciones.tipo as tipo",
                    "lpu.fechProgCompleto",
                    "lpu.tiempoVideoProg",
                    "lpu.idProgreso"
                )->selectRaw(
                    "(IF(lpu.intentos_adicionales IS NULL, 0, lpu.intentos_adicionales) + IF(lecciones.intentos_base IS NULL, 0, lecciones.intentos_base)) AS totalIntentos"
                )
                ->join("lecciones", function ($join) {
                    $join->on('lecciones_unidades.fk_leccion', 'lecciones.id')->where('lecciones.estado', 1);
                })
                ->leftJoinSub($progresoAct, "lpu", function ($join) {
                    $join->on("lecciones.id", "=", "lpu.fk_leccion");
                })
                ->where('lecciones_unidades.fk_unidad', $idUnidad)
                ->where('lecciones_unidades.estado',1)
                ->orderBy('lecciones_unidades.orden','asc')
                ->get();
        }

        foreach ($info as $key => $value) {
            if ($value->tipo == 2 || $value->tipo == 4) {
                //Cuando sea una evaluación devolvemos un json
                if ($value->tipo == 2) {
                    $value->contenido = $this->evaluacionEstructura($value->id, true);
                }
                $value->intentos =  $this->obtenerIntentosLeccion($value->idProgreso);
                $value->masIntento = true;
                if ($value->totalIntentos != 0 && count($value->intentos) >= $value->totalIntentos) {
                    $value->masIntento = false;
                }
            }
        }
        return $info;
    }

    private function obtenerIntentosLeccion($idProgreso) {
        $intentos = DB::table('intento_leccion_usuario AS ILU')
            ->select(
                "ILU.id",
                "ILU.num_preguntas_correctas",
                "ILU.num_preguntas_totales",
                "ILU.fecha_inicio",
                "ILU.fecha_final", 
                "ILU.captura_pantalla",
                "LPU.fk_leccion AS Leccion"
            )->selectRaw("TIMEDIFF(ILU.fecha_final, ILU.fecha_inicio) AS tiempo")
            ->join('lecciones_progreso_usuarios AS LPU', 'ILU.fk_leccion_progreso', '=', 'LPU.id')
            ->where('ILU.fk_leccion_progreso', $idProgreso)
            ->get();    
        
        foreach ($intentos as $int) {
            $int->contenido = $this->evaluacionEstructura($int->Leccion, false, $int->id);
        }
    
        return $intentos;
    }

    //guardar cantidad de intentos de exmane por usuario
    public function cantidadIntentos(Request $request) {
        $resp["success"] = false;
        $resp["msj"] = "No fue posible modificar la cantidad.";
        try {

            $progreso = DB::table('lecciones_progreso_usuarios')->where('id', $request->progreso);

            $datActr = [
                "intentos_adicionales" => $request->intentos,
                'updated_at' => now()
            ];

            if ($progreso->update($datActr)) {
                $resp["success"] = true;
                $resp["msj"] = "Cantidad modificada correctmente";
                DB::commit();
                return $resp;
            } else {
                DB::rollback();
                return $resp;
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $resp;
        }
    }

    //Obtener screenshot juego
    public function getScreenShot($id, $filename, $navegador){
        $path = storage_path('app/public/juegos/'. $id . '/' . $filename);
        if (!File::exists($path)) {
            $path = resource_path('assets/image/nofoto.png');
        }

        $file = File::get($path);
        $size = File::size($path);
        $type = File::mimeType($path);

        $codigo = 206;
        if ($navegador == 'firefox') $codigo = 200;

        $response = Response::make($file, $codigo);
        $response->header("Content-Type", $type); 
        $response->header("Content-Range", "bytes 0-" . ($size - 1) . "/" . $size); 
        return $response;
    }

    //creacion progreso lección
    public function crearProgreso(Request $request){
        $resp["success"] = false;
        try {

            $progreso = DB::table('lecciones_progreso_usuarios')
                ->where('fk_leccion', $request->fk_leccion)
                ->where('fk_user', $request->fk_user)
                ->first();

            $request->fecha_completado = $request->fecha_completado == 0 ? null : date('Y-m-d H-i-s');
         
            if (isset($progreso->id)) {
                $request->idProgreso = $progreso->id;
                return $this->actualizarProgreso($request);
            }

            $id = DB::table('lecciones_progreso_usuarios')->insertGetId([
                "fk_user" => $request->fk_user,
                "fk_leccion" => $request->fk_leccion,
                "fecha_completado" => $request->fecha_completado,
                "tiempo_video" => (isset($request->tiempo_video) ? $request->tiempo_video : null),
                "updated_at" => now(),
                "created_at" => now() 
            ]);

            if (isset($request->guardarIntento) && $request->guardarIntento == true) {

                if (isset($request->imagen)) {
                    $image = str_replace('data:image/png;base64,', '', $request->imagen);
                    $image = base64_decode(str_replace(' ', '+', $image));
    
                    $file = Storage::disk('public')->put($request->carpetaJuegos, $image);
                }

                $idIntentoLeccionUsuario  = DB::table('intento_leccion_usuario')->insertGetId([
                    "fk_leccion_progreso" => $id,
                    "num_preguntas_correctas" => $request->palabrasTotal,
                    "num_preguntas_totales" => $request->palabrasCompletadas,
                    "fecha_inicio" => $request->fechaInicio,
                    "fecha_final" => $request->fechaFinal,
                    "captura_pantalla" => isset($request->nombreCaptura) ? $request->nombreCaptura : null,
                    "updated_at" => now(),
                    "created_at" => now() 
                ]);

                if (isset($request->tipo) && $request->tipo == 2) {
                    $VALIDANEITOR = false;   
                    foreach ($request->respuestas as $res) {
                        foreach ($res['respuestas'] as $res2) {
                            $evaRespuesta = new evaluacion_respuesta;
                            $evaRespuesta->fk_intento_leccion = $idIntentoLeccionUsuario;
                            $evaRespuesta->fk_pregunta_respuesta = $res2;

                            if(!$evaRespuesta->save()) {
                                $VALIDANEITOR = true;
                                break;
                            }
                        }

                        if($VALIDANEITOR == true){
                            break;
                        }

                    }
                }

                $resp['intentos'] = $this->obtenerIntentosLeccion($id);
            }

            $resp["success"] = true;
            $resp["msj"] = "Progreso registrado correctamente.";
            $resp["fechProgCompleto"] = $request->fecha_completado;
            $resp['idProgreso'] = $id;
            DB::commit();

            return $resp;
        } catch (\Exception $e) {
            DB::rollback();
            $resp["msj"] = " Error al registrar el progreso.";

            return $resp;
        }
    }

    //modificación progreso lección
    public function actualizarProgreso(Request $request){
        $resp["success"] = false;
        $resp["msj"] = "Ha ocúrrido un problema.";
        try {

            $request->fecha_completado = $request->fecha_completado == 0 ? null : date('Y-m-d H-i-s');

            $progreso = DB::table('lecciones_progreso_usuarios')
                ->where('id', $request->idProgreso);

            $datActr = [
                "fecha_completado" => $request->fecha_completado,
                "tiempo_video" => (isset($request->tiempo_video) ? $request->tiempo_video : null),
                'updated_at' => now()
            ];

            if (isset($request->guardarIntento) && $request->guardarIntento == true) {

                if (isset($request->imagen)) {
                    $image = str_replace('data:image/png;base64,', '', $request->imagen);
                    $image = base64_decode(str_replace(' ', '+', $image));
    
                    $file = Storage::disk('public')->put($request->carpetaJuegos, $image);
                }

                $idIntentoLeccionUsuario = DB::table('intento_leccion_usuario')->insertGetId([
                    "fk_leccion_progreso" => $request->idProgreso,
                    "num_preguntas_correctas" => $request->palabrasTotal,
                    "num_preguntas_totales" => $request->palabrasCompletadas,
                    "fecha_inicio" => $request->fechaInicio,
                    "fecha_final" => $request->fechaFinal,
                    "captura_pantalla" => isset($request->nombreCaptura) ? $request->nombreCaptura : null,
                    "updated_at" => now(),
                    "created_at" => now() 
                ]);

                if (isset($request->tipo) && $request->tipo == 2) {
                    $VALIDANEITOR = false;   
                    foreach ($request->respuestas as $res) {
                        foreach ($res['respuestas'] as $res2) {
                            $evaRespuesta = new evaluacion_respuesta;
                            $evaRespuesta->fk_intento_leccion = $idIntentoLeccionUsuario;
                            $evaRespuesta->fk_pregunta_respuesta = $res2;

                            if(!$evaRespuesta->save()) {
                                $VALIDANEITOR = true;
                                break;
                            }
                        }

                        if($VALIDANEITOR == true){
                            break;
                        }
                    }
                }

                $resp['intentos'] = $this->obtenerIntentosLeccion($request->idProgreso);
            }

            if (!is_null($progreso->first()->fecha_completado)) {
                $resp["success"] = true;
                $resp["msj"] = "Progreso modificado correctamente";
                $resp["fechProgCompleto"] = $request->fecha_completado;
                $resp['idProgreso'] = $request->idProgreso;
                return $resp;
            }

            if ($progreso->update($datActr)) {
                $resp["success"] = true;
                $resp["msj"] = "Progreso modificado correctamente";
                $resp["fechProgCompleto"] = $request->fecha_completado;
                $resp['idProgreso'] = $request->idProgreso;
                DB::commit();
                return $resp;
            } else {
                DB::rollback();
                return $resp;
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $resp;
        }
    }

    // función para crear video en lección
    public function crearVideo(Request $request){
        $resp["success"] = false;
        $resp["nombre"]= $request->nombre;
        $editar = isset($request->editar) ? True : False;

        $datos = json_decode($request->datos);

        DB::beginTransaction();

        $configFFP = [
            'ffmpeg.binaries'  => resource_path('ffmpeg/ffmpeg.exe'), // the path to the FFMpeg binary
            'ffprobe.binaries' => resource_path('ffmpeg/ffprobe.exe'), // the path to the FFProbe binary
            'timeout'          => 3600, // the timeout for the underlying process
            'ffmpeg.threads'   => 12,   // the number of threads that FFMpeg should use
        ];

        
        if(isset($request->file)){
            try {
                $rutaVideo = Storage::putFileAs('public/' . $request->ruta . "/" . $request->nombre , $request->file, "video." . $request->file('file')->getClientOriginalExtension());
            } catch (\Exception $e) {
                $resp["msj"] = "Error al subir el video.";
            }

            // códdigo que genera gif (el que lea esto es gay)
            if(isset($rutaVideo)) {
                $ffprobe = FFProbe::create($configFFP);
                $duracion = (int) $ffprobe->format(storage_path('app/' . $rutaVideo))->get('duration');
                $timeSkip = rand(1, $duracion - 3);
            }
            
        }


        if(isset($request->poster)){
            try {
                $rutaPoster = Storage::putFileAs('public/' . $request->ruta . "/" . $request->nombre , $request->poster, "poster." . $request->file('poster')->getClientOriginalExtension());
            } catch (\Exception $e) {
                $resp["msj"] = "Error al subir el poster.";
                $error = $th;
            }
        }
        else {
            try {
                if(!isset($request->editar)){
                    $ffmpeg = FFMpeg::create($configFFP);
                    $ffmpeg->open(storage_path('app/' . $rutaVideo))
                    ->frame(TimeCode::fromSeconds($timeSkip))
                    ->save(storage_path("app/public/" . $request->ruta . "/" . $request->nombre . "/poster.png"));
                    $rutaPoster = 'poster.png';
                }
            } catch (\Throwable $th) { 
                $resp["msj"] = "Error al subir el poster predeterminado.";
                $error = $th;
            }
        }
        
        if (isset($error)) {
            DB::rollback();
            $delete = Storage::deleteDirectory('public/' . $request->ruta . "/" . $request->nombre);
            $resp["success"] = false;
        } else {
            DB::commit();
            $resp["success"] = true;
            
            if(isset($rutaVideo)) {
                $resp['pathVideo'] = $rutaVideo;
            }

            if(isset($rutaPoster)){
                $resp['pathPic'] = $rutaPoster;
            }

            $resp["msj"] = $request->nombre . " se ha creado correctamente.";
        }
        
        return $resp;
    }

    public function getVideo($id, $tipo, $filename, $navegador){
        $path = storage_path('app/public/videos/'. $id . '/' . $filename);
        if (!File::exists($path)) {
            if($tipo == 1) {
                $path = resource_path('assets/videos/error.mp4');
            } else {
                $path = resource_path('assets/image/nofoto.png');
            }
        }

        $file = File::get($path);
        $size = File::size($path);
        $type = File::mimeType($path);

        $codigo = 206;
        if ($navegador == 'firefox') $codigo = 200;

        $response = Response::make($file, $codigo);
        $response->header("Content-Type", $type); 
        $response->header("Content-Range", "bytes 0-" . ($size - 1) . "/" . $size); 

        return $response;
    }

    public function subirArchivo(Request $request){

        $file = $request->file('file');

        try {
            $ruta = Storage::putFileAs('public/archivos/' . $request->folder, $file, $file->getClientOriginalName());
        } catch (\Exception $e) { 
            $resp["success"] = false;
            $resp["msj"] = "Error al subir el archivo: ".$file->getClientOriginalName();
        }

        $resp["success"] = true;
        $resp["msj"] = "archivo subido";
        $resp["path"] = $ruta;

        return $resp;
    }

    public function descargarArchivo($folder, $archivo){
        $path = storage_path('app/public/archivos/'.$folder.'/'.$archivo);
        if (File::exists($path)) {
            $type = File::mimeType($path);
            $file = Storage::disk('public')->get('archivos/'.$folder.'/'.$archivo);
            $response = Response::make($file, 200);
            $response->header("Content-Type", $type);
            return $response;
        }
    }

    public function traerTodosArchivos($folderName){
        try{
            $path = storage_path('app/public/archivos/'.$folderName);
            $files = File::allFiles($path);
            $names = [];
            if(isset($files)){
                foreach($files as $file){
                    $obj = array(
                        "subido" => True,
                        "name" => $file->getFilename()
                    );
                    array_push($names, $obj);
                }
            }
            return $names;
        }
        catch(\Exception $e){
            // directorio no existe :()
            return [];
        }
    }

    public function eliminarArchivo(Request $request){

        $folder = $request->folderName;
        $file =  $request->fileName;

        try {
            $ruta = storage_path('app/public/archivos/'.$folder.'/'.$file);
            return File::delete($ruta);
        } catch (\Exception $e) {
           return $e;
        }
        
    }

    public function guardarEvaluacion(Request $request){
        DB::beginTransaction();
        $resp["success"] = false;
        $nombre = $request->nombre;
        $contenido = $request->contenido;
        $estado = $request->estado;
        $tipo = $request->tipo;
        $url_contenido = $request->url_contenido;
        $porcentaje_ganar = $request->porcentaje;
        $mensaje_ganar = $request->mensaje_ganar;
        $mensaje_perder = $request->mensaje_perder;

        $leccion = $this->crearLeccion($nombre, $contenido, $estado, $tipo, $url_contenido, 3, $porcentaje_ganar, $mensaje_ganar, $mensaje_perder);

        if($leccion['success'] == true){
            foreach ($request->preguntas as $pre) {
                $pregunta = new evaluacion_pregunta;
                $pregunta->pregunta = $pre['pregunta'];
                $pregunta->fk_leccion = $leccion['id'];
                $pregunta->tipo_pregunta = $pre['tipoPregunta'];

                if($pregunta->save()){
                    foreach ($pre['respuestas'] as $res) {
                        $opciones = new evaluacion_preguntas_opcione;
                        $opciones->descripcion = $res['respuesta'];
                        $opciones->correcta = $res['correcta'] == true ? 1 : 0;
                        $opciones->fk_pregunta = $pregunta->id;

                        if ($opciones->save()) {
                            $resp["success"] = true;
                        } else {
                            $resp["success"] = false;
                            $resp["msj"] = "Error al crear las opciones de {$res['respuesta']}";
                            break;
                        }
                    }
                }else{
                    $resp["msj"] = "Error al crear las preguntas";
                    break;
                }
            }
        } else {
            $resp['msj'] = $leccion['msj'];
        }

        if ($resp['success'] == true) {
            $resp['msj'] = "Evaluación creada correctamente."; 
            DB::commit();
        } else {
            DB::rollBack();
        }

        return $resp;
    }

    public function modificarEvaluacion(Request $request){
        DB::beginTransaction();
        $resp["success"] = false;
        
        // datos lección
        $nombre = $request->nombre;
        $contenido = $request->contenido;
        $estado = $request->estado;
        $tipo = $request->tipo;
        $url_contenido = $request->url_contenido;
        $porcentaje_ganar = $request->porcentaje;
        // datos evaluación
        $id = $request->id;
        $preguntas = $request->preguntas;
        $respuestasEliminar = $request->respuestasEliminar;
        $preguntasEliminar = $request->preguntasEliminar;
        $mensaje_ganar = $request->mensaje_ganar;
        $mensaje_perder = $request->mensaje_perder;

        // actualización y/o creación de preguntas y respuestas nuevas
        foreach ($request->preguntas as $pre) {
            $pregunta = isset($pre['id']) ? evaluacion_pregunta::find($pre['id']) : new evaluacion_pregunta;
            $pregunta->pregunta = $pre['pregunta'];
            $pregunta->fk_leccion = $id;
            $pregunta->tipo_pregunta = $pre['tipoPregunta'];

            if($pregunta->save()){

                foreach ($pre['respuestas'] as $res) {

                    $opciones = isset($res['id']) ? evaluacion_preguntas_opcione::find($res['id']) : new evaluacion_preguntas_opcione;
                    $opciones->descripcion = $res['respuesta'];
                    $opciones->correcta = $res['correcta'] == true ? 1 : 0;
                    $opciones->fk_pregunta = $pregunta->id;

                    if ($opciones->save()) {
                        $resp["success"] = true;
                    } else {
                        $resp["success"] = false;
                        $resp["msj"] = "Error al crear las opciones de {$res['respuesta']}";
                        break;
                    }
                }
            }else{
                $resp["msj"] = "Error al actualizar las preguntas";
                break;
            }
        }

        foreach ($respuestasEliminar as $respEliminar) {
            $respuestaEliminar = evaluacion_preguntas_opcione::find($respEliminar);
            if(isset($respuestaEliminar)){
                $eliminar = $respuestaEliminar->delete();
            }
        }

        foreach ($preguntasEliminar as $pregEliminar) {
            $preguntaEliminar = evaluacion_pregunta::find($pregEliminar);
            if(isset($preguntaEliminar)){
                $eliminar = $preguntaEliminar->delete();
            }
        }


        if ($resp['success'] == true) {
            $resp['msj'] = "Evaluación Actualizada correctamente."; 
            DB::commit();
        } else {
            DB::rollBack();
        }

        return $resp;


    }

    function evaluacionEstructura($idLeccion, $random = false, $intento = null){
        $preguntas = evaluacion_pregunta::select("id", "pregunta", "tipo_pregunta")->where("fk_leccion", $idLeccion);

        if($random == true){
            $preguntas->inRandomOrder();
        }

        $preguntas = $preguntas->get();

        foreach ($preguntas as $pre) {
            $respuestas = evaluacion_preguntas_opcione::select(
                            "evaluacion_preguntas_opciones.id", 
                            "evaluacion_preguntas_opciones.descripcion AS respuesta", 
                            "evaluacion_preguntas_opciones.correcta");

            if ($intento != null && !is_null($intento)) {
                $respuestas->selectRaw("IF(evaluacion_preguntas_opciones.id = ER.fk_pregunta_respuesta, 1, 0) AS aprobo")
                            ->leftJoin("evaluacion_respuestass AS ER", function ($join) use ($intento) {
                                $join->on("ER.fk_pregunta_respuesta", "=", "evaluacion_preguntas_opciones.id")
                                    ->where("ER.fk_intento_leccion", $intento);
                            });
            }

            $respuestas->where("fk_pregunta", $pre->id);

            if($random == true){
                $respuestas->inRandomOrder();
            }

            $pre->respuestas = $respuestas->get();
        }
        
        return $preguntas;
    }

}

