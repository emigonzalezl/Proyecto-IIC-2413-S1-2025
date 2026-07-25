<?php
// Consulta inestructurada del item 2.5

require_once 'utils.php';
requireAdminGestion();

$tituloPagina = 'Consulta inestructurada';
$pdo = null;
$error = '';
$mensaje = '';
$resultados = [];
$columnasResultado = [];
$sqlSeguro = '';
$parametrosDebug = [];
$tablasDisponibles = [];
$columnasTablaSeleccionada = [];

$selectA = limpiarInput($_POST['select_a'] ?? '');
$fromB = limpiarInput($_POST['from_b'] ?? '');
$whereC = limpiarInput($_POST['where_c'] ?? '');

// Leo las tablas y vistas reales para no aceptar cualquier texto en B
function consultaSqlObtenerTablas(PDO $pdo) {
    $sql = "
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_type IN ('BASE TABLE', 'VIEW')
        ORDER BY table_name
    ";

    $filas = $pdo->query($sql)->fetchAll();
    $tablas = [];

    foreach ($filas as $fila) {
        $tablas[] = $fila['table_name'];
    }

    return $tablas;
}

function consultaSqlObtenerColumnas(PDO $pdo, $tabla) {
    $stmt = $pdo->prepare("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = :tabla
        ORDER BY ordinal_position
    ");
    $stmt->execute([':tabla' => $tabla]);

    $filas = $stmt->fetchAll();
    $columnas = [];

    foreach ($filas as $fila) {
        $columnas[] = $fila['column_name'];
    }

    return $columnas;
}

function consultaSqlQuoteIdent($identificador) {
    return '"' . str_replace('"', '""', $identificador) . '"';
}

function consultaSqlNormalizarIdentificador($valor) {
    $valor = trim((string)$valor);
    if (stripos($valor, 'public.') === 0) {
        $valor = substr($valor, 7);
    }
    return strtolower($valor);
}

function consultaSqlValidarNombreSimple($valor) {
    return preg_match('/^[a-z_][a-z0-9_]*$/', $valor) === 1;
}

// Rechazo cosas típicas de inyección, como ; o DROP.
function consultaSqlTextoPeligroso($texto) {
    $patrones = [
        '/;/' => 'No se permiten múltiples sentencias SQL.',
        '/--/' => 'No se permiten comentarios SQL.',
        '/\/\*/' => 'No se permiten comentarios SQL.',
        '/\*\//' => 'No se permiten comentarios SQL.',
        '/\b(insert|update|delete|drop|alter|create|truncate|grant|revoke|copy|execute|call|do|vacuum|analyze|union|intersect|except)\b/i' => 'No se permiten palabras SQL peligrosas.',
    ];

    foreach ($patrones as $patron => $mensaje) {
        if (preg_match($patron, $texto)) {
            throw new Exception($mensaje);
        }
    }
}

function consultaSqlPrepararTabla($tablaIngresada, array $tablasDisponibles) {
    consultaSqlTextoPeligroso($tablaIngresada);
    $tabla = consultaSqlNormalizarIdentificador($tablaIngresada);

    if ($tabla === '') {
        throw new Exception('Debes ingresar una tabla o vista en B.');
    }
    if (!consultaSqlValidarNombreSimple($tabla)) {
        throw new Exception('B sólo puede ser el nombre de una tabla o vista existente. No uses espacios, comillas ni símbolos.');
    }
    if (!in_array($tabla, $tablasDisponibles, true)) {
        throw new Exception('La tabla o vista ingresada en B no existe en el esquema public.');
    }

    return $tabla;
}

// A solo puede ser * o columnas reales separadas por coma.
function consultaSqlPrepararSelect($selectIngresado, array $columnasDisponibles) {
    consultaSqlTextoPeligroso($selectIngresado);
    $selectIngresado = trim((string)$selectIngresado);

    if ($selectIngresado === '') {
        throw new Exception('Debes ingresar al menos una columna en A.');
    }

    $columnasDisponiblesLower = [];
    foreach ($columnasDisponibles as $columnaReal) {
        $columnasDisponiblesLower[] = strtolower($columnaReal);
    }

    if ($selectIngresado === '*') {
        return '*';
    }

    if (preg_match("/[()'\"\\\\\/]/", $selectIngresado)) {
        throw new Exception('A sólo puede contener nombres de columnas separados por coma, o el símbolo *.');
    }

    $partesSinLimpiar = explode(',', $selectIngresado);
    $partes = [];
    foreach ($partesSinLimpiar as $parte) {
        $partes[] = trim($parte);
    }

    $columnasSQL = [];
    $usadas = [];

    foreach ($partes as $columna) {
        $columna = consultaSqlNormalizarIdentificador($columna);
        if ($columna === '') {
            continue;
        }
        if (!consultaSqlValidarNombreSimple($columna)) {
            throw new Exception('A contiene una columna con formato inválido. Usa sólo nombres de columnas separados por coma.');
        }
        if (!in_array($columna, $columnasDisponiblesLower, true)) {
            throw new Exception('La columna "' . $columna . '" no existe en la tabla seleccionada.');
        }
        if (!isset($usadas[$columna])) {
            $columnasSQL[] = consultaSqlQuoteIdent($columna);
            $usadas[$columna] = true;
        }
    }

    if (empty($columnasSQL)) {
        throw new Exception('Debes ingresar al menos una columna válida en A.');
    }

    return implode(', ', $columnasSQL);
}

function consultaSqlLimpiarValorCondicion($valor) {
    $valor = trim((string)$valor);
    if ($valor === '') {
        throw new Exception('La condición C tiene un valor vacío.');
    }

    $primer = substr($valor, 0, 1);
    $ultimo = substr($valor, -1);
    if (($primer === "'" && $ultimo === "'") || ($primer === '"' && $ultimo === '"')) {
        $valor = substr($valor, 1, -1);
    }

    return $valor;
}

// C acepta condiciones simples. Los valores quedan como parámetros, no pegados directo al SQL.
function consultaSqlPrepararWhere($whereIngresado, array $columnasDisponibles) {
    $whereIngresado = trim((string)$whereIngresado);

    if ($whereIngresado === '') {
        throw new Exception('Debes ingresar una condición en C.');
    }

    consultaSqlTextoPeligroso($whereIngresado);

    if (preg_match('/\bOR\b/i', $whereIngresado)) {
        throw new Exception('Por seguridad, esta versión sólo permite unir condiciones con AND.');
    }

    $columnasDisponiblesLower = [];
    foreach ($columnasDisponibles as $columnaReal) {
        $columnasDisponiblesLower[] = strtolower($columnaReal);
    }

    $partes = preg_split('/\s+AND\s+/i', $whereIngresado);
    $condicionesSQL = [];
    $params = [];
    $i = 0;

    foreach ($partes as $parte) {
        $parte = trim($parte);
        if ($parte === '') {
            continue;
        }

        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s+IS\s+(NOT\s+)?NULL$/i', $parte, $m)) {
            $columna = consultaSqlNormalizarIdentificador($m[1]);
            if (!in_array($columna, $columnasDisponiblesLower, true)) {
                throw new Exception('La columna "' . $columna . '" usada en C no existe en la tabla seleccionada.');
            }
            $condicionesSQL[] = consultaSqlQuoteIdent($columna) . ' IS ' . (!empty($m[2]) ? 'NOT ' : '') . 'NULL';
            continue;
        }

        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*(ILIKE|LIKE|<>|!=|<=|>=|=|<|>)\s*(.+)$/i', $parte, $m)) {
            throw new Exception('C debe tener condiciones simples. Ejemplos: codigo_comuna = 13101, email ILIKE %@mail.cl, fecha_fin IS NULL.');
        }

        $columna = consultaSqlNormalizarIdentificador($m[1]);
        $operador = strtoupper($m[2]);
        $valor = consultaSqlLimpiarValorCondicion($m[3]);

        if (!in_array($columna, $columnasDisponiblesLower, true)) {
            throw new Exception('La columna "' . $columna . '" usada en C no existe en la tabla seleccionada.');
        }

        if (strtoupper($valor) === 'NULL') {
            throw new Exception('Para comparar con NULL usa: ' . $columna . ' IS NULL o ' . $columna . ' IS NOT NULL.');
        }

        $nombreParametro = ':p' . $i;
        $condicionesSQL[] = consultaSqlQuoteIdent($columna) . ' ' . $operador . ' ' . $nombreParametro;
        $params[$nombreParametro] = $valor;
        $i++;
    }

    if (empty($condicionesSQL)) {
        throw new Exception('Debes ingresar al menos una condición válida en C.');
    }

    return [
        'sql' => implode(' AND ', $condicionesSQL),
        'params' => $params,
    ];
}

function consultaSqlConstruirConsultaSegura(PDO $pdo, $selectA, $fromB, $whereC, array $tablasDisponibles) {
    $tabla = consultaSqlPrepararTabla($fromB, $tablasDisponibles);
    $columnas = consultaSqlObtenerColumnas($pdo, $tabla);

    if (empty($columnas)) {
        throw new Exception('No se pudieron leer las columnas de la tabla seleccionada.');
    }

    $selectSQL = consultaSqlPrepararSelect($selectA, $columnas);
    $wherePreparado = consultaSqlPrepararWhere($whereC, $columnas);

    $sql = 'SELECT ' . $selectSQL . ' FROM ' . consultaSqlQuoteIdent($tabla) . ' WHERE ' . $wherePreparado['sql'] . ' LIMIT 100';

    return [
        'sql' => $sql,
        'params' => $wherePreparado['params'],
        'tabla' => $tabla,
        'columnas' => $columnas,
    ];
}

try {
    $pdo = conectarDB();
    $tablasDisponibles = consultaSqlObtenerTablas($pdo);

    if ($fromB !== '') {
        try {
            $tablaPreview = consultaSqlPrepararTabla($fromB, $tablasDisponibles);
            $columnasTablaSeleccionada = consultaSqlObtenerColumnas($pdo, $tablaPreview);
        } catch (Exception $e) {
            $columnasTablaSeleccionada = [];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $consulta = consultaSqlConstruirConsultaSegura($pdo, $selectA, $fromB, $whereC, $tablasDisponibles);
        $sqlSeguro = $consulta['sql'];
        $parametrosDebug = $consulta['params'];
        $columnasTablaSeleccionada = $consulta['columnas'];

        $stmt = $pdo->prepare($sqlSeguro);
        foreach ($parametrosDebug as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor);
        }
        $stmt->execute();
        $resultados = $stmt->fetchAll();

        if (!empty($resultados)) {
            $columnasResultado = array_keys($resultados[0]);
            $mensaje = 'Consulta ejecutada correctamente. Se muestran máximo 100 filas.';
        } else {
            $mensaje = 'Consulta ejecutada correctamente, pero no encontró filas.';
        }

        registrarAccionSistema(
            'CONSULTA_INESTRUCTURADA',
            'A=' . $selectA . ' B=' . $fromB . ' C=' . $whereC,
            true
        );
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        registrarAccionSistema(
            'CONSULTA_INESTRUCTURADA',
            'A=' . $selectA . ' B=' . $fromB . ' C=' . $whereC . ' ERROR=' . $error,
            false
        );
    }
}
?>
<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>
<main class="main">
  <?php include 'partials/topbar.php'; ?>

  <section class="page-title">
    <h1>Consulta inestructurada</h1>
    <p>Ingresa tres textos A, B y C. El sistema ejecuta una consulta segura con estructura <strong>SELECT A FROM B WHERE C</strong>.</p>
  </section>

  <?php mostrarFlash(); ?>
  <?php if ($error): ?>
    <?php mostrarMensaje('error', $error); ?>
  <?php endif; ?>
  <?php if ($mensaje): ?>
    <?php mostrarMensaje('success', $mensaje); ?>
  <?php endif; ?>

  <section class="grid cols-2">
    <form class="card" method="POST" action="consulta_sql.php" novalidate>
      <h3>Formulario A, B, C</h3>
      <p class="muted">Completa los campos para construir una consulta de sólo lectura. No escribas la palabra SELECT: la página la arma automáticamente.</p>

      <div class="form-stack">
        <div class="field">
          <label>A: columnas a seleccionar</label>
          <input name="select_a" value="<?php echo escaparHTML($selectA); ?>" placeholder="nombre_completo, run" required>
          <small>Usa columnas separadas por coma o <code>*</code>. Ejemplo: <code>nombre_completo, email</code>.</small>
        </div>

        <div class="field">
          <label>B: tabla o vista</label>
          <input name="from_b" value="<?php echo escaparHTML($fromB); ?>" list="tablas_disponibles" placeholder="persona" required>
          <datalist id="tablas_disponibles">
            <?php foreach ($tablasDisponibles as $tabla): ?>
              <option value="<?php echo escaparHTML($tabla); ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <small>Debe existir en el esquema <code>public</code>. Puedes usar tablas o vistas.</small>
        </div>

        <div class="field">
          <label>C: condición WHERE</label>
          <input name="where_c" value="<?php echo escaparHTML($whereC); ?>" placeholder="email ILIKE %@mail.cl" required>
          <small>Ejemplos: <code>run = 14443188-2</code>, <code>email ILIKE %@mail.cl</code>, <code>fecha_fin IS NULL</code>.</small>
        </div>

        <button class="btn" type="submit">Ejecutar consulta segura</button>
      </div>
    </form>

    <article class="card soft-card pink">
      <h3>Seguridad contra SQL injection</h3>
      <p class="muted">El pie de página del enunciado advierte que se debe evitar que el input cambie la consulta. Esta implementación lo hace así:</p>
      <ul class="clean-list">
        <li>✓ B se valida contra tablas/vistas reales de <code>information_schema</code>.</li>
        <li>✓ A sólo acepta columnas reales de la tabla elegida o <code>*</code>.</li>
        <li>✓ C sólo acepta condiciones simples y valores parametrizados.</li>
        <li>✓ Se bloquean <code>;</code>, comentarios SQL y palabras peligrosas.</li>
        <li>✓ La operación siempre es sólo lectura: <code>SELECT</code>.</li>
        <li>✓ El resultado se limita a 100 filas.</li>
      </ul>

      <h4>Consulta segura generada</h4>
      <pre class="code-preview"><?php echo $sqlSeguro ? escaparHTML($sqlSeguro) : "SELECT A\nFROM B\nWHERE C\nLIMIT 100;"; ?></pre>

      <?php if (!empty($parametrosDebug)): ?>
        <h4>Parámetros preparados</h4>
        <pre class="code-preview"><?php echo escaparHTML(print_r($parametrosDebug, true)); ?></pre>
      <?php endif; ?>
    </article>
  </section>

  <?php if (!empty($columnasTablaSeleccionada)): ?>
    <section class="card" style="margin-top:16px;">
      <h3>Columnas disponibles para <?php echo escaparHTML(consultaSqlNormalizarIdentificador($fromB)); ?></h3>
      <p class="muted">Estas son las columnas reales que puedes usar en A y C.</p>
      <div class="tag-row">
        <?php foreach ($columnasTablaSeleccionada as $columna): ?>
          <span class="pill"><?php echo escaparHTML($columna); ?></span>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="card" style="margin-top:16px;">
    <div class="section-heading">
      <div>
        <h3>Resultado</h3>
        <p class="muted">Máximo 100 filas para evitar consultas demasiado pesadas.</p>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <?php if (!empty($columnasResultado)): ?>
          <thead>
            <tr>
              <?php foreach ($columnasResultado as $columna): ?>
                <th><?php echo escaparHTML($columna); ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($resultados as $fila): ?>
              <tr>
                <?php foreach ($columnasResultado as $columna): ?>
                  <td><?php echo escaparHTML($fila[$columna] ?? ''); ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        <?php else: ?>
          <thead><tr><th>Resultado</th><th>Detalle</th></tr></thead>
          <tbody>
            <tr>
              <td>--</td>
              <td><?php echo $mensaje ? escaparHTML($mensaje) : 'Ejecuta una consulta para ver resultados.'; ?></td>
            </tr>
          </tbody>
        <?php endif; ?>
      </table>
    </div>
  </section>
</main>
<?php include 'partials/footer.php'; ?>
