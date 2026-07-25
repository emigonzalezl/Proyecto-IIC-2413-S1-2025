<?php
// Barra superior con datos simples del usuario conectado.
 $usuario = usuarioActual(); ?>
<header class="topbar">
  <div>
    <strong><?php echo escaparHTML($tituloPagina ?? 'Panel DCColo'); ?></strong>
    <?php if ($usuario): ?>
      <span class="topbar-user">Sesión: <?php echo escaparHTML($usuario['nombre_completo']); ?> · <?php echo escaparHTML($usuario['tipo_usuario']); ?></span>
    <?php endif; ?>
  </div>
  <div class="topbar-icons">
    <span>⌕</span><span>●</span><span>⚙</span>
  </div>
</header>
