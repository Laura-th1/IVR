<?php
require_once "../config/bd.php";

header("Content-Type: text/xml");

//debug temporal

@file_put_contents("log.txt", print_r($_POST, true) . "\n--- " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

$telefono = $_POST['From'] ?? '';
$respuesta = $_POST['SpeechResult'] ?? '';
$step = $_GET['step'] ?? 1;


if (empty($respuesta)) {
    echo "<Response>";
    echo "<Say voice='Polly.Lupe'>No entendí tu respuesta. Intenta nuevamente por favor.</Say>";
    echo "<Redirect>https://ivr-3knv.onrender.com/controllers/voice.php</Redirect>";
    echo "</Response>";
    exit;
}



echo "<Response>";



if ($step == 1) {

    // Guardar nombre
   
    $query = "INSERT INTO respuestas (telefono, pregunta, respuesta) VALUES ('" . pg_escape_string($telefono) . "', 'Nombre', '" . pg_escape_string($respuesta) . "')";
    $result = pg_query($conn, $query);
    
    if (!$result) {
        echo "<Say voice='Polly.Lupe'>Error al guardar. Intenta nuevamente.</Say>";
    } else {
        echo "<Say voice='Polly.Lupe'>Gracias $respuesta. Ahora dime tu edad.</Say>";
    }

    echo "<Gather input='speech' method='POST' timeout='5'
          action='https://ivr-3knv.onrender.com/controllers/process.php?step=2'></Gather>";

} elseif ($step == 2) {

    // Guardar edad
   
    $query = "INSERT INTO respuestas (telefono, pregunta, respuesta) VALUES ('" . pg_escape_string($telefono) . "', 'Edad', '" . pg_escape_string($respuesta) . "')";
    $result = pg_query($conn, $query);
    
    if (!$result) {
        echo "<Say voice='Polly.Lupe'>Error al guardar edad.</Say>";
  
  }      } else {
        echo "<Say voice='Polly.Lupe'>Gracias. Tus datos han sido guardados correctamente.</Say>";
    }


        /* =========================
           TELEGRAM (REEMPLAZO DE TWILIO)
           ========================= */
        $token = getenv("TELEGRAM_TOKEN");
$chat_id = "-4994123276"; // Reemplaza con tu chat ID

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

    
echo "</Response>";






