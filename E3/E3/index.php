<?php
// Página de inicio de sesión.
// Acá se revisa el usuario y la clave contra la base de datos.

require_once 'utils.php';

if (estaLogueado()) {
    header('Location: dashboard.php');
    exit;
}

$mensaje = '';

if (isset($_GET['error']) && $_GET['error'] === 'debes_iniciar_sesion') {
    $mensaje = 'Debes iniciar sesión para acceder al sistema.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailLogin = limpiarInput($_POST['email_login'] ?? '');
    $clave = (string)($_POST['clave'] ?? '');

    try {
        $pdo = conectarDB();
        $usuario = buscarUsuarioPorLogin($pdo, $emailLogin);
        $loginCorrecto = $usuario && claveCoincide($clave, $usuario['clave_encriptada']) && usuarioAutorizado($usuario);

        registrarAcceso($emailLogin, $loginCorrecto);

        if ($loginCorrecto) {
            iniciarSesionUsuario($usuario);
            header('Location: dashboard.php');
            exit;
        }

        $mensaje = 'Usuario o Clave errónea';
    } catch (Exception $e) {
        registrarAcceso($emailLogin, false);
        $mensaje = 'Usuario o Clave errónea';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inicio de sesión | DCColo</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
  <main class="login-shell">
    <section class="login-hero">
      <div class="brand"><span class="brand-mark">D</span><span>DCColo</span></div>
      <h1>Excelencia deportiva y exclusividad</h1>
      <p>Gestión de socios, reservas y eventos del Club Social y Deportivo DCColo.</p>
    </section>

    <section class="login-form">
      <div class="brand"><span class="brand-mark">D</span><span>DCColo</span></div>
      <h2>Bienvenido de nuevo</h2>
      <p class="subtitle">Ingresa con tu usuario y clave almacenados en la base de datos.</p>

      <?php mostrarMensaje('error', $mensaje); ?>

      <form class="form-stack" method="POST" action="index.php" autocomplete="on" novalidate>
        <div class="field">
          <label for="email_login">Usuario / correo</label>
          <input id="email_login" name="email_login" type="text" placeholder="usuario@dccolo.cl" required>
        </div>

        <div class="field">
          <label for="clave">Contraseña</label>
          <input id="clave" name="clave" type="password" placeholder="••••••••" required>
        </div>

        <button class="btn" type="submit">Iniciar sesión →</button>
      </form>
    </section>
  </main>
</body>
</html>
