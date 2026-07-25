<?php
// Pantalla para crear eventos.
// Todo se hace en una transacción: evento, reserva, pago e invitados.

require_once 'utils.php';
requireAdminGestion();

$pdo = conectarDB();
$usuario = usuarioActual();
$mensajeExito = '';
$mensajeError = '';
$tituloPagina = 'Eventos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $resultado = registrarEventoCompleto($pdo, $_POST, $usuario);
        registrarAccionSistema(
            'CREACION_EVENTO',
            'codigo_evento=' . $resultado['codigo_evento']
            . ' codigo_reserva=' . $resultado['codigo_reserva_evento']
            . ' lugar=' . $resultado['codigo_lugar']
            . ' cliente=' . $resultado['tipo_cliente'] . ':' . $resultado['identificador_cliente']
            . ' inicio=' . $resultado['fecha_inicio']
            . ' fin=' . $resultado['fecha_fin']
            . ' primera_cuota=' . $resultado['monto_primera_cuota']
            . ' invitados=' . $resultado['cantidad_invitados'],
            true
        );
        $mensajeExito = 'Evento registrado correctamente. Código evento: ' . $resultado['codigo_evento'] . '. Código reserva: ' . $resultado['codigo_reserva_evento'] . '. Primera cuota registrada: $' . number_format($resultado['monto_primera_cuota'], 0, ',', '.');
        $_POST = [];
    } catch (Exception $e) {
        registrarAccionSistema(
            'CREACION_EVENTO',
            'codigo_evento=auto'
            . ' lugar=' . limpiarInput($_POST['codigo_lugar'] ?? '')
            . ' error=' . $e->getMessage(),
            false
        );
        $mensajeError = 'Evento no se puede registrar. ' . $e->getMessage();
    }
}

$sucursales = obtenerSucursales($pdo);
$sucursalPreview = limpiarInput($_POST['codigo_sucursal'] ?? ($_GET['codigo_sucursal'] ?? ''));
$lugares = obtenerLugaresEvento($pdo);
$sociosContratantes = obtenerSociosContratantesEvento($pdo);
$empresas = obtenerEmpresasEvento($pdo);
$ultimosEventos = obtenerUltimosEventos($pdo, 8);

$fechaPreview = limpiarInput($_POST['fecha_evento'] ?? ($_GET['fecha_evento'] ?? date('Y-m-d', strtotime('+1 day'))));
$lugarPreview = limpiarInput($_POST['codigo_lugar'] ?? ($_GET['codigo_lugar'] ?? ''));
$slotsPreview = ($lugarPreview !== '' && fechaValida($fechaPreview)) ? construirSlotsDisponibilidadEvento($pdo, $lugarPreview, $fechaPreview) : [];
?>
<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>
<main class="main">
  <?php include 'partials/topbar.php'; ?>

  <section class="page-title">
    <p class="eyebrow">Eventos y lista de invitados</p>
    <h1>Creación de evento</h1>
    <p>Registra el evento, cliente contratante, lugar disponible, primera cuota e invitados en una sola transacción.</p>
  </section>

  <?php mostrarMensaje('success', $mensajeExito); ?>
  <?php mostrarMensaje('error', $mensajeError); ?>

  <section class="grid cols-3">
    <article class="card stat-card bg-pink">
      <h3>Transacción</h3>
      <div class="big">ACID</div>
      <p>Si falla una parte, no se guarda nada.</p>
    </article>
    <article class="card stat-card bg-yellow">
      <h3>Pago inicial</h3>
      <div class="big">50%</div>
      <p>Se registra automáticamente la primera cuota.</p>
    </article>
    <article class="card stat-card bg-blue">
      <h3>Disponibilidad</h3>
      <div class="big">Lugar</div>
      <p>Se bloquean choques con reservas y eventos.</p>
    </article>
  </section>

  <form class="card form-card" method="POST" action="eventos.php">
    <section class="form-section">
      <h3>Datos del evento</h3>
      <div class="form-grid cols-3">
        <div class="field span-3">
          <label>Nombre evento</label>
          <input name="nombre_evento" maxlength="150" value="<?php echo escaparHTML($_POST['nombre_evento'] ?? ''); ?>" placeholder="Cena aniversario DCColo" required>
          <span class="field-hint">El código del evento se crea automáticamente siguiendo la numeración existente.</span>
        </div>
        <div class="field">
          <label>Fecha evento</label>
          <input name="fecha_evento" type="date" value="<?php echo escaparHTML($fechaPreview); ?>" required>
        </div>
        <div class="field">
          <label>Hora inicio</label>
          <input name="hora_inicio" type="time" value="<?php echo escaparHTML($_POST['hora_inicio'] ?? '18:00'); ?>" required>
        </div>
        <div class="field">
          <label>Hora término</label>
          <input name="hora_termino" type="time" value="<?php echo escaparHTML($_POST['hora_termino'] ?? '22:00'); ?>" required>
        </div>
      </div>
    </section>

    <section class="form-section">
      <h3>Sucursal y lugar</h3>
      <div class="form-grid">
        <div class="field">
          <label>Sucursal</label>
          <select name="codigo_sucursal" required>
            <option value="">Selecciona una sucursal</option>
            <?php foreach ($sucursales as $sucursal): ?>
              <?php $sel = ($sucursalPreview === $sucursal['valor']) ? 'selected' : ''; ?>
              <option value="<?php echo escaparHTML($sucursal['valor']); ?>" <?php echo $sel; ?>><?php echo escaparHTML($sucursal['texto']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Lugar</label>
          <select name="codigo_lugar" required>
            <option value="" data-sucursal="">Primero selecciona una sucursal</option>
            <?php foreach ($lugares as $lugar): ?>
              <?php $sel = ($lugarPreview === $lugar['codigo_lugar']) ? 'selected' : ''; ?>
              <option
                value="<?php echo escaparHTML($lugar['codigo_lugar']); ?>"
                data-sucursal="<?php echo escaparHTML($lugar['codigo_sucursal']); ?>"
                <?php echo $sel; ?>
              >
                <?php echo escaparHTML($lugar['nombre'] . ' · ' . $lugar['tipo_lugar'] . ' · cap. ' . $lugar['capacidad']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="field-hint">Acá filtro los lugares según la sucursal elegida, para no mezclar lugares de otra sede.</span>
        </div>
      </div>

      <?php if ($lugarPreview !== '' && $slotsPreview): ?>
        <div class="table-wrap compact-table" style="margin-top:14px;">
          <table>
            <thead>
              <tr><th>Fecha revisada</th><th>Horario</th><th>Estado</th></tr>
            </thead>
            <tbody>
              <?php foreach ($slotsPreview as $slot): ?>
                <tr>
                  <td><?php echo escaparHTML($fechaPreview); ?></td>
                  <td><?php echo escaparHTML($slot['label']); ?></td>
                  <td><span class="badge <?php echo $slot['ocupado'] ? 'badge-pending' : 'badge-success'; ?>"><?php echo $slot['ocupado'] ? 'Ocupado' : 'Disponible'; ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="form-section">
      <h3>Cliente contratante</h3>
      <div class="form-grid cols-3">
        <div class="field">
          <label>Tipo cliente</label>
          <select name="tipo_cliente" required>
            <?php $tipoPost = $_POST['tipo_cliente'] ?? 'socio'; ?>
            <option value="socio" <?php echo $tipoPost === 'socio' ? 'selected' : ''; ?>>Socio titular</option>
            <option value="empresa" <?php echo $tipoPost === 'empresa' ? 'selected' : ''; ?>>Empresa / institución</option>
          </select>
        </div>
        <div class="field span-2">
          <label>Socio contratante vigente</label>
          <select name="id_socio_cliente">
            <option value="">Selecciona si el cliente es socio</option>
            <?php foreach ($sociosContratantes as $socio): ?>
              <?php $sel = ((string)($_POST['id_socio_cliente'] ?? '') === (string)$socio['id_socio']) ? 'selected' : ''; ?>
              <option value="<?php echo (int)$socio['id_socio']; ?>" <?php echo $sel; ?>>
                <?php echo escaparHTML($socio['nombre_completo'] . ' · ' . $socio['run_persona'] . ' · ' . ($socio['sucursal'] ?? 'Sin sucursal')); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="field-hint">Para socios se exige vigencia y sin deudas vencidas.</span>
        </div>
        <div class="field">
          <label>RUT empresa</label>
          <input name="rut_empresa" value="<?php echo escaparHTML($_POST['rut_empresa'] ?? ''); ?>" placeholder="76000000-0">
        </div>
        <div class="field span-2">
          <label>Nombre empresa</label>
          <input name="nombre_empresa" maxlength="150" value="<?php echo escaparHTML($_POST['nombre_empresa'] ?? ''); ?>" placeholder="Empresa SpA">
        </div>
        <div class="field">
          <label>RUN contacto empresa</label>
          <input name="run_contacto_empresa" value="<?php echo escaparHTML($_POST['run_contacto_empresa'] ?? ''); ?>" placeholder="12345678-9">
        </div>
        <div class="field">
          <label>Nombre contacto</label>
          <input name="nombre_contacto_empresa" maxlength="50" value="<?php echo escaparHTML($_POST['nombre_contacto_empresa'] ?? ''); ?>" placeholder="Nombre contacto">
        </div>
        <div class="field">
          <label>Cargo contacto</label>
          <input name="cargo_contacto_empresa" maxlength="50" value="<?php echo escaparHTML($_POST['cargo_contacto_empresa'] ?? ''); ?>" placeholder="Gerente, productor, etc.">
        </div>
      </div>
    </section>

    <section class="form-section">
      <h3>Valor y pago de la primera cuota</h3>
      <div class="form-grid cols-3">
        <div class="field">
          <label>Valor total del evento</label>
          <input id="valor_evento" name="valor_evento" type="number" min="1" step="1" value="<?php echo escaparHTML($_POST['valor_evento'] ?? ''); ?>" placeholder="800000" required>
        </div>
        <div class="field">
          <label>Monto primera cuota</label>
          <input id="monto_primera_cuota" name="monto_primera_cuota" type="number" min="1" step="1" value="<?php echo escaparHTML($_POST['monto_primera_cuota'] ?? ''); ?>" placeholder="Si lo dejas vacío usa 50%">
          <span class="field-hint">No puede ser mayor que el valor total del evento.</span>
        </div>
        <div class="field">
          <label>Fecha pago primera cuota</label>
          <input name="fecha_pago" type="date" value="<?php echo escaparHTML($_POST['fecha_pago'] ?? date('Y-m-d')); ?>" required>
          <span class="field-hint">Se inserta en pago_evento como primera_cuota.</span>
        </div>
      </div>
    </section>

    <section class="form-section">
      <h3>Lista de invitados</h3>
      <div class="form-grid">
        <div class="field span-2">
          <label>Invitados</label>
          <textarea name="invitados" rows="8" placeholder="RUN o DNI; Nombre invitado&#10;12345678-9; Ana Pérez&#10;DNI001; Invitado Extranjero&#10;Invitado sin identificador" required><?php echo escaparHTML($_POST['invitados'] ?? ''); ?></textarea>
          <span class="field-hint">Una línea por invitado. El identificador puede omitirse, pero el nombre es obligatorio.</span>
        </div>
      </div>
    </section>

    <div class="actions-row">
      <a class="btn secondary" href="dashboard.php">Cancelar</a>
      <button class="btn" type="submit">Crear evento</button>
    </div>
  </form>

  <section class="card table-card">
    <div class="section-heading">
      <p class="eyebrow">Últimos registros</p>
      <h2>Eventos recientes</h2>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Código</th><th>Evento</th><th>Fecha</th><th>Sucursal</th><th>Lugar</th><th>Cliente</th><th>Primera cuota</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ultimosEventos as $evento): ?>
            <tr>
              <td><?php echo escaparHTML($evento['codigo_evento']); ?></td>
              <td><?php echo escaparHTML($evento['nombre']); ?></td>
              <td><?php echo escaparHTML($evento['fecha_evento']); ?></td>
              <td><?php echo escaparHTML($evento['sucursal']); ?></td>
              <td><?php echo escaparHTML($evento['lugar']); ?></td>
              <td><?php echo escaparHTML($evento['tipo_cliente'] . ' · ' . $evento['identificador_cliente']); ?></td>
              <td><?php echo $evento['primera_cuota'] !== null ? '$' . number_format((int)$evento['primera_cuota'], 0, ',', '.') : '—'; ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$ultimosEventos): ?>
            <tr><td colspan="7">No hay eventos registrados.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script>
// Este JS es solo para que la lista de lugares se ordene mejor en la página.
// Igual la validación importante se vuelve a hacer en PHP antes de insertar.
const sucursalEvento = document.querySelector('select[name="codigo_sucursal"]');
const lugarEvento = document.querySelector('select[name="codigo_lugar"]');
const valorEvento = document.getElementById('valor_evento');
const montoPrimeraCuota = document.getElementById('monto_primera_cuota');
let lugaresOriginales = [];

if (lugarEvento) {
  lugarEvento.querySelectorAll('option').forEach(function(opcion) {
    lugaresOriginales.push({
      value: opcion.value,
      text: opcion.textContent,
      sucursal: opcion.dataset.sucursal || '',
      selected: opcion.selected
    });
  });
}

function filtrarLugaresPorSucursal() {
  if (!sucursalEvento || !lugarEvento) {
    return;
  }

  const codigoSucursal = sucursalEvento.value;
  const lugarElegidoAntes = lugarEvento.value;
  lugarEvento.innerHTML = '';

  const primeraOpcion = document.createElement('option');
  primeraOpcion.value = '';
  primeraOpcion.textContent = codigoSucursal === '' ? 'Primero selecciona una sucursal' : 'Selecciona un lugar';
  lugarEvento.appendChild(primeraOpcion);

  lugaresOriginales.forEach(function(opcionGuardada) {
    if (opcionGuardada.value === '') {
      return;
    }
    if (codigoSucursal === '' || opcionGuardada.sucursal !== codigoSucursal) {
      return;
    }

    const opcionNueva = document.createElement('option');
    opcionNueva.value = opcionGuardada.value;
    opcionNueva.textContent = opcionGuardada.text;
    if (opcionGuardada.value === lugarElegidoAntes || opcionGuardada.selected) {
      opcionNueva.selected = true;
      opcionGuardada.selected = false;
    }
    lugarEvento.appendChild(opcionNueva);
  });

  if (codigoSucursal === '') {
    lugarEvento.value = '';
  }
}

function actualizarMaximoPrimeraCuota() {
  if (!valorEvento || !montoPrimeraCuota) {
    return;
  }

  if (valorEvento.value !== '') {
    montoPrimeraCuota.max = valorEvento.value;
  } else {
    montoPrimeraCuota.removeAttribute('max');
  }
}

if (sucursalEvento) {
  sucursalEvento.addEventListener('change', filtrarLugaresPorSucursal);
  filtrarLugaresPorSucursal();
}

if (valorEvento) {
  valorEvento.addEventListener('input', actualizarMaximoPrimeraCuota);
  actualizarMaximoPrimeraCuota();
}
</script>

<?php include 'partials/footer.php'; ?>
