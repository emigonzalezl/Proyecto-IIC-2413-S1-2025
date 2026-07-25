<?php
// Funciones comunes del proyecto.
// Dejé acá la conexión, permisos, logs y consultas que se repiten en varias páginas.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');

define('DB_NAME', 'egonzalezl8.e3');
define('DB_USER', 'egonzalezl8.e3');
define('DB_PASSWORD', '24626473');

define('LOG_PATH', __DIR__ . '/logs/dccolo.log');

// Con esta función abro la conexión a la base de datos.
// Si falla, dejo que el error suba para poder mostrarlo desde la página.

function conectarDB() {
    $dsn = 'pgsql:host=' . DB_HOST . ';dbname=' . DB_NAME;
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (DB_PASSWORD === '') {
        return new PDO($dsn, DB_USER, null, $opciones);
    }
    return new PDO($dsn, DB_USER, DB_PASSWORD, $opciones);
}

// Función simple para sacar espacios de los datos que vienen del formulario.
function limpiarInput($valor) {
    return trim((string)($valor ?? ''));
}

// Normalizo el RUN para evitar duplicados por puntos o guion distinto.
// Ejemplo: 21.557.645-0 y 215576450 quedan como el mismo RUN.
function normalizarRun($run) {
    $run = strtoupper(trim((string)$run));
    $run = str_replace(['.', ' '], '', $run);
    $run = preg_replace('/[^0-9K\-]/', '', $run);

    if ($run === '') {
        return '';
    }

    if (strpos($run, '-') !== false) {
        $partes = explode('-', $run);
        if (count($partes) !== 2) {
            return '';
        }
        $cuerpo = preg_replace('/\D/', '', $partes[0]);
        $dv = strtoupper($partes[1]);
    } else {
        if (strlen($run) < 2) {
            return '';
        }
        $cuerpo = substr($run, 0, -1);
        $dv = substr($run, -1);
    }

    if ($cuerpo === '' || !ctype_digit($cuerpo) || !preg_match('/^[0-9K]$/', $dv)) {
        return '';
    }

    return $cuerpo . '-' . $dv;
}

function claveRunNormalizada($run) {
    $normalizado = normalizarRun($run);
    if ($normalizado === '') {
        return '';
    }
    return str_replace('-', '', $normalizado);
}

function buscarRunPersonaPorRun($pdo, $run) {
    $clave = claveRunNormalizada($run);
    if ($clave === '') {
        return null;
    }

    $stmt = $pdo->prepare(""
        . "SELECT run FROM persona "
        . "WHERE regexp_replace(upper(run), '[\\.\\-[:space:]]', '', 'g') = :clave "
        . "ORDER BY run "
        . "LIMIT 1"
    );
    $stmt->execute([':clave' => $clave]);
    $runExistente = $stmt->fetchColumn();
    return $runExistente ?: null;
}

function escaparHTML($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function mostrarMensaje($tipo, $mensaje) {
    if (!$mensaje) {
        return;
    }
    $tipoSeguro = escaparHTML($tipo);
    $mensajeSeguro = escaparHTML($mensaje);
    echo "<div class='alert {$tipoSeguro}'>{$mensajeSeguro}</div>";
}

function setFlash($tipo, $mensaje) {
    $_SESSION['flash'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje,
    ];
}

function mostrarFlash() {
    if (!isset($_SESSION['flash'])) {
        return;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    mostrarMensaje($flash['tipo'] ?? 'success', $flash['mensaje'] ?? '');
}

function paginaActual() {
    return basename($_SERVER['PHP_SELF']);
}

function esActivo($archivo) {
    return paginaActual() === $archivo ? 'active' : '';
}

// Este log es el que pide el enunciado para cada intento de acceso.
function registrarAcceso($usuario, $exitoso) {
    $directorio = dirname(LOG_PATH);
    if (!is_dir($directorio)) {
        mkdir($directorio, 0775, true);
    }

    $estado = $exitoso ? 'Exitoso' : 'Fallido';
    $usuarioLog = str_replace(["\n", "\r"], '', $usuario ?: 'sin_usuario');
    $linea = date('Y-m-d H:i:s') . " ACCESO {$usuarioLog} {$estado}" . PHP_EOL;
    file_put_contents(LOG_PATH, $linea, FILE_APPEND | LOCK_EX);
}

function usuarioActualParaLog() {
    $usuario = usuarioActual();
    if (!$usuario) {
        return 'sin_sesion';
    }
    return $usuario['email_login'] ?? $usuario['run_persona'] ?? 'sin_usuario';
}

function textoSeguroLog($valor, $max = 220) {
    $valor = str_replace(["\n", "\r", "\t"], ' ', (string)($valor ?? ''));
    $valor = preg_replace('/\s+/', ' ', trim($valor));
    if (strlen($valor) > $max) {
        $valor = substr($valor, 0, $max) . '...';
    }
    return $valor;
}

// Además del login, guardo acciones importantes para poder revisar qué pasó.
function registrarAccionSistema($accion, $detalle = '', $exitoso = true, $usuario = null) {
    $directorio = dirname(LOG_PATH);
    if (!is_dir($directorio)) {
        mkdir($directorio, 0775, true);
    }

    $estado = $exitoso ? 'Exitoso' : 'Fallido';
    $usuarioLog = textoSeguroLog($usuario ?: usuarioActualParaLog(), 120);
    $accionLog = textoSeguroLog($accion, 80);
    $detalleLog = textoSeguroLog($detalle, 240);
    $linea = date('Y-m-d H:i:s') . " ACCION {$usuarioLog} {$accionLog} {$estado}";
    if ($detalleLog !== '') {
        $linea .= " {$detalleLog}";
    }
    $linea .= PHP_EOL;
    file_put_contents(LOG_PATH, $linea, FILE_APPEND | LOCK_EX);
}

// En el dump hay claves en texto simple, pero los usuarios nuevos quedan con hash.
// Por eso se revisan los dos casos.
function claveCoincide($claveIngresada, $claveGuardada) {
    $claveIngresada = trim((string)$claveIngresada);
    $claveGuardada = trim((string)$claveGuardada);

    if ($claveIngresada === $claveGuardada) {
        return true;
    }

    $infoHash = password_get_info($claveGuardada);
    if (($infoHash['algo'] ?? 0) !== 0 && password_verify($claveIngresada, $claveGuardada)) {
        return true;
    }

    return false;
}

function normalizarEtiqueta($valor) {
    $valor = strtolower(trim((string)$valor));
    $valor = str_replace([' ', '-'], '_', $valor);
    return $valor;
}

// Solo estos tipos de usuario pueden entrar al sistema.
function usuarioAutorizado($usuario) {
    $tipoUsuario = normalizarEtiqueta($usuario['tipo_usuario'] ?? '');
    $tipoSocio = normalizarEtiqueta($usuario['tipo_socio'] ?? '');

    if (in_array($tipoUsuario, ['administrativo', 'administrador', 'admin'], true)) {
        return true;
    }

    if (in_array($tipoUsuario, ['socio', 'socio_titular'], true) && $tipoSocio === 'socio_titular') {
        return true;
    }

    return false;
}

function buscarUsuarioPorLogin($pdo, $emailLogin) {
    $sql = "
        SELECT
            u.id_usuario,
            u.run_persona,
            u.email_login,
            u.clave_encriptada,
            u.tipo_usuario,
            p.nombre_completo,
            p.email,
            s.id_socio,
            s.tipo_socio,
            s.fecha_fin
        FROM usuario u
        JOIN persona p ON p.run = u.run_persona
        LEFT JOIN socio s ON s.run_persona = p.run
            AND replace(lower(s.tipo_socio), ' ', '_') = 'socio_titular'
            AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURRENT_DATE)
        WHERE lower(u.email_login) = lower(:email_login)
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':email_login', $emailLogin);
    $stmt->execute();
    return $stmt->fetch();
}

function iniciarSesionUsuario($usuario) {
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id_usuario' => $usuario['id_usuario'],
        'run_persona' => $usuario['run_persona'],
        'email_login' => $usuario['email_login'],
        'tipo_usuario' => $usuario['tipo_usuario'],
        'nombre_completo' => $usuario['nombre_completo'],
        'id_socio' => $usuario['id_socio'] ?? null,
        'tipo_socio' => $usuario['tipo_socio'] ?? null,
    ];
}

function usuarioActual() {
    return $_SESSION['usuario'] ?? null;
}

function estaLogueado() {
    return isset($_SESSION['usuario']);
}

function requireLogin() {
    if (!estaLogueado()) {
        header('Location: index.php?error=debes_iniciar_sesion');
        exit;
    }
}

function requireRol($rolesPermitidos) {
    requireLogin();
    $usuario = usuarioActual();
    $tipo = strtolower($usuario['tipo_usuario'] ?? '');

    // Reviso los roles permitidos uno por uno.
    // Lo dejé así porque es más fácil de leer que usar funciones extra de arreglos.
    $puedeEntrar = false;
    foreach ($rolesPermitidos as $rol) {
        if ($tipo === strtolower($rol)) {
            $puedeEntrar = true;
        }
    }

    if (!$puedeEntrar) {
        header('Location: dashboard.php?error=sin_permiso');
        exit;
    }
}

function cerrarSesion() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function obtenerOpciones($pdo, $tabla, $columnaValor, $columnaTexto, $orden = null) {
    $permitidas = [
        'cargo' => ['id_cargo', 'nombre'],
        'sucursal' => ['codigo_sucursal', 'nombre'],
        'comuna' => ['codigo_comuna', 'nombre'],
    ];

    if (!isset($permitidas[$tabla]) || !in_array($columnaValor, $permitidas[$tabla], true) || !in_array($columnaTexto, $permitidas[$tabla], true)) {
        return [];
    }

    $ordenSeguro = $orden ?: $columnaTexto;
    if (!in_array($ordenSeguro, $permitidas[$tabla], true)) {
        $ordenSeguro = $columnaTexto;
    }

    $sql = "SELECT {$columnaValor} AS valor, {$columnaTexto} AS texto FROM {$tabla} ORDER BY {$ordenSeguro}";
    return $pdo->query($sql)->fetchAll();
}

function emailValido($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function telefonoValido($telefono) {
    return $telefono === '' || preg_match('/^[0-9+ ()-]{8,20}$/', $telefono);
}

function fechaValida($fecha) {
    if ($fecha === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}

function normalizarTipoUsuarioRegistro($tipo) {
    $tipo = strtolower(trim($tipo));
    if ($tipo === 'administrativo') {
        return 'administrativo';
    }
    if ($tipo === 'administrador' || $tipo === 'admin') {
        return 'administrador';
    }
    return '';
}

// Registro de usuario administrativo/administrador.
// Se usa beginTransaction para que todo quede junto o nada quede insertado.
function registrarNuevoUsuarioAdministrativo($pdo, $datos) {
    $runOriginal = limpiarInput($datos['run_persona'] ?? '');
    $run = normalizarRun($runOriginal);
    $nombre = limpiarInput($datos['nombre_completo'] ?? '');
    $emailPersonal = limpiarInput($datos['email'] ?? '');
    $telefonoCelular = limpiarInput($datos['telefono_celular'] ?? '');
    $telefonoAlternativo = limpiarInput($datos['telefono_alternativo'] ?? '');
    $direccion = limpiarInput($datos['direccion_calle'] ?? '');
    $codigoComuna = limpiarInput($datos['codigo_comuna'] ?? '');
    $fechaNacimiento = limpiarInput($datos['fecha_nacimiento'] ?? '');

    $emailLogin = limpiarInput($datos['email_login'] ?? '');
    $clave = (string)($datos['clave_encriptada'] ?? '');
    $tipoUsuario = normalizarTipoUsuarioRegistro($datos['tipo_usuario'] ?? '');

    $idCargo = limpiarInput($datos['id_cargo'] ?? '');
    $codigoSucursal = limpiarInput($datos['codigo_sucursal'] ?? '');
    $fechaInicio = limpiarInput($datos['fecha_inicio'] ?? '');
    $fechaTermino = limpiarInput($datos['fecha_termino'] ?? '');

    if ($run === '') {
        throw new Exception('run_invalido');
    }

    if ($nombre === '' || $emailLogin === '' || $clave === '' || $tipoUsuario === '' || $idCargo === '' || $codigoSucursal === '' || $codigoComuna === '') {
        throw new Exception('faltan_campos');
    }

    if (!emailValido($emailLogin) || ($emailPersonal !== '' && !emailValido($emailPersonal))) {
        throw new Exception('email_invalido');
    }

    if (!telefonoValido($telefonoCelular) || !telefonoValido($telefonoAlternativo)) {
        throw new Exception('telefono_invalido');
    }

    if (!fechaValida($fechaNacimiento) || !fechaValida($fechaInicio)) {
        throw new Exception('fecha_invalida');
    }

    if ($fechaTermino !== '' && !fechaValida($fechaTermino)) {
        throw new Exception('fecha_termino_invalida');
    }

    $pdo->beginTransaction();
    try {
        // Sincronizo estas secuencias para evitar duplicate key si la BD ya venía con datos.
        sincronizarSecuenciaLocal($pdo, 'usuario_id_usuario_seq', 'usuario', 'id_usuario');
        sincronizarSecuenciaLocal($pdo, 'persona_cargo_id_persona_cargo_seq', 'persona_cargo', 'id_persona_cargo');

        if (buscarRunPersonaPorRun($pdo, $run)) {
            throw new Exception('persona_ya_existe');
        }

        $existeUsuario = $pdo->prepare('SELECT 1 FROM usuario WHERE lower(email_login) = lower(:email_login) LIMIT 1');
        $existeUsuario->execute([':email_login' => $emailLogin]);
        if ($existeUsuario->fetch()) {
            throw new Exception('usuario_ya_existe');
        }

        $stmtPersona = $pdo->prepare('
            INSERT INTO persona
                (run, nombre_completo, email, telefono_celular, telefono_alternativo, direccion_calle, codigo_comuna, fecha_nacimiento)
            VALUES
                (:run, :nombre_completo, :email, :telefono_celular, :telefono_alternativo, :direccion_calle, :codigo_comuna, :fecha_nacimiento)
        ');
        $stmtPersona->execute([
            ':run' => $run,
            ':nombre_completo' => $nombre,
            ':email' => $emailPersonal ?: null,
            ':telefono_celular' => $telefonoCelular ?: null,
            ':telefono_alternativo' => $telefonoAlternativo ?: null,
            ':direccion_calle' => $direccion ?: null,
            ':codigo_comuna' => (int)$codigoComuna,
            ':fecha_nacimiento' => $fechaNacimiento,
        ]);

        $claveGuardada = password_hash($clave, PASSWORD_DEFAULT);
        $stmtUsuario = $pdo->prepare('
            INSERT INTO usuario (run_persona, email_login, clave_encriptada, tipo_usuario)
            VALUES (:run_persona, :email_login, :clave_encriptada, :tipo_usuario)
        ');
        $stmtUsuario->execute([
            ':run_persona' => $run,
            ':email_login' => $emailLogin,
            ':clave_encriptada' => $claveGuardada,
            ':tipo_usuario' => $tipoUsuario,
        ]);

        $stmtCargo = $pdo->prepare('
            INSERT INTO persona_cargo (run_persona, id_cargo, codigo_sucursal, fecha_inicio, fecha_termino)
            VALUES (:run_persona, :id_cargo, :codigo_sucursal, :fecha_inicio, :fecha_termino)
        ');
        $stmtCargo->execute([
            ':run_persona' => $run,
            ':id_cargo' => (int)$idCargo,
            ':codigo_sucursal' => $codigoSucursal,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_termino' => $fechaTermino ?: null,
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function esUsuarioAdministrativoGestion($usuario = null) {
    $usuario = $usuario ?: usuarioActual();
    $tipo = normalizarEtiqueta($usuario['tipo_usuario'] ?? '');
    return in_array($tipo, ['administrativo', 'administrador', 'admin'], true);
}

// Esta función protege las páginas que no debería ver un socio normal.
function requireAdminGestion() {
    requireLogin();
    if (!esUsuarioAdministrativoGestion()) {
        header('Location: dashboard.php?error=sin_permiso');
        exit;
    }
}

function obtenerComunas($pdo) {
    return $pdo->query('SELECT codigo_comuna AS valor, nombre AS texto FROM comuna ORDER BY nombre')->fetchAll();
}

function obtenerSucursales($pdo) {
    return $pdo->query('SELECT codigo_sucursal AS valor, nombre AS texto FROM sucursal ORDER BY nombre')->fetchAll();
}

// Para beneficiarios se pide elegir un socio titular.
// No filtro por vigencia porque esta parte es administración familiar.
function obtenerSociosTitularesActivos($pdo) {
    
    $sql = "
        SELECT
            s.id_socio,
            s.run_persona,
            p.nombre_completo,
            s.codigo_sucursal_base,
            suc.nombre AS nombre_sucursal,
            s.fecha_inicio,
            s.fecha_fin
        FROM socio s
        JOIN persona p ON p.run = s.run_persona
        LEFT JOIN sucursal suc ON suc.codigo_sucursal = s.codigo_sucursal_base
        WHERE replace(lower(s.tipo_socio), ' ', '_') = 'socio_titular'
        ORDER BY p.nombre_completo
    ";
    return $pdo->query($sql)->fetchAll();
}

function obtenerSociosTitularesRecientes($pdo, $limite = 8) {
    $sql = "
        SELECT
            s.id_socio,
            s.run_persona,
            p.nombre_completo,
            p.email,
            s.fecha_inicio,
            s.fecha_fin,
            suc.nombre AS sucursal
        FROM socio s
        JOIN persona p ON p.run = s.run_persona
        LEFT JOIN sucursal suc ON suc.codigo_sucursal = s.codigo_sucursal_base
        WHERE replace(lower(s.tipo_socio), ' ', '_') = 'socio_titular'
        ORDER BY s.id_socio DESC
        LIMIT :limite
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function obtenerDependientesRecientes($pdo, $limite = 12) {
    $sql = "
        SELECT
            r.id_relacion,
            pt.nombre_completo AS titular,
            pd.nombre_completo AS dependiente,
            sd.tipo_socio,
            r.parentesco
        FROM relacion_socio r
        JOIN socio st ON st.id_socio = r.id_socio_titular
        JOIN persona pt ON pt.run = st.run_persona
        JOIN socio sd ON sd.id_socio = r.id_socio_dependiente
        JOIN persona pd ON pd.run = sd.run_persona
        ORDER BY r.id_relacion DESC
        LIMIT :limite
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function obtenerCuotasSocio($pdo, $idSocio) {
    if (!$idSocio) {
        return [];
    }
    $stmt = $pdo->prepare('
        SELECT id_pago_cuota, cuota_numero, fecha_pago, monto_pagado, medio_pago, monto_base, monto_adicional
        FROM pago_cuota
        WHERE id_socio = :id_socio
        ORDER BY cuota_numero ASC, id_pago_cuota ASC
    ');
    $stmt->execute([':id_socio' => (int)$idSocio]);
    return $stmt->fetchAll();
}

// Cuando cargo un dump local, a veces las secuencias quedan atrasadas.
// Esta función las deja en el último id usado para que no choquen las PK.
function sincronizarSecuenciaLocal($pdo, $secuencia, $tabla, $columna) {
    $permitidas = [
        'usuario_id_usuario_seq' => ['usuario', 'id_usuario'],
        'persona_cargo_id_persona_cargo_seq' => ['persona_cargo', 'id_persona_cargo'],
        'socio_id_socio_seq' => ['socio', 'id_socio'],
        'membresia_id_membresia_seq' => ['membresia', 'id_socio'],
        'relacion_socio_id_relacion_seq' => ['relacion_socio', 'id_relacion'],
        'pago_cuota_id_pago_cuota_seq' => ['pago_cuota', 'id_pago_cuota'],
        'asistente_evento_id_asistente_seq' => ['asistente_evento', 'id_asistente'],
        'contacto_empresa_id_contacto_seq' => ['contacto_empresa', 'id_contacto'],
        'pago_evento_id_pago_evento_seq' => ['pago_evento', 'id_pago_evento'],
        'precio_lugar_id_precio_seq' => ['precio_lugar', 'id_precio'],
    ];
    if (!isset($permitidas[$secuencia])) {
        throw new Exception('Secuencia no permitida.');
    }
    [$tablaEsperada, $columnaEsperada] = $permitidas[$secuencia];
    if ($tabla !== $tablaEsperada || $columna !== $columnaEsperada) {
        throw new Exception('Tabla o columna no coincide con la secuencia.');
    }
    $sql = "SELECT setval('public.{$secuencia}', COALESCE((SELECT MAX({$columna}) FROM public.{$tabla}), 1), true)";
    $pdo->exec($sql);
}

// Si la persona ya existe, la actualizo.
// Si no existe, la inserto. Así no duplico personas por RUN.
function insertarOActualizarPersona($pdo, $datosPersona) {
    $run = normalizarRun($datosPersona['run'] ?? '');
    if ($run === '') {
        throw new Exception('RUN inválido.');
    }

    $runExistente = buscarRunPersonaPorRun($pdo, $run);
    $runParaUsar = $runExistente ?: $run;

    if ($runExistente) {
        $stmtUpdate = $pdo->prepare('
            UPDATE persona
            SET nombre_completo = :nombre_completo,
                email = :email,
                telefono_celular = :telefono_celular,
                telefono_alternativo = :telefono_alternativo,
                direccion_calle = :direccion_calle,
                codigo_comuna = :codigo_comuna,
                fecha_nacimiento = :fecha_nacimiento
            WHERE run = :run
        ');
        $stmtUpdate->execute([
            ':run' => $runParaUsar,
            ':nombre_completo' => $datosPersona['nombre_completo'],
            ':email' => $datosPersona['email'] ?: null,
            ':telefono_celular' => $datosPersona['telefono_celular'] ?: null,
            ':telefono_alternativo' => $datosPersona['telefono_alternativo'] ?: null,
            ':direccion_calle' => $datosPersona['direccion_calle'] ?: null,
            ':codigo_comuna' => ($datosPersona['codigo_comuna'] ?? '') !== '' ? (int)$datosPersona['codigo_comuna'] : null,
            ':fecha_nacimiento' => $datosPersona['fecha_nacimiento'],
        ]);
        return 'actualizada';
    }

    $stmtPersona = $pdo->prepare('
        INSERT INTO persona
            (run, nombre_completo, email, telefono_celular, telefono_alternativo, direccion_calle, codigo_comuna, fecha_nacimiento)
        VALUES
            (:run, :nombre_completo, :email, :telefono_celular, :telefono_alternativo, :direccion_calle, :codigo_comuna, :fecha_nacimiento)
    ');
    $stmtPersona->execute([
        ':run' => $runParaUsar,
        ':nombre_completo' => $datosPersona['nombre_completo'],
        ':email' => $datosPersona['email'] ?: null,
        ':telefono_celular' => $datosPersona['telefono_celular'] ?: null,
        ':telefono_alternativo' => $datosPersona['telefono_alternativo'] ?: null,
        ':direccion_calle' => $datosPersona['direccion_calle'] ?: null,
        ':codigo_comuna' => ($datosPersona['codigo_comuna'] ?? '') !== '' ? (int)$datosPersona['codigo_comuna'] : null,
        ':fecha_nacimiento' => $datosPersona['fecha_nacimiento'],
    ]);
    return 'insertada';
}

// Registro de socio titular y membresía 2026.
// También revisa que no exista otro socio titular activo con el mismo RUN.
function registrarSocioTitular($pdo, $datos) {
    $runOriginal = limpiarInput($datos['run_persona'] ?? '');
    $run = normalizarRun($runOriginal);
    $nombre = limpiarInput($datos['nombre_completo'] ?? '');
    $email = limpiarInput($datos['email'] ?? '');
    $emailLogin = limpiarInput($datos['email_login'] ?? '');
    $claveUsuario = trim((string)($datos['clave_usuario'] ?? ''));
    $telefonoCelular = limpiarInput($datos['telefono_celular'] ?? '');
    $telefonoAlternativo = limpiarInput($datos['telefono_alternativo'] ?? '');
    $direccion = limpiarInput($datos['direccion_calle'] ?? '');
    $codigoComuna = limpiarInput($datos['codigo_comuna'] ?? '');
    $fechaNacimiento = limpiarInput($datos['fecha_nacimiento'] ?? '');
    $codigoSucursal = limpiarInput($datos['codigo_sucursal_base'] ?? '');
    $fechaInicioSocio = limpiarInput($datos['fecha_inicio_socio'] ?? '');
    $fechaFinSocio = limpiarInput($datos['fecha_fin_socio'] ?? '');
    $anio = limpiarInput($datos['anio'] ?? '2026');
    $fechaInicioMembresia = limpiarInput($datos['fecha_inicio_membresia'] ?? '');
    $fechaFinMembresia = limpiarInput($datos['fecha_fin_membresia'] ?? '');

    if ($run === '') {
        throw new Exception('El RUN del socio titular no tiene un formato válido. Usa formato 12345678-9, sin puntos.');
    }
    if ($nombre === '' || $email === '' || $codigoComuna === '' || $codigoSucursal === '' || $fechaNacimiento === '' || $fechaInicioSocio === '' || $anio === '' || $fechaInicioMembresia === '' || $fechaFinMembresia === '') {
        throw new Exception('Faltan campos obligatorios del socio titular.');
    }
    if (!emailValido($email)) {
        throw new Exception('El correo del socio titular no tiene un formato válido.');
    }
    if ($emailLogin === '') {
        $emailLogin = $email;
    }
    if (!emailValido($emailLogin)) {
        throw new Exception('El correo de login del socio titular no tiene un formato válido.');
    }
    if ($claveUsuario === '') {
        throw new Exception('Debes ingresar una clave de acceso para el socio titular.');
    }
    if (!telefonoValido($telefonoCelular) || !telefonoValido($telefonoAlternativo)) {
        throw new Exception('El teléfono ingresado no tiene un formato válido.');
    }
    if (!fechaValida($fechaNacimiento) || !fechaValida($fechaInicioSocio) || !fechaValida($fechaInicioMembresia) || !fechaValida($fechaFinMembresia)) {
        throw new Exception('Alguna fecha obligatoria no tiene formato válido.');
    }
    if ($fechaFinSocio !== '' && !fechaValida($fechaFinSocio)) {
        throw new Exception('La fecha de fin del socio no tiene formato válido.');
    }
    if ($fechaFinSocio !== '' && $fechaFinSocio < $fechaInicioSocio) {
        throw new Exception('La fecha de fin del socio no puede ser anterior a la fecha de inicio.');
    }
    if ($fechaFinMembresia < $fechaInicioMembresia) {
        throw new Exception('La fecha de fin de membresía no puede ser anterior a la fecha de inicio.');
    }
    if ((int)$anio !== 2026) {
        throw new Exception('El plan de membresía pedido para esta etapa debe ser del año 2026.');
    }

    $pdo->beginTransaction();
    try {
        sincronizarSecuenciaLocal($pdo, 'socio_id_socio_seq', 'socio', 'id_socio');
        sincronizarSecuenciaLocal($pdo, 'membresia_id_membresia_seq', 'membresia', 'id_socio');
        sincronizarSecuenciaLocal($pdo, 'usuario_id_usuario_seq', 'usuario', 'id_usuario');

        $runExistente = buscarRunPersonaPorRun($pdo, $run);
        $runParaGuardar = $runExistente ?: $run;

        $estadoPersona = insertarOActualizarPersona($pdo, [
            'run' => $runParaGuardar,
            'nombre_completo' => $nombre,
            'email' => $email,
            'telefono_celular' => $telefonoCelular,
            'telefono_alternativo' => $telefonoAlternativo,
            'direccion_calle' => $direccion,
            'codigo_comuna' => $codigoComuna,
            'fecha_nacimiento' => $fechaNacimiento,
        ]);

        $stmtSocioExistente = $pdo->prepare(""
            . "SELECT id_socio FROM socio "
            . "WHERE regexp_replace(upper(run_persona), '[\.\-[:space:]]', '', 'g') = :clave_run "
            . "AND replace(lower(tipo_socio), ' ', '_') = 'socio_titular' "
            . "AND (fecha_fin IS NULL OR fecha_fin >= :fecha_inicio) "
            . "LIMIT 1"
        );
        $stmtSocioExistente->execute([
            ':clave_run' => claveRunNormalizada($runParaGuardar),
            ':fecha_inicio' => $fechaInicioSocio,
        ]);
        $socioExistente = $stmtSocioExistente->fetchColumn();
        if ($socioExistente) {
            throw new Exception('Esta persona ya tiene un registro de socio titular activo.');
        }

        $stmtSocio = $pdo->prepare('
            INSERT INTO socio (run_persona, tipo_socio, fecha_inicio, fecha_fin, codigo_sucursal_base)
            VALUES (:run_persona, :tipo_socio, :fecha_inicio, :fecha_fin, :codigo_sucursal_base)
            RETURNING id_socio
        ');
        $stmtSocio->execute([
            ':run_persona' => $runParaGuardar,
            ':tipo_socio' => 'socio_titular',
            ':fecha_inicio' => $fechaInicioSocio,
            ':fecha_fin' => $fechaFinSocio ?: null,
            ':codigo_sucursal_base' => $codigoSucursal,
        ]);
        $idSocio = (int)$stmtSocio->fetchColumn();

        $stmtMembresia = $pdo->prepare('
            INSERT INTO membresia (id_socio_titular, anio, fecha_inicio, fecha_fin)
            VALUES (:id_socio_titular, :anio, :fecha_inicio, :fecha_fin)
            RETURNING id_socio
        ');
        $stmtMembresia->execute([
            ':id_socio_titular' => $idSocio,
            ':anio' => (int)$anio,
            ':fecha_inicio' => $fechaInicioMembresia,
            ':fecha_fin' => $fechaFinMembresia,
        ]);
        $idMembresia = (int)$stmtMembresia->fetchColumn();

        // El socio titular también queda como usuario para que pueda iniciar sesión.
        $estadoUsuario = 'creado';
        $stmtUsuarioRun = $pdo->prepare('SELECT id_usuario FROM usuario WHERE run_persona = :run_persona LIMIT 1');
        $stmtUsuarioRun->execute([':run_persona' => $runParaGuardar]);
        $usuarioExistenteRun = $stmtUsuarioRun->fetchColumn();

        if ($usuarioExistenteRun) {
            $estadoUsuario = 'ya_existia';
        } else {
            $stmtUsuarioEmail = $pdo->prepare('SELECT 1 FROM usuario WHERE lower(email_login) = lower(:email_login) LIMIT 1');
            $stmtUsuarioEmail->execute([':email_login' => $emailLogin]);
            if ($stmtUsuarioEmail->fetch()) {
                throw new Exception('El correo de login ya está usado por otro usuario.');
            }

            $stmtUsuario = $pdo->prepare('
                INSERT INTO usuario (run_persona, email_login, clave_encriptada, tipo_usuario)
                VALUES (:run_persona, :email_login, :clave_encriptada, :tipo_usuario)
            ');
            $stmtUsuario->execute([
                ':run_persona' => $runParaGuardar,
                ':email_login' => $emailLogin,
                ':clave_encriptada' => password_hash($claveUsuario, PASSWORD_DEFAULT),
                ':tipo_usuario' => 'socio_titular',
            ]);
        }

        $pdo->commit();
        return [
            'id_socio' => $idSocio,
            'id_membresia' => $idMembresia,
            'run' => $runParaGuardar,
            'estado_persona' => $estadoPersona,
            'estado_usuario' => $estadoUsuario,
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function normalizarTipoDependiente($tipo) {
    $tipo = normalizarEtiqueta($tipo);
    if ($tipo === 'beneficiario') {
        return 'beneficiario';
    }
    if ($tipo === 'adicional') {
        return 'adicional';
    }
    return '';
}

// Registro de beneficiario/adicional.
// La transacción inserta persona, socio dependiente y relacion_socio.
function registrarDependienteSocio($pdo, $datos) {
    $idTitular = (int)limpiarInput($datos['id_socio_titular'] ?? '0');
    $tipoSocio = normalizarTipoDependiente($datos['tipo_socio'] ?? '');
    $parentesco = limpiarInput($datos['parentesco'] ?? '');
    $runOriginal = limpiarInput($datos['run_persona'] ?? '');
    $run = normalizarRun($runOriginal);
    $nombre = limpiarInput($datos['nombre_completo'] ?? '');
    $email = limpiarInput($datos['email'] ?? '');
    $telefonoCelular = limpiarInput($datos['telefono_celular'] ?? '');
    $telefonoAlternativo = limpiarInput($datos['telefono_alternativo'] ?? '');
    $direccion = limpiarInput($datos['direccion_calle'] ?? '');
    $codigoComuna = limpiarInput($datos['codigo_comuna'] ?? '');
    $fechaNacimiento = limpiarInput($datos['fecha_nacimiento'] ?? '');
    $fechaInicio = limpiarInput($datos['fecha_inicio'] ?? date('Y-m-d'));
    $fechaFin = limpiarInput($datos['fecha_fin'] ?? '');

    if ($run === '') {
        throw new Exception('El RUN del beneficiario/adicional no tiene un formato válido. Usa formato 12345678-9, sin puntos.');
    }
    if ($idTitular <= 0 || $tipoSocio === '' || $parentesco === '' || $nombre === '' || $fechaNacimiento === '' || $fechaInicio === '') {
        throw new Exception('Faltan campos obligatorios del beneficiario/adicional.');
    }
    if ($email !== '' && !emailValido($email)) {
        throw new Exception('El correo del dependiente no tiene un formato válido.');
    }
    if (!telefonoValido($telefonoCelular) || !telefonoValido($telefonoAlternativo)) {
        throw new Exception('El teléfono del dependiente no tiene un formato válido.');
    }
    if (!fechaValida($fechaNacimiento) || !fechaValida($fechaInicio)) {
        throw new Exception('Alguna fecha obligatoria del dependiente no tiene formato válido.');
    }
    if ($fechaFin !== '' && !fechaValida($fechaFin)) {
        throw new Exception('La fecha de fin del dependiente no tiene formato válido.');
    }
    if ($fechaFin !== '' && $fechaFin < $fechaInicio) {
        throw new Exception('La fecha de fin del dependiente no puede ser anterior a la fecha de inicio.');
    }

    $pdo->beginTransaction();
    try {
        sincronizarSecuenciaLocal($pdo, 'socio_id_socio_seq', 'socio', 'id_socio');
        sincronizarSecuenciaLocal($pdo, 'relacion_socio_id_relacion_seq', 'relacion_socio', 'id_relacion');

        $stmtTitular = $pdo->prepare(""
            . "SELECT id_socio, codigo_sucursal_base "
            . "FROM socio "
            . "WHERE id_socio = :id_socio "
            . "AND replace(lower(tipo_socio), ' ', '_') = 'socio_titular' "
            . "LIMIT 1"
        );
        $stmtTitular->execute([':id_socio' => $idTitular]);
        $titular = $stmtTitular->fetch();
        if (!$titular) {
            throw new Exception('El socio titular seleccionado no existe.');
        }

        $runExistente = buscarRunPersonaPorRun($pdo, $run);
        $runParaGuardar = $runExistente ?: $run;
        $estadoPersona = insertarOActualizarPersona($pdo, [
            'run' => $runParaGuardar,
            'nombre_completo' => $nombre,
            'email' => $email,
            'telefono_celular' => $telefonoCelular,
            'telefono_alternativo' => $telefonoAlternativo,
            'direccion_calle' => $direccion,
            'codigo_comuna' => $codigoComuna,
            'fecha_nacimiento' => $fechaNacimiento,
        ]);

        $stmtSocioExistente = $pdo->prepare(""
            . "SELECT id_socio FROM socio "
            . "WHERE regexp_replace(upper(run_persona), '[\.\-[:space:]]', '', 'g') = :clave_run "
            . "AND replace(lower(tipo_socio), ' ', '_') = :tipo_socio "
            . "AND (fecha_fin IS NULL OR fecha_fin >= :fecha_inicio) "
            . "ORDER BY id_socio DESC "
            . "LIMIT 1"
        );
        $stmtSocioExistente->execute([
            ':clave_run' => claveRunNormalizada($runParaGuardar),
            ':tipo_socio' => $tipoSocio,
            ':fecha_inicio' => $fechaInicio,
        ]);
        $idDependiente = (int)($stmtSocioExistente->fetchColumn() ?: 0);

        if ($idDependiente <= 0) {
            $stmtSocio = $pdo->prepare('
                INSERT INTO socio (run_persona, tipo_socio, fecha_inicio, fecha_fin, codigo_sucursal_base)
                VALUES (:run_persona, :tipo_socio, :fecha_inicio, :fecha_fin, :codigo_sucursal_base)
                RETURNING id_socio
            ');
            $stmtSocio->execute([
                ':run_persona' => $runParaGuardar,
                ':tipo_socio' => $tipoSocio,
                ':fecha_inicio' => $fechaInicio,
                ':fecha_fin' => $fechaFin ?: null,
                ':codigo_sucursal_base' => $titular['codigo_sucursal_base'] ?? null,
            ]);
            $idDependiente = (int)$stmtSocio->fetchColumn();
        }

        $stmtRelacionExiste = $pdo->prepare('
            SELECT 1
            FROM relacion_socio
            WHERE id_socio_titular = :id_socio_titular
              AND id_socio_dependiente = :id_socio_dependiente
            LIMIT 1
        ');
        $stmtRelacionExiste->execute([
            ':id_socio_titular' => $idTitular,
            ':id_socio_dependiente' => $idDependiente,
        ]);
        if ($stmtRelacionExiste->fetch()) {
            throw new Exception('Ese beneficiario/adicional ya está asociado al socio titular seleccionado.');
        }

        $stmtRelacion = $pdo->prepare('
            INSERT INTO relacion_socio (id_socio_titular, id_socio_dependiente, parentesco)
            VALUES (:id_socio_titular, :id_socio_dependiente, :parentesco)
        ');
        $stmtRelacion->execute([
            ':id_socio_titular' => $idTitular,
            ':id_socio_dependiente' => $idDependiente,
            ':parentesco' => $parentesco,
        ]);

        $pdo->commit();
        return $idDependiente;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Llama al procedimiento almacenado cargado desde sql/procedimientos.sql.
function generarPlanPagosSocio($pdo, $idSocio) {
    $idSocio = (int)$idSocio;
    if ($idSocio <= 0) {
        throw new Exception('Debes seleccionar un socio titular.');
    }

    $stmt = $pdo->prepare('SELECT sp_generar_plan_pagos_2026(:id_socio)');
    try {
        $stmt->execute([':id_socio' => $idSocio]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'sp_generar_plan_pagos_2026') !== false || strpos($e->getMessage(), 'undefined_function') !== false) {
            throw new Exception('No se encontró el procedimiento sp_generar_plan_pagos_2026. Carga primero sql/procedimientos.sql y sql/triggers.sql en psql.');
        }
        throw $e;
    }
    return true;
}

// Pago de cuota: primero busco la cuota más antigua impaga y luego la actualizo.
// Lo hice en dos pasos para que se vea más claro que una consulta WITH.
function pagarCuotaMasAntigua($pdo, $datos) {
    $idSocio = (int)limpiarInput($datos['id_socio_pago'] ?? '0');
    $medioPago = limpiarInput($datos['medio_pago'] ?? '');
    $fechaPago = limpiarInput($datos['fecha_pago'] ?? '');

    if ($idSocio <= 0 || $medioPago === '' || $fechaPago === '') {
        throw new Exception('Debes seleccionar socio, medio de pago y fecha de pago.');
    }
    if (!fechaValida($fechaPago)) {
        throw new Exception('La fecha de pago no tiene formato válido.');
    }

    $pdo->beginTransaction();
    try {
        $stmtBuscar = $pdo->prepare('
            SELECT id_pago_cuota, COALESCE(monto_base, 0) + COALESCE(monto_adicional, 0) AS total
            FROM pago_cuota
            WHERE id_socio = :id_socio
              AND cuota_numero BETWEEN 1 AND 12
              AND (fecha_pago IS NULL OR monto_pagado = 0)
            ORDER BY cuota_numero ASC, id_pago_cuota ASC
            LIMIT 1
            FOR UPDATE
        ');
        $stmtBuscar->execute([':id_socio' => $idSocio]);
        $cuota = $stmtBuscar->fetch();

        if (!$cuota) {
            throw new Exception('No hay cuotas impagas disponibles para ese socio. Genera primero el plan de pagos.');
        }

        $stmtActualizar = $pdo->prepare('
            UPDATE pago_cuota
            SET fecha_pago = :fecha_pago,
                medio_pago = :medio_pago,
                monto_pagado = :monto_pagado
            WHERE id_pago_cuota = :id_pago_cuota
            RETURNING *
        ');
        $stmtActualizar->execute([
            ':fecha_pago' => $fechaPago,
            ':medio_pago' => $medioPago,
            ':monto_pagado' => (int)$cuota['total'],
            ':id_pago_cuota' => (int)$cuota['id_pago_cuota'],
        ]);

        $cuotaPagada = $stmtActualizar->fetch();
        $pdo->commit();
        return $cuotaPagada;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// El arriendo lo pueden hacer administrativos, administradores y socios titulares.
function usuarioPuedeArrendarCanchas($usuario = null) {
    $usuario = $usuario ?: usuarioActual();
    $tipoUsuario = normalizarEtiqueta($usuario['tipo_usuario'] ?? '');
    $tipoSocio = normalizarEtiqueta($usuario['tipo_socio'] ?? '');

    if (in_array($tipoUsuario, ['administrativo', 'administrador', 'admin'], true)) {
        return true;
    }

    return in_array($tipoUsuario, ['socio', 'socio_titular'], true) && $tipoSocio === 'socio_titular';
}

function requireAccesoArriendoCanchas() {
    requireLogin();
    if (!usuarioPuedeArrendarCanchas()) {
        header('Location: dashboard.php?error=sin_permiso');
        exit;
    }
}

function esSocioTitularSesion($usuario = null) {
    $usuario = $usuario ?: usuarioActual();
    $tipoUsuario = normalizarEtiqueta($usuario['tipo_usuario'] ?? '');
    $tipoSocio = normalizarEtiqueta($usuario['tipo_socio'] ?? '');
    return in_array($tipoUsuario, ['socio', 'socio_titular'], true) && $tipoSocio === 'socio_titular';
}

function obtenerCanchasArrendables($pdo, $codigoSucursal = '') {
    $codigoSucursal = limpiarInput($codigoSucursal);
    $sql = "
        SELECT
            l.codigo_lugar,
            l.nombre,
            l.capacidad,
            l.tipo_lugar,
            l.codigo_sucursal,
            s.nombre AS nombre_sucursal
        FROM lugar l
        JOIN sucursal s ON s.codigo_sucursal = l.codigo_sucursal
        WHERE (lower(l.tipo_lugar) LIKE 'cancha%' OR lower(l.nombre) LIKE 'cancha%')
    ";
    $params = [];
    if ($codigoSucursal !== '') {
        $sql .= ' AND l.codigo_sucursal = :codigo_sucursal';
        $params[':codigo_sucursal'] = $codigoSucursal;
    }
    $sql .= ' ORDER BY s.nombre, l.nombre';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function obtenerCanchaPorCodigo($pdo, $codigoLugar) {
    $stmt = $pdo->prepare(""
        . "SELECT l.codigo_lugar, l.nombre, l.capacidad, l.tipo_lugar, l.codigo_sucursal, s.nombre AS nombre_sucursal "
        . "FROM lugar l JOIN sucursal s ON s.codigo_sucursal = l.codigo_sucursal "
        . "WHERE l.codigo_lugar = :codigo_lugar "
        . "AND (lower(l.tipo_lugar) LIKE 'cancha%' OR lower(l.nombre) LIKE 'cancha%') "
        . "LIMIT 1"
    );
    $stmt->execute([':codigo_lugar' => $codigoLugar]);
    return $stmt->fetch();
}

function obtenerSocioTitularActivoPorRun($pdo, $run) {
    $clave = claveRunNormalizada($run);
    if ($clave === '') {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT
            s.id_socio,
            s.run_persona,
            p.nombre_completo,
            s.codigo_sucursal_base,
            suc.nombre AS nombre_sucursal
        FROM socio s
        JOIN persona p ON p.run = s.run_persona
        LEFT JOIN sucursal suc ON suc.codigo_sucursal = s.codigo_sucursal_base
        WHERE regexp_replace(upper(s.run_persona), '[\.\-[:space:]]', '', 'g') = :clave_run
          AND replace(lower(s.tipo_socio), ' ', '_') = 'socio_titular'
          AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURRENT_DATE)
        ORDER BY s.id_socio DESC
        LIMIT 1
    ");
    $stmt->execute([':clave_run' => $clave]);
    return $stmt->fetch();
}

// Para arriendo sí exijo socio vigente y sin deuda vencida.
function obtenerSociosTitularesActivosSinDeuda($pdo) {
    
    $sql = "
        SELECT
            s.id_socio,
            s.run_persona,
            p.nombre_completo,
            p.email,
            s.fecha_inicio,
            s.fecha_fin,
            s.codigo_sucursal_base,
            suc.nombre AS nombre_sucursal
        FROM socio s
        JOIN persona p ON p.run = s.run_persona
        LEFT JOIN sucursal suc ON suc.codigo_sucursal = s.codigo_sucursal_base
        WHERE replace(lower(s.tipo_socio), ' ', '_') = 'socio_titular'
          AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURRENT_DATE)
          AND NOT EXISTS (
              SELECT 1
              FROM pago_cuota pc
              WHERE pc.id_socio = s.id_socio
                AND pc.fecha_pago IS NULL
                AND pc.cuota_numero IS NOT NULL
                AND pc.cuota_numero <= EXTRACT(MONTH FROM CURRENT_DATE)
          )
        ORDER BY s.id_socio DESC, p.nombre_completo
    ";
    return $pdo->query($sql)->fetchAll();
}

function horaValidaHHMM($hora) {
    return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', (string)$hora) === 1;
}

function normalizarHoraSQL($hora) {
    $hora = limpiarInput($hora);
    if (preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $hora)) {
        return $hora . ':00';
    }
    if (preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hora)) {
        return $hora;
    }
    return '';
}

function diaSemanaEspanol($fecha) {
    $mapa = [
        1 => 'lunes',
        2 => 'martes',
        3 => 'miercoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sabado',
        7 => 'domingo',
    ];
    $dt = new DateTime($fecha);
    return $mapa[(int)$dt->format('N')] ?? '';
}

// Reviso si el intervalo se cruza con otra reserva no cancelada.
function reservaTieneConflicto($pdo, $codigoLugar, $fechaInicio, $fechaFin) {
    $stmt = $pdo->prepare("
        SELECT codigo_reserva
        FROM reserva
        WHERE codigo_lugar = :codigo_lugar
          AND lower(estado) <> 'cancelada'
          AND fecha_inicio < :fecha_fin
          AND fecha_fin > :fecha_inicio
        LIMIT 1
    ");
    $stmt->execute([
        ':codigo_lugar' => $codigoLugar,
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin,
    ]);
    return (bool)$stmt->fetch();
}

function calcularMontoReservaCancha($pdo, $codigoLugar, $fecha, $horaInicio, $horaFin) {
    $horaInicio = normalizarHoraSQL($horaInicio);
    $horaFin = normalizarHoraSQL($horaFin);
    if ($codigoLugar === '' || !fechaValida($fecha) || $horaInicio === '' || $horaFin === '') {
        return null;
    }

    $dia = diaSemanaEspanol($fecha);
    $stmt = $pdo->prepare("
        SELECT monto
        FROM precio_lugar
        WHERE codigo_lugar = :codigo_lugar
          AND lower(tipo_precio) = 'hora'
          AND lower(dia_semana) = :dia_semana
          AND hora_inicio <= CAST(:hora_inicio AS time)
          AND hora_termino >= CAST(:hora_fin AS time)
          AND (fecha_inicio IS NULL OR fecha_inicio <= CAST(:fecha AS date))
          AND (fecha_fin IS NULL OR fecha_fin >= CAST(:fecha AS date))
        ORDER BY fecha_inicio DESC NULLS LAST, id_precio DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':codigo_lugar' => $codigoLugar,
        ':dia_semana' => $dia,
        ':hora_inicio' => $horaInicio,
        ':hora_fin' => $horaFin,
        ':fecha' => $fecha,
    ]);
    $monto = $stmt->fetchColumn();
    return $monto === false ? null : (int)$monto;
}

function generarCodigoNumericoUnico($pdo, $tabla, $columna) {
    $sqlMax = "SELECT COALESCE(MAX(CAST($columna AS integer)), 0) + 1 FROM $tabla WHERE $columna ~ '^[0-9]+$'";
    $codigo = (string)$pdo->query($sqlMax)->fetchColumn();

    while (true) {
        $stmt = $pdo->prepare("SELECT 1 FROM $tabla WHERE $columna = :codigo LIMIT 1");
        $stmt->execute([':codigo' => $codigo]);
        if (!$stmt->fetch()) {
            return $codigo;
        }
        $codigo = (string)((int)$codigo + 1);
    }
}

function generarCodigoReservaUnico($pdo) {
    return generarCodigoNumericoUnico($pdo, 'reserva', 'codigo_reserva');
}

function construirSlotsDisponibilidad($pdo, $codigoLugar, $fecha) {
    $slots = [];
    if ($codigoLugar === '' || !fechaValida($fecha)) {
        return $slots;
    }

    for ($hora = 8; $hora < 23; $hora++) {
        $inicio = sprintf('%s %02d:00:00', $fecha, $hora);
        $fin = sprintf('%s %02d:00:00', $fecha, $hora + 1);
        $horaInicio = sprintf('%02d:00', $hora);
        $horaFin = sprintf('%02d:00', $hora + 1);
        $ocupado = reservaTieneConflicto($pdo, $codigoLugar, $inicio, $fin);
        $monto = calcularMontoReservaCancha($pdo, $codigoLugar, $fecha, $horaInicio, $horaFin);
        $slots[] = [
            'inicio' => $horaInicio,
            'fin' => $horaFin,
            'label' => $horaInicio . ' - ' . $horaFin,
            'ocupado' => $ocupado,
            'monto' => $monto,
        ];
    }
    return $slots;
}

function inicioSemana($fecha) {
    $dt = new DateTime($fecha);
    $dt->modify('monday this week');
    return $dt;
}

function construirDisponibilidadSemana($pdo, $codigoLugar, $fechaReferencia) {
    $dias = [];
    if ($codigoLugar === '' || !fechaValida($fechaReferencia)) {
        return $dias;
    }
    $inicio = inicioSemana($fechaReferencia);
    for ($i = 0; $i < 7; $i++) {
        $dia = clone $inicio;
        $dia->modify("+{$i} days");
        $fecha = $dia->format('Y-m-d');
        $slots = construirSlotsDisponibilidad($pdo, $codigoLugar, $fecha);
        $disponibles = 0;
        foreach ($slots as $slot) {
            if (!$slot['ocupado']) {
                $disponibles++;
            }
        }
        $dias[] = [
            'fecha' => $fecha,
            'dia_corto' => ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'][$i],
            'dia_numero' => $dia->format('d'),
            'disponibles' => $disponibles,
            'total' => count($slots),
        ];
    }
    return $dias;
}

function obtenerReservasDiaCancha($pdo, $codigoLugar, $fecha) {
    if ($codigoLugar === '' || !fechaValida($fecha)) {
        return [];
    }
    $stmt = $pdo->prepare("
        SELECT
            r.codigo_reserva,
            r.fecha_inicio,
            r.fecha_fin,
            r.estado,
            r.run_reservante,
            p.nombre_completo
        FROM reserva r
        LEFT JOIN persona p ON p.run = r.run_reservante
        WHERE r.codigo_lugar = :codigo_lugar
          AND CAST(r.fecha_inicio AS date) = CAST(:fecha AS date)
        ORDER BY r.fecha_inicio
    ");
    $stmt->execute([
        ':codigo_lugar' => $codigoLugar,
        ':fecha' => $fecha,
    ]);
    return $stmt->fetchAll();
}

function obtenerUltimasReservasCancha($pdo, $limite = 10) {
    $stmt = $pdo->prepare("
        SELECT
            r.codigo_reserva,
            r.fecha_inicio,
            r.fecha_fin,
            r.estado,
            l.nombre AS lugar,
            p.nombre_completo AS reservante
        FROM reserva r
        JOIN lugar l ON l.codigo_lugar = r.codigo_lugar
        LEFT JOIN persona p ON p.run = r.run_reservante
        WHERE lower(l.tipo_lugar) LIKE 'cancha%' OR lower(l.nombre) LIKE 'cancha%'
        ORDER BY r.fecha_inicio DESC, r.codigo_reserva DESC
        LIMIT :limite
    ");
    $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function resolverRunReservanteParaArriendo($pdo, $datos, $usuario) {
    if (esSocioTitularSesion($usuario)) {
        $socio = obtenerSocioTitularActivoPorRun($pdo, $usuario['run_persona'] ?? '');
        if (!$socio) {
            throw new Exception('Tu sesión no está asociada a un socio titular vigente.');
        }
        return $socio['run_persona'];
    }

    if (!esUsuarioAdministrativoGestion($usuario)) {
        throw new Exception('No tienes permiso para registrar arriendos de cancha.');
    }

    $idSocio = (int)limpiarInput($datos['id_socio_reservante'] ?? '0');
    if ($idSocio <= 0) {
        throw new Exception('Debes seleccionar un socio titular vigente y sin deudas vencidas.');
    }

    $stmt = $pdo->prepare("
        SELECT s.run_persona
        FROM socio s
        WHERE s.id_socio = :id_socio
          AND replace(lower(s.tipo_socio), ' ', '_') = 'socio_titular'
          AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURRENT_DATE)
          AND NOT EXISTS (
              SELECT 1
              FROM pago_cuota pc
              WHERE pc.id_socio = s.id_socio
                AND pc.fecha_pago IS NULL
                AND pc.cuota_numero IS NOT NULL
                AND pc.cuota_numero <= EXTRACT(MONTH FROM CURRENT_DATE)
          )
        LIMIT 1
    ");
    $stmt->execute([':id_socio' => $idSocio]);
    $run = $stmt->fetchColumn();
    if (!$run) {
        throw new Exception('El socio seleccionado no existe, no está vigente o tiene deudas vencidas.');
    }
    return $run;
}

// Inserta la reserva de cancha como una transacción.
function registrarArriendoCancha($pdo, $datos, $usuario) {
    if (!usuarioPuedeArrendarCanchas($usuario)) {
        throw new Exception('No tienes permiso para arrendar canchas.');
    }

    $codigoLugar = limpiarInput($datos['codigo_lugar'] ?? '');
    $fecha = limpiarInput($datos['fecha'] ?? '');
    $slot = limpiarInput($datos['slot'] ?? '');

    if ($codigoLugar === '' || $fecha === '' || $slot === '') {
        throw new Exception('Debes seleccionar una cancha, fecha y horario.');
    }
    if (!fechaValida($fecha)) {
        throw new Exception('La fecha seleccionada no tiene formato válido.');
    }

    $partes = explode('|', $slot);
    if (count($partes) !== 2 || !horaValidaHHMM($partes[0]) || !horaValidaHHMM($partes[1])) {
        throw new Exception('El horario seleccionado no es válido.');
    }
    [$horaInicio, $horaFin] = $partes;

    $inicio = $fecha . ' ' . $horaInicio . ':00';
    $fin = $fecha . ' ' . $horaFin . ':00';
    if (strtotime($fin) <= strtotime($inicio)) {
        throw new Exception('La hora de término debe ser posterior a la hora de inicio.');
    }

    $cancha = obtenerCanchaPorCodigo($pdo, $codigoLugar);
    if (!$cancha) {
        throw new Exception('La cancha seleccionada no existe o no es arrendable.');
    }

    $runReservante = resolverRunReservanteParaArriendo($pdo, $datos, $usuario);

    $pdo->beginTransaction();
    try {
        if (reservaTieneConflicto($pdo, $codigoLugar, $inicio, $fin)) {
            throw new Exception('El horario seleccionado ya no está disponible para esa cancha.');
        }

        $codigoReserva = generarCodigoReservaUnico($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO reserva (codigo_reserva, codigo_lugar, run_reservante, fecha_inicio, fecha_fin, estado)
            VALUES (:codigo_reserva, :codigo_lugar, :run_reservante, :fecha_inicio, :fecha_fin, 'reservada')
            RETURNING codigo_reserva, codigo_lugar, run_reservante, fecha_inicio, fecha_fin, estado
        ");
        $stmt->execute([
            ':codigo_reserva' => $codigoReserva,
            ':codigo_lugar' => $codigoLugar,
            ':run_reservante' => $runReservante,
            ':fecha_inicio' => $inicio,
            ':fecha_fin' => $fin,
        ]);
        $reserva = $stmt->fetch();
        $pdo->commit();
        return $reserva;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Desde aquí parte la parte de eventos del item 2.6.
// Mantengo funciones separadas para no dejar eventos.php demasiado largo.
function normalizarCodigoEvento($codigo) {
    $codigo = strtoupper(trim((string)$codigo));
    $codigo = preg_replace('/[^A-Z0-9_\-]/', '', $codigo);
    if ($codigo === '') {
        return '';
    }
    if (strlen($codigo) > 20) {
        return '';
    }
    return $codigo;
}

function generarCodigoEventoUnico($pdo) {
    return generarCodigoNumericoUnico($pdo, 'evento', 'codigo_evento');
}

function normalizarRutEmpresa($rut) {
    $rut = strtoupper(trim((string)$rut));
    $rut = str_replace(['.', ' '], '', $rut);
    $rut = preg_replace('/[^0-9K\-]/', '', $rut);
    if ($rut === '') {
        return '';
    }
    if (strpos($rut, '-') === false && strlen($rut) >= 2) {
        $rut = substr($rut, 0, -1) . '-' . substr($rut, -1);
    }
    if (!preg_match('/^[0-9]{7,9}\-[0-9K]$/', $rut)) {
        return '';
    }
    return $rut;
}

function obtenerLugaresEvento($pdo, $codigoSucursal = '') {
    $codigoSucursal = limpiarInput($codigoSucursal);
    $sql = "
        SELECT
            l.codigo_lugar,
            l.nombre,
            l.tipo_lugar,
            l.capacidad,
            l.codigo_sucursal,
            s.nombre AS sucursal
        FROM lugar l
        JOIN sucursal s ON s.codigo_sucursal = l.codigo_sucursal
        WHERE (:codigo_sucursal = '' OR l.codigo_sucursal = :codigo_sucursal)
        ORDER BY
            s.nombre,
            CASE
                WHEN lower(l.tipo_lugar) LIKE '%salon%' OR lower(l.tipo_lugar) LIKE '%evento%' THEN 0
                WHEN lower(l.tipo_lugar) LIKE '%restaurant%' OR lower(l.tipo_lugar) LIKE '%quincho%' THEN 1
                ELSE 2
            END,
            l.nombre
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':codigo_sucursal' => $codigoSucursal]);
    return $stmt->fetchAll();
}

function obtenerLugarEventoPorCodigo($pdo, $codigoLugar, $codigoSucursal = '') {
    $codigoLugar = limpiarInput($codigoLugar);
    $codigoSucursal = limpiarInput($codigoSucursal);
    if ($codigoLugar === '') {
        return null;
    }
    $stmt = $pdo->prepare(""
        . "SELECT l.*, s.nombre AS nombre_sucursal "
        . "FROM lugar l "
        . "JOIN sucursal s ON s.codigo_sucursal = l.codigo_sucursal "
        . "WHERE l.codigo_lugar = :codigo_lugar "
        . "AND (:codigo_sucursal = '' OR l.codigo_sucursal = :codigo_sucursal) "
        . "LIMIT 1"
    );
    $stmt->execute([
        ':codigo_lugar' => $codigoLugar,
        ':codigo_sucursal' => $codigoSucursal,
    ]);
    $lugar = $stmt->fetch();
    return $lugar ?: null;
}

function obtenerEmpresasEvento($pdo) {
    return $pdo->query('SELECT rut_empresa, nombre FROM empresa ORDER BY nombre LIMIT 300')->fetchAll();
}

function obtenerSociosContratantesEvento($pdo) {
    $sql = "
        SELECT
            s.id_socio,
            s.run_persona,
            p.nombre_completo,
            suc.nombre AS sucursal,
            s.fecha_inicio,
            s.fecha_fin
        FROM socio s
        JOIN persona p ON p.run = s.run_persona
        LEFT JOIN sucursal suc ON suc.codigo_sucursal = s.codigo_sucursal_base
        WHERE replace(lower(s.tipo_socio), ' ', '_') = 'socio_titular'
          AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURRENT_DATE)
          AND NOT EXISTS (
              SELECT 1
              FROM pago_cuota pc
              WHERE pc.id_socio = s.id_socio
                AND pc.fecha_pago IS NULL
                AND pc.cuota_numero IS NOT NULL
                AND pc.cuota_numero <= EXTRACT(MONTH FROM CURRENT_DATE)
          )
        ORDER BY p.nombre_completo
    ";
    return $pdo->query($sql)->fetchAll();
}

function obtenerSocioContratanteEventoPorId($pdo, $idSocio) {
    $stmt = $pdo->prepare(""
        . "SELECT s.id_socio, s.run_persona, p.nombre_completo "
        . "FROM socio s "
        . "JOIN persona p ON p.run = s.run_persona "
        . "WHERE s.id_socio = :id_socio "
        . "AND replace(lower(s.tipo_socio), ' ', '_') = 'socio_titular' "
        . "AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURRENT_DATE) "
        . "AND NOT EXISTS ( "
        . "  SELECT 1 FROM pago_cuota pc "
        . "  WHERE pc.id_socio = s.id_socio "
        . "    AND pc.fecha_pago IS NULL "
        . "    AND pc.cuota_numero IS NOT NULL "
        . "    AND pc.cuota_numero <= EXTRACT(MONTH FROM CURRENT_DATE) "
        . ") "
        . "LIMIT 1"
    );
    $stmt->execute([':id_socio' => (int)$idSocio]);
    $socio = $stmt->fetch();
    return $socio ?: null;
}

function eventoTieneConflictoDia($pdo, $codigoLugar, $fecha, $codigoEventoIgnorar = '') {
    $stmt = $pdo->prepare(""
        . "SELECT codigo_evento FROM evento "
        . "WHERE codigo_lugar = :codigo_lugar "
        . "AND fecha_evento = CAST(:fecha AS date) "
        . "AND (:codigo_ignorar = '' OR codigo_evento <> :codigo_ignorar) "
        . "LIMIT 1"
    );
    $stmt->execute([
        ':codigo_lugar' => $codigoLugar,
        ':fecha' => $fecha,
        ':codigo_ignorar' => $codigoEventoIgnorar,
    ]);
    return (bool)$stmt->fetch();
}

function construirSlotsDisponibilidadEvento($pdo, $codigoLugar, $fecha) {
    $slots = [];
    if ($codigoLugar === '' || !fechaValida($fecha)) {
        return $slots;
    }
    $diaBloqueadoPorEvento = eventoTieneConflictoDia($pdo, $codigoLugar, $fecha);
    for ($hora = 8; $hora < 23; $hora++) {
        $inicio = sprintf('%s %02d:00:00', $fecha, $hora);
        $fin = sprintf('%s %02d:00:00', $fecha, $hora + 1);
        $horaInicio = sprintf('%02d:00', $hora);
        $horaFin = sprintf('%02d:00', $hora + 1);
        $ocupado = $diaBloqueadoPorEvento || reservaTieneConflicto($pdo, $codigoLugar, $inicio, $fin);
        $slots[] = [
            'inicio' => $horaInicio,
            'fin' => $horaFin,
            'label' => $horaInicio . ' - ' . $horaFin,
            'ocupado' => $ocupado,
        ];
    }
    return $slots;
}

function normalizarIdentificadorAsistenteEvento($identificador) {
    $identificador = strtoupper(trim((string)$identificador));
    if ($identificador === '') {
        return null;
    }

    $sinPuntosEspacios = str_replace(['.', ' '], '', $identificador);
    if (preg_match('/^[0-9]{6,9}-[0-9K]$/', $sinPuntosEspacios) || preg_match('/^[0-9]{7,10}[0-9K]$/', $sinPuntosEspacios)) {
        $intentoRun = normalizarRun($identificador);
        if ($intentoRun !== '') {
            return $intentoRun;
        }
    }

    $identificador = preg_replace('/[^A-Z0-9\-]/', '', $identificador);
    if ($identificador === '' || strlen($identificador) > 12) {
        throw new Exception('El identificador de un invitado debe tener máximo 12 caracteres. Usa RUN o DNI corto.');
    }
    return $identificador;
}

// Cada línea del textarea puede traer RUN;Nombre o solo Nombre.
// La convierto en un arreglo para después insertar los invitados.
function parsearInvitadosEvento($texto) {
    $texto = trim((string)$texto);
    if ($texto === '') {
        return [];
    }
    $lineas = preg_split('/\r\n|\r|\n/', $texto);
    $invitados = [];
    $vistos = [];
    foreach ($lineas as $numero => $linea) {
        $linea = trim($linea);
        if ($linea === '') {
            continue;
        }
        $partesSinLimpiar = explode(';', $linea);
        $partes = [];
        foreach ($partesSinLimpiar as $parte) {
            $partes[] = trim($parte);
        }

        if (count($partes) >= 2) {
            $identificadorRaw = $partes[0];
            unset($partes[0]);
            $nombre = trim(implode(' ', $partes));
        } else {
            $identificadorRaw = '';
            $nombre = $partes[0] ?? '';
        }
        $nombre = preg_replace('/\s+/', ' ', $nombre);
        if ($nombre === '') {
            throw new Exception('Hay un invitado sin nombre en la línea ' . ($numero + 1) . '.');
        }
        if (strlen($nombre) > 150) {
            throw new Exception('El nombre de un invitado supera 150 caracteres en la línea ' . ($numero + 1) . '.');
        }
        $identificador = normalizarIdentificadorAsistenteEvento($identificadorRaw);
        $clave = ($identificador ?: 'SINID') . '|' . strtolower($nombre);
        if (isset($vistos[$clave])) {
            continue;
        }
        $vistos[$clave] = true;
        $invitados[] = [
            'run_asistente' => $identificador,
            'nombre_asistente' => $nombre,
        ];
    }
    if (count($invitados) > 500) {
        throw new Exception('La lista de invitados es demasiado grande para esta interfaz.');
    }
    return $invitados;
}

function normalizarTipoClienteEvento($tipo) {
    $tipo = normalizarEtiqueta($tipo);
    if ($tipo === 'socio') {
        return 'socio';
    }
    if ($tipo === 'empresa' || $tipo === 'empresa_institucion' || $tipo === 'empresa-institucion') {
        return 'empresa-institucion';
    }
    return '';
}

function registrarEventoCompleto($pdo, $datos, $usuario) {
    if (!esUsuarioAdministrativoGestion($usuario)) {
        throw new Exception('No tienes permiso para crear eventos.');
    }

    $codigoEvento = '';
    // El código lo genera la página para que siga la secuencia numérica de la base.
    $nombreEvento = limpiarInput($datos['nombre_evento'] ?? ($datos['nombre'] ?? ''));
    $codigoSucursal = limpiarInput($datos['codigo_sucursal'] ?? '');
    $codigoLugar = limpiarInput($datos['codigo_lugar'] ?? '');
    $fechaEvento = limpiarInput($datos['fecha_evento'] ?? '');
    $horaInicio = limpiarInput($datos['hora_inicio'] ?? '');
    $horaTermino = limpiarInput($datos['hora_termino'] ?? '');
    $tipoCliente = normalizarTipoClienteEvento($datos['tipo_cliente'] ?? '');
    $idSocioCliente = (int)limpiarInput($datos['id_socio_cliente'] ?? '0');
    $rutEmpresa = normalizarRutEmpresa($datos['rut_empresa'] ?? ($datos['identificador_cliente'] ?? ''));
    $nombreEmpresa = limpiarInput($datos['nombre_empresa'] ?? '');
    $runContacto = normalizarRun($datos['run_contacto_empresa'] ?? '');
    $nombreContacto = limpiarInput($datos['nombre_contacto_empresa'] ?? ($datos['contacto_empresa'] ?? ''));
    $cargoContacto = limpiarInput($datos['cargo_contacto_empresa'] ?? '');
    $valorEvento = limpiarInput($datos['valor_evento'] ?? ($datos['monto'] ?? ''));
    $montoPrimeraCuotaInput = limpiarInput($datos['monto_primera_cuota'] ?? '');
    $fechaPago = limpiarInput($datos['fecha_pago'] ?? date('Y-m-d'));
    $invitadosTexto = (string)($datos['invitados'] ?? '');

    if ($nombreEvento === '' || $codigoSucursal === '' || $codigoLugar === '' || $fechaEvento === '' || $horaInicio === '' || $horaTermino === '' || $tipoCliente === '' || $valorEvento === '' || $fechaPago === '') {
        throw new Exception('Faltan campos obligatorios para crear el evento.');
    }
    if (!fechaValida($fechaEvento) || !fechaValida($fechaPago)) {
        throw new Exception('La fecha del evento o del pago no tiene formato válido.');
    }
    if (!horaValidaHHMM($horaInicio) || !horaValidaHHMM($horaTermino)) {
        throw new Exception('La hora de inicio o término no es válida.');
    }
    if (!ctype_digit((string)$valorEvento) || (int)$valorEvento <= 0) {
        throw new Exception('El valor del evento debe ser un entero positivo.');
    }
    $valorEvento = (int)$valorEvento;

    // Si no se escribe monto, uso 50% como primera cuota.
    // Si se escribe, reviso que no sea más que el total del evento.
    if ($montoPrimeraCuotaInput === '') {
        $montoPrimeraCuota = (int)ceil($valorEvento * 0.5);
    } else {
        if (!ctype_digit((string)$montoPrimeraCuotaInput) || (int)$montoPrimeraCuotaInput <= 0) {
            throw new Exception('El monto de la primera cuota debe ser un entero positivo.');
        }
        $montoPrimeraCuota = (int)$montoPrimeraCuotaInput;
    }

    if ($montoPrimeraCuota > $valorEvento) {
        throw new Exception('La primera cuota no puede ser mayor que el valor total del evento.');
    }

    $inicio = $fechaEvento . ' ' . $horaInicio . ':00';
    $fin = $fechaEvento . ' ' . $horaTermino . ':00';
    if (strtotime($fin) <= strtotime($inicio)) {
        throw new Exception('La hora de término debe ser posterior a la hora de inicio.');
    }
    if (strtotime($inicio) <= time()) {
        throw new Exception('Solo se pueden crear eventos en fechas y horarios futuros.');
    }
    if ($fechaPago > $fechaEvento) {
        throw new Exception('La fecha de pago de la primera cuota no puede ser posterior a la fecha del evento.');
    }

    $lugar = obtenerLugarEventoPorCodigo($pdo, $codigoLugar, $codigoSucursal);
    if (!$lugar) {
        throw new Exception('El lugar seleccionado no existe o no pertenece a la sucursal seleccionada.');
    }

    $invitados = parsearInvitadosEvento($invitadosTexto);
    if (count($invitados) === 0) {
        throw new Exception('Debes ingresar al menos un invitado.');
    }
    if (count($invitados) > (int)$lugar['capacidad']) {
        throw new Exception('La lista de invitados supera la capacidad del lugar seleccionado.');
    }

    if ($codigoEvento === '') {
        $codigoEvento = generarCodigoEventoUnico($pdo);
    }

    $identificadorCliente = '';
    if ($tipoCliente === 'socio') {
        $socio = obtenerSocioContratanteEventoPorId($pdo, $idSocioCliente);
        if (!$socio) {
            throw new Exception('El socio contratante no existe, no está vigente o tiene deudas vencidas.');
        }
        $identificadorCliente = $socio['run_persona'];
    } elseif ($tipoCliente === 'empresa-institucion') {
        if ($rutEmpresa === '' || $nombreEmpresa === '') {
            throw new Exception('Para empresa debes ingresar RUT y nombre de la empresa.');
        }
        $identificadorCliente = $rutEmpresa;
    }

    $pdo->beginTransaction();
    try {
        sincronizarSecuenciaLocal($pdo, 'pago_evento_id_pago_evento_seq', 'pago_evento', 'id_pago_evento');
        sincronizarSecuenciaLocal($pdo, 'asistente_evento_id_asistente_seq', 'asistente_evento', 'id_asistente');
        sincronizarSecuenciaLocal($pdo, 'contacto_empresa_id_contacto_seq', 'contacto_empresa', 'id_contacto');

        $stmtCodigo = $pdo->prepare('SELECT 1 FROM evento WHERE codigo_evento = :codigo LIMIT 1');
        $stmtCodigo->execute([':codigo' => $codigoEvento]);
        if ($stmtCodigo->fetch()) {
            throw new Exception('El código de evento ya existe.');
        }

        if (reservaTieneConflicto($pdo, $codigoLugar, $inicio, $fin) || eventoTieneConflictoDia($pdo, $codigoLugar, $fechaEvento, $codigoEvento)) {
            throw new Exception('El lugar seleccionado no está disponible para esa fecha u horario.');
        }

        $codigoReservaEvento = generarCodigoReservaUnico($pdo);

        if ($tipoCliente === 'empresa-institucion') {
            $stmtExisteEmpresa = $pdo->prepare('SELECT 1 FROM empresa WHERE rut_empresa = :rut_empresa LIMIT 1');
            $stmtExisteEmpresa->execute([':rut_empresa' => $rutEmpresa]);

            if ($stmtExisteEmpresa->fetch()) {
                $stmtEmpresa = $pdo->prepare('UPDATE empresa SET nombre = :nombre WHERE rut_empresa = :rut_empresa');
            } else {
                $stmtEmpresa = $pdo->prepare('INSERT INTO empresa (rut_empresa, nombre) VALUES (:rut_empresa, :nombre)');
            }

            $stmtEmpresa->execute([
                ':rut_empresa' => $rutEmpresa,
                ':nombre' => $nombreEmpresa,
            ]);

            if ($nombreContacto !== '') {
                $stmtContacto = $pdo->prepare(''
                    . 'INSERT INTO contacto_empresa (rut_empresa, run_persona, nombre, cargo) '
                    . 'VALUES (:rut_empresa, :run_persona, :nombre, :cargo)'
                );
                $stmtContacto->execute([
                    ':rut_empresa' => $rutEmpresa,
                    ':run_persona' => $runContacto ?: null,
                    ':nombre' => $nombreContacto,
                    ':cargo' => $cargoContacto ?: null,
                ]);
            }
        }

        $stmtReserva = $pdo->prepare(''
            . "INSERT INTO reserva (codigo_reserva, codigo_lugar, run_reservante, fecha_inicio, fecha_fin, estado) "
            . "VALUES (:codigo_reserva, :codigo_lugar, :run_reservante, :fecha_inicio, :fecha_fin, 'reservada_evento')"
        );
        $stmtReserva->execute([
            ':codigo_reserva' => $codigoReservaEvento,
            ':codigo_lugar' => $codigoLugar,
            ':run_reservante' => $identificadorCliente,
            ':fecha_inicio' => $inicio,
            ':fecha_fin' => $fin,
        ]);

        $stmtEvento = $pdo->prepare(''
            . 'INSERT INTO evento (codigo_evento, nombre, fecha_evento, codigo_lugar, codigo_sucursal, tipo_cliente, identificador_cliente) '
            . 'VALUES (:codigo_evento, :nombre, :fecha_evento, :codigo_lugar, :codigo_sucursal, :tipo_cliente, :identificador_cliente)'
        );
        $stmtEvento->execute([
            ':codigo_evento' => $codigoEvento,
            ':nombre' => $nombreEvento,
            ':fecha_evento' => $fechaEvento,
            ':codigo_lugar' => $codigoLugar,
            ':codigo_sucursal' => $codigoSucursal,
            ':tipo_cliente' => $tipoCliente,
            ':identificador_cliente' => $identificadorCliente,
        ]);

        $stmtPago = $pdo->prepare(''
            . 'INSERT INTO pago_evento (codigo_evento, fecha_pago, monto, tipo_pago) '
            . 'VALUES (:codigo_evento, :fecha_pago, :monto, :tipo_pago) '
            . 'RETURNING id_pago_evento'
        );
        $stmtPago->execute([
            ':codigo_evento' => $codigoEvento,
            ':fecha_pago' => $fechaPago,
            ':monto' => $montoPrimeraCuota,
            ':tipo_pago' => 'primera_cuota',
        ]);
        $idPago = (int)$stmtPago->fetchColumn();

        $stmtInvitado = $pdo->prepare(''
            . 'INSERT INTO asistente_evento (codigo_evento, run_asistente, nombre_asistente) '
            . 'VALUES (:codigo_evento, :run_asistente, :nombre_asistente)'
        );
        foreach ($invitados as $invitado) {
            $stmtInvitado->execute([
                ':codigo_evento' => $codigoEvento,
                ':run_asistente' => $invitado['run_asistente'],
                ':nombre_asistente' => $invitado['nombre_asistente'],
            ]);
        }

        $pdo->commit();
        return [
            'codigo_evento' => $codigoEvento,
            'codigo_reserva_evento' => $codigoReservaEvento,
            'codigo_lugar' => $codigoLugar,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'tipo_cliente' => $tipoCliente,
            'identificador_cliente' => $identificadorCliente,
            'valor_evento' => $valorEvento,
            'monto_primera_cuota' => $montoPrimeraCuota,
            'id_pago_evento' => $idPago,
            'cantidad_invitados' => count($invitados),
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function obtenerUltimosEventos($pdo, $limite = 8) {
    $stmt = $pdo->prepare(""
        . "SELECT e.codigo_evento, e.nombre, e.fecha_evento, e.tipo_cliente, e.identificador_cliente, "
        . "       l.nombre AS lugar, s.nombre AS sucursal, "
        . "       (SELECT pe.monto FROM pago_evento pe WHERE pe.codigo_evento = e.codigo_evento ORDER BY pe.fecha_pago ASC, pe.id_pago_evento ASC LIMIT 1) AS primera_cuota "
        . "FROM evento e "
        . "LEFT JOIN lugar l ON l.codigo_lugar = e.codigo_lugar "
        . "LEFT JOIN sucursal s ON s.codigo_sucursal = e.codigo_sucursal "
        . "ORDER BY e.fecha_evento DESC, e.codigo_evento DESC "
        . "LIMIT :limite"
    );
    $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

?>
