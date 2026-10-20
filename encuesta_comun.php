<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/encuestas/config/config.php';

const ENCUESTA_ACTUAL = 1;

function encuestaEscapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function encuestaObtenerTitulo(mysqli $conn): string
{
    $stmt = $conn->prepare('SELECT titulo FROM encuestas WHERE id = ?');
    $encuestaId = ENCUESTA_ACTUAL;
    $stmt->bind_param('i', $encuestaId);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();

    return $fila['titulo'] ?? 'Encuesta';
}

function encuestaCargarPreguntas(mysqli $conn, int $pagina = 0): array
{
    $filtroOrden = '';
    if ($pagina === 1 || $pagina === 2) {
        $filtroOrden = ' AND p.orden = ?';
    } elseif ($pagina === 3) {
        $filtroOrden = ' AND p.orden >= ?';
    }

    $sql = 'SELECT p.id, p.texto, p.tipo, p.obligatoria, p.pide_justificacion, p.orden,
                   o.id AS opcion_id, o.texto AS opcion_texto
            FROM preguntas p
            LEFT JOIN opciones o ON o.pregunta_id = p.id
            WHERE p.encuesta_id = ?' . $filtroOrden . '
            ORDER BY p.orden, p.id, o.id';
    $stmt = $conn->prepare($sql);
    $encuestaId = ENCUESTA_ACTUAL;

    if ($filtroOrden !== '') {
        $stmt->bind_param('ii', $encuestaId, $pagina);
    } else {
        $stmt->bind_param('i', $encuestaId);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();
    $preguntas = [];

    while ($fila = $resultado->fetch_assoc()) {
        $preguntaId = (int) $fila['id'];
        if (!isset($preguntas[$preguntaId])) {
            $preguntas[$preguntaId] = [
                'id' => $preguntaId,
                'texto' => $fila['texto'],
                'tipo' => $fila['tipo'],
                'obligatoria' => (bool) $fila['obligatoria'],
                'pide_justificacion' => (bool) $fila['pide_justificacion'],
                'opciones' => [],
            ];
        }

        if ($fila['opcion_id'] !== null) {
            $preguntas[$preguntaId]['opciones'][] = [
                'id' => (int) $fila['opcion_id'],
                'texto' => $fila['opcion_texto'],
            ];
        }
    }

    $stmt->close();
    return array_values($preguntas);
}

function encuestaProcesarPagina(array $preguntas, bool $validarObligatorias = true): array
{
    $errores = [];
    $respuestas = [];
    $campos = $_POST['respuesta'] ?? [];
    $justificaciones = $_POST['justificacion'] ?? [];

    if (!is_array($campos)) {
        return ['Las respuestas enviadas no tienen un formato válido.'];
    }

    foreach ($preguntas as $pregunta) {
        $preguntaId = $pregunta['id'];
        $opcionesValidas = array_column($pregunta['opciones'], 'id');
        $opcionesElegidas = [];
        $texto = '';
        $campo = $campos[$preguntaId] ?? null;

        if ($pregunta['tipo'] === 'opcion_unica') {
            if ($campo !== null && is_scalar($campo)) {
                $opcionId = filter_var($campo, FILTER_VALIDATE_INT);
                if ($opcionId !== false && in_array($opcionId, $opcionesValidas, true)) {
                    $opcionesElegidas[] = $opcionId;
                } else {
                    $errores[] = 'Una opción enviada no corresponde a la pregunta: ' . $pregunta['texto'];
                }
            }
        } elseif ($pregunta['tipo'] === 'multiple') {
            if ($campo !== null && !is_array($campo)) {
                $errores[] = 'Las opciones enviadas no tienen un formato válido.';
            } else {
                foreach ((array) $campo as $valor) {
                    $opcionId = is_scalar($valor) ? filter_var($valor, FILTER_VALIDATE_INT) : false;
                    if ($opcionId !== false && in_array($opcionId, $opcionesValidas, true)) {
                        $opcionesElegidas[] = $opcionId;
                    } else {
                        $errores[] = 'Una opción enviada no corresponde a la pregunta: ' . $pregunta['texto'];
                    }
                }
                $opcionesElegidas = array_values(array_unique($opcionesElegidas));
            }
        } elseif ($pregunta['tipo'] === 'texto') {
            if ($campo !== null && is_scalar($campo)) {
                $texto = trim((string) $campo);
            }
        } else {
            $errores[] = 'La pregunta tiene un tipo no compatible: ' . $pregunta['texto'];
        }

        if ($validarObligatorias && $pregunta['obligatoria']) {
            $respondida = $pregunta['tipo'] === 'texto'
                ? $texto !== ''
                : count($opcionesElegidas) > 0;
            if (!$respondida) {
                $errores[] = 'Debes responder: ' . $pregunta['texto'];
            }
        }

        $justificacion = '';
        if ($pregunta['pide_justificacion'] && is_array($justificaciones)) {
            $valorJustificacion = $justificaciones[$preguntaId] ?? '';
            if (is_scalar($valorJustificacion)) {
                $justificacion = trim((string) $valorJustificacion);
            }
        }

        $respuestas[$preguntaId] = [
            'opciones' => $opcionesElegidas,
            'texto' => $texto,
            'justificacion' => $justificacion,
        ];
    }

    if ($errores === []) {
        if (!isset($_SESSION['encuesta_respuestas'])) {
            $_SESSION['encuesta_respuestas'] = [];
        }
        $_SESSION['encuesta_respuestas'] = array_replace(
            $_SESSION['encuesta_respuestas'],
            $respuestas
        );
    }

    return $errores;
}

function encuestaMostrarPreguntas(array $preguntas): void
{
    foreach ($preguntas as $pregunta) {
        $preguntaId = $pregunta['id'];
        $guardada = $_SESSION['encuesta_respuestas'][$preguntaId] ?? [];
        $opcionesGuardadas = $guardada['opciones'] ?? [];
        $textoGuardado = $guardada['texto'] ?? '';
        $justificacionGuardada = $guardada['justificacion'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $publicadas = $_POST['respuesta'] ?? [];
            if (is_array($publicadas) && array_key_exists($preguntaId, $publicadas)) {
                $valor = $publicadas[$preguntaId];
                if ($pregunta['tipo'] === 'texto' && is_scalar($valor)) {
                    $textoGuardado = (string) $valor;
                } elseif ($pregunta['tipo'] === 'multiple' && is_array($valor)) {
                    $opcionesGuardadas = array_map('intval', $valor);
                } elseif (is_scalar($valor)) {
                    $opcionesGuardadas = [(int) $valor];
                }
            }

            $justificaciones = $_POST['justificacion'] ?? [];
            if (is_array($justificaciones) && isset($justificaciones[$preguntaId]) && is_scalar($justificaciones[$preguntaId])) {
                $justificacionGuardada = (string) $justificaciones[$preguntaId];
            }
        }

        echo '<section class="question">';
        echo '<p>' . encuestaEscapar($pregunta['texto']) . '</p>';

        if ($pregunta['tipo'] === 'texto') {
            echo '<textarea name="respuesta[' . $preguntaId . ']" rows="4" placeholder="Escribe tu respuesta aquí..."'
                . ($pregunta['obligatoria'] ? ' required' : '') . '>'
                . encuestaEscapar($textoGuardado) . '</textarea>';
        } else {
            foreach ($pregunta['opciones'] as $opcion) {
                $opcionId = $opcion['id'];
                $multiple = $pregunta['tipo'] === 'multiple';
                $tipoInput = $multiple ? 'checkbox' : 'radio';
                $nombre = $multiple
                    ? 'respuesta[' . $preguntaId . '][]'
                    : 'respuesta[' . $preguntaId . ']';
                $marcada = in_array($opcionId, $opcionesGuardadas, true);

                echo '<label><input type="' . $tipoInput . '" name="' . $nombre . '" value="' . $opcionId . '"'
                    . ($marcada ? ' checked' : '')
                    . (!$multiple && $pregunta['obligatoria'] ? ' required' : '')
                    . '> ' . encuestaEscapar($opcion['texto']) . '</label>';
            }
        }

        if ($pregunta['pide_justificacion']) {
            echo '<label for="justificacion-' . $preguntaId . '">Justifica tu respuesta:</label>';
            echo '<textarea id="justificacion-' . $preguntaId . '" name="justificacion[' . $preguntaId . ']" rows="3" placeholder="Escribe tu justificación aquí...">'
                . encuestaEscapar($justificacionGuardada) . '</textarea>';
        }

        echo '</section>';
    }
}
