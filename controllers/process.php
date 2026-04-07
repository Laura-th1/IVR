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
    echo "<Redirect>https://ivr-3knv.onrender.com/controllers/voice.php</Redirect>";
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
                hints="1,2,3,4,5,6,7,8,9,10,20,30,40,50,60,70,80,90,100"
                method="POST"
                action="https://ivr-3knv.onrender.com/controllers/process.php?step=2">

                <Say voice="Polly.Lupe">
                    Gracias ' . $respuesta_segura . '. Ahora dime tu edad.
                </Say>
                
              </Gather>';
    }

/* =========================
   STEP 2 - GUARDAR EDAD + TELEGRAM
   ========================= */
} elseif ($step == 2) {

    // Guardar edad
    $query = "INSERT INTO respuestas (telefono, pregunta, respuesta) 
              VALUES ($1, $2, $3)";
    
    $result = pg_query_params($conn, $query, [$telefono, 'Edad', $respuesta]);

    if (!$result) {
        echo "<Say voice='Polly.Lupe'>Error al guardar edad.</Say>";
    
    } else {

        $edad = $respuesta;

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

        /* =========================
           TELEGRAM
           ========================= */
        $token = getenv("TELEGRAM_TOKEN");
        $chat_id = "-4994123276";

        $mensaje = "📞 Nuevo registro IVR:\n";
        $mensaje .= "Telefono: $telefono\n";
        $mensaje .= "Nombre: $nombre\n";
        $mensaje .= "Edad: $edad";

        $url = "https://api.telegram.org/bot$token/sendMessage";

        $response = @file_get_contents($url . "?chat_id=$chat_id&text=" . urlencode($mensaje));

        $data = $response ? json_decode($response, true) : null;

        if (!$response || !isset($data["ok"]) || !$data["ok"]) {
            echo "<Say voice='Polly.Lupe'>Tus datos fueron guardados, pero hubo un error al enviar el mensaje.</Say>";
        } else {
            echo "<Say voice='Polly.Lupe'>Tus datos fueron guardados y el mensaje fue enviado correctamente.</Say>";
        }
    }

} else {
    echo "<Say voice='Polly.Lupe'>Paso no válido.</Say>";
}

echo "</Response>";