<?php
require_once "../config/bd.php";
require_once "../config/openai.php";

header("Content-Type: text/xml");

$telefono  = $_POST['From'] ?? '';
$texto     = trim($_POST['SpeechResult'] ?? '');

echo "<Response>";

// =========================
// VALIDAR ENTRADA
// =========================
if (empty($texto)) {
    echo "<Say voice='Polly.Lupe'>No entendí. Por favor repite.</Say>";
    echo "<Redirect>https://ivr-3knv.onrender.com/controllers/voice_ai.php</Redirect>";
    echo "</Response>";
    exit;
}

// =========================
// IA PROCESA TODO
// =========================
$resultadoIA = extraerDatosReservaIA($texto);

$data = $resultadoIA["data"];

$nombre     = $data["nombre"] ?? "";
$personas   = $data["personas"] ?? null;
$fechaHora  = $data["fecha_hora"] ?? "";

// =========================
// VALIDACIÓN INTELIGENTE
// =========================

// Falta personas
if (!$personas) {
    echo '<Gather input="speech" method="POST" action="https://ivr-3knv.onrender.com/controllers/process_ai.php">';
    echo '<Say voice="Polly.Lupe">¿Para cuántas personas es la reserva?</Say>';
    echo '</Gather>';
    echo "</Response>";
    exit;
}

// Falta fecha
if (!$fechaHora) {
    echo '<Gather input="speech" method="POST" action="https://ivr-3knv.onrender.com/controllers/process_ai.php">';
    echo '<Say voice="Polly.Lupe">¿Para qué día y hora deseas la reserva?</Say>';
    echo '</Gather>';
    echo "</Response>";
    exit;
}

// =========================
// GUARDAR EN BD
// =========================
pg_query_params($conn,
    "INSERT INTO reservas (telefono, nombre, personas, fecha_hora)
     VALUES ($1, $2, $3, $4)",
    [$telefono, $nombre, $personas, $fechaHora]
);

// =========================
// TELEGRAM
// =========================
$token = getenv("TELEGRAM_TOKEN");
$chat_id = "-4994123276";

$mensaje = "📞 *Nueva reserva By Wifer*\n\n";
$mensaje .= "👤 *Nombre:* $nombre\n";
$mensaje .= "👥 *Personas:* $personas\n";
$mensaje .= "📅 *Fecha:* $fechaHora\n";
$mensaje .= "📱 *Teléfono:* $telefono";

$url = "https://api.telegram.org/bot$token/sendMessage";

file_get_contents($url . "?" . http_build_query([
    "chat_id" => $chat_id,
    "text" => $mensaje,
    "parse_mode" => "Markdown"
]));

// =========================
// RESPUESTA FINAL
// =========================
echo "<Say voice='Polly.Lupe'>
Perfecto $nombre. Tu reserva para $personas personas el $fechaHora ha sido confirmada.
</Say>";

echo "</Response>";