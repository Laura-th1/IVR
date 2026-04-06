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
        echo "<Gather input='speech' method='POST' timeout='5'
              action='https://ivr-3knv.onrender.com/controllers/process.php?step=2'>
                <Say voice='Polly.Lupe'>Gracias $respuesta_segura. Ahora dime tu edad.</Say>
              </Gather>";
    }

/* =========================
   STEP 2 - GUARDAR EDAD + SMS
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

        // 🔐 VARIABLES (usa ENV en Render en producción)
        $accountSid   = getenv("TWILIO_ACCOUNT_SID");
        $authToken    = getenv("TWILIO_AUTH_TOKEN");
        $twilioNumber = getenv("TWILIO_NUMBER");
        $miNumero     = getenv("MY_NUMBER");

        $mensaje = "Nuevo registro:\nTelefono: $telefono\nNombre: $nombre\nEdad: $edad";

        $url = "https://api.twilio.com/2010-04-01/Accounts/$accountSid/Messages.json";

        $data = http_build_query([
            "From" => $twilioNumber,
            "To"   => $miNumero,
            "Body" => $mensaje
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $accountSid . ":" . $authToken);

        $smsResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            echo "<Say voice='Polly.Lupe'>Tus datos fueron guardados, pero hubo un error al enviar el mensaje.</Say>";
        } elseif ($httpCode == 201) {
            echo "<Say voice='Polly.Lupe'>Tus datos fueron guardados y el mensaje fue enviado correctamente.</Say>";
        } else {
            echo "<Say voice='Polly.Lupe'>Tus datos fueron guardados, pero el mensaje no pudo enviarse.</Say>";
        }
    }

} else {
    echo "<Say voice='Polly.Lupe'>Paso no válido.</Say>";
}

echo "</Response>";