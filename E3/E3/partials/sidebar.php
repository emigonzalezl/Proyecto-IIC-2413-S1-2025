<aside class="sidebar">
  <?php $esAdminMenu = esUsuarioAdministrativoGestion(); ?>
  <a class="brand" href="dashboard.php">
    <span class="brand-mark">D</span>
    <span>DCColo</span>
  </a>

  <div class="nav-title">General</div>
  <a class="nav-link <?php echo esActivo('dashboard.php'); ?>" href="dashboard.php">▣ Panel principal</a>

  <?php if ($esAdminMenu): ?>
    <a class="nav-link <?php echo esActivo('registrar_usuario.php'); ?>" href="registrar_usuario.php">◉ Registrar usuario</a>
    <a class="nav-link <?php echo esActivo('socios.php'); ?>" href="socios.php">◇ Socios titulares</a>
    <a class="nav-link <?php echo esActivo('beneficiarios.php'); ?>" href="beneficiarios.php">◎ Beneficiarios</a>
    <a class="nav-link <?php echo esActivo('cuotas.php'); ?>" href="cuotas.php">◌ Cuotas</a>
  <?php endif; ?>

  <div class="nav-title">Operaciones</div>
  <a class="nav-link <?php echo esActivo('arriendo_canchas.php'); ?>" href="arriendo_canchas.php">▤ Arriendo de canchas</a>

  <?php if ($esAdminMenu): ?>
    <a class="nav-link <?php echo esActivo('eventos.php'); ?>" href="eventos.php">✦ Eventos</a>
    <a class="nav-link <?php echo esActivo('consultas.php'); ?>" href="consultas.php">▥ Vista de Consultas</a>
    <a class="nav-link <?php echo esActivo('consulta_sql.php'); ?>" href="consulta_sql.php">⌁ Consulta SQL</a>
  <?php endif; ?>

  <div class="sidebar-bottom">
    <a class="nav-link" href="logout.php">↩ Cerrar sesión</a>
  </div>
</aside>
