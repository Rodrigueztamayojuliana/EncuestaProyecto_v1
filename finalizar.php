 <?php
include(__DIR__ . "/encuestas/config/config.php"); // conexión a la base de datos

// Recibir respuestas desde los formularios
$empleos       = $_POST['empleos'] ?? null;
$justificacion1 = $_POST['justificacion'] ?? null;

$impacto       = $_POST['impacto'] ?? null;
$justificacion2 = $_POST['justificacion2'] ?? null;

$miedo         = $_POST['miedo'] ?? null;
$justificacion3 = $_POST['justificacion3'] ?? null;

// Registrar encuestado (ejemplo simple, puedes ampliar con más datos)
$sql_encuestado = "INSERT INTO encuestados (nombre) VALUES ('Anonimo')";
//if ($conn->query($sql_encuestado) === TRUE) {
    $id_encuestado = $conn->insert_id;

    // Guardar respuestas
    //$conn->query("INSERT INTO respuestas (id_pregunta, id_encuestado, respuesta_texto, fecha_respuesta) 
    //              VALUES (1, $id_encuestado, '$empleos - $justificacion1', NOW())");

    //$conn->query("INSERT INTO respuestas (id_pregunta, id_encuestado, respuesta_texto, fecha_respuesta) 
    //              VALUES (2, $id_encuestado, '$impacto - $justificacion2', NOW())");

    //$conn->query("INSERT INTO respuestas (id_pregunta, id_encuestado, respuesta_texto, fecha_respuesta) 
    //              VALUES (3, $id_encuestado, '$miedo - $justificacion3', NOW())");

    //echo "<h2>¡Gracias por participar en la encuesta!</h2>";
//} else {
    echo "Error al registrar encuestado: " . $conn->error;
//}

$conn->close();
?>
