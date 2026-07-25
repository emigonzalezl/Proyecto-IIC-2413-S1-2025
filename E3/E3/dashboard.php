<?php
// Página principal después de iniciar sesión.
// Desde aquí se llega a las funcionalidades del sistema.

require_once 'utils.php';
requireLogin();
$esAdminDashboard = esUsuarioAdministrativoGestion();
$tituloPagina = 'Panel principal';
?>
<?php include 'partials/header.php'; ?>
<?php include 'partials/sidebar.php'; ?>
<main class="main">
  <?php include 'partials/topbar.php'; ?>

  <section class="page-title dashboard-title">
    <p class="eyebrow">Bienvenida/o al sistema DCColo</p>
    <h1>Panel administrativo</h1>
    <p>Accede a las consultas, registros y transacciones principales de la E3.</p>
  </section>

  <?php mostrarFlash(); ?>
  <?php if (isset($_GET['error']) && $_GET['error'] === 'sin_permiso'): ?>
    <?php mostrarMensaje('error', 'No tienes permisos para acceder a esa funcionalidad.'); ?>
  <?php endif; ?>

  <section class="grid cols-4 dashboard-summary">
    <article class="stat-card card bg-yellow">
      <h3>Usuarios</h3>
      <div class="big">BD</div>
      <p>Ingreso con Persona y Usuario.</p>
    </article>
    <article class="stat-card card bg-pink">
      <h3>Socios</h3>
      <div class="big">2026</div>
      <p>Titulares, beneficiarios y adicionales.</p>
    </article>
    <article class="stat-card card bg-green">
      <h3>Reservas</h3>
      <div class="big">Cancha</div>
      <p>Arriendos y pagos asociados.</p>
    </article>
    <article class="stat-card card bg-blue">
      <h3>Eventos</h3>
      <div class="big">Club</div>
      <p>Eventos, pagos e invitados.</p>
    </article>
  </section>

  <section class="card quick-panel">
    <div class="section-heading">
      <p class="eyebrow">Acciones rápidas</p>
      <h2>¿Qué quieres hacer?</h2>
    </div>

    <div class="quick-actions">
      <?php if ($esAdminDashboard): ?>
        <a class="action-card" href="registrar_usuario.php">
          <span class="action-icon">+</span>
          <strong>Registrar usuario</strong>
          <p>Crear un Administrativo o Administrador en una transacción.</p>
        </a>
        <a class="action-card" href="socios.php">
          <span class="action-icon">S</span>
          <strong>Crear socio titular</strong>
          <p>Registrar persona, socio titular y membresía 2026.</p>
        </a>
        <a class="action-card" href="beneficiarios.php">
          <span class="action-icon">B</span>
          <strong>Beneficiarios</strong>
          <p>Agregar beneficiarios o adicionales a un socio titular.</p>
        </a>
        <a class="action-card" href="cuotas.php">
          <span class="action-icon">$</span>
          <strong>Cuotas</strong>
          <p>Generar plan 2026 y pagar la cuota impaga más antigua.</p>
        </a>
        <a class="action-card" href="consultas.php">
          <span class="action-icon">V</span>
          <strong>Vista de Consultas</strong>
          <p>Ejecutar las cinco vistas SQL del ítem 2.4.</p>
        </a>
        <a class="action-card" href="eventos.php">
          <span class="action-icon">E</span>
          <strong>Crear evento</strong>
          <p>Evento, pago inicial y lista de invitados.</p>
        </a>
      <?php endif; ?>
      <a class="action-card" href="arriendo_canchas.php">
        <span class="action-icon">R</span>
        <strong>Arrendar cancha</strong>
        <p>Seleccionar cancha, fecha y horario disponible.</p>
      </a>
    </div>
  </section>
</main>
<?php include 'partials/footer.php'; ?>
