<?php
// Pantalla de arriendo de canchas.
// Se revisa disponibilidad antes de insertar la reserva.

require_once 'utils.php';
requireAccesoArriendoCanchas();
$tituloPagina = 'Arriendo de canchas';

$pdo = null;
$usuario = usuarioActual();
$mensajeError = '';
$sucursales = [];
$canchas = [];
$sociosReservantes = [];
$socioSesion = null;
$slots = [];
$semana = [];
$reservasDia = [];
$ultimasReservas = [];
$canchaSeleccionada = null;

$codigoSucursal = limpiarInput($_GET['codigo_sucursal'] ?? $_POST['codigo_sucursal'] ?? '');
$codigoLugar = limpiarInput($_GET['codigo_lugar'] ?? $_POST['codigo_lugar'] ?? '');
$fechaSeleccionada = limpiarInput($_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d'));
if (!fechaValida($fechaSeleccionada)) {
    $fechaSeleccionada = date('Y-m-d');
}

try {
    $pdo = conectarDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $reserva = registrarArriendoCancha($pdo, $_POST, $usuario);
            registrarAccionSistema('ARRIENDO_CANCHA', 'codigo_reserva=' . $reserva['codigo_reserva'] . ' lugar=' . $reserva['codigo_lugar'] . ' run=' . $reserva['run_reservante'] . ' inicio=' . $reserva['fecha_inicio'] . ' fin=' . $reserva['fecha_fin'], true);
            setFlash('success', 'Reserva registrada correctamente. Código: ' . $reserva['codigo_reserva'] . '. Estado: reservada.');
            header('Location: arriendo_canchas.php?codigo_sucursal=' . urlencode($codigoSucursal) . '&codigo_lugar=' . urlencode($codigoLugar) . '&fecha=' . urlencode($fechaSeleccionada));
            exit;
        } catch (Exception $e) {
            registrarAccionSistema('ARRIENDO_CANCHA', 'lugar=' . limpiarInput($_POST['codigo_lugar'] ?? '') . ' fecha=' . limpiarInput($_POST['fecha'] ?? '') . ' slot=' . limpiarInput($_POST['slot'] ?? '') . ' error=' . $e->getMessage(), false);
            $mensajeError = $e->getMessage();
        }
    }

    $sucursales = obtenerSucursales($pdo);

    if ($codigoSucursal === '' && $sucursales) {
        $codigoSucursal = (string)$sucursales[0]['valor'];
    }

    $canchas = obtenerCanchasArrendables($pdo, $codigoSucursal);

    if ($codigoLugar === '' && $canchas) {
        $codigoLugar = (string)$canchas[0]['codigo_lugar'];
    }

    if ($codigoLugar !== '') {
        $canchaSeleccionada = obtenerCanchaPorCodigo($pdo, $codigoLugar);
        $slots = construirSlotsDisponibilidad($pdo, $codigoLugar, $fechaSeleccionada);
        $semana = construirDisponibilidadSemana($pdo, $codigoLugar, $fechaSeleccionada);
        $reservasDia = obtenerReservasDiaCancha($pdo, $codigoLugar, $fechaSeleccionada);
    }

    if (esSocioTitularSesion($usuario)) {
        $socioSesion = obtenerSocioTitularActivoPorRun($pdo, $usuario['run_persona'] ?? '');
    } else {
        $sociosReservantes = obtenerSociosTitularesActivosSinDeuda($pdo);
    }

    $ultimasReservas = obtenerUltimasReservasCancha($pdo, 8);
} catch (Exception $e) {
    $mensajeError = 'No se pudo cargar arriendo de canchas: ' . $e->getMessage();
}
?>
<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>
<main class="main">
  <?php include 'partials/topbar.php'; ?>

  <section class="page-title page-hero hero-canchas">
    <div>
      <p class="eyebrow">Reserva deportiva</p>
      <h1>Arriendo de canchas</h1>
      <p>Selecciona una cancha, revisa disponibilidad semanal, elige un horario y registra la reserva en estado <strong>reservada</strong>.</p>
    </div>
    <span class="hero-chip">Transacción segura</span>
  </section>

  <?php mostrarFlash(); ?>
  <?php mostrarMensaje('error', $mensajeError); ?>

  <section class="card filter-card">
    <form class="form-grid cols-3" method="GET" action="arriendo_canchas.php">
      <div class="field">
        <label>Sucursal</label>
        <select name="codigo_sucursal" onchange="this.form.submit()">
          <option value="">Selecciona una sucursal</option>
          <?php foreach ($sucursales as $sucursal): ?>
            <option value="<?php echo escaparHTML($sucursal['valor']); ?>" <?php echo ($codigoSucursal === (string)$sucursal['valor']) ? 'selected' : ''; ?>>
              <?php echo escaparHTML($sucursal['texto']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Cancha disponible para arriendo</label>
        <select name="codigo_lugar" required>
          <?php if (!$canchas): ?>
            <option value="">No hay canchas para esta sucursal</option>
          <?php endif; ?>
          <?php foreach ($canchas as $cancha): ?>
            <option value="<?php echo escaparHTML($cancha['codigo_lugar']); ?>" <?php echo ($codigoLugar === (string)$cancha['codigo_lugar']) ? 'selected' : ''; ?>>
              <?php echo escaparHTML($cancha['nombre'] . ' · ' . $cancha['nombre_sucursal']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Día seleccionado</label>
        <input name="fecha" type="date" value="<?php echo escaparHTML($fechaSeleccionada); ?>" required>
      </div>
      <div class="actions-row span-3 compact-actions">
        <button class="btn" type="submit">Ver disponibilidad</button>
      </div>
    </form>
  </section>

  <section class="grid cols-2 align-start cancha-layout">
    <article class="card">
      <div class="section-inline">
        <div>
          <p class="eyebrow">Calendario</p>
          <h3>Disponibilidad semanal</h3>
        </div>
        <?php if ($canchaSeleccionada): ?>
          <span class="badge"><?php echo escaparHTML($canchaSeleccionada['nombre']); ?></span>
        <?php endif; ?>
      </div>

      <?php if (!$codigoLugar): ?>
        <p class="muted-text">Selecciona una cancha para ver el calendario.</p>
      <?php else: ?>
        <div class="week-availability">
          <?php foreach ($semana as $dia): ?>
            <?php
              $active = $dia['fecha'] === $fechaSeleccionada;
              $sinCupos = (int)$dia['disponibles'] === 0;
              $link = 'arriendo_canchas.php?codigo_sucursal=' . urlencode($codigoSucursal) . '&codigo_lugar=' . urlencode($codigoLugar) . '&fecha=' . urlencode($dia['fecha']);
            ?>
            <a class="day-card <?php echo $active ? 'active' : ''; ?> <?php echo $sinCupos ? 'full' : ''; ?>" href="<?php echo escaparHTML($link); ?>">
              <span><?php echo escaparHTML($dia['dia_corto']); ?></span>
              <strong><?php echo escaparHTML($dia['dia_numero']); ?></strong>
              <small><?php echo escaparHTML($dia['disponibles']); ?>/<?php echo escaparHTML($dia['total']); ?> libres</small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="slot-legend">
        <span><i class="legend-free"></i> Disponible</span>
        <span><i class="legend-busy"></i> Ocupado</span>
      </div>

      <div class="slot-grid">
        <?php if (!$slots): ?>
          <p class="muted-text">No hay horarios para mostrar todavía.</p>
        <?php endif; ?>
        <?php foreach ($slots as $slot): ?>
          <div class="slot-card <?php echo $slot['ocupado'] ? 'busy' : 'free'; ?>">
            <strong><?php echo escaparHTML($slot['label']); ?></strong>
            <small>
              <?php if ($slot['ocupado']): ?>
                Ocupado
              <?php elseif ($slot['monto'] !== null): ?>
                Disponible · $<?php echo number_format((int)$slot['monto'], 0, ',', '.'); ?>
              <?php else: ?>
                Disponible
              <?php endif; ?>
            </small>
          </div>
        <?php endforeach; ?>
      </div>
    </article>

    <form id="form-reserva-cancha" class="card" method="POST" action="arriendo_canchas.php">
      <input type="hidden" name="codigo_sucursal" value="<?php echo escaparHTML($codigoSucursal); ?>">
      <input type="hidden" name="codigo_lugar" value="<?php echo escaparHTML($codigoLugar); ?>">
      <input type="hidden" name="fecha" value="<?php echo escaparHTML($fechaSeleccionada); ?>">

      <p class="eyebrow">Nueva reserva</p>
      <h3>Confirmar arriendo</h3>
      <p class="muted-text">La reserva se insertará en la tabla <code>reserva</code> con estado <code>reservada</code>.</p>

      <div class="reservation-summary">
        <div>
          <span>Sucursal</span>
          <strong><?php echo escaparHTML($canchaSeleccionada['nombre_sucursal'] ?? 'Selecciona una cancha'); ?></strong>
        </div>
        <div>
          <span>Cancha</span>
          <strong><?php echo escaparHTML($canchaSeleccionada['nombre'] ?? 'Sin cancha'); ?></strong>
        </div>
        <div>
          <span>Fecha</span>
          <strong><?php echo escaparHTML($fechaSeleccionada); ?></strong>
        </div>
      </div>

      <div class="field">
        <label>Horario disponible</label>
        <select name="slot" required <?php echo (!$slots) ? 'disabled' : ''; ?>>
          <option value="">Selecciona hora de inicio y término</option>
          <?php foreach ($slots as $slot): ?>
            <?php if (!$slot['ocupado']): ?>
              <option value="<?php echo escaparHTML($slot['inicio'] . '|' . $slot['fin']); ?>" <?php echo (($_POST['slot'] ?? '') === ($slot['inicio'] . '|' . $slot['fin'])) ? 'selected' : ''; ?>>
                <?php echo escaparHTML($slot['label']); ?><?php echo $slot['monto'] !== null ? ' · $' . number_format((int)$slot['monto'], 0, ',', '.') : ''; ?>
              </option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
        <small class="field-hint">Este horario se guarda como fecha_inicio y fecha_fin en la reserva.</small>
      </div>

      <?php if (esSocioTitularSesion($usuario)): ?>
        <div class="field">
          <label>Socio titular</label>
          <input value="<?php echo escaparHTML(($socioSesion['nombre_completo'] ?? $usuario['nombre_completo']) . ' · ' . ($usuario['run_persona'] ?? '')); ?>" readonly>
        </div>
        <p class="helper-text">Los datos del socio titular se completan automáticamente desde la sesión.</p>
      <?php else: ?>
        <div class="field">
          <label>Socio titular activo sin deudas</label>
          <select name="id_socio_reservante" required>
            <option value="">Selecciona un socio titular</option>
            <?php foreach ($sociosReservantes as $socio): ?>
              <option value="<?php echo escaparHTML($socio['id_socio']); ?>" <?php echo (($_POST['id_socio_reservante'] ?? '') == $socio['id_socio']) ? 'selected' : ''; ?>>
                <?php echo escaparHTML($socio['nombre_completo'] . ' · ' . $socio['run_persona'] . ' · ' . ($socio['nombre_sucursal'] ?? 'Sin sucursal')); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (!$sociosReservantes): ?>
            <small class="field-hint">No se encontraron socios titulares disponibles. Revisa que existan socios titulares en la base local.</small>
          <?php else: ?>
            <small class="field-hint">Se muestran socios titulares habilitados para reservar según los datos cargados.</small>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="actions-row">
        <a class="btn secondary" href="arriendo_canchas.php">Cancelar</a>
        <button class="btn success" type="submit" <?php echo (!$codigoLugar || !$slots) ? 'disabled' : ''; ?>>Confirmar reserva</button>
      </div>
    </form>
  </section>

  <section class="grid cols-2 align-start">
    <article class="card table-card">
      <div class="section-inline">
        <div>
          <p class="eyebrow">Día seleccionado</p>
          <h3>Reservas de la cancha</h3>
        </div>
      </div>
      <div class="table-wrap compact-table">
        <table>
          <thead>
            <tr>
              <th>Código</th>
              <th>Horario</th>
              <th>Reservante</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$reservasDia): ?>
              <tr><td colspan="4">No hay reservas registradas para esta cancha en el día seleccionado.</td></tr>
            <?php endif; ?>
            <?php foreach ($reservasDia as $res): ?>
              <tr>
                <td><?php echo escaparHTML($res['codigo_reserva']); ?></td>
                <td><?php echo escaparHTML(substr($res['fecha_inicio'], 11, 5) . ' - ' . substr($res['fecha_fin'], 11, 5)); ?></td>
                <td><?php echo escaparHTML($res['nombre_completo'] ?? $res['run_reservante']); ?></td>
                <td><span class="badge <?php echo normalizarEtiqueta($res['estado']) === 'reservada' ? 'badge-success' : 'badge-pending'; ?>"><?php echo escaparHTML($res['estado']); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </article>

    <article class="card table-card">
      <div class="section-inline">
        <div>
          <p class="eyebrow">Últimas reservas</p>
          <h3>Historial de canchas</h3>
        </div>
      </div>
      <div class="table-wrap compact-table">
        <table>
          <thead>
            <tr>
              <th>Código</th>
              <th>Cancha</th>
              <th>Fecha</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$ultimasReservas): ?>
              <tr><td colspan="4">No hay reservas de cancha para mostrar.</td></tr>
            <?php endif; ?>
            <?php foreach ($ultimasReservas as $res): ?>
              <tr>
                <td><?php echo escaparHTML($res['codigo_reserva']); ?></td>
                <td><?php echo escaparHTML($res['lugar']); ?></td>
                <td><?php echo escaparHTML(substr($res['fecha_inicio'], 0, 16)); ?></td>
                <td><span class="badge"><?php echo escaparHTML($res['estado']); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</main>
<?php include 'partials/footer.php'; ?>
