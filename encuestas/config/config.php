<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "encuestas_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
