<?php
// Archivo chico para probar si PHP logra conectarse a PostgreSQL.
// No es una pantalla del enunciado, solo ayuda para revisar la conexión.

require_once 'utils.php';

header('Content-Type: text/html; charset=utf-8');

echo '<h1>Prueba de conexión a PostgreSQL</h1>';

try {
    $pdo = conectarDB();
    echo '<p style="color: green; font-weight: bold;">Conexión exitosa.</p>';

    $stmt = $pdo->query("SELECT current_database() AS base, current_user AS usuario");
    $info = $stmt->fetch();
    echo '<pre>' . escaparHTML(print_r($info, true)) . '</pre>';

    $stmt = $pdo->query("SELECT email_login, clave_encriptada, tipo_usuario FROM usuario LIMIT 5");
    $usuarios = $stmt->fetchAll();
    echo '<h2>Primeros usuarios</h2>';
    echo '<pre>' . escaparHTML(print_r($usuarios, true)) . '</pre>';
} catch (Exception $e) {
    echo '<p style="color: red; font-weight: bold;">No se pudo conectar.</p>';
    echo '<pre>' . escaparHTML($e->getMessage()) . '</pre>';
}
