<?php

$sep = ";";

$carpeta_entrada = __DIR__ . "/archivos";
$carpeta_salida = __DIR__ . "/archivos_generados";

if (!is_dir($carpeta_salida)) {
    mkdir($carpeta_salida, 0777, true);
}

$regiones = cargar_regiones($carpeta_entrada . "/regiones_comunas.csv", $sep);
$comunas = cargar_comunas($carpeta_entrada . "/regiones_comunas.csv", $sep);

copy(
    $carpeta_entrada . "/regiones_comunas.csv",
    $carpeta_salida . "/regiones_comunasOK.csv"
);

$regiones_err = fopen($carpeta_salida . "/regiones_comunasERR.csv", "w");
fputcsv($regiones_err, array("errores"), $sep, '"', "\\");
fclose($regiones_err);

$regiones_log = fopen($carpeta_salida . "/regiones_comunasLOG.csv", "w");
fputcsv($regiones_log, array("linea", "archivo", "identificador", "accion"), $sep, '"', "\\");
fputcsv($regiones_log, array(0, "regiones_comunas.csv", "", "Archivo copiado directamente como regiones_comunasOK.csv"), $sep, '"', "\\");
fclose($regiones_log);

procesar_archivo(
    "personas_socios.csv",
    "personas_sociosOK.csv",
    "personas_sociosERR.csv",
    "personas_sociosLOG.csv",
    "revisar_personas_socios",
    $sep,
    $comunas,
    $regiones,
    $carpeta_entrada,
    $carpeta_salida
);

procesar_archivo(
    "sucursales_lugares.csv",
    "sucursales_lugaresOK.csv",
    "sucursales_lugaresERR.csv",
    "sucursales_lugaresLOG.csv",
    "revisar_sucursales_lugares",
    $sep,
    $comunas,
    $regiones,
    $carpeta_entrada,
    $carpeta_salida
);

procesar_archivo(
    "reservas_arriendos.csv",
    "reservas_arriendosOK.csv",
    "reservas_arriendosERR.csv",
    "reservas_arriendosLOG.csv",
    "revisar_reservas_arriendos",
    $sep,
    $comunas,
    $regiones,
    $carpeta_entrada,
    $carpeta_salida
);

procesar_archivo(
    "eventos.csv",
    "eventosOK.csv",
    "eventosERR.csv",
    "eventosLOG.csv",
    "revisar_eventos",
    $sep,
    $comunas,
    $regiones,
    $carpeta_entrada,
    $carpeta_salida
);

procesar_archivo(
    "pagos_membresias.csv",
    "pagos_membresiasOK.csv",
    "pagos_membresiasERR.csv",
    "pagos_membresiasLOG.csv",
    "revisar_pagos_membresias",
    $sep,
    $comunas,
    $regiones,
    $carpeta_entrada,
    $carpeta_salida
);

procesar_archivo(
    "cargos_administrativos.csv",
    "cargos_administrativosOK.csv",
    "cargos_administrativosERR.csv",
    "cargos_administrativosLOG.csv",
    "revisar_cargos_administrativos",
    $sep,
    $comunas,
    $regiones,
    $carpeta_entrada,
    $carpeta_salida
);

function procesar_archivo($archivo_entrada, $archivo_ok, $archivo_err, $archivo_log, $funcion_revision, $sep, $comunas, $regiones, $carpeta_entrada, $carpeta_salida) {

    $ruta_entrada = $carpeta_entrada . "/" . $archivo_entrada;
    $ruta_ok = $carpeta_salida . "/" . $archivo_ok;
    $ruta_err = $carpeta_salida . "/" . $archivo_err;
    $ruta_log = $carpeta_salida . "/" . $archivo_log;

    $entrada = fopen($ruta_entrada, "r");

    if (!$entrada) {
        echo "No se pudo abrir $ruta_entrada\n";
        return;
    }

    $salida_ok = fopen($ruta_ok, "w");
    $salida_err = fopen($ruta_err, "w");
    $salida_log = fopen($ruta_log, "w");

    if (!$salida_ok || !$salida_err || !$salida_log) {
        echo "No se pudo crear algun archivo de salida para $archivo_entrada\n";
        fclose($entrada);
        return;
    }

    $header = fgetcsv($entrada, 0, $sep, '"', "\\");

    if ($header === false) {
        echo "El archivo $archivo_entrada esta vacio\n";
        fclose($entrada);
        fclose($salida_ok);
        fclose($salida_err);
        fclose($salida_log);
        return;
    }

    fputcsv($salida_ok, $header, $sep, '"', "\\");

    $header_err = $header;
    $header_err[] = "errores";
    fputcsv($salida_err, $header_err, $sep, '"', "\\");

    fputcsv($salida_log, array("linea", "archivo", "identificador", "accion"), $sep, '"', "\\");

    $numero_linea = 1;

    while (($fila = fgetcsv($entrada, 0, $sep, '"', "\\")) !== false) {
        $numero_linea++;

        $fila_original = $fila;
        $acciones = array();
        $errores = array();

        $funcion_revision($fila, $acciones, $errores, $comunas, $regiones);

        $identificador = "";

        if (isset($fila_original[0])) {
            $identificador = $fila_original[0];
        }

        for ($i = 0; $i < count($acciones); $i++) {
            fputcsv(
                $salida_log,
                array($numero_linea, $archivo_entrada, $identificador, $acciones[$i]),
                $sep,
                '"',
                "\\"
            );
        }

        if (count($errores) > 0) {
            $fila_err = $fila_original;
            $fila_err[] = implode(" | ", $errores);

            fputcsv($salida_err, $fila_err, $sep, '"', "\\");

            fputcsv(
                $salida_log,
                array($numero_linea, $archivo_entrada, $identificador, "Fila enviada a ERR: " . implode(" | ", $errores)),
                $sep,
                '"',
                "\\"
            );
        }
        else {
            fputcsv($salida_ok, $fila, $sep, '"', "\\");
        }
    }

    fclose($entrada);
    fclose($salida_ok);
    fclose($salida_err);
    fclose($salida_log);

    echo "Procesado: $archivo_entrada\n";
}

// ----------- FUNCIONES DE CADA ARCHIVO -----------------
function revisar_personas_socios(&$fila, &$acciones, &$errores, $comunas, $regiones){
    
    //primero vemos que tenga todas las columnas.
    if (count($fila) != 19) {
        $errores[] = "La cantidad de columnas no es correcta";
        return;
    }

    // Primero revisamos el run_persona, que sea valido y no nulo
    revisar_run($fila, 0, "run_persona", $acciones, $errores);

    // Ahora nombre_completo
    revisar_nombre($fila, 1, "nombre_completo", $errores);
    
    // Revisamos los emails
    revisar_email($fila, 2, "email", $acciones); // Como puede venir nulo no hay que pasarle $errores

    // Revisar telefono celular y alternativo
    revisar_telefono($fila, 3, "telefono_celular", $acciones);
    revisar_telefono($fila, 4, "telefono_alternativo", $acciones);

    // revisar dirección
    revisar_direccion($fila, 5, "direccion_calle", $acciones);

    // revisar nombre comuna
    revisar_comuna($fila, 6, "comuna_nombre", $acciones, $errores, $comunas);
    // revisar codigo región
    revisar_region_codigo($fila, 7, "region_codigo", $acciones, $errores);

    // Revisar nombre de región
    revisar_region_nombre($fila, 8, "region_nombre", $acciones, $errores, $regiones);


    //revisar tipo de persona
    $tipos_persona = array("socio titular", "beneficiario", "adicional", "invitado", "administrativo");
    $tipo_persona = trim($fila[9]);
    $tipo_persona = str_replace("_", " ", $tipo_persona);
    $tipo_persona = str_replace("-", " ", $tipo_persona);
    $tipo_persona = normalizar_texto($tipo_persona);
    $tipo_persona = strtolower($tipo_persona);
    $tipo_persona = limpiar_espacios($tipo_persona);

    // Revisamos que no sea nulo y que sea un tipo de persona valido
    if ($tipo_persona == "") {
    $errores[] = "tipo_persona es obligatorio y viene nulo.";
    }
    elseif (!in_array($tipo_persona, $tipos_persona)) {
        $errores[] = "tipo_persona tiene un valor invalido: $tipo_persona";
    }
    else {
        // Lo devolvemos al csv normalizado y sin espacios extra
        $fila[9] = $tipo_persona;
    }

    // Revisar run_socio_titular y parentesco según tipo_persona
    if ($tipo_persona == "socio titular") {
        if (trim($fila[10]) != "") {
            $acciones[] = "socio titular no debe tener run_socio_titular, se deja nulo.";
            $fila[10] = "";
        }
        if (trim($fila[11]) != "") {
            $acciones[] = "socio titular no debe tener parentesco, se deja nulo.";
            $fila[11] = "";
        }
    }
    elseif ($tipo_persona == "beneficiario") {
        revisar_run($fila, 10, "run_socio_titular", $acciones, $errores);
        $parentesco_original = $fila[11];
        $parentesco = limpiar_espacios($fila[11]);
        if ($parentesco == "") {
            $errores[] = "Tipo de persona beneficiario no tiene parentesco a un socio titular.";
        }
        else {
            if ($parentesco_original != $parentesco) {
                $acciones[] = "parentesco reparado de $parentesco_original a $parentesco";
            }
            $fila[11] = $parentesco;
        }
    }
    elseif ($tipo_persona == "adicional") {

        revisar_run($fila, 10, "run_socio_titular", $acciones, $errores);

        if (trim($fila[11]) != "") {
            $acciones[] = "Persona adicional no debe tener parentesco, se deja nulo.";
            $fila[11] = "";
        }
    }
    else {
        // invitado o administrativo no aplica run_socio_titular ni parentesco
        if (trim($fila[10]) != "") {
            $acciones[] = "run_socio_titular no aplica para $tipo_persona, se deja nulo.";
            $fila[10] = "";
        }

        if (trim($fila[11]) != "") {
            $acciones[] = "parentesco no aplica para $tipo_persona, se deja nulo.";
            $fila[11] = "";
        }
    }
    // Para las de la fecha revisamos el formato.
    revisar_fecha($fila, 12, "fecha_nacimiento", $acciones);
    revisar_fecha($fila, 13, "fecha_inicio_membresia", $acciones);
    revisar_fecha($fila, 14, "fecha_fin_membresia", $acciones);
    // vemos que se cumpla continuidad de fechas y si no las mandamos a nulo
    revisar_rango_fechas_opcional($fila, 13, 14, "fecha_inicio_membresia", "fecha_fin_membresia", $acciones);
    
    // Revisamos usuario
    $tipos_usuario = array("admin", "administrativo", "socio");

    $es_usuario_original = $fila[15];
    $es_usuario_sistema = trim($fila[15]);
    $es_usuario_sistema = normalizar_texto($es_usuario_sistema);
    $es_usuario_sistema = strtoupper($es_usuario_sistema);

    if ($es_usuario_sistema == ""){
        $errores[] = "No se indica si persona es usuario sistema o no.";
    }
    elseif ($es_usuario_sistema == "SI"){
        if ($es_usuario_original != $es_usuario_sistema){
            $acciones[] = "Se cambio es_usuario_sistema de $es_usuario_original a $es_usuario_sistema.";
        }
        $fila[15] = $es_usuario_sistema;

        // Si es usuario del sistema tiene que tener tipo_usuario
        $tipo_usuario_original = $fila[16];
        $tipo_usuario = trim($fila[16]);
        $tipo_usuario = strtolower($tipo_usuario);
        if ($tipo_usuario == ""){
            $errores[] = "Persona marcada como es_usuario_sistema SI, pero tipo_usuario viene nulo.";
        }
        elseif (!in_array($tipo_usuario, $tipos_usuario)){
            $errores[] = "tipo_usuario invalido: $tipo_usuario_original.";
        }
        else{
            if ($tipo_usuario_original != $tipo_usuario){
                $acciones[] = "Se cambio tipo_usuario de $tipo_usuario_original a $tipo_usuario.";
            }

            $fila[16] = $tipo_usuario;
        }
    }
    elseif ($es_usuario_sistema == "NO"){

        if ($es_usuario_original != $es_usuario_sistema){
            $acciones[] = "Se cambio es_usuario_sistema de $es_usuario_original a $es_usuario_sistema.";
        }

        $fila[15] = $es_usuario_sistema;

        // Si no es usuario del sistema tipo_usuario no aplica
        if (trim($fila[16]) != ""){
            $acciones[] = "Persona no es usuario sistema, tipo_usuario se deja nulo.";
            $fila[16] = "";
        }
    }
    else{
        $errores[] = "es_usuario_sistema debe ser SI o NO.";
    }
    // Revisamos clave en texto plano si es que es usuario
    if ($es_usuario_sistema == "SI") {
        // Si es usuario del sistema, debe tener clave
        revisar_texto_obligatorio($fila, 17, "clave_texto_plano", $acciones, $errores);
    }
    else{
        // Si no es usuario del sistema o el parametro era invlaido no tiene clave.
        if (trim($fila[17]) != "") {
            $acciones[] = "Persona no es usuario sistema, clave_texto_plano se deja nula.";
            $fila[17] = "";
        }

    }

    // Revisamos sucursal_base_nombre
    $sucursal_original = $fila[18];
    $sucursal_base_nombre = limpiar_espacios($fila[18]);

    if ($sucursal_base_nombre == "") {
        $errores[] = "sucursal_base_nombre es obligatoria y viene nula.";
    }
    else {
        if ($sucursal_original != $sucursal_base_nombre) {
            $acciones[] = "sucursal_base_nombre reparado de $sucursal_original a $sucursal_base_nombre.";
        }

        $fila[18] = $sucursal_base_nombre;
    }
}

function revisar_sucursales_lugares(&$fila, &$acciones, &$errores, $comunas, $regiones){
    
// ?revisamos sucursal_nombre
    revisar_texto_obligatorio($fila, 0, "sucursal_nombre", $acciones, $errores);
    revisar_direccion($fila, 1, "direccion_sucursal", $acciones); // solo revisa opcional revisamos qu eno haya quedado nulo.
    if ($fila[1] == "") {
        $errores[] = "direccion_sucursal es obligatoria y venía nulo o no se pudo reparar.";
    }
    // Revisamos comuna
    revisar_comuna($fila, 2, "comuna_nombre", $acciones, $errores, $comunas);
    // Revisamos lugar_nombre.
    revisar_texto_obligatorio($fila, 3, "lugar_nombre", $acciones, $errores);
    // Revisamos tipo_lugar
    revisar_texto_obligatorio($fila, 4, "tipo_lugar", $acciones, $errores);
    // Revisamos capacidad_personas
    revisar_numero_obligatorio($fila, 5, "capacidad_personas", $acciones, $errores);
    // Revisamos precio.
    revisar_numero_obligatorio($fila, 6, "precio", $acciones, $errores);
    // Revisamos el descuento de socio y que este dentro del rango permitido
    revisar_decimal_rango($fila, 7, "descuento_socio_evento", 0, 100, $acciones);
    
    // Revisamos el tipo de precio
    $tipos_precio = array("hora", "dia", "día");
    revisar_opcion_opcional($fila, 8, "tipo_precio", $tipos_precio, $acciones);
    if (normalizar_texto($fila[8]) == "dia") {
        $fila[8] = "dia";
    }
    // Revisamos el dia de la seamna
    $dias_semana = array("lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo");
    $original = $fila[9];
    $dia = limpiar_espacios($fila[9]);
    // Puede venir nulo
    if ($dia == "") {
        $fila[9] = "";
    }
    else {
        $dia_normalizado = normalizar_texto($dia);
        if (in_array($dia_normalizado, $dias_semana)) {
            if ($original != $dia_normalizado) {
                $acciones[] = "dia_semana reparado de $original a $dia_normalizado";
            }
            $fila[9] = $dia_normalizado;
        }
        else {
            $acciones[] = "dia_semana tiene un dia invalido ($original), se deja nulo";
            $fila[9] = "";
        }
    }
    revisar_hora($fila, 10, "hora_inicio", $acciones);
    revisar_hora($fila, 11, "hora_termino", $acciones);
    revisar_fecha($fila, 12, "fecha_inicio_vigencia", $acciones);
    revisar_fecha($fila, 13, "fecha_fin_vigencia", $acciones);
    revisar_rango_fechas_opcional($fila, 12, 13, "fecha_inicio_vigencia", "fecha_fin_vigencia", $acciones);
}

function revisar_reservas_arriendos(&$fila, &$acciones, &$errores, $comunas, $regiones){

    // No hacemos nada en el codigo de reserva porque lo generamos en sql.
    
    // Revisamos fecha de reserva
    revisar_fecha($fila, 1, "fecha_reserva", $acciones);

    if (trim($fila[1]) == "") {
        $errores[] = "fecha_reserva es obligatoria y viene nula o invalida.";
    }

    // fecha inicio
    revisar_fecha_hora_con_default($fila, 2, "fecha_inicio", "00:00", $acciones);
    if ($fila[2] == ""){
        $errores[] = "fecha_inicio es obligatoria y viene nula o invalida.";
    }
    // fecha fin
    revisar_fecha_hora_con_default($fila, 3, "fecha_fin", "23:59", $acciones);
    if ($fila[3] == ""){
        $errores[] = "fecha_fin es obligatoria y viene nula o invalida.";
    }

    // Que la de inicio no sea despues de la final
    revisar_rango_fechas_opcional($fila, 2, 3, "fecha_inicio", "fecha_fin", $acciones);

    // Revisar estado de la reserva
    $estados_reserva = array("reservada", "ejecutada", "cancelada");
    $estado_original = $fila[4];
    $estado_reserva = limpiar_espacios($fila[4]);
    $estado_reserva = normalizar_texto($estado_reserva);

    if ($estado_reserva == "") {
        $errores[] = "estado_reserva es obligatorio y viene nulo.";
    }
    elseif (!in_array($estado_reserva, $estados_reserva)) {
        $errores[] = "estado_reserva tiene un valor invalido de $estado_original";
    }
    else {
        if ($estado_original != $estado_reserva) {
            $acciones[] = "estado_reserva reparado de $estado_original a $estado_reserva";
        }

        $fila[4] = $estado_reserva;
    }
    // revisamos run
    revisar_run($fila, 5, "run_reservante", $acciones, $errores);
    // revisamos nombre reservante
    revisar_nombre($fila, 6, "nombre_reservante", $errores);
    // revisamos si es socio
    $es_socio_original = $fila[7];
    $es_socio = limpiar_espacios($fila[7]);
    $es_socio = normalizar_texto($es_socio);
    $es_socio = strtoupper($es_socio);

    if ($es_socio == "") {
        $errores[] = "es_socio es obligatorio y viene nulo.";
    }
    elseif ($es_socio != "SI" && $es_socio != "NO") {
        $errores[] = "es_socio debe ser SI o NO.";
    }
    else {
        if ($es_socio_original != $es_socio) {
            $acciones[] = "es_socio reparado de $es_socio_original a $es_socio";
        }

        $fila[7] = $es_socio;
    }
    // revisar que haya lugar_nombre
    revisar_texto_obligatorio($fila, 8, "lugar_nombre", $acciones, $errores);

    // Reparacion de caso error conocido cabaña
    $lugar_original = $fila[8];
    $fila[8] = reparar_texto_general($fila[8]);
    if ($lugar_original != $fila[8]) {
        $acciones[] = "lugar_nombre reparado de $lugar_original a " . $fila[8];
    }

    // Revisar que haya nombre de la sucursal.
    revisar_texto_obligatorio($fila, 9, "sucursal_nombre", $acciones, $errores);

    // Revisar monto_total
    revisar_numero_obligatorio($fila, 10, "monto_total", $acciones, $errores);


    // Revisar monto_pagado
    revisar_numero_opcional($fila, 11, "monto_pagado", $acciones);

    if (trim($fila[11]) != "" && trim($fila[10]) != "") {
        if (intval($fila[11]) > intval($fila[10])) {
            $acciones[] = "monto_pagado es mayor que monto_total, se deja monto_pagado nulo.";
            $fila[11] = "";
        }
    }

    // Revisamos medio de pago
    $medios_pago = array("efectivo", "transferencia", "tarjeta");
    $medio_original = $fila[12];
    $medio_pago = limpiar_espacios($fila[12]);
    $medio_pago = normalizar_texto($medio_pago);
    if ($medio_pago == "") {
        $fila[12] = "";
    }
    elseif (!in_array($medio_pago, $medios_pago)) {
        $acciones[] = "medio_pago tiene valor no permitido $medio_original, se deja nulo.";
        $fila[12] = "";
    }
    else {
        if ($medio_original != $medio_pago) {
            $acciones[] = "medio_pago reparado de $medio_original a $medio_pago";
        }

        $fila[12] = $medio_pago;
    }

    // Revisar fecha_pago
    revisar_fecha($fila, 13, "fecha_pago", $acciones);

    // Revisar bien los pagos (que se realicen cuando corresponden)
    // Si monto_pagado es 0 o nulo entonces medio_pago y fecha_pago no aplican
    if (trim($fila[11]) == "" || intval($fila[11]) == 0) {

        if (trim($fila[12]) != "") {
            $acciones[] = "monto_pagado es 0 o nulo, medio_pago se deja nulo.";
            $fila[12] = "";
        }

        if (trim($fila[13]) != "") {
            $acciones[] = "monto_pagado es 0 o nulo, fecha_pago se deja nula.";
            $fila[13] = "";
        }
    }

    // Si monto_pagado es mayor que 0 -> medio_pago y fecha_pago
    if (trim($fila[11]) != "" && intval($fila[11]) > 0) {
        if (trim($fila[12]) == "") {
            $errores[] = "Hay monto_pagado mayor que 0, pero medio_pago viene nulo.";
        }

        if (trim($fila[13]) == "") {
            $errores[] = "Hay monto_pagado mayor que 0, pero fecha_pago viene nula o invalida.";
        }
    }
}

function revisar_eventos(&$fila, &$acciones, &$errores, $comunas, $regiones){
    // Para los ids se asume correctitud ya que no se pide revision en enunciado y aun seria seria un trabajo para sql.
    // Revisamos que el evento tenga nombre.
    $nombre_evento_original = $fila[1];
    $nombre_evento = reparar_texto_eventos($fila[1]);

    if ($nombre_evento == "") {
        $errores[] = "nombre_evento es obligatorio y viene nulo.";
    }
    else {
        if ($nombre_evento_original != $nombre_evento) {
            $acciones[] = "nombre_evento reparado de $nombre_evento_original a $nombre_evento";
        }

        $fila[1] = $nombre_evento;
    }
    // Revisar fecha_contratación
    revisar_fecha($fila, 2, "fecha_contratacion", $acciones);
    if (trim($fila[2]) == "") {
        $errores[] = "fecha_contratacion es obligatoria y viene nula o invalida.";
    }
    // Revisar fecha_evento
    revisar_fecha($fila, 3, "fecha_evento", $acciones);
    if (trim($fila[3]) == "") {
        $errores[] = "fecha_evento es obligatoria y viene nula o invalida.";
    }
    // fecha_contratacion no puede ser posterior a fecha_evento.
    revisar_rango_fechas_opcional($fila, 2, 3, "fecha_contratacion", "fecha_evento", $acciones);
    if (trim($fila[2]) == "" || trim($fila[3]) == "") {
        $errores[] = "fecha_contratacion y fecha_evento deben existir y ser coherentes.";
    }
    // Revisar lugar_nombre
    revisar_texto_obligatorio($fila, 4, "lugar_nombre", $acciones, $errores);
    //Revisar sucursal_nombre
    revisar_texto_obligatorio($fila, 5, "sucursal_nombre", $acciones, $errores);
    
    // Revisamos tipo de cliente
    $tipo_cliente_original = $fila[6];
    $tipo_cliente = limpiar_espacios($fila[6]);
    $tipo_cliente = normalizar_texto($tipo_cliente);
    // Normalizamos todos los tipos de empresa a solo empresa
    if ($tipo_cliente == "empresa-institucion" || $tipo_cliente == "empresa institucion") {
        $tipo_cliente = "empresa";
    }

    $tipos_cliente = array("socio", "persona", "empresa");
    if ($tipo_cliente == "") {
        $errores[] = "tipo_cliente es obligatorio y viene nulo.";
    }
    elseif (!in_array($tipo_cliente, $tipos_cliente)) {
        $errores[] = "tipo_cliente tiene un valor invalido $tipo_cliente_original";
    }
    else {
        if ($tipo_cliente_original != $tipo_cliente) {
            $acciones[] = "tipo_cliente reparado de $tipo_cliente_original a $tipo_cliente";
        }
        $fila[6] = $tipo_cliente;
    }

    // Revisamos run cliente
    revisar_run($fila, 7, "rut_cliente", $acciones, $errores);

    $nombre_cliente_original = $fila[8];
    $fila[8] = reparar_texto_eventos($fila[8]);

    if ($tipo_cliente == "socio") {
        // En el csv se vio que el tipo de personas ocio muchas veces venia vacio, pero no lo mandamso a error porque se puede identificar por run
        if (trim($fila[8]) != "") {
            revisar_nombre($fila, 8, "nombre_cliente", $errores);
        }
    }
    else {
        revisar_nombre($fila, 8, "nombre_cliente", $errores);
    }

    if ($nombre_cliente_original != $fila[8]) {
        $acciones[] = "nombre_cliente reparado de $nombre_cliente_original a " . $fila[8];
    }

    // atributos opcionales de si es empresa.
    if ($tipo_cliente == "empresa") {
        // run_contacto_empresa. PAra empresa si tiene que estar.
        revisar_run($fila, 9, "run_contacto_empresa", $acciones, $errores);
        // nombre_contacto_empresa
        $nombre_contacto_original = $fila[10];
        $fila[10] = reparar_texto_eventos($fila[10]);
        revisar_nombre($fila, 10, "nombre_contacto_empresa", $errores);

        if ($nombre_contacto_original != $fila[10]) {
            $acciones[] = "nombre_contacto_empresa reparado de $nombre_contacto_original a " . $fila[10];
        }
        // cargo_contacto
        $cargo_original = $fila[11];
        $cargo_contacto = reparar_texto_eventos($fila[11]);
        if ($cargo_contacto == "") {
            $errores[] = "cargo_contacto es obligatorio para cliente empresa.";
        }
        else {
            if ($cargo_original != $cargo_contacto) {
                $acciones[] = "cargo_contacto reparado de $cargo_original a $cargo_contacto";
            }
            $fila[11] = $cargo_contacto;
        }
    }
    else {
        // Si no es empresa que sean nulo
        if (trim($fila[9]) != "") {
            $acciones[] = "run_contacto_empresa no aplica para tipo_cliente $tipo_cliente, se deja nulo.";
            $fila[9] = "";
        }

        if (trim($fila[10]) != "") {
            $acciones[] = "nombre_contacto_empresa no aplica para tipo_cliente $tipo_cliente, se deja nulo.";
            $fila[10] = "";
        }

        if (trim($fila[11]) != "") {
            $acciones[] = "cargo_contacto no aplica para tipo_cliente $tipo_cliente, se deja nulo.";
            $fila[11] = "";
        }
    }

    // asistentes_texto
    $asistentes_original = $fila[12];
    $asistentes_texto = trim($fila[12]);
    if ($asistentes_texto == "") {
        $fila[12] = "";
    }
    else {
        $asistentes = explode(";", $asistentes_texto);
        $asistentes_limpios = array();
        for ($i = 0; $i < count($asistentes); $i++) {
            $asistente = reparar_texto_eventos($asistentes[$i]);
            if ($asistente != "") {
                $partes = explode(" ", $asistente);
                $partes_validas = array();
                for ($j = 0; $j < count($partes); $j++) {
                    $parte = trim($partes[$j]);
                    if ($parte != "") {
                        $partes_validas[] = $parte;
                    }
                }
                if (count($partes_validas) < 2) {
                    $acciones[] = "Asistente $asistente no tiene formato nombre-apellido, se elimina de asistentes_texto.";
                }
                else {
                    $asistentes_limpios[] = $asistente;
                }
            }
        }
        $asistentes_final = implode(";", $asistentes_limpios);
        if ($asistentes_original != $asistentes_final) {
            $acciones[] = "asistentes_texto fue limpiado.";
        }
        $fila[12] = $asistentes_final;
    }

    // Para los pagos:
    revisar_numero_obligatorio($fila, 13, "monto_total_evento", $acciones, $errores);
    revisar_numero_opcional($fila, 14, "monto_pagado_reserva", $acciones);
    revisar_numero_opcional($fila, 15, "monto_pagado_ejecucion", $acciones);

    // Y igual que antes revisamso la coherencia
    if (trim($fila[13]) != "" && trim($fila[14]) != "") {
        if (intval($fila[14]) > intval($fila[13])) {
            $acciones[] = "monto_pagado_reserva es mayor que monto_total_evento, se deja nulo.";
            $fila[14] = "";
        }
    }
    if (trim($fila[13]) != "" && trim($fila[15]) != "") {
        if (intval($fila[15]) > intval($fila[13])) {
            $acciones[] = "monto_pagado_ejecucion es mayor que monto_total_evento, se deja nulo.";
            $fila[15] = "";
        }
    }
    if (trim($fila[13]) != "" && trim($fila[14]) != "" && trim($fila[15]) != "") {
        if (intval($fila[14]) + intval($fila[15]) > intval($fila[13])) {
            $acciones[] = "La suma de monto_pagado_reserva y monto_pagado_ejecucion supera monto_total_evento, se deja monto_pagado_ejecucion nulo.";
            $fila[15] = "";
        }
    }


}

function revisar_pagos_membresias(&$fila, &$acciones, &$errores, $comunas, $regiones ){

    // Reviamos que tenga id. No se encontraron errores.
    revisar_numero_obligatorio($fila, 0, "pago_id", $acciones, $errores);
    // revisar el run
    revisar_run($fila, 1, "run_socio_titular", $acciones, $errores);
    // revisar nombre_socio
    revisar_nombre($fila, 2, "nombre_socio_titular", $errores);
    // revisar año (anio) membresia
    revisar_numero_obligatorio($fila, 3, "anio_membresia", $acciones, $errores);
    if (trim($fila[3]) != "") {
        $anio = intval($fila[3]);
        if ($anio < 1950 || $anio > 2050) {
            $errores[] = "anio_membresia debe estar entre 1950 y 2050.";
        }
    }

    // revisar cuota y que sea n° mes valido
    revisar_numero_obligatorio($fila, 4, "mes_cuota", $acciones, $errores);
    if (trim($fila[4]) != "") {
        $mes = intval($fila[4]);
        if ($mes < 1 || $mes > 12) {
            $errores[] = "mes_cuota debe estar entre 1 y 12.";
        }
    }

    // fecha vencimiento
    revisar_fecha($fila, 5, "fecha_vencimiento", $acciones);
    if (trim($fila[5]) == "") {
        $errores[] = "fecha_vencimiento es obligatoria y viene nula o invalida.";
    }
    // monto membresia 
    revisar_numero_obligatorio($fila, 6, "monto_membresia", $acciones, $errores);
    
    //montos adicionales
    revisar_numero_opcional($fila, 7, "monto_adicionales", $acciones);
    // Si viene nulo, para revisar la suma lo interpretamos como 0
    if (trim($fila[7]) == "") {
        $monto_adicionales = 0;
    }

    // monto total
    revisar_numero_obligatorio($fila, 8, "monto_total", $acciones, $errores);

    // Revisamos que los montos sean coherentes y den resultados correctos al sumar.
    if (trim($fila[6]) != "" && trim($fila[8]) != "") {
        $monto_membresia = intval($fila[6]);
        $monto_total = intval($fila[8]);
        if (trim($fila[7]) == "") {
            $monto_adicionales = 0;
        }
        else {
            $monto_adicionales = intval($fila[7]);
        }
        if ($monto_membresia < 0) {
            $errores[] = "monto_membresia no puede ser negativo.";
        }
        if ($monto_adicionales < 0) {
            $errores[] = "monto_adicionales no puede ser negativo.";
        }
        if ($monto_total < 0) {
            $errores[] = "monto_total no puede ser negativo.";
        }
        if ($monto_membresia + $monto_adicionales != $monto_total) {
            $errores[] = "monto_total debe ser igual a monto_membresia + monto_adicionales.";
        }
    }

    // Estado_pago (pagado o atrasado)
    $estados_pago = array("pagado", "atrasado");
    revisar_opcion_opcional($fila, 9, "estado_pago", $estados_pago, $acciones);
    if (trim($fila[9]) == "") {
        $errores[] = "estado_pago es obligatorio y viene nulo o invalido.";
    }

    // fecha_pago
    revisar_fecha($fila, 10, "fecha_pago", $acciones);

    // medio_pago
    $medios_pago = array("transferencia", "tarjeta", "tarjeta_credito", "efectivo");
    revisar_opcion_opcional($fila, 11, "medio_pago", $medios_pago, $acciones);

    // Coherencia según estado_pago
    if ($fila[9] == "pagado") {
        if (trim($fila[10]) == "") {
            $errores[] = "estado_pago es pagado, pero fecha_pago viene nula o invalida.";
        }
        if (trim($fila[11]) == "") {
            $errores[] = "estado_pago es pagado, pero medio_pago viene nulo o invalido.";
        }
    }
    if ($fila[9] == "atrasado") {
        if (trim($fila[10]) != "") {
            $acciones[] = "estado_pago es atrasado, fecha_pago no aplica y se deja nula.";
            $fila[10] = "";
        }
        if (trim($fila[11]) != "") {
            $acciones[] = "estado_pago es atrasado, medio_pago no aplica y se deja nulo.";
            $fila[11] = "";
        }
    }
}

function revisar_cargos_administrativos(&$fila, &$acciones, &$errores, $comunas, $regiones){

    // revisamos el rut
    revisar_run($fila, 0, "run_persona", $acciones, $errores);

    // revisamos el nombre de la sucursal.
    revisar_texto_obligatorio($fila, 1, "sucursal_nombre", $acciones, $errores);

    // Revisamos el nombre del cargo, se encontraron errores similares a los de eventos. Usamos su funcion.
    $cargo_original = $fila[2];
    $nombre_cargo = reparar_texto_eventos($fila[2]);

    if ($nombre_cargo == "") {
        $errores[] = "nombre_cargo es obligatorio y viene nulo.";
    }
    else {
        if ($cargo_original != $nombre_cargo) {
            $acciones[] = "nombre_cargo reparado de $cargo_original a $nombre_cargo";
        }

        $fila[2] = $nombre_cargo;
    }

    // revisamos fecha inicio cargo
    revisar_fecha($fila, 3, "fecha_inicio_cargo", $acciones);
    if (trim($fila[3]) == "") {
        $errores[] = "fecha_inicio_cargo es obligatoria y viene nula o invalida.";
    }

    // revisamos fecha termino cargo
    revisar_fecha($fila, 4, "fecha_termino_cargo", $acciones);

    // revisamos que fechas sean coherentes
    revisar_rango_fechas_opcional($fila, 3, 4, "fecha_inicio_cargo", "fecha_termino_cargo", $acciones);
    // Avisar error si no paso prueba de rango
    if (trim($fila[3]) == "") {
        $errores[] = "fecha_inicio_cargo debe existir y ser coherente con fecha_termino_cargo.";
    }

}

// ----------- FUNCIONES AUXILIARES -----------
// Se comparten entre archivos
// En orden de implementación para la revisión de cada archivo.

function revisar_run(&$fila, $pos, $columna, &$acciones, &$errores) {
    //guardamos el valor original
    $original = $fila[$pos];

    //valor corregido si hace falta
    $fila[$pos] = arreglar_run($fila[$pos]);

    if ($original != $fila[$pos]){
        $acciones[] = "Se cambio $columna de $original a ". $fila[$pos];
    }
    if($fila[$pos] == ""){
        $errores[] = "$columna no se pudo arreglar o estaba vacio";
        return;
    }
    if(!run_valido($fila[$pos])){
        $errores[] = "$columna no es valido";
    }
}

function arreglar_run($run) {
    $run = trim($run); // borar espacios al principio y al final
    $run = strtoupper($run); // por ruts terminando en k
    $run = str_replace(".", "", $run); // le quitamos los puntos
    $run = str_replace(" ", "", $run); // le quitamos los espacios

        // validamos formato run con regex
    if (!preg_match('/^[0-9]{1,8}-[0-9K]$/', $run)) {
        return "";
    }

    return $run;
}

function run_valido($run) {
    // Validamos con regex
    if (!preg_match('/^[0-9]{1,8}-[0-9K]$/', $run)) {
        return false;
    }

    //Separamos el rut del digito verificador
    $partes = explode("-", $run);
    $numero = $partes[0];
    $dv = $partes[1];

    //lo pasamos por nuestra función que calcula dv
    if ($dv == calcular_dv($numero)) {
        return true;
    }

    return false;
}

function calcular_dv($numero) {
    $suma = 0;
    $multiplicador = 2;

    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma = $suma + intval($numero[$i]) * $multiplicador;
        $multiplicador = $multiplicador + 1;
        if ($multiplicador > 7) {
            $multiplicador = 2;
        }
    }

    $resto = $suma % 11;
    $resultado = 11 - $resto;

    if ($resultado == 11) {
        return "0";
    }
    if ($resultado == 10) {
        return "K";
    }

    return strval($resultado);
}

function revisar_nombre(&$fila, $pos, $columna, &$errores){
    $fila[$pos] = trim($fila[$pos]);

    if ($fila[$pos] == "") {
        $errores[] = "$columna es obligatorio y viene nulo.";
        return;
    }

    $partes = explode(" ", $fila[$pos]);
    $partes_validas = array();

    //Revisamos las partes validas en caso de que haya nombres separados con multiples espacios o mas de un nombre/apellido
    for ($i = 0; $i < count($partes); $i++) {
        $parte = trim($partes[$i]);

        if ($parte != "") {
            $partes_validas[] = $parte;
        }
    }

    if (count($partes_validas) < 2) {
        $errores[] = "$columna no esta en formato nombre-apellido.";
    }
}

function revisar_email(&$fila, $pos, $columna, &$acciones){
    $original = $fila[$pos];

    // Puede venir nulo asi que no damos error si esta vacio el string.
    $email = trim($fila[$pos]);
    if ($email == ""){
        $fila[$pos] = "";
        return;
    }

    $email = strtolower($email);

    //Vemos que no tenga dobles:
    while(strpos($email,"..") !== false){
        $email = str_replace("..", ".", $email);
    }
    while(strpos($email,"@@") !== false){
        $email = str_replace("@@", "@", $email);
    }

    // Vemos si se hico alguna correción
    if ($original != $email){
        $acciones[] = "Se corrigio $columna de $original a $email";
    }

    // Revisamos con filter_var
    if ($email != "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $acciones[] = "email invalido, se deja nulo";
        $fila[$pos] = "";
        return;
    }

    // Vemos que no pase el largo máximo
    if (strlen($email) > 254) {
        $acciones[] = "$columna supera largo maximo RFC 5321, se deja nulo.";
        $fila[$pos] = "";
        return;
    }

    // Si paso todas las validaciones o se reparo, guardar
    $fila[$pos] = $email;
}

function revisar_telefono(&$fila, $pos, $columna, &$acciones){
    // telefonos de 8 cifras se le añade un 9 y se asume que usan la notación antigua. Puede venir nulo
    $original = $fila[$pos];
    $numero = trim($fila[$pos]);

    if ($numero == ""){
        $fila[$pos] = "";
        return;
    }

    //Vemos que no tenga caracteres raros
    $numero = str_replace(" ", "", $numero);
    $numero = str_replace(".0", "", $numero);
    $numero = str_replace(".", "", $numero);
    $numero = str_replace("-", "", $numero);
    $numero = str_replace(",", "", $numero);

    // revisar que sean solo numeros,
    if (preg_match('/^[0-9]+$/', $numero)) {
        // Tiene solo numeros, continuar revisión
    } else {
        $acciones[] = "Numero invalido en $columna, se reemplaza por nulo";
        $fila[$pos] = "";
        return;
    }
    // Revisar el largo
    if(strlen($numero) == 8){
        $numero = "9" . $numero;
    }
    elseif(strlen($numero) == 9){
        //ok

    }else{
        $acciones[] = "Numero con largo invalido en $columna, se reemplaza por nulo";
        $fila[$pos] = "";
        return;
        }

    if($original != $numero){
        $acciones[] = "$columna reparado de $original a $numero";
    }
    $fila[$pos] = $numero;
}

function revisar_direccion(&$fila, $pos, $columna, &$acciones){
    $original = $fila[$pos];
    $direccion = trim($fila[$pos]);

    $direccion = str_replace(chr(150), "ñ", $direccion);
    $direccion = str_replace("Vicu�a", "Vicuña", $direccion);
    $direccion = str_replace("Vicu\x96a", "Vicuña", $direccion);

    if($direccion == ""){
        // puede ser nulo
        $fila[$pos] = "";
        return;
    }

    // Se inspeccionó que el unico caso de dirección con caracter dañado era Vicuña Mackenna, por lo que se puede reparar este caso específico.
    if (strpos($direccion, "Vicu�a") !== False) {
        $direccion = str_replace("Vicu�a", "Vicuña", $direccion);
}

    // separar letras pegadas a numeros, y numeros pegados a letras.
    $direccion = preg_replace('/([A-Za-zÁÉÍÓÚáéíóúÑñ])([0-9])/', '$1 $2', $direccion);
    $direccion = preg_replace('/([0-9])([A-Za-zÁÉÍÓÚáéíóúÑñ])/', '$1 $2', $direccion);

    // Quitamos espacios múltiples.
    $partes = explode(" ", $direccion);
    $partes_validas = array();

    // Contamos las partes validas, es deceir no nulas despues de separar los espacios
    for ($i = 0; $i < count($partes); $i++){
        $parte = trim($partes[$i]);

        if ($parte != ""){
            $partes_validas[] = $parte;
        }
    }
    // Unimos todo y lo guardamos como direccion revisada
    $direccion_rev = implode(" ", $partes_validas);

    // Revisar si hay al menos una parte con letras y una parte con números (osea si es valida)
    $tiene_letras = false;
    $tiene_numero = false;

    for ($i = 0; $i < count($partes_validas); $i++){
        if (preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]/', $partes_validas[$i])){
            $tiene_letras = true;
        }

        if (preg_match('/[0-9]/', $partes_validas[$i])){
            $tiene_numero = true;
        }
    }
    if (!$tiene_letras){
        $acciones[] = "$columna no tiene parte de texto, se reemplaza nulo.";
        $fila[$pos] = "";
        return;
    }
    if (!$tiene_numero){
        $acciones[] = "$columna no tiene numeración, se reempalza nulo";
        $fila[$pos] = "";
        return;
    }

    // Reemplazamos con la revisada si se reparo.
    if ($original != $direccion_rev){
        $acciones[] = "$columna reparada de $original a $direccion_rev";
    }

    $fila[$pos] = $direccion_rev;
}

function revisar_comuna(&$fila, $pos, $columna, &$acciones, &$errores, $comunas){
    //Normalizamos el texto de el nombre original de la comuna y de la comuna en la columna, los pasamos a lower y comparamos
    $original = $fila[$pos];;
    $comuna = trim($fila[$pos]);

    if ($comuna == ""){
        $errores[] = "$columna es obligatoria y viene nula.";
        return;
    }

    $encontrada = false;
    $comuna_real = "";

    // Normalizamos la comuna del csv y la comuna original del listado de comunas oficiales para compararlas
    for ($i = 0; $i < count($comunas); $i++){
        if (normalizar_texto($comuna) == normalizar_texto($comunas[$i])){
            $encontrada = true;
            // guardamos la no normalizada (version reparada) a partir de la lista.
            $comuna_real= $comunas[$i];
        }
    }

    // Si no se encontro la comuna reportar error.
    if (!$encontrada){
        $errores[] = "$columna no pertenece a la lista oficial de comunas.";
        return;
    }
    // Si se arreglo reportar accion.
    if ($original != $comuna_real){
        $acciones[] = "$columna reparada de $original a $comuna_real";
    }

    $fila[$pos] = $comuna_real;

}

function cargar_comunas($nombre_archivo, $sep){
    $comunas = array();
    $archivo = fopen($nombre_archivo, "r");
    if (!$archivo) {
        echo "No se pudo abrir $nombre_archivo\n";
        return $comunas;
    }
    $header = fgetcsv($archivo, 0, $sep, '"', "\\");
    while (($fila = fgetcsv($archivo, 0, $sep, '"', "\\")) !== false) {
        $comuna = trim($fila[1]);
        if ($comuna != "") {
            $comunas[] = $comuna;
        }
    }
    fclose($archivo);
    return $comunas;
}

function cargar_regiones($nombre_archivo, $sep){
    $regiones = array();
    $archivo = fopen($nombre_archivo, "r");
    if (!$archivo) {
        echo "No se pudo abrir $nombre_archivo\n";
        return $regiones;
    }
    $header = fgetcsv($archivo, 0, $sep, '"', "\\");
    while (($fila = fgetcsv($archivo, 0, $sep, '"', "\\")) !== false) {
        $region = trim($fila[3]);
        if ($region != "" && !in_array($region, $regiones)) {
            $regiones[] = $region;
        }
    }
    fclose($archivo);
    return $regiones;
}

function normalizar_texto($texto) {

    $texto = trim($texto);
    $texto = strtolower($texto);

    $texto = str_replace("á", "a", $texto);
    $texto = str_replace("Á", "a", $texto);

    $texto = str_replace("é", "e", $texto);
    $texto = str_replace("É", "e", $texto);

    $texto = str_replace("í", "i", $texto);
    $texto = str_replace("Í", "i", $texto);

    $texto = str_replace("ó", "o", $texto);
    $texto = str_replace("Ó", "o", $texto);

    $texto = str_replace("ú", "u", $texto);
    $texto = str_replace("Ú", "u", $texto);

    $texto = str_replace("ñ", "n", $texto);
    $texto = str_replace("Ñ", "n", $texto);

    return $texto;
}

function revisar_region_codigo(&$fila, $pos, $columna, &$acciones, &$errores){
    $original = $fila[$pos];
    $region_codigo = trim($fila[$pos]);

    if ($region_codigo == ""){
        $errores[] = "$columna es obligatoria y viene nula.";
        return;
    
    }

    //Eliminamos cualquier caracter quen o sea un numero
    $region_codigo= preg_replace('/[^0-9]/', "", $region_codigo);
    // Si limpiamos y no quedan caracteres validos tirar error.
    if ($region_codigo == ""){
        $errores[] = "$columna no contiene ningun caracter valido (numerico).";
        return;
    }

    // revisamos rango pasandolo a int
    $region_c_num = intval($region_codigo);

    if ($region_c_num < 1 || $region_c_num > 16){
        $errores[] = "$columna debe estar entre 1 y 16.";
        return;
    }
    // lo volvemos a dejar como str y sin ceros adelante si es que habian

    $region_codigo = strval($region_c_num);
    if ($original != $region_codigo){
        $acciones[] = "$columna reparado de $original a $region_codigo";
    }

    $fila[$pos] = $region_codigo;
}

function revisar_region_nombre(&$fila, $pos, $columna, &$acciones, &$errores, $regiones){
    $original = $fila[$pos];
    $region = trim($fila[$pos]);

    if ($region == ""){
        $errores[] = "$columna es obligatoria y viene nula.";
        return;
    }

    $encontrada = false;
    $region_real = "";

    // Normalizamos la región del CSV y la región oficial para compararlas.
    for ($i = 0; $i < count($regiones); $i++){
        if (normalizar_texto($region) == normalizar_texto($regiones[$i])){
            $encontrada = true;
            // Guardamos la version oficial del array no normalizada.
            $region_real = $regiones[$i];
        }
    }

    if (!$encontrada){
        $errores[] = "$columna no pertenece a la lista oficial de regiones.";
        return;
    }
    if ($original != $region_real){
        $acciones[] = "$columna reparada de $original a $region_real";
    }

    $fila[$pos] = $region_real;
}

function revisar_fecha(&$fila, $pos, $columna, &$acciones){
    $original = $fila[$pos];
    $fecha = trim($fila[$pos]);

    // Puede venir nulo
    if ($fecha == ""){
        $fila[$pos] = "";
        return;
    }

    // Caso correcto: YYYY-MM-DD
    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $fecha)) {
        $partes = explode("-", $fecha);

        // Por si acaso use año como anio para evitar errores
        $anio = intval($partes[0]);
        $mes = intval($partes[1]);
        $dia = intval($partes[2]);

        if (checkdate($mes, $dia, $anio)) {
            $fila[$pos] = $fecha;
            return;
        } else {
            $acciones[] = "$columna invalida, se reemplaza por nulo";
            $fila[$pos] = "";
            return;
        }
    }

    // Para tratar de reparar la mayor cantidad de fechas posibles inspeccinoamos por casos comunes.
    // Caso de DD-MM-YY
    if (preg_match('/^[0-9]{2}-[0-9]{2}-[0-9]{2}$/', $fecha)) {
        $partes = explode("-", $fecha);
        $dia = intval($partes[0]);
        $mes = intval($partes[1]);
        $anio_2dig = intval($partes[2]);

        if ($anio_2dig <= 26) {
            $anio = 2000 + $anio_2dig;
        } else {
            $anio = 1900 + $anio_2dig;
        }
        if (checkdate($mes, $dia, $anio)) {
            $fecha_reparada = sprintf("%04d-%02d-%02d", $anio, $mes, $dia); // Como en C!
            if ($original != $fecha_reparada) {
                $acciones[] = "$columna reparada de $original a $fecha_reparada";
            }
            $fila[$pos] = $fecha_reparada;
            return;
        } else {
            $acciones[] = "$columna invalida, se reemplaza por nulo";
            $fila[$pos] = "";
            return;
        }
    }

    
    // Caso de DD-MM-YYYY
    if (preg_match('/^[0-9]{2}-[0-9]{2}-[0-9]{4}$/', $fecha)) {
        $partes = explode("-", $fecha);
        $dia = intval($partes[0]);
        $mes = intval($partes[1]);
        $anio = intval($partes[2]);

        if (checkdate($mes, $dia, $anio)) {
            $fecha_reparada = sprintf("%04d-%02d-%02d", $anio, $mes, $dia);
            if ($original != $fecha_reparada) {
                $acciones[] = "$columna reparada de $original a $fecha_reparada";
            }
            $fila[$pos] = $fecha_reparada;
            return;
        } else {
            $acciones[] = "$columna invalida, se reemplaza por nulo";
            $fila[$pos] = "";
            return;
        }
    }

    // Casos donde faltan datos
    $acciones[] = "$columna con formato incompleto o que no se pudo reparar, se reemplaza por nulo";
    $fila[$pos] = "";
}

function revisar_rango_fechas_opcional(&$fila, $pos_inicio, $pos_fin, $col_inicio, $col_fin, &$acciones) {
    if (trim($fila[$pos_inicio]) == "" || trim($fila[$pos_fin]) == "") {
        return;
    }

    if ($fila[$pos_inicio] > $fila[$pos_fin]) {
        $acciones[] = "$col_inicio no puede ser posterior a $col_fin. Se dejan nulo.";
        $fila[$pos_inicio] = "";
        $fila[$pos_fin] = "";
    }
}

function revisar_texto_obligatorio(&$fila, $pos, $columna, &$acciones, &$errores){
    $original = $fila[$pos];
    $texto = limpiar_espacios($original);
    if ($texto == ""){
        $errores[] = "$columna es obligatorio y viene nulo.";
    }
    elseif ($original != $texto) {
        $acciones[] = "$columna reparado de $original a $texto";
    }

    $fila[$pos] = $texto;
}

function limpiar_espacios($texto) {
    $texto = trim($texto);
    while (strpos($texto, "  ") !== false) {
        $texto = str_replace("  ", " ", $texto);
    }
    return $texto;
}

function revisar_numero_obligatorio(&$fila, $pos, $columna, &$acciones, &$errores) {
    $original = $fila[$pos];
    $numero = trim($fila[$pos]);

    if ($numero == "") {
        $errores[] = "$columna es obligatorio y viene nulo.";
        $fila[$pos] = "";
        return;
    }

    // Eliminamos puntos, espacios y comas por si vienen como 1.000 o 1,000
    $numero = str_replace(" ", "", $numero);
    $numero = str_replace(".", "", $numero);
    $numero = str_replace(",", "", $numero);

    if (!preg_match('/^[0-9]+$/', $numero)) {
        $errores[] = "$columna debe ser un numero.";
        return;
    }

    if ($original != $numero) {
        $acciones[] = "$columna reparado de $original a $numero";
    }

    $fila[$pos] = $numero;
}

function revisar_numero_opcional(&$fila, $pos, $columna, &$acciones) {
    $original = $fila[$pos];
    $numero = trim($fila[$pos]);

    if ($numero == "") {
        $fila[$pos] = "";
        return;
    }

    // Eliminamos puntos espacios y comas por si vienen como 1.000 o 1,000
    $numero = str_replace(" ", "", $numero);
    $numero = str_replace(".", "", $numero);
    $numero = str_replace(",", "", $numero);

    if (!preg_match('/^[0-9]+$/', $numero)) {
        $acciones[] = "$columna debe ser un numero y recibe formato invalido, se cambia a nulo.";
        $fila[$pos] = "";
        return;
    }

    if ($original != $numero) {
        $acciones[] = "$columna reparado de $original a $numero";
    }

    $fila[$pos] = $numero;
}

function revisar_hora_obligatoria(&$fila, $pos, $columna, &$acciones, &$errores) {
    $original = $fila[$pos];
    $hora = trim($fila[$pos]);

    if ($hora == "") {
        $errores[] = "$columna es obligatoria y viene nula.";
        return;
    }

    // Revisamos que este en formato HH:MM
    if (!preg_match('/^[0-9]{1,2}:[0-9]{2}$/', $hora)) {
        $errores[] = "$columna debe tener formato HH:MM.";
        return;
    }

    $partes = explode(":", $hora);
    $hh = intval($partes[0]);
    $mm = intval($partes[1]);

    if ($hh < 0 || $hh > 23 || $mm < 0 || $mm > 59) {
        $errores[] = "$columna tiene una hora invalida.";
        return;
    }

    $hora_reparada = sprintf("%02d:%02d", $hh, $mm);

    if ($original != $hora_reparada) {
        $acciones[] = "$columna reparada de $original a $hora_reparada";
    }

    $fila[$pos] = $hora_reparada;
}
function revisar_hora(&$fila, $pos, $columna, &$acciones) {
    $original = $fila[$pos];
    $hora = trim($fila[$pos]);

    if ($hora == "") {
        $fila[$pos] = "";
        return;
    }

    if (preg_match('/^[0-9]{1,2}:[0-9]{2}(:[0-9]{2})?$/', $hora)) {
        $partes = explode(":", $hora);
        $h = intval($partes[0]);
        $m = intval($partes[1]);

        if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59) {
            $hora_reparada = sprintf("%02d:%02d", $h, $m);
            if ($original != $hora_reparada) {
                $acciones[] = "$columna reparada de $original a $hora_reparada";
            }
            $fila[$pos] = $hora_reparada;
            return;
        }
    }

    $acciones[] = "$columna invalida, se reemplaza por nulo";
    $fila[$pos] = "";
}

function revisar_rango_horas(&$fila, $pos_inicio, $pos_fin, $col_inicio, $col_fin, &$acciones) {
    $inicio = trim($fila[$pos_inicio]);
    $fin = trim($fila[$pos_fin]);
    if ($inicio == "" || $fin == "") {
        return;
    }

    if ($inicio >= $fin) {
        $acciones[] = "$col_inicio debe ser anterior a $col_fin. Se deja nulo.";
    }
}

function revisar_decimal_rango(&$fila, $pos, $columna, $min, $max, &$acciones) {
    $original = $fila[$pos];
    $valor = trim($fila[$pos]);

    if ($valor == "") {
        $fila[$pos] = "";
        return;
    }

    $valor = str_replace("%", "", $valor);
    $valor = str_replace(",", ".", $valor);
    $valor = str_replace(" ", "", $valor);

    if (!is_numeric($valor)) {
        $acciones[] = "$columna debe ser decimal, se asume que no hay descuento.";
        $fila[$pos] = "0";
        return;
    }

    $num = floatval($valor);
    if ($num < $min || $num > $max) {
        $acciones[] = "$columna debe estar entre $min y $max, se descarta descuento";
        $fila[$pos] = "0";
        return;
    }

    $valor_final = strval($num);
    if ($original != $valor_final) {
        $acciones[] = "$columna reparado de $original a $valor_final";
    }
    $fila[$pos] = $valor_final;
}

function revisar_opcion_opcional(&$fila, $pos, $columna, $opciones, &$acciones) {
    $original = $fila[$pos];
    $valor = limpiar_espacios($fila[$pos]);

    if ($valor == "") {
        $fila[$pos] = "";
        return;
    }

    $valor_norm = normalizar_texto($valor);
    $encontrado = false;
    $valor_real = "";

    for ($i = 0; $i < count($opciones); $i++) {
        if ($valor_norm == normalizar_texto($opciones[$i])) {
            $encontrado = true;
            $valor_real = $opciones[$i];
        }
    }

    if (!$encontrado) {
        $acciones[] = "$columna tiene valor no permitido $valor, se deja nulo.";
        $fila[$pos] = "";
        return;
    }

    if ($original != $valor_real) {
        $acciones[] = "$columna reparado de $original a $valor_real";
    }
    $fila[$pos] = $valor_real;
}

function revisar_fecha_hora(&$fila, $pos, $columna, &$acciones) {
    $original = $fila[$pos];
    $valor = limpiar_espacios($fila[$pos]);

    // Puede venir nulo
    if ($valor == "") {
        $fila[$pos] = "";
        return;
    }

    // Separamos fecha y hora
    $partes = explode(" ", $valor);

    if (count($partes) != 2) {
        $acciones[] = "$columna debe venir en formato fecha hora, se reemplaza por nulo.";
        $fila[$pos] = "";
        return;
    }

    $fecha = $partes[0];
    $hora = $partes[1];

    // Usamos tus funciones ya existentes, pero sobre arrays temporales
    $fila_fecha = array($fecha);
    $fila_hora = array($hora);

    revisar_fecha($fila_fecha, 0, $columna, $acciones);
    revisar_hora($fila_hora, 0, $columna, $acciones);

    // Si alguna quedó nula, la fecha hora completa es inválida
    if ($fila_fecha[0] == "" || $fila_hora[0] == "") {
        $acciones[] = "$columna tiene fecha u hora invalida, se reemplaza por nulo.";
        $fila[$pos] = "";
        return;
    }

    // Rearmamos
    $fecha_hora = $fila_fecha[0] . " " . $fila_hora[0];

    if ($original != $fecha_hora) {
        $acciones[] = "$columna reparada de $original a $fecha_hora";
    }

    $fila[$pos] = $fecha_hora;
}

// funcion reparar casos notables arreglables de eventos.csv

// -- Añadidas despues de ejecucino para asegurar correctitud

function reparar_texto_eventos($texto) {
    $texto = limpiar_espacios($texto);

    $texto = str_replace("Ã¡", "á", $texto);
    $texto = str_replace("Ã©", "é", $texto);
    $texto = str_replace("Ã­", "í", $texto);
    $texto = str_replace("Ã³", "ó", $texto);
    $texto = str_replace("Ãº", "ú", $texto);

    $texto = str_replace("Ã", "Á", $texto);
    $texto = str_replace("Ã‰", "É", $texto);
    $texto = str_replace("Ã", "Í", $texto);
    $texto = str_replace("Ã“", "Ó", $texto);
    $texto = str_replace("Ã", "Ó", $texto);
    $texto = str_replace("Ãš", "Ú", $texto);

    $texto = str_replace("Ã±", "ñ", $texto);
    $texto = str_replace("Ã‘", "Ñ", $texto);

    $texto = str_replace("Ã®", "î", $texto);

    $texto = str_replace("Â°", "°", $texto);
    $texto = str_replace("Âº", "º", $texto);
    $texto = str_replace("Âª", "ª", $texto);
    $texto = str_replace("Â¿", "¿", $texto);
    $texto = str_replace("Â¡", "¡", $texto);

    $texto = str_replace(chr(150), "ñ", $texto);

    return $texto;
}

function revisar_fecha_hora_con_default(&$fila, $pos, $columna, $hora_default, &$acciones) {
    $original = $fila[$pos];
    $valor = limpiar_espacios($fila[$pos]);

    if ($valor == "") {
        $fila[$pos] = "";
        return;
    }

    $partes = explode(" ", $valor);

    // Caso 1: viene fecha y hora
    if (count($partes) == 2) {
        revisar_fecha_hora($fila, $pos, $columna, $acciones);
        return;
    }

    // Caso 2: viene solo fecha
    if (count($partes) == 1) {
        $fila_fecha = array($valor);

        revisar_fecha($fila_fecha, 0, $columna, $acciones);

        if ($fila_fecha[0] == "") {
            $acciones[] = "$columna tiene fecha invalida, se reemplaza por nulo.";
            $fila[$pos] = "";
            return;
        }

        $fecha_hora = $fila_fecha[0] . " " . $hora_default;

        if ($original != $fecha_hora) {
            $acciones[] = "$columna venia sin hora, se reparo de $original a $fecha_hora.";
        }

        $fila[$pos] = $fecha_hora;
        return;
    }

    $acciones[] = "$columna tiene formato invalido, se reemplaza por nulo.";
    $fila[$pos] = "";
}

function reparar_texto_general($texto) {
    $texto = limpiar_espacios($texto);

    $texto = str_replace("Caba�a", "Cabaña", $texto);
    $texto = str_replace("Caba\x96a", "Cabaña", $texto);
    $texto = str_replace(chr(150), "ñ", $texto);

    return $texto;
}