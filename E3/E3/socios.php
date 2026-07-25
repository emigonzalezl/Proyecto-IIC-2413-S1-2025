<?php
// Formulario para registrar socios titulares.
// También sirve si la persona ya existía en la tabla persona.

require_once 'utils.php';
requireAdminGestion();
$tituloPagina = 'Socios titulares';

$pdo = null;
$comunas = [];
$sucursales = [];
$sociosRecientes = [];
$mensajeError = '';

try {
    $pdo = conectarDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $resultadoSocio = registrarSocioTitular($pdo, $_POST);
            registrarAccionSistema('REGISTRO_SOCIO_TITULAR', 'id_socio=' . $resultadoSocio['id_socio'] . ' id_membresia=' . $resultadoSocio['id_membresia'] . ' run=' . $resultadoSocio['run'] . ' persona=' . $resultadoSocio['estado_persona'], true);
            $detalleUsuario = ($resultadoSocio['estado_usuario'] ?? '') === 'creado' ? ' También se creó su usuario de acceso.' : ' El socio ya tenía usuario de acceso.';
            setFlash('success', 'Socio titular registrado correctamente. ID socio: ' . $resultadoSocio['id_socio'] . '.' . $detalleUsuario);
            header('Location: socios.php');
            exit;
        } catch (Exception $e) {
            registrarAccionSistema('REGISTRO_SOCIO_TITULAR', 'run=' . limpiarInput($_POST['run_persona'] ?? '') . ' error=' . $e->getMessage(), false);
            $mensajeError = 'Socio titular no se puede registrar. ' . $e->getMessage();
        }
    }

    $comunas = obtenerComunas($pdo);
    $sucursales = obtenerSucursales($pdo);
    $sociosRecientes = obtenerSociosTitularesRecientes($pdo, 8);
} catch (Exception $e) {
    $mensajeError = 'No se pudo cargar la información de socios: ' . $e->getMessage();
}
?>
<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>
<main class="main">
  <?php include 'partials/topbar.php'; ?>

  <section class="page-title page-hero hero-socios">
    <div>
      <p class="eyebrow">Administración de socios</p>
      <h1>Registro de socio titular</h1>
      <p>Registra la información personal, la ficha de socio titular y su membresía 2026 en una sola transacción.</p>
    </div>
    <span class="hero-chip">Socio titular · 2026</span>
  </section>

  <?php mostrarFlash(); ?>
  <?php mostrarMensaje('error', $mensajeError); ?>

  <form class="card form-card wide-form" method="POST" action="socios.php">
    <section class="form-section">
      <h3>Detalles de membresía</h3>
      <div class="form-grid cols-3">
        <div class="field">
          <label>Tipo de socio</label>
          <input value="Socio titular" disabled>
        </div>
        <div class="field">
          <label>Sucursal base</label>
          <select name="codigo_sucursal_base" required>
            <option value="">Selecciona una sucursal</option>
            <?php foreach ($sucursales as $sucursal): ?>
              <option value="<?php echo escaparHTML($sucursal['valor']); ?>" <?php echo (($_POST['codigo_sucursal_base'] ?? '') === $sucursal['valor']) ? 'selected' : ''; ?>>
                <?php echo escaparHTML($sucursal['texto']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Año membresía</label>
          <input name="anio" type="number" value="2026" readonly required>
          <small class="help-text">Fijado en 2026 porque el enunciado pide plan de pagos mensual para el año 2026.</small>
        </div>
        <div class="field">
          <label>Fecha inicio socio</label>
          <input name="fecha_inicio_socio" type="date" value="<?php echo escaparHTML($_POST['fecha_inicio_socio'] ?? '2026-01-01'); ?>" required>
        </div>
        <div class="field">
          <label>Fecha fin socio</label>
          <input name="fecha_fin_socio" type="date" value="<?php echo escaparHTML($_POST['fecha_fin_socio'] ?? ''); ?>">
        </div>
        <div class="field">
          <label>Fecha inicio membresía</label>
          <input name="fecha_inicio_membresia" type="date" value="<?php echo escaparHTML($_POST['fecha_inicio_membresia'] ?? '2026-01-01'); ?>" required>
        </div>
        <div class="field">
          <label>Fecha fin membresía</label>
          <input name="fecha_fin_membresia" type="date" value="<?php echo escaparHTML($_POST['fecha_fin_membresia'] ?? '2026-12-31'); ?>" required>
        </div>
      </div>
    </section>

    <section class="form-section">
      <h3>Información personal</h3>
      <div class="form-grid">
        <div class="field">
          <label>RUN</label>
          <input name="run_persona" placeholder="12.345.678-9" value="<?php echo escaparHTML($_POST['run_persona'] ?? ''); ?>" required>
        </div>
        <div class="field">
          <label>Nombre completo</label>
          <input name="nombre_completo" placeholder="Ej. Juan Pablo Silva Pérez" value="<?php echo escaparHTML($_POST['nombre_completo'] ?? ''); ?>" required>
        </div>
        <div class="field">
          <label>Email personal</label>
          <input name="email" type="email" placeholder="nombre@ejemplo.com" value="<?php echo escaparHTML($_POST['email'] ?? ''); ?>" required>
        </div>
        <div class="field">
          <label>Correo de login</label>
          <input name="email_login" type="email" placeholder="socio@ejemplo.com" value="<?php echo escaparHTML($_POST['email_login'] ?? ($_POST['email'] ?? '')); ?>" required>
          <small class="help-text">Con este correo el socio podrá iniciar sesión.</small>
        </div>
        <div class="field">
          <label>Clave de acceso</label>
          <input name="clave_usuario" type="password" placeholder="Clave para entrar" required>
        </div>
        <div class="field">
          <label>Fecha de nacimiento</label>
          <input name="fecha_nacimiento" type="date" value="<?php echo escaparHTML($_POST['fecha_nacimiento'] ?? ''); ?>" required>
        </div>
        <div class="field">
          <label>Teléfono celular</label>
          <input name="telefono_celular" type="tel" placeholder="+56 9 1234 5678" value="<?php echo escaparHTML($_POST['telefono_celular'] ?? ''); ?>">
        </div>
        <div class="field">
          <label>Teléfono alternativo</label>
          <input name="telefono_alternativo" type="tel" placeholder="+56 2 2234 5678" value="<?php echo escaparHTML($_POST['telefono_alternativo'] ?? ''); ?>">
        </div>
        <div class="field span-2">
          <label>Dirección calle</label>
          <input name="direccion_calle" placeholder="Av. Los Trappenses 1234" value="<?php echo escaparHTML($_POST['direccion_calle'] ?? ''); ?>">
        </div>
        <div class="field">
          <label>Comuna</label>
          <select name="codigo_comuna" required>
            <option value="">Selecciona una comuna</option>
            <?php foreach ($comunas as $comuna): ?>
              <option value="<?php echo escaparHTML($comuna['valor']); ?>" <?php echo (($_POST['codigo_comuna'] ?? '') == $comuna['valor']) ? 'selected' : ''; ?>>
                <?php echo escaparHTML($comuna['texto']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <div class="actions-row">
      <a class="btn secondary" href="dashboard.php">Cancelar</a>
      <button class="btn" type="submit">Registrar socio</button>
    </div>
  </form>

  <section class="card table-card">
    <div class="section-inline">
      <div>
        <p class="eyebrow">Últimos registros</p>
        <h3>Socios titulares en la base</h3>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID socio</th>
            <th>RUN</th>
            <th>Nombre</th>
            <th>Email</th>
            <th>Sucursal</th>
            <th>Inicio</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$sociosRecientes): ?>
            <tr><td colspan="6">No hay socios para mostrar o no se pudo cargar la tabla.</td></tr>
          <?php endif; ?>
          <?php foreach ($sociosRecientes as $socio): ?>
            <tr>
              <td><?php echo escaparHTML($socio['id_socio']); ?></td>
              <td><?php echo escaparHTML($socio['run_persona']); ?></td>
              <td><?php echo escaparHTML($socio['nombre_completo']); ?></td>
              <td><?php echo escaparHTML($socio['email']); ?></td>
              <td><?php echo escaparHTML($socio['sucursal']); ?></td>
              <td><?php echo escaparHTML($socio['fecha_inicio']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php include 'partials/footer.php'; ?>
