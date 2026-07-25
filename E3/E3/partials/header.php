<?php
// Cabecera HTML que se reutiliza en las páginas internas.

require_once __DIR__ . "/../utils.php";
$tituloPagina = $tituloPagina ?? "DCColo";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo limpiarInput($tituloPagina); ?> | DCColo</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="app-shell">
