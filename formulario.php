<?php
require_once __DIR__ . '/encuesta_comun.php';

$pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
$pagina = in_array($pagina, [1, 2, 3], true) ? $pagina : 1;
$errores = [];
$finalizada = false;

try {
    $titulo = encuestaObtenerTitulo($conn);
    $preguntasPagina = encuestaCargarPreguntas($conn, $pagina);
    $preguntasEncuesta = encuestaCargarPreguntas($conn);
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('No se pudo cargar la encuesta. Revisa la conexión y la configuración de la base de datos.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'siguiente';
    $errores = encuestaProcesarPagina($preguntasPagina, $accion !== 'anterior');

    if ($errores === [] && $accion === 'anterior') {
        header('Location: formulario.php?pagina=' . max(1, $pagina - 1));
        exit;
    }

    if ($errores === [] && $pagina < 3) {
        header('Location: formulario.php?pagina=' . ($pagina + 1));
        exit;
    }

    if ($errores === [] && $pagina === 3) {
        foreach ($preguntasEncuesta as $pregunta) {
            if (!$pregunta['obligatoria']) {
                continue;
            }

            $guardada = $_SESSION['encuesta_respuestas'][$pregunta['id']] ?? null;
            $respondida = $guardada !== null && (
                $pregunta['tipo'] === 'texto'
                    ? trim($guardada['texto'] ?? '') !== ''
                    : !empty($guardada['opciones'])
            );

            if (!$respondida) {
                $errores[] = 'Falta responder: ' . $pregunta['texto'];
            }
        }

        if ($errores === []) {
            try {
                $conn->begin_transaction();

                $encuestaId = ENCUESTA_ACTUAL;
                $stmtRespuesta = $conn->prepare('INSERT INTO respuestas (encuesta_id) VALUES (?)');
                $stmtRespuesta->bind_param('i', $encuestaId);
                $stmtRespuesta->execute();
                $respuestaId = $conn->insert_id;
                $stmtRespuesta->close();

                $stmtDetalle = $conn->prepare(
                    'INSERT INTO respuesta_detalle (respuesta_id, pregunta_id, opcion_id, texto_libre) VALUES (?, ?, ?, ?)'
                );

                foreach ($preguntasEncuesta as $pregunta) {
                    $preguntaId = $pregunta['id'];
                    $guardada = $_SESSION['encuesta_respuestas'][$preguntaId] ?? [];
                    $opciones = $guardada['opciones'] ?? [];
                    $texto = trim($guardada['texto'] ?? '');
                    $justificacion = trim($guardada['justificacion'] ?? '');

                    if ($pregunta['tipo'] === 'texto' && $texto !== '') {
                        $opcionId = null;
                        $textoLibre = $texto;
                        $stmtDetalle->bind_param('iiis', $respuestaId, $preguntaId, $opcionId, $textoLibre);
                        $stmtDetalle->execute();
                    }

                    foreach ($opciones as $opcionId) {
                        $textoLibre = null;
                        $stmtDetalle->bind_param('iiis', $respuestaId, $preguntaId, $opcionId, $textoLibre);
                        $stmtDetalle->execute();
                    }

                    if ($justificacion !== '') {
                        $opcionId = null;
                        $textoLibre = $justificacion;
                        $stmtDetalle->bind_param('iiis', $respuestaId, $preguntaId, $opcionId, $textoLibre);
                        $stmtDetalle->execute();
                    }
                }

                $stmtDetalle->close();
                $conn->commit();
                unset($_SESSION['encuesta_respuestas']);
                $finalizada = true;
            } catch (Throwable $error) {
                $conn->rollback();
                error_log($error->getMessage());
                $errores[] = 'No se pudieron guardar las respuestas. Inténtalo de nuevo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= encuestaEscapar($titulo) ?> - Página <?= $pagina ?></title>
  <link rel="stylesheet" href="styles.css?v=2">
</head>
<body>
  <div class="phone survey">
    <div class="screen">
      <?php if ($finalizada): ?>
        <h2>¡Gracias por participar!</h2>
        <p>Tu respuesta se guardó correctamente.</p>
        <div class="buttons">
          <a href="index.html"><button type="button" class="start">Finalizar</button></a>
        </div>
      <?php else: ?>
        <h2><?= encuestaEscapar($titulo) ?></h2>
        <p>Página <?= $pagina ?> de 3</p>

        <?php foreach ($errores as $error): ?>
          <p class="form-error"><?= encuestaEscapar($error) ?></p>
        <?php endforeach; ?>

        <?php if ($preguntasPagina === []): ?>
          <p>Aún no hay preguntas asignadas a esta página.</p>
        <?php else: ?>
          <form action="formulario.php?pagina=<?= $pagina ?>" method="post">
            <?php encuestaMostrarPreguntas($preguntasPagina); ?>
            <div class="buttons">
              <?php if ($pagina === 1): ?>
                <a href="index.html"><button type="button" class="exit">Cancelar</button></a>
              <?php else: ?>
                <button type="submit" name="accion" value="anterior" formnovalidate>Página anterior</button>
              <?php endif; ?>

              <?php if ($pagina < 3): ?>
                <button type="submit" name="accion" value="siguiente" class="start">Siguiente</button>
              <?php else: ?>
                <button type="submit" name="accion" value="finalizar" class="start">Finalizar</button>
              <?php endif; ?>
            </div>
          </form>
        <?php endif; ?>

        <?php if ($preguntasPagina === [] && $pagina > 1): ?>
          <div class="buttons">
            <a href="formulario.php?pagina=<?= $pagina - 1 ?>"><button type="button">Página anterior</button></a>
            <?php if ($pagina < 3): ?>
              <a href="formulario.php?pagina=<?= $pagina + 1 ?>"><button type="button" class="start">Siguiente</button></a>
            <?php else: ?>
              <form action="formulario.php?pagina=3" method="post">
                <button type="submit" name="accion" value="finalizar" class="start">Finalizar</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
