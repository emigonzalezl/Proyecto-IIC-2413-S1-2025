<?php
// Formulario para crear usuarios administrativos o administradores.
// Se usa transacción porque se insertan datos en más de una tabla.

require_once 'utils.php';
requireRol(['administrativo', 'administrador']);

$tituloPagina = 'Registrar usuario';
$mensajeExito = '';
$mensajeError = '';
$cargos = [];
$sucursales = [];
$comunas = [];

try {
    $pdo = conectarDB();
    $cargos = obtenerOpciones($pdo, 'cargo', 'id_cargo', 'nombre');
    $sucursales = obtenerOpciones($pdo, 'sucursal', 'codigo_sucursal', 'nombre');
    $comunas = obtenerOpciones($pdo, 'comuna', 'codigo_comuna', 'nombre');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            registrarNuevoUsuarioAdministrativo($pdo, $_POST);
            registrarAccionSistema('REGISTRO_USUARIO', 'email_login=' . limpiarInput($_POST['email_login'] ?? '') . ' run=' . limpiarInput($_POST['run_persona'] ?? ''), true);
            setFlash('success', 'Usuario registrado correctamente');
            header('Location: dashboard.php');
            exit;
        } catch (Exception $e) {
            registrarAccionSistema('REGISTRO_USUARIO', 'email_login=' . limpiarInput($_POST['email_login'] ?? '') . ' run=' . limpiarInput($_POST['run_persona'] ?? '') . ' error=' . $e->getMessage(), false);
            $mensajeError = 'Usuario no se puede registrar';
        }
    }
} catch (Exception $e) {
    $mensajeError = 'No se pudo cargar la información de apoyo desde la base de datos.';
}

function valorCampo($nombre) {
    return escaparHTML($_POST[$nombre] ?? '');
}
?>
<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>
<main class="main">
  <?php include 'partials/topbar.php'; ?>

  <section class="page-title">
    <h1>Nuevo perfil administrativo</h1>
    <p>Inserta en persona, usuario y persona_cargo como una sola transacción.</p>
  </section>

  <?php mostrarMensaje('success', $mensajeExito); ?>
  <?php mostrarMensaje('error', $mensajeError); ?>

  <form class="card form-card" method="POST" action="registrar_usuario.php" autocomplete="off">
    <section class="form-section">
      <h3>Credenciales de acceso</h3>
      <div class="form-grid">
        <div class="field">
          <label>Correo de acceso</label>
          <input name="email_login" type="email" placeholder="admin@dccolo.cl" value="<?php echo valorCampo('email_login'); ?>" required>
        </div>
        <div class="field">
          <label>Contraseña</label>
          <input name="clave_encriptada" type="password" placeholder="Contraseña segura" required>
        </div>
        <div class="field">
          <label>Tipo de usuario</label>
          <select name="tipo_usuario" required>
            <option value="">Seleccionar</option>
            <option value="administrativo" <?php echo (($_POST['tipo_usuario'] ?? '') === 'administrativo') ? 'selected' : ''; ?>>Administrativo</option>
            <option value="administrador" <?php echo (($_POST['tipo_usuario'] ?? '') === 'administrador') ? 'selected' : ''; ?>>Administrador</option>
          </select>
        </div>
        <div class="field">
          <label>Cargo</label>
          <select name="id_cargo" required>
            <option value="">Seleccionar cargo</option>
            <?php foreach ($cargos as $cargo): ?>
              <option value="<?php echo escaparHTML($cargo['valor']); ?>" <?php echo (($_POST['id_cargo'] ?? '') == $cargo['valor']) ? 'selected' : ''; ?>>
                <?php echo escaparHTML($cargo['texto']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <section class="form-section">
      <h3>Información laboral</h3>
      <div class="form-grid cols-3">
        <div class="field">
          <label>RUN persona</label>
          <input name="run_persona" placeholder="12345678-9" value="<?php echo valorCampo('run_persona'); ?>" required>
        </div>
        <div class="field">
          <label>Sucursal</label>
          <select name="codigo_sucursal" required>
            <option value="">Seleccionar sucursal</option>
            <?php foreach ($sucursales as $sucursal): ?>
              <option value="<?php echo escaparHTML($sucursal['valor']); ?>" <?php echo (($_POST['codigo_sucursal'] ?? '') == $sucursal['valor']) ? 'selected' : ''; ?>>
                <?php echo escaparHTML($sucursal['texto']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Fecha de inicio</label>
          <input name="fecha_inicio" type="date" value="<?php echo valorCampo('fecha_inicio'); ?>" required>
        </div>
        <div class="field">
          <label>Fecha de término opcional</label>
          <input name="fecha_termino" type="date" value="<?php echo valorCampo('fecha_termino'); ?>">
        </div>
      </div>
    </section>

    <section class="form-section">
      <h3>Datos personales</h3>
      <div class="form-grid">
        <div class="field span-2">
          <label>Nombre completo</label>
          <input name="nombre_completo" placeholder="Nombre Apellido" value="<?php echo valorCampo('nombre_completo'); ?>" required>
        </div>
        <div class="field">
          <label>Email personal</label>
          <input name="email" type="email" placeholder="persona@email.com" value="<?php echo valorCampo('email'); ?>">
        </div>
        <div class="field">
          <label>Fecha de nacimiento</label>
          <input name="fecha_nacimiento" type="date" value="<?php echo valorCampo('fecha_nacimiento'); ?>" required>
        </div>
        <div class="field">
          <label>Teléfono celular</label>
          <input name="telefono_celular" type="tel" placeholder="912345678" value="<?php echo valorCampo('telefono_celular'); ?>">
        </div>
        <div class="field">
          <label>Teléfono alternativo</label>
          <input name="telefono_alternativo" type="tel" placeholder="912345678" value="<?php echo valorCampo('telefono_alternativo'); ?>">
        </div>
        <div class="field span-2">
          <label>Dirección</label>
          <input name="direccion_calle" placeholder="Av. Siempre Viva 123" value="<?php echo valorCampo('direccion_calle'); ?>">
        </div>
        <div class="field">
          <label>Comuna</label>
          <select name="codigo_comuna" required>
            <option value="">Seleccionar comuna</option>
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
      <button class="btn success" type="submit">Registrar usuario</button>
    </div>
  </form>
</main>
<?php include 'partials/footer.php'; ?>
