<?php 
require_once "../config/bd.php";

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

        $fechaHora = $respuesta;

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