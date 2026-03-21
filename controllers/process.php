<?php
require_once "../config/bd.php";

header("Content-Type: text/xml");

$telefono = $_POST['From'] ?? '';
$respuesta = $_POST['SpeechResult'] ?? '';
$step = $_GET['step'] ?? 1;

echo "<Response>";

if ($step == 1) {

    // Guardar nombre
    pg_query($conn, "INSERT INTO respuestas (telefono, pregunta, respuesta)
                     VALUES ('$telefono', 'Nombre', '$respuesta')");

    echo "<Say voice='Polly.Lupe'>Gracias $respuesta. Ahora dime tu edad.</Say>";

    echo "<Gather input='speech' method='POST'
          action='https://ivr-3knv.onrender.com/controllers/process.php?step=2'></Gather>";

} elseif ($step == 2) {

    // Guardar edad
    pg_query($conn, "INSERT INTO respuestas (telefono, pregunta, respuesta)
                     VALUES ('$telefono', 'Edad', '$respuesta')");

    echo "<Say voice='Polly.Lupe'>Gracias. Tus datos han sido guardados.</Say>";
}

echo "</Response>";