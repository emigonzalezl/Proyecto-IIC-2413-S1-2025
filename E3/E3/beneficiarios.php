<?php
// Formulario para agregar beneficiarios o adicionales a un socio titular.
// La relación se guarda en relacion_socio.

require_once 'utils.php';
requireAdminGestion();
$tituloPagina = 'Beneficiarios y adicionales';

$pdo = null;
$sociosTitulares = [];
$comunas = [];
$dependientesRecientes = [];
$mensajeError = '';

try {
    $pdo = conectarDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $idDependiente = registrarDependienteSocio($pdo, $_POST);
            registrarAccionSistema('REGISTRO_BENEFICIARIO_ADICIONAL', 'id_dependiente=' . $idDependiente . ' id_titular=' . limpiarInput($_POST['id_socio_titular'] ?? '') . ' run=' . limpiarInput($_POST['run_persona'] ?? ''), true);
            setFlash('success', 'Beneficiario/adicional incorporado correctamente. ID socio dependiente: ' . $idDependiente . '. Si el trigger está cargado, el plan de pagos se actualizó automáticamente.');
            header('Location: beneficiarios.php');
            exit;
        } catch (Exception $e) {
            registrarAccionSistema('REGISTRO_BENEFICIARIO_ADICIONAL', 'id_titular=' . limpiarInput($_POST['id_socio_titular'] ?? '') . ' run=' . limpiarInput($_POST['run_persona'] ?? '') . ' error=' . $e->getMessage(), false);
            $mensajeError = 'Beneficiario/adicional no se puede registrar. ' . $e->getMessage();
        }
    }

    $sociosTitulares = obtenerSociosTitularesActivos($pdo);
    $comunas = obtenerComunas($pdo);
    $dependientesRecientes = obtenerDependientesRecientes($pdo, 12);
} catch (Exception $e) {
    $mensajeError = 'No se pudo cargar la información de beneficiarios: ' . $e->getMessage();
}
?>
<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>
<main class="main">
  <?php include 'partials/topbar.php'; ?>

  <section class="page-title page-hero hero-beneficiarios">
    <div>
      <p class="eyebrow">Administración familiar</p>
      <h1>Beneficiarios y adicionales</h1>
      <p>Selecciona un socio titular e incorpora beneficiarios o adicionales con la información necesaria para persona, socio y relación familiar.</p>
    </div>
    <span class="hero-chip">Trigger + SP</span>
  </section>

  <?php mostrarFlash(); ?>
  <?php mostrarMensaje('error', $mensajeError); ?>

  <section class="grid cols-2 align-start">
    <form class="card" method="POST" action="beneficiarios.php">
      <h3>Agregar beneficiario/adicional</h3>
      <div class="form-stack">
        <div class="field">
          <label>Socio titular</label>
          <select name="id_socio_titular" required>
            <option value="">Selecciona un socio titular</option>
            <?php foreach ($sociosTitulares as $titular): ?>
              <option value="<?php echo escaparHTML($titular['id_socio']); ?>" <?php echo (($_POST['id_socio_titular'] ?? '') == $titular['id_socio']) ? 'selected' : ''; ?>>
                <?php echo escaparHTML($titular['nombre_completo'] . ' · ' . $titular['run_persona'] . ' · ' . ($titular['nombre_sucursal'] ?? 'Sin sucursal')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Tipo de dependiente</label>
          <select name="tipo_socio" required>
            <option value="beneficiario" <?php echo (($_POST['tipo_socio'] ?? '') === 'beneficiario') ? 'selected' : ''; ?>>Beneficiario</option>
            <option value="adicional" <?php echo (($_POST['tipo_socio'] ?? '') === 'adicional') ? 'selected' : ''; ?>>Adicional</option>
          </select>
        </div>
        <div class="field">
          <label>Parentesco</label>
          <input name="parentesco" placeholder="Hijo/a, cónyuge, familiar..." value="<?php echo escaparHTML($_POST['parentesco'] ?? ''); ?>" required>
        </div>
        <div class="field">
          <label>RUN dependiente</label>
          <input name="run_persona" placeholder="12.345.678-9" value="<?php echo escaparHTML($_POST['run_persona'] ?? ''); ?>" required>
        </div>
        <div class="field">
          <label>Nombre completo</label>
          <input name="nombre_completo" value="<?php echo escaparHTML($_POST['nombre_completo'] ?? ''); ?>" required>
        </div>
        <div class="field">
          <label>Email</label>
          <input name="email" type="email" value="<?php echo escaparHTML($_POST['email'] ?? ''); ?>">
        </div>
        <div class="field">
          <label>Teléfono celular</label>
          <input name="telefono_celular" type="tel" value="<?php echo escaparHTML($_POST['telefono_celular'] ?? ''); ?>">
        </div>
        <div class="field">
          <label>Teléfono alternativo</label>
          <input name="telefono_alternativo" type="tel" value="<?php echo escaparHTML($_POST['telefono_alternativo'] ?? ''); ?>">
        </div>
        <div class="field">
          <label>Fecha de nacimiento</label>
          <input name="fecha_nacimiento" type="date" value="<?php echo escaparHTML($_POST['fecha_nacimiento'] ?? ''); ?>" required>
        </div>
        <div class="field">
          <label>Fecha inicio relación</label>
          <input name="fecha_inicio" type="date" value="<?php echo escaparHTML($_POST['fecha_inicio'] ?? date('Y-m-d')); ?>" required>
        </div>
        <div class="field">
          <label>Fecha fin relación</label>
          <input name="fecha_fin" type="date" value="<?php echo escaparHTML($_POST['fecha_fin'] ?? ''); ?>">
        </div>
        <div class="field">
          <label>Comuna</label>
          <select name="codigo_comuna">
            <option value="">Sin comuna / no informado</option>
            <?php foreach ($comunas as $comuna): ?>
              <option value="<?php echo escaparHTML($comuna['valor']); ?>" <?php echo (($_POST['codigo_comuna'] ?? '') == $comuna['valor']) ? 'selected' : ''; ?>>
                <?php echo escaparHTML($comuna['texto']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Dirección</label>
          <input name="direccion_calle" value="<?php echo escaparHTML($_POST['direccion_calle'] ?? ''); ?>">
        </div>
        <button class="btn" type="submit">Agregar dependiente</button>
      </div>
    </form>

    <article class="card info-card">
      <p class="eyebrow">Funcionamiento</p>
      <h3>Qué se guarda al enviar</h3>
      <p>El formulario inserta una nueva persona, crea su fila en socio como beneficiario o adicional, y registra la relación con el socio titular.</p>
      <p>Cuando cargues los archivos SQL de la carpeta <code>sql/</code>, el trigger sobre <code>relacion_socio</code> ejecutará el procedimiento que actualiza el plan de pagos 2026.</p>
      <div class="code-preview small-code">psql -d e3_local -f sql/procedimientos.sql<br>psql -d e3_local -f sql/triggers.sql</div>
    </article>
  </section>

  <section class="card table-card">
    <div class="section-inline">
      <div>
        <p class="eyebrow">Últimos registros</p>
        <h3>Dependientes registrados</h3>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Titular</th>
            <th>Dependiente</th>
            <th>Tipo</th>
            <th>Parentesco</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$dependientesRecientes): ?>
            <tr><td colspan="4">No hay dependientes para mostrar o no se pudo cargar la tabla.</td></tr>
          <?php endif; ?>
          <?php foreach ($dependientesRecientes as $dep): ?>
            <tr>
              <td><?php echo escaparHTML($dep['titular']); ?></td>
              <td><?php echo escaparHTML($dep['dependiente']); ?></td>
              <td><span class="badge"><?php echo escaparHTML($dep['tipo_socio']); ?></span></td>
              <td><?php echo escaparHTML($dep['parentesco']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php include 'partials/footer.php'; ?>
