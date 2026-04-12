<?php 
require_once "../config/bd.php";
require_once "../config/openai.php";

date_default_timezone_set('America/Mexico_City');

function normalizarHora($hora, $minutos = "00", $periodo = "") {
    $hora = intval($hora);
    $minutos = str_pad($minutos, 2, '0', STR_PAD_LEFT);
    $periodo = strtolower($periodo);

    if ($periodo === 'am') {
        if ($hora === 12) {
            $hora = 0;
        }
    } elseif ($periodo === 'pm') {
        if ($hora !== 12) {
            $hora += 12;
        }
    } elseif ($periodo === 'mañana') {
        if ($hora === 12) {
            $hora = 0;
        }
    } elseif (in_array($periodo, ['tarde', 'noche', 'madrugada'], true)) {
        if ($hora < 12) {
            $hora += 12;
        }
    }

    return sprintf('%02d:%02d', $hora, $minutos);
}

function extraerHoraTexto($texto) {
    $texto = strtolower($texto);

    if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $texto, $match)) {
        return normalizarHora($match[1], $match[2] ?? '00', $match[3]);
    }

    if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s+de la\s+(mañana|tarde|noche|madrugada)\b/i', $texto, $match)) {
        return normalizarHora($match[1], $match[2] ?? '00', $match[3]);
    }

    if (preg_match('/\b(\d{1,2})(?::(\d{2}))\b/', $texto, $match)) {
        return normalizarHora($match[1], $match[2]);
    }

    if (preg_match('/\b(\d{1,2})\b/', $texto, $match)) {
        return normalizarHora($match[1]);
    }

    return null;
}

function parseFechaHoraManual($texto) {
    $texto = strtolower($texto);
    $fecha = new DateTime();

    if (strpos($texto, 'pasado mañana') !== false) {
        $fecha->modify('+2 days');
    } elseif (strpos($texto, 'mañana') !== false) {
        $fecha->modify('+1 day');
    } elseif (strpos($texto, 'hoy') !== false) {
        // misma fecha
    } else {
        $dias = [
            'lunes', 'martes', 'miércoles', 'miercoles',
            'jueves', 'viernes', 'sábado', 'sabado', 'domingo'
        ];

        foreach ($dias as $dia) {
            if (strpos($texto, $dia) !== false) {
                $fecha->modify('next ' . $dia);
                break;
            }
        }
    }

    $hora = extraerHoraTexto($texto);

    if ($hora) {
        return $fecha->format('d/m/y') . ' ' . $hora;
    }

    return $fecha->format('d/m/y') . ' 00:00';
}

header("Content-Type: text/xml");

// Debug temporal
@file_put_contents("log.txt", print_r($_POST, true) . "\n--- " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

$telefono  = $_POST['From'] ?? '';
$respuesta = trim($_POST['SpeechResult'] ?? '');
$step      = $_GET['step'] ?? 1;

// Validar conexión
if (!$conn) {
    echo "<Response><Say>Error de conexión con la base de datos.</Say></Response>";
    exit;
}

// Validar respuesta vacía
if (empty($respuesta)) {
    echo "<Response>";
    echo "<Say voice='Polly.Lupe'>No entendí tu respuesta. Intenta nuevamente por favor.</Say>";
    echo "<Redirect>https://ivr-3knv.onrender.com/controllers/process.php?step=$step</Redirect>";
    echo "</Response>";
    exit;
}

// Sanitizar para XML
$respuesta_segura = htmlspecialchars($respuesta);

echo "<Response>";

/* =========================
   STEP 1 - GUARDAR NOMBRE
   ========================= */
if ($step == 1) {

    $query = "INSERT INTO respuestas (telefono, pregunta, respuesta) 
              VALUES ($1, $2, $3)";
    
    $result = pg_query_params($conn, $query, [$telefono, 'Nombre', $respuesta]);

    if (!$result) {
        echo "<Say voice='Polly.Lupe'>Error al guardar. Intenta nuevamente.</Say>";
    } else {

        echo '<Gather 
                input="speech" 
                language="es-ES"
                speechTimeout="auto"
                hints="1,2,3,4,5,6,7,8,9,10,12,15,20"
                method="POST"
                action="https://ivr-3knv.onrender.com/controllers/process.php?step=2">

                <Say voice="Polly.Lupe">
                    Gracias ' . $respuesta_segura . '. Para cuántas personas es la reserva.
                </Say>
                
              </Gather>';
    }

/* =========================
   STEP 2 - GUARDAR PERSONAS
   ========================= */
} elseif ($step == 2) {

    $query = "INSERT INTO respuestas (telefono, pregunta, respuesta) 
              VALUES ($1, $2, $3)";
    
    $result = pg_query_params($conn, $query, [$telefono, 'Personas', $respuesta]);

    if (!$result) {
        echo "<Say voice='Polly.Lupe'>Error al guardar la cantidad de personas.</Say>";
    
    } else {

        echo '<Gather 
                input="speech" 
                language="es-ES"
                speechTimeout="auto"
                method="POST"
                action="https://ivr-3knv.onrender.com/controllers/process.php?step=3">

                <Say voice="Polly.Lupe">
                    Perfecto. Qué día y a qué hora le gustaría hacer la reserva.
                </Say>
                
              </Gather>';
    }

/* =========================
   STEP 3 - GUARDAR FECHA/HORA + TELEGRAM
   ========================= */
} elseif ($step == 3) {

    // Guardar fecha y hora
    $query = "INSERT INTO respuestas (telefono, pregunta, respuesta) 
              VALUES ($1, $2, $3)";
    
    $result = pg_query_params($conn, $query, [$telefono, 'FechaHora', $respuesta]);

    if (!$result) {
        echo "<Say voice='Polly.Lupe'>Error al guardar la fecha y hora.</Say>";
    
    } else {
        // Fecha
if (!empty($datosIA["fecha_hora"]) && preg_match('/^\d{2}\/\d{2}\/\d{2}\s\d{2}:\d{2}$/', $datosIA["fecha_hora"])) {
    $fechaHora = $datosIA["fecha_hora"];
} else {
    $fechaHora = parseFechaHoraManual($respuesta);
}

// Personas (si IA lo mejora)
if (!empty($datosIA["personas"])) {
    $personas = $datosIA["personas"];
}

// Nombre (si IA lo detecta)
if (!empty($datosIA["nombre"])) {
    $nombre = $datosIA["nombre"];
}
  // Obtener nombre
        $queryNombre = "SELECT respuesta FROM respuestas 
                        WHERE telefono = $1 
                        AND pregunta = 'Nombre'
                        ORDER BY fecha DESC
                        LIMIT 1";

        $resultNombre = pg_query_params($conn, $queryNombre, [$telefono]);

        $nombre = "Desconocido";
         if ($resultNombre && pg_num_rows($resultNombre) > 0) {
            $filaNombre = pg_fetch_assoc($resultNombre);
            $nombre = $filaNombre['respuesta'];
        }
        
        // Obtener personas
        $queryPersonas = "SELECT respuesta FROM respuestas 
                          WHERE telefono = $1 
                          AND pregunta = 'Personas'
                          ORDER BY fecha DESC
                          LIMIT 1";

        $resultPersonas = pg_query_params($conn, $queryPersonas, [$telefono]);

        $personas = "Desconocido";
          if ($resultPersonas && pg_num_rows($resultPersonas) > 0) {
            $filaPersonas = pg_fetch_assoc($resultPersonas);
            $personas = $filaPersonas['respuesta'];
        }
          /* =========================
           TELEGRAM
           ========================= */
        $token = getenv("TELEGRAM_TOKEN");
        $chat_id = "-4994123276";

        $mensaje = "📞 Nueva reserva By Wifer:\n";
        $mensaje .= "Telefono: $telefono\n";
        $mensaje .= "Nombre: $nombre\n";
        $mensaje .= "Personas: $personas\n";
        $mensaje .= "Fecha y hora: $fechaHora";

        $url = "https://api.telegram.org/bot$token/sendMessage";

        $response = @file_get_contents($url . "?chat_id=$chat_id&text=" . urlencode($mensaje));

        $data = $response ? json_decode($response, true) : null;

        if (!$response || !isset($data["ok"]) || !$data["ok"]) {
            echo "<Say voice='Polly.Lupe'>Su reserva fue completada, pero hubo un error al enviar la notificación.</Say>";
        } else {
            echo "<Say voice='Polly.Lupe'>Ok, su reserva ha sido completada. Gracias por llamar a By Wifer.</Say>";
        }
    }

} else {
    echo "<Say voice='Polly.Lupe'>Paso no válido.</Say>";
}

echo "</Response>";
?>