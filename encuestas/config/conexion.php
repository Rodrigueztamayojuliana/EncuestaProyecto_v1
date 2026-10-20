<?php
// Conexión a la base de datos MySQL (XAMPP)
$host    = "localhost";
$usuario = "root";
$clave   = "";
$base    = "encuestas_db";

$conexion = new mysqli($host, $usuario, $clave, $base);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");


