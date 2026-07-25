<?php
// Pantalla de cuotas.
// Permite generar el plan y pagar la cuota impaga más antigua.

require_once 'utils.php';
requireAdminGestion();
$tituloPagina = 'Pago de cuotas';

$pdo = null;
$sociosTitulares = [];
$cuotas = [];
$idSocioSeleccionado = (int)($_POST['id_socio'] ?? $_POST['id_socio_pago'] ?? $_GET['id_socio'] ?? 0);
$mensajeError = '';

try {
    $pdo = conectarDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';
        try {
            if ($accion === 'generar_plan') {
                generarPlanPagosSocio($pdo, $_POST['id_socio'] ?? 0);
                registrarAccionSistema('GENERAR_PLAN_PAGOS_2026', 'id_socio=' . limpiarInput($_POST['id_socio'] ?? ''), true);
                setFlash('success', 'Plan de pagos 2026 generado o actualizado correctamente.');
                header('Location: cuotas.php?id_socio=' . urlencode((string)($_POST['id_socio'] ?? '')));
                exit;
            }
            if ($accion === 'pagar_cuota') {
                $cuota = pagarCuotaMasAntigua($pdo, $_POST);
                registrarAccionSistema('PAGO_CUOTA', 'id_socio=' . limpiarInput($_POST['id_socio_pago'] ?? '') . ' cuota=' . $cuota['cuota_numero'] . ' id_pago_cuota=' . $cuota['id_pago_cuota'], true);
                setFlash('success', 'Pago registrado correctamente para la cuota N° ' . $cuota['cuota_numero'] . '.');
                header('Location: cuotas.php?id_socio=' . urlencode((string)($_POST['id_socio_pago'] ?? '')));
                exit;
            }
        } catch (Exception $e) {
            registrarAccionSistema(($accion === 'pagar_cuota') ? 'PAGO_CUOTA' : 'GENERAR_PLAN_PAGOS_2026', 'id_socio=' . limpiarInput($_POST['id_socio'] ?? $_POST['id_socio_pago'] ?? '') . ' error=' . $e->getMessage(), false);
            $mensajeError = $e->getMessage();
        }
    }

    $sociosTitulares = obtenerSociosTitularesActivos($pdo);
    $cuotas = obtenerCuotasSocio($pdo, $idSocioSeleccionado);
} catch (Exception $e) {
    $mensajeError = 'No se pudo cargar la información de cuotas: ' . $e->getMessage();
}
?>
<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>
<main class="main">
  <?php include 'partials/topbar.php'; ?>

  <section class="page-title page-hero hero-cuotas">
    <div>
      <p class="eyebrow">Plan mensual 2026</p>
      <h1>Plan de pagos y cuotas</h1>
      <p>Genera el plan mensual usando el procedimiento almacenado y registra el pago de la cuota impaga más antigua.</p>
    </div>
    <span class="hero-chip">SP + transacción</span>
  </section>

  <?php mostrarFlash(); ?>
  <?php mostrarMensaje('error', $mensajeError); ?>

  <section class="grid cols-2 align-start">
    <form class="card" method="POST" action="cuotas.php">
      <input type="hidden" name="accion" value="generar_plan">
      <h3>Generar plan de pagos</h3>
      <p class="muted-text">Ejecuta <code>sp_generar_plan_pagos_2026</code> para el socio titular seleccionado.</p>
      <div class="form-stack">
        <div class="field">
          <label>Socio titular</label>
          <select name="id_socio" required>
            <option value="">Selecciona un socio titular</option>
            <?php foreach ($sociosTitulares as $titular): ?>
              <option value="<?php echo escaparHTML($titular['id_socio']); ?>" <?php echo ($idSocioSeleccionado === (int)$titular['id_socio']) ? 'selected' : ''; ?>>
                <?php echo escaparHTML($titular['nombre_completo'] . ' · ' . $titular['run_persona']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Año</label>
          <input name="anio" type="number" value="2026" readonly>
        </div>
        <button class="btn" type="submit">Ejecutar SP de plan de pagos</button>
      </div>
    </form>

    <form class="card" method="POST" action="cuotas.php">
      <input type="hidden" name="accion" value="pagar_cuota">
      <h3>Registrar pago de cuota</h3>
      <p class="muted-text">Actualiza la cuota impaga más antigua del socio seleccionado.</p>
      <div class="form-stack">
        <div class="field">
          <label>Socio titular</label>
          <select name="id_socio_pago" required>
            <option value="">Selecciona un socio titular</option>
            <?php foreach ($sociosTitulares as $titular): ?>
              <option value="<?php echo escaparHTML($titular['id_socio']); ?>" <?php echo ($idSocioSeleccionado === (int)$titular['id_socio']) ? 'selected' : ''; ?>>
                <?php echo escaparHTML($titular['nombre_completo'] . ' · ' . $titular['run_persona']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Medio de pago</label>
          <select name="medio_pago" required>
            <option value="Tarjeta">Tarjeta</option>
            <option value="Transferencia">Transferencia</option>
            <option value="Efectivo">Efectivo</option>
          </select>
        </div>
        <div class="field">
          <label>Fecha de pago</label>
          <input name="fecha_pago" type="date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <button class="btn success" type="submit">Pagar cuota impaga más antigua</button>
      </div>
    </form>
  </section>

  <section class="card table-card">
    <div class="section-inline">
      <div>
        <p class="eyebrow">Detalle</p>
        <h3>Cuotas del socio seleccionado</h3>
      </div>
      <?php if ($idSocioSeleccionado): ?>
        <a class="btn secondary" href="cuotas.php?id_socio=<?php echo escaparHTML($idSocioSeleccionado); ?>">Actualizar tabla</a>
      <?php endif; ?>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>N° cuota</th>
            <th>Fecha pago</th>
            <th>Medio pago</th>
            <th>Monto base</th>
            <th>Monto adicional</th>
            <th>Monto pagado</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$cuotas): ?>
            <tr><td colspan="7">Selecciona un socio y genera su plan de pagos para ver cuotas.</td></tr>
          <?php endif; ?>
          <?php foreach ($cuotas as $cuota): ?>
            <?php $pagada = !empty($cuota['fecha_pago']) && (int)$cuota['monto_pagado'] > 0; ?>
            <tr>
              <td><?php echo escaparHTML($cuota['cuota_numero']); ?></td>
              <td><?php echo escaparHTML($cuota['fecha_pago'] ?? ''); ?></td>
              <td><?php echo escaparHTML($cuota['medio_pago'] ?? ''); ?></td>
              <td>$<?php echo number_format((int)($cuota['monto_base'] ?? 0), 0, ',', '.'); ?></td>
              <td>$<?php echo number_format((int)($cuota['monto_adicional'] ?? 0), 0, ',', '.'); ?></td>
              <td>$<?php echo number_format((int)($cuota['monto_pagado'] ?? 0), 0, ',', '.'); ?></td>
              <td><span class="badge <?php echo $pagada ? 'badge-success' : 'badge-pending'; ?>"><?php echo $pagada ? 'Pagada' : 'Pendiente'; ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php include 'partials/footer.php'; ?>
