<?php
// Página Vista de Consultas
// Ejecuta las vistas que se cargan desde sql/vistas.sql

require_once 'utils.php';
requireAdminGestion();

$tituloPagina = 'Vista de Consultas';
$pdo = conectarDB();
$sucursales = obtenerSucursales($pdo);

$reportes = [
    'agenda_sucursal' => [
        'numero' => '1',
        'titulo' => 'Agenda semanal de sucursal',
        'descripcion' => 'Día, fecha, hora, lugar y evento o socio que reservó. Se filtra por sucursal y semana.',
    ],
    'ingreso_mensual' => [
        'numero' => '2',
        'titulo' => 'Ingreso mensual por sucursal',
        'descripcion' => 'Membresías, reservas ejecutadas y eventos del mes actual, separados entre recibidos y futuros.',
    ],
    'cuotas_atrasadas' => [
        'numero' => '3',
        'titulo' => 'Socios con cuotas atrasadas',
        'descripcion' => 'Nombre, RUN, sucursal, monto y número de cuotas pendientes.',
    ],
    'hijos_29' => [
        'numero' => '4',
        'titulo' => 'Beneficiarios hijos que cumplen 29 años',
        'descripcion' => 'Beneficiarios-hijos que deben pasar a costo adicional en la renovación 2026.',
    ],
    'reporte_sucursales_2025' => [
        'numero' => '5',
        'titulo' => 'Reporte de sucursales 2025',
        'descripcion' => 'Gerente, ingresos totales y porcentaje del total del club por sucursal.',
    ],
];

$reporte = $_GET['reporte'] ?? 'agenda_sucursal';
if (!isset($reportes[$reporte])) {
    $reporte = 'agenda_sucursal';
}

$codigoSucursalDefault = $sucursales[0]['valor'] ?? '';
$codigoSucursal = limpiarInput($_GET['codigo_sucursal'] ?? $codigoSucursalDefault);
$fechaSemanaIngresada = limpiarInput($_GET['fecha_semana'] ?? '2026-04-06');
$fechaSemana = DateTime::createFromFormat('Y-m-d', $fechaSemanaIngresada) ?: new DateTime('2026-04-06');
$fechaSemana->modify('-' . ((int)$fechaSemana->format('N') - 1) . ' days');
$inicioSemana = $fechaSemana->format('Y-m-d');
$finSemanaDt = clone $fechaSemana;
$finSemanaDt->modify('+6 days');
$finSemana = $finSemanaDt->format('Y-m-d');

$filas = [];
$columnas = [];
$mensajeError = '';
$mensajeInfo = '';

function ejecutarConsultaReporte($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function columnasDeFilas($filas) {
    if (!$filas) {
        return [];
    }
    return array_keys($filas[0]);
}

try {
    if ($reporte === 'agenda_sucursal') {
        $filas = ejecutarConsultaReporte($pdo, "
            SELECT
                dia,
                fecha,
                hora,
                lugar,
                tipo_registro,
                reservado_por,
                codigo_referencia,
                estado
            FROM vista_agenda_sucursal
            WHERE codigo_sucursal = :codigo_sucursal
              AND fecha BETWEEN :inicio_semana AND :fin_semana
            ORDER BY fecha, hora, lugar, tipo_registro
        ", [
            ':codigo_sucursal' => $codigoSucursal,
            ':inicio_semana' => $inicioSemana,
            ':fin_semana' => $finSemana,
        ]);
        $mensajeInfo = 'Mostrando semana desde lunes ' . $inicioSemana . ' hasta domingo ' . $finSemana . '.';
    }

    if ($reporte === 'ingreso_mensual') {
        $filas = ejecutarConsultaReporte($pdo, "
            SELECT
                sucursal,
                concepto,
                tipo_ingreso,
                monto_total
            FROM vista_ingreso_mensual_sucursal
            WHERE codigo_sucursal = :codigo_sucursal
            ORDER BY concepto, tipo_ingreso
        ", [
            ':codigo_sucursal' => $codigoSucursal,
        ]);
        $mensajeInfo = 'Mostrando ingresos del mes actual para la sucursal seleccionada.';
    }

    if ($reporte === 'cuotas_atrasadas') {
        $filas = ejecutarConsultaReporte($pdo, "
            SELECT
                nombre_completo,
                run,
                sucursal,
                monto_atrasado,
                numero_cuotas_atrasadas
            FROM vista_cuotas_atrasadas
            ORDER BY numero_cuotas_atrasadas DESC, monto_atrasado DESC, nombre_completo
        ");
        $mensajeInfo = 'Mostrando socios con cuotas impagas hasta el mes actual.';
    }

    if ($reporte === 'hijos_29') {
        $filas = ejecutarConsultaReporte($pdo, "
            SELECT
                run_beneficiario,
                beneficiario,
                correo_beneficiario,
                telefono_beneficiario,
                run_titular,
                socio_titular,
                correo_titular,
                telefono_titular,
                sucursal,
                fecha_nacimiento
            FROM vista_beneficiarios_hijos_29
            ORDER BY socio_titular, beneficiario
        ");
        $mensajeInfo = 'Mostrando beneficiarios-hijos que cumplen 29 años durante la renovación 2026.';
    }

    if ($reporte === 'reporte_sucursales_2025') {
        $filas = ejecutarConsultaReporte($pdo, "
            SELECT
                sucursal,
                gerente_a_cargo,
                ingresos_totales,
                porcentaje_total_club
            FROM vista_reporte_sucursales_2025
            ORDER BY ingresos_totales DESC, sucursal
        ");
        $mensajeInfo = 'Mostrando reporte anual 2025 por sucursal.';
    }

    $columnas = columnasDeFilas($filas);
    registrarAccionSistema('VISTA_CONSULTAS', 'reporte=' . $reporte . ' filas=' . count($filas), true);
} catch (Exception $e) {
    $mensajeError = 'No se pudo ejecutar la vista. Verifica que hayas cargado sql/vistas.sql en la base de datos.';
    registrarAccionSistema('VISTA_CONSULTAS', 'reporte=' . $reporte . ' error=' . $e->getMessage(), false);
}
?>
<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>
<main class="main">
  <?php include 'partials/topbar.php'; ?>

  <section class="page-title hero-soft">
    <p class="eyebrow">Reportes administrativos</p>
    <h1>Vista de Consultas</h1>
    <p>Ejecuta las vistas SQL solicitadas en el ítem 2.4. Esta sección está disponible solo para usuarios Administrativo o Administrador.</p>
  </section>

  <?php mostrarFlash(); ?>
  <?php if ($mensajeError): ?>
    <?php mostrarMensaje('error', $mensajeError); ?>
  <?php elseif ($mensajeInfo): ?>
    <?php mostrarMensaje('success', $mensajeInfo); ?>
  <?php endif; ?>

  <section class="card">
    <div class="section-heading">
      <p class="eyebrow">Menú de opciones</p>
      <h2>Selecciona una vista</h2>
    </div>

    <div class="report-list">
      <?php foreach ($reportes as $clave => $datos): ?>
        <form class="report-item <?php echo $reporte === $clave ? 'selected' : ''; ?>" method="GET" action="consultas.php">
          <div>
            <strong><?php echo escaparHTML($datos['numero'] . '. ' . $datos['titulo']); ?></strong>
            <p><?php echo escaparHTML($datos['descripcion']); ?></p>
          </div>
          <input type="hidden" name="reporte" value="<?php echo escaparHTML($clave); ?>">
          <?php if (in_array($clave, ['agenda_sucursal', 'ingreso_mensual'], true)): ?>
            <input type="hidden" name="codigo_sucursal" value="<?php echo escaparHTML($codigoSucursal); ?>">
          <?php endif; ?>
          <?php if ($clave === 'agenda_sucursal'): ?>
            <input type="hidden" name="fecha_semana" value="<?php echo escaparHTML($inicioSemana); ?>">
          <?php endif; ?>
          <button class="btn secondary" type="submit">Ver</button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if (in_array($reporte, ['agenda_sucursal', 'ingreso_mensual'], true)): ?>
    <section class="card" style="margin-top:16px;">
      <div class="section-heading">
        <p class="eyebrow">Filtros</p>
        <h2>Parámetros de la consulta</h2>
      </div>
      <form class="form-grid cols-3" method="GET" action="consultas.php">
        <input type="hidden" name="reporte" value="<?php echo escaparHTML($reporte); ?>">
        <div class="field">
          <label for="codigo_sucursal">Sucursal</label>
          <select id="codigo_sucursal" name="codigo_sucursal" required>
            <?php foreach ($sucursales as $sucursal): ?>
              <option value="<?php echo escaparHTML($sucursal['valor']); ?>" <?php echo $codigoSucursal === $sucursal['valor'] ? 'selected' : ''; ?>>
                <?php echo escaparHTML($sucursal['texto']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($reporte === 'agenda_sucursal'): ?>
          <div class="field">
            <label for="fecha_semana">Fecha dentro de la semana</label>
            <input id="fecha_semana" name="fecha_semana" type="date" value="<?php echo escaparHTML($inicioSemana); ?>" required>
          </div>
          <div class="field helper-field">
            <label>Semana calculada</label>
            <div class="input-like">Lunes <?php echo escaparHTML($inicioSemana); ?></div>
          </div>
        <?php else: ?>
          <div class="field helper-field">
            <label>Mes actual</label>
            <div class="input-like"><?php echo escaparHTML(date('Y-m')); ?></div>
          </div>
        <?php endif; ?>

        <div class="form-actions align-end">
          <button class="btn" type="submit">Ejecutar vista</button>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <section class="card" style="margin-top:16px;">
    <div class="section-heading split-heading">
      <div>
        <p class="eyebrow">Resultado</p>
        <h2><?php echo escaparHTML($reportes[$reporte]['titulo']); ?></h2>
      </div>
      <span class="pill"><?php echo count($filas); ?> filas</span>
    </div>

    <?php if ($mensajeError): ?>
      <p class="muted">Carga las vistas con:</p>
      <pre class="code-block">psql -d e3_local -f sql/vistas.sql</pre>
    <?php elseif (!$filas): ?>
      <div class="empty-state">
        <strong>Sin resultados para esta selección.</strong>
        <p>Esto puede ser normal si la base no tiene datos que cumplan el filtro.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <?php foreach ($columnas as $columna): ?>
                <th><?php echo escaparHTML(str_replace('_', ' ', $columna)); ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($filas as $fila): ?>
              <tr>
                <?php foreach ($columnas as $columna): ?>
                  <td><?php echo escaparHTML($fila[$columna] ?? ''); ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php include 'partials/footer.php'; ?>
