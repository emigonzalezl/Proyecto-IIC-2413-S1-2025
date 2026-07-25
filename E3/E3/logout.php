<?php
// Cierra la sesión actual y vuelve al login.

require_once 'utils.php';
cerrarSesion();
header('Location: index.php');
exit;
?>
